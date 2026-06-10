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

		return $payload;
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
				$lines[] = $this->line( $item, $quantity );
			}
		}

		return $lines;
	}

	private function shipping_lines( WC_Order $order ): array {
		$lines = array();

		// Shipping is an accessory to the sale: its own line, at the rate WC already applied.
		foreach ( $order->get_shipping_methods() as $shipping ) {
			if ( (float) $shipping->get_total() > 0.0 ) {
				$lines[] = $this->line( $shipping, 1.0 );
			}
		}

		return $lines;
	}

	private function line( WC_Order_Item $item, float $quantity ): array {
		// Amount is net (ex-tax, after discounts), split across the quantity, in cents.
		$net = (float) $item->get_total();

		return array(
			'designation'   => $item->get_name(),
			'quantity'      => $quantity,
			'unitPrice'     => Money::to_cents( $net / $quantity ),
			'isTaxIncluded' => false,
			'vatCode'       => $this->vat_code( $net, (float) $item->get_total_tax() ),
		);
	}

	private function vat_code( float $net, float $tax ): string {
		$rate = $net > 0.0 ? round( $tax / $net * 100, 1 ) : 0.0;

		return VatCode::from_rate( $rate )->value;
	}
}
