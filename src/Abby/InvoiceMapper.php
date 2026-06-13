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
use WC_Order_Item;

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

			if ( $quantity <= 0.0 ) {
				continue;
			}

			// Read WooCommerce's own per-unit price in the shop's tax base; never recompute it.
			$lines[] = $this->line(
				$item->get_name(),
				$quantity,
				$order->get_item_subtotal( $item, $this->prices_include_tax(), false ),
				$this->vat_code( (float) $item->get_total(), (float) $item->get_total_tax() ),
				$this->line_discount( $order, $item )
			);
		}

		return $lines;
	}

	private function shipping_lines( WC_Order $order ): array {
		$lines = array();

		foreach ( $order->get_shipping_methods() as $shipping ) {
			$total = $order->get_line_total( $shipping, $this->prices_include_tax(), false );

			if ( $total > 0.0 ) {
				$lines[] = $this->line(
					$shipping->get_name(),
					1.0,
					$total,
					$this->vat_code( (float) $shipping->get_total(), (float) $shipping->get_total_tax() )
				);
			}
		}

		return $lines;
	}

	/**
	 * The coupon discount WooCommerce already computed for the line (subtotal − total), in cents.
	 *
	 * Rounds the difference once, not each term, to avoid a double-rounding cent.
	 */
	private function line_discount( WC_Order $order, WC_Order_Item $item ): int {
		$inc_tax = $this->prices_include_tax();

		return Money::to_cents(
			$order->get_line_subtotal( $item, $inc_tax, false ) - $order->get_line_total( $item, $inc_tax, false )
		);
	}

	private function line( string $name, float $quantity, float $unit_price, string $vat_code, int $discount = 0 ): array {
		$line = array(
			'designation'   => $name,
			'quantity'      => $quantity,
			// WooCommerce's unit price in cents, kept at Abby's max precision (1 decimal) so that
			// round(unitPrice * quantity) lands back on the exact amount the customer paid.
			'unitPrice'     => round( $unit_price * 100, 1 ),
			'isTaxIncluded' => $this->prices_include_tax(),
			'vatCode'       => $vat_code,
		);

		if ( $discount > 0 ) {
			$line['discount'] = array(
				'mode'   => DiscountMode::AMOUNT->value,
				'amount' => $discount,
			);
		}

		return $line;
	}

	private function prices_include_tax(): bool {
		return wc_prices_include_tax();
	}

	private function vat_code( float $net, float $tax ): string {
		$rate = $net > 0.0 ? round( $tax / $net * 100, 1 ) : 0.0;

		return VatCode::from_rate( $rate )->value;
	}
}
