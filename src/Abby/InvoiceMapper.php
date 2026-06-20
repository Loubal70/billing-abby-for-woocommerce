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
use WC_Tax;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the Abby contact and draft-invoice-line payloads from a WooCommerce order.
 */
final class InvoiceMapper {

	/**
	 * Memoised rate id => VAT percent for the lines of one order build.
	 *
	 * @var array<int, float>
	 */
	private array $rate_percents = array();

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
		$inc_tax = $this->prices_include_tax();

		$lines = array_merge(
			$this->product_lines( $order, $inc_tax ),
			$this->fee_lines( $order, $inc_tax ),
			$this->shipping_lines( $order, $inc_tax )
		);

		// One order has a single tax base, so the flag is the same on every line.
		return array_map(
			static fn ( array $line ): array => $line + array( 'isTaxIncluded' => $inc_tax ),
			$lines
		);
	}

	private function product_lines( WC_Order $order, bool $inc_tax ): array {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			$quantity = (float) $item->get_quantity();

			if ( $quantity <= 0.0 ) {
				continue;
			}

			$subtotal_cents = Money::to_cents( $order->get_line_subtotal( $item, $inc_tax, false ) );

			$lines[] = $this->line(
				$item->get_name(),
				$quantity,
				$this->unit_price_cents( $subtotal_cents, $quantity ),
				$this->vat_code_for( $item ),
				$this->line_discount( $order, $item, $inc_tax )
			);
		}

		return $lines;
	}

	/**
	 * Map order fees (surcharges, gift wrap, rebates) to invoice lines, one each like shipping.
	 * A negative fee becomes a negative line — Abby accepts a negative unitPrice and applies the
	 * matching negative VAT (live-confirmed), keeping the invoice total exact.
	 */
	private function fee_lines( WC_Order $order, bool $inc_tax ): array {
		$lines = array();

		foreach ( $order->get_fees() as $fee ) {
			$total_cents = Money::to_cents( $order->get_line_total( $fee, $inc_tax, false ) );

			if ( 0 !== $total_cents ) {
				$lines[] = $this->line(
					$fee->get_name(),
					1.0,
					$this->unit_price_cents( $total_cents, 1.0 ),
					$this->vat_code_for( $fee )
				);
			}
		}

		return $lines;
	}

	private function shipping_lines( WC_Order $order, bool $inc_tax ): array {
		$lines = array();

		foreach ( $order->get_shipping_methods() as $shipping ) {
			$total_cents = Money::to_cents( $order->get_line_total( $shipping, $inc_tax, false ) );

			if ( $total_cents > 0 ) {
				$lines[] = $this->line(
					$shipping->get_name(),
					1.0,
					$this->unit_price_cents( $total_cents, 1.0 ),
					$this->vat_code_for( $shipping )
				);
			}
		}

		return $lines;
	}

	/**
	 * The Abby unit price in cents, kept at Abby's maximum precision (1 decimal) and derived from
	 * the line total WooCommerce charged so that round( unitPrice * quantity ) lands back on that
	 * exact total. Dividing the rounded line total (rather than reading WooCommerce's reconstructed
	 * per-unit price) keeps the sub-cent rounding from being amplified by the quantity.
	 */
	private function unit_price_cents( int $line_cents, float $quantity ): float {
		return round( $line_cents / $quantity, 1 );
	}

	/**
	 * The coupon discount WooCommerce already computed for the line (subtotal − total), in cents.
	 *
	 * Rounds the difference once, not each term, to avoid a double-rounding cent.
	 */
	private function line_discount( WC_Order $order, WC_Order_Item $item, bool $inc_tax ): int {
		return Money::to_cents(
			$order->get_line_subtotal( $item, $inc_tax, false ) - $order->get_line_total( $item, $inc_tax, false )
		);
	}

	private function line( string $name, float $quantity, float $unit_price_cents, string $vat_code, int $discount = 0 ): array {
		$line = array(
			'designation' => $name,
			'quantity'    => $quantity,
			'unitPrice'   => $unit_price_cents,
			'vatCode'     => $vat_code,
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

	/**
	 * The Abby VAT code for a line, from the rate WooCommerce actually applied. Reading the rate from
	 * the line's tax data (not inferring it from the rounded net/tax, which drifts off the scale for
	 * small amounts and would reject e.g. a 1.30 EUR line) keeps a 20% line mapped to FR_2000.
	 */
	private function vat_code_for( WC_Order_Item $item ): string {
		return VatCode::from_rate( $this->applied_rate( $item ) )->value;
	}

	private function applied_rate( WC_Order_Item $item ): float {
		$taxes = $item->get_taxes();
		$rate  = 0.0;

		foreach ( array_keys( $taxes['total'] ?? array() ) as $rate_id ) {
			$rate += $this->rate_percent( $rate_id );
		}

		return round( $rate, 1 );
	}

	/**
	 * The VAT percent of a tax rate id, memoised: WC_Tax::get_rate_percent_value queries the DB
	 * uncached, and an order reuses the same one or two rate ids across all its lines.
	 */
	private function rate_percent( int $rate_id ): float {
		return $this->rate_percents[ $rate_id ] ??= (float) WC_Tax::get_rate_percent_value( $rate_id );
	}
}
