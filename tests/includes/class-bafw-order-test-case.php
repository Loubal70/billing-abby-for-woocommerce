<?php
/**
 * Shared base for the mapper tests: builds REAL orders and reconciles mapped lines against what the
 * customer paid. The only modelled arithmetic is how Abby bills the lines (abby_billed_cents()).
 *
 * Dev-only file (excluded from the build via .distignore); not collected as a test (no "test-" prefix).
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Support\Money;

abstract class BAFW_Order_Test_Case extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WC_Order' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded in the test environment.' );
		}

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_round_at_subtotal', 'no' );
	}

	public function tear_down() {
		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		wp_cache_flush();

		parent::tear_down();
	}

	/**
	 * Set the run's tax base; a base that does not match the booted run is skipped (covered there).
	 */
	protected function set_tax_base( bool $inclusive ): void {
		if ( $inclusive !== $this->run_is_tax_inclusive() ) {
			$this->markTestSkipped( 'Covered by the other tax-base run (BAFW_PRICES_INCLUDE_TAX).' );
		}

		update_option( 'woocommerce_prices_include_tax', $inclusive ? 'yes' : 'no' );
	}

	protected function run_is_tax_inclusive(): bool {
		return 2 === wc_get_tax_rounding_mode();
	}

	protected function make_product( float $price, string $tax_status = 'taxable' ): WC_Product_Simple {
		$product = new WC_Product_Simple();
		$product->set_regular_price( (string) $price );
		$product->set_tax_status( $tax_status );
		$product->save();

		return $product;
	}

	/**
	 * Insert once per test method; never churn rates within a method — insert/delete cycles corrupt
	 * WooCommerce's tax cache and fabricate rounding errors. Pass a class slug (e.g. 'reduced-rate')
	 * to register a second rate for a mixed-rate order.
	 */
	protected function add_tax_rate( float $rate, string $tax_class = '' ): int {
		return WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => (string) $rate,
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_class'    => $tax_class,
			)
		);
	}

	protected function add_shipping( WC_Order $order, float $cost ): void {
		$rate     = new WC_Shipping_Rate( 'flat_rate:1', 'Flat rate', $cost, array(), 'flat_rate' );
		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_shipping_rate( $rate );
		$order->add_item( $shipping );
	}

	protected function add_fee( WC_Order $order, string $name, float $amount ): void {
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( $name );
		$fee->set_total( (string) $amount );
		$fee->set_tax_status( 'taxable' );
		$order->add_item( $fee );
	}

	/**
	 * Bill the lines the way Abby does — round( unitPrice * quantity ) minus discount, in cents.
	 * The external model the conformity fix targets, deliberately not the mapper's own arithmetic.
	 *
	 * @param array<int, array<string, mixed>> $lines Mapped invoice lines.
	 */
	protected function abby_billed_cents( array $lines ): int {
		$cents = 0;

		foreach ( $lines as $line ) {
			$cents += (int) round( $line['unitPrice'] * $line['quantity'] );
			$cents -= $line['discount']['amount'] ?? 0;
		}

		return $cents;
	}

	/**
	 * What WooCommerce charged in the lines' own base: grand total for a tax-inclusive shop, ex-tax
	 * total for a tax-exclusive one — on a separate code path the mapper cannot fake.
	 */
	protected function charged_cents( WC_Order $order ): int {
		if ( wc_prices_include_tax() ) {
			return Money::to_cents( (float) $order->get_total() );
		}

		return $this->order_net_cents( $order );
	}

	protected function order_net_cents( WC_Order $order ): int {
		return Money::to_cents( (float) $order->get_total() - (float) $order->get_total_tax() );
	}
}
