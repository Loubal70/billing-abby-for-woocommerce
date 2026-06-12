<?php
/**
 * Maps WooCommerce orders to Abby API payloads.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

use Rankea\BillingAbby\Support\Money;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Abby contact and draft-invoice-line payloads from a WooCommerce order.
 */
final class InvoiceMapper {

	public function contact_payload( WC_Order $order ): array {
		$payload = array(
			'firstname' => $order->get_billing_first_name(),
			'lastname'  => $order->get_billing_last_name(),
		);

		$email = $order->get_billing_email();

		if ( '' !== $email ) {
			$payload['emails'] = array( $email );
		}

		$phone = $order->get_billing_phone();

		if ( '' !== $phone ) {
			$payload['phone'] = $phone;
		}

		$billing = $this->address( $order->get_address( 'billing' ) );

		if ( null !== $billing ) {
			$payload['billingAddress'] = $billing;
		}

		$delivery = $this->address( $order->get_address( 'shipping' ) );

		if ( null !== $delivery ) {
			$payload['deliveryAddress'] = $delivery;
		}

		return $payload;
	}

	/**
	 * Map a WooCommerce address to an Abby address, or null when it is incomplete.
	 *
	 * @param array<string, mixed> $wc A WC_Order::get_address() result.
	 * @return array<string, string>|null Abby requires address, city, zipCode and country.
	 */
	private function address( array $wc ): ?array {
		$line1   = (string) ( $wc['address_1'] ?? '' );
		$city    = (string) ( $wc['city'] ?? '' );
		$zip     = (string) ( $wc['postcode'] ?? '' );
		$country = (string) ( $wc['country'] ?? '' );

		if ( '' === $line1 || '' === $city || '' === $zip || '' === $country ) {
			return null;
		}

		$address = array(
			'address' => $line1,
			'city'    => $city,
			'zipCode' => $zip,
			'country' => $country,
		);

		$complement = (string) ( $wc['address_2'] ?? '' );

		if ( '' !== $complement ) {
			$address['complement'] = $complement;
		}

		$state = (string) ( $wc['state'] ?? '' );

		if ( '' !== $state ) {
			$address['state'] = $state;
		}

		return $address;
	}

	public function invoice_lines( WC_Order $order ): array {
		return array_merge(
			$this->product_lines( $order ),
			$this->shipping_lines( $order )
		);
	}

	private function product_lines( WC_Order $order ): array {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			$quantity = (float) $item->get_quantity();

			if ( $quantity > 0.0 ) {
				// Coupons live in the subtotal/total gap: keep the original price, show the discount.
				$lines[] = $this->line(
					$item->get_name(),
					$quantity,
					(float) $item->get_subtotal(),
					(float) $item->get_total(),
					(float) $item->get_total_tax()
				);
			}
		}

		return $lines;
	}

	private function shipping_lines( WC_Order $order ): array {
		$lines = array();

		// A free-shipping coupon zeroes this total, so a discounted shipping line just drops out.
		foreach ( $order->get_shipping_methods() as $shipping ) {
			$total = (float) $shipping->get_total();

			if ( $total > 0.0 ) {
				$lines[] = $this->line( $shipping->get_name(), 1.0, $total, $total, (float) $shipping->get_total_tax() );
			}
		}

		return $lines;
	}

	private function line( string $name, float $quantity, float $gross, float $net, float $tax ): array {
		$line = array(
			'designation'   => $name,
			'quantity'      => $quantity,
			'unitPrice'     => Money::to_cents( $gross / $quantity ),
			'isTaxIncluded' => false,
			'vatCode'       => $this->vat_code( $net, $tax ),
		);

		$discount = round( $gross - $net, 2 );

		if ( $discount > 0.0 ) {
			// The discount amount is in cents, like unitPrice (confirmed live: euros are read as cents).
			$line['discount'] = array(
				'mode'   => DiscountMode::AMOUNT->value,
				'amount' => Money::to_cents( $discount ),
			);
		}

		return $line;
	}

	private function vat_code( float $net, float $tax ): string {
		$rate = $net > 0.0 ? round( $tax / $net * 100, 1 ) : 0.0;

		return VatCode::from_rate( $rate )->value;
	}
}
