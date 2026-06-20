<?php
/**
 * Systematic amount-conformity coverage for the order to Abby invoice-line mapping.
 *
 * Two techniques, both over REAL orders and both reconciling against WooCommerce's own get_total()
 * (an oracle the mapper never touches), so neither can pass by echoing the implementation:
 *   1. a data-provider MATRIX over rate x quantity x tax-base, asserting the VAT code and that the
 *      lines reconcile to the cent;
 *   2. a SEEDED price sweep that, for each (rate, tax-base), runs hundreds of real orders at a
 *      single fixed rate and reconciles every one — reproducing any failure from its logged seed.
 *
 * The rate is inserted once per test method and never churned mid-method: that churn was what
 * corrupted WooCommerce's tax cache and fabricated phantom one-cent errors in the previous version.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Abby\InvoiceMapper;
use Rankea\BillingAbby\Abby\VatCode;

class Test_Invoice_Mapper_Matrix extends BAFW_Order_Test_Case {

	// Exercises the 1-decimal unit-price precision: 19.99 carries a third decimal at most rates.
	private const PRICE = 19.99;

	private const SUPPORTED_RATES = array( 20.0, 10.0, 8.5, 5.5, 2.1, 0.0 );

	private const SWEEP_ORDERS = 200;

	/**
	 * Every (rate x quantity x tax-base) combination, each row labelled so a failure names the
	 * exact case. Coupons are covered by the dedicated tests in test-invoice-mapper.php.
	 *
	 * @return array<string, array{0:float,1:int,2:bool}>
	 */
	public function matrix_provider(): array {
		$quantities = array( 1, 2, 3, 7 );
		$tax_bases  = array(
			'tax-exclusive' => false,
			'tax-inclusive' => true,
		);

		$rows = array();

		foreach ( self::SUPPORTED_RATES as $rate ) {
			foreach ( $quantities as $quantity ) {
				foreach ( $tax_bases as $base_label => $prices_include_tax ) {
					$label          = sprintf( '%s%% x%d %s', $rate, $quantity, $base_label );
					$rows[ $label ] = array( $rate, $quantity, $prices_include_tax );
				}
			}
		}

		return $rows;
	}

	/**
	 * @dataProvider matrix_provider
	 */
	public function test_lines_reconcile_across_the_matrix( float $rate, int $quantity, bool $prices_include_tax ) {
		$this->set_tax_base( $prices_include_tax );
		$this->add_tax_rate( $rate );

		$order = new WC_Order();
		$order->add_product( $this->make_product( self::PRICE ), $quantity );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( VatCode::from_rate( $rate )->value, $lines[0]['vatCode'] );
		$this->assertSame( $prices_include_tax, $lines[0]['isTaxIncluded'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	/**
	 * One (rate, tax-base) per row; each runs the seeded price sweep at that single fixed rate.
	 *
	 * @return array<string, array{0:float,1:bool}>
	 */
	public function rate_base_provider(): array {
		$rows = array();

		foreach ( self::SUPPORTED_RATES as $rate ) {
			$rows[ sprintf( '%s%% HT', $rate ) ]  = array( $rate, false );
			$rows[ sprintf( '%s%% TTC', $rate ) ] = array( $rate, true );
		}

		return $rows;
	}

	/**
	 * Conservation invariant: at a fixed rate and base, hundreds of real orders over random
	 * price x quantity each reconcile to what WooCommerce charged. Deterministic — the seed folds
	 * in the case, and a failure prints seed+iteration to replay it:
	 *   BAFW_SEED=12345 vendor/bin/phpunit --filter test_price_sweep_reconciles
	 *
	 * @dataProvider rate_base_provider
	 */
	public function test_price_sweep_reconciles( float $rate, bool $prices_include_tax ) {
		$this->set_tax_base( $prices_include_tax );
		$this->add_tax_rate( $rate );

		$seed = (int) ( getenv( 'BAFW_SEED' ) ?: 20260620 );
		// Hash the case into the seed so every (rate, base) explores its own prices and a new rate
		// can never collide with an existing row's sweep.
		mt_srand( $seed + (int) crc32( sprintf( '%.1f-%d', $rate, $prices_include_tax ) ) );

		for ( $iteration = 0; $iteration < self::SWEEP_ORDERS; $iteration++ ) {
			// Down to 1 cent — covers the small TTC prices that threw before the VAT-rate fix.
			$price_cents = mt_rand( 1, 100000 );
			$quantity    = mt_rand( 1, 30 );

			$product = $this->make_product( $price_cents / 100 );
			$order   = new WC_Order();
			$order->add_product( $product, $quantity );
			$order->calculate_totals();

			$lines = ( new InvoiceMapper() )->invoice_lines( $order );

			$context = sprintf(
				'seed=%d iter=%d price=%d qty=%d rate=%s %s',
				$seed,
				$iteration,
				$price_cents,
				$quantity,
				$rate,
				$prices_include_tax ? 'TTC' : 'HT'
			);
			$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ), $context );

			// Drop the order and product, but never the rate: re-inserting it would churn the cache.
			$order->delete( true );
			$product->delete( true );
		}
	}
}
