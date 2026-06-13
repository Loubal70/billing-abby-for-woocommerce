<?php
/**
 * Maps a paid WooCommerce order to Abby income-book entries.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

use Rankea\BillingAbby\Support\Money;
use WC_DateTime;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Abby "livre des recettes" payloads, one per product type in the order so each
 * BIC/BNC category is recorded under the right activity.
 */
final class IncomeMapper {

	// 1 = bank transfer, the only confirmed Abby payment-method value.
	private const PAYMENT_METHOD_TRANSFER = 1;

	/**
	 * Build one Abby income-book payload per product type in the order.
	 *
	 * @param WC_Order $order         The paid order.
	 * @param callable $type_for_item fn(WC_Order_Item_Product): ProductType.
	 * @return array<int, array<string, mixed>>
	 */
	public function entries( WC_Order $order, callable $type_for_item ): array {
		$groups = $this->group_by_type( $order, $type_for_item );

		if ( array() === $groups ) {
			return array();
		}

		$groups  = $this->with_shipping( $order, $groups );
		$entries = array();

		foreach ( $groups as $type => $amount ) {
			$entries[] = $this->entry( $order, $type, $amount );
		}

		return $entries;
	}

	private function group_by_type( WC_Order $order, callable $type_for_item ): array {
		$groups = array();

		foreach ( $order->get_items() as $item ) {
			$type = $type_for_item( $item )->value;

			$groups[ $type ]['net'] = ( $groups[ $type ]['net'] ?? 0.0 ) + (float) $item->get_total();
			$groups[ $type ]['tax'] = ( $groups[ $type ]['tax'] ?? 0.0 ) + (float) $item->get_total_tax();
		}

		return $groups;
	}

	/**
	 * Adds shipping to the income groups, spread across them by their net share — it is accessory
	 * to the sale rather than its own income type.
	 */
	private function with_shipping( WC_Order $order, array $groups ): array {
		$net       = (float) $order->get_shipping_total();
		$tax       = (float) $order->get_shipping_tax();
		$total_net = array_sum( array_column( $groups, 'net' ) );

		if ( ( $net <= 0.0 && $tax <= 0.0 ) || $total_net <= 0.0 ) {
			return $groups;
		}

		foreach ( $groups as $type => $amount ) {
			$share                  = $amount['net'] / $total_net;
			$groups[ $type ]['net'] = $amount['net'] + $net * $share;
			$groups[ $type ]['tax'] = $amount['tax'] + $tax * $share;
		}

		return $groups;
	}

	private function entry( WC_Order $order, int $product_type, array $amount ): array {
		// Round the parts, then sum for the total, so priceWithoutTax + vatAmount == priceTotalTax.
		$net_cents = Money::to_cents( $amount['net'] );
		$tax_cents = Money::to_cents( $amount['tax'] );

		return array(
			'client'            => $this->client_name( $order ),
			'priceWithoutTax'   => $net_cents,
			'priceTotalTax'     => $net_cents + $tax_cents,
			'vatAmount'         => $tax_cents,
			'productType'       => $product_type,
			'paidAt'            => $this->paid_date( $order ),
			'paymentMethodUsed' => array( 'value' => self::PAYMENT_METHOD_TRANSFER ),
			'isTaxIncluded'     => true,
			'reference'         => $order->get_order_number(),
		);
	}

	private function client_name( WC_Order $order ): string {
		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		return '' !== $name ? $name : $order->get_billing_company();
	}

	private function paid_date( WC_Order $order ): string {
		$date = $order->get_date_paid() ?? $order->get_date_created();

		return $date instanceof WC_DateTime ? $date->date( 'Y-m-d' ) : '';
	}
}
