<?php
/**
 * Named-scenario tests for the WooCommerce order to Abby invoice-line mapping.
 *
 * Each test builds a REAL order (real product, real tax rate, WooCommerce computes every amount)
 * and asserts the mapped lines, billed the way Abby bills them, reconcile to the figure actually
 * charged — which the mapper cannot fake. Shared plumbing lives in BAFW_Order_Test_Case.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Abby\InvoiceMapper;
use Rankea\BillingAbby\Support\Money;

class Test_Invoice_Mapper extends BAFW_Order_Test_Case {

	public function test_tax_exclusive_order_reconciles_to_the_order_total() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 2 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( 'FR_2000', $lines[0]['vatCode'] );
		$this->assertFalse( $lines[0]['isTaxIncluded'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_tax_inclusive_order_reconciles_to_the_paid_total() {
		$this->set_tax_base( true );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 19.99 ), 3 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertTrue( $lines[0]['isTaxIncluded'] );
		$this->assertSame( 'FR_2000', $lines[0]['vatCode'] );
		// Abby rejects more than 1 decimal: enforce the precision the reconciliation relies on.
		$this->assertSame( round( $lines[0]['unitPrice'], 1 ), $lines[0]['unitPrice'] );
		// get_total() is WooCommerce's own figure, on a different code path; the 1-decimal-cent
		// unit price must reconstruct it to the cent.
		$this->assertSame( Money::to_cents( (float) $order->get_total() ), $this->abby_billed_cents( $lines ) );
	}

	public function test_coupon_discount_reconciles_to_the_order_total() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'tenpercent' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 3 );
		$order->apply_coupon( 'tenpercent' );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		// The line discount equals the discount WooCommerce itself computed for the order.
		$this->assertSame( Money::to_cents( (float) $order->get_discount_total() ), $lines[0]['discount']['amount'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_shipping_is_its_own_line_and_reconciles() {
		$this->set_tax_base( false );
		update_option( 'woocommerce_shipping_tax_class', 'inherit' );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$this->add_shipping( $order, 10.0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Flat rate', $lines[1]['designation'] );
		$this->assertFalse( $lines[1]['isTaxIncluded'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_zero_rate_line_is_exempt_and_reconciles() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 0.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 50.0 ), 1 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( 'FR_00HT', $lines[0]['vatCode'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_reduced_vat_rate_maps_to_its_code() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 2.1 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		// 2.1% must map to FR_210; a vat_code rounded to 0 decimals would throw instead.
		$this->assertSame( 'FR_210', $lines[0]['vatCode'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_unsupported_vat_rate_is_refused() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 21.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$order->calculate_totals();

		$this->expectException( DomainException::class );

		( new InvoiceMapper() )->invoice_lines( $order );
	}

	public function test_fee_is_its_own_line_and_reconciles() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$this->add_fee( $order, 'Gift wrap', 5.0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Gift wrap', $lines[1]['designation'] );
		$this->assertSame( 'FR_2000', $lines[1]['vatCode'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_fee_reconciles_in_a_tax_inclusive_shop() {
		$this->set_tax_base( true );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 12.0 ), 1 );
		$this->add_fee( $order, 'Gift wrap', 5.0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Gift wrap', $lines[1]['designation'] );
		$this->assertTrue( $lines[1]['isTaxIncluded'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_negative_fee_is_a_negative_line_and_reconciles() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$this->add_fee( $order, 'Loyalty rebate', -10.0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Loyalty rebate', $lines[1]['designation'] );
		$this->assertLessThan( 0, $lines[1]['unitPrice'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_negative_fee_reconciles_in_a_tax_inclusive_shop() {
		$this->set_tax_base( true );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$this->add_fee( $order, 'Loyalty rebate', -10.0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertLessThan( 0, $lines[1]['unitPrice'] );
		$this->assertTrue( $lines[1]['isTaxIncluded'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_full_checkout_reconciles_tax_exclusive() {
		$this->set_tax_base( false );

		$order = $this->full_checkout_order();
		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_full_checkout_reconciles_tax_inclusive() {
		$this->set_tax_base( true );

		$order = $this->full_checkout_order();
		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	/**
	 * A realistic checkout: two product lines, a percentage coupon, taxed shipping and a fee, so the
	 * reconciliation stresses rounding accumulated across every line type at once.
	 */
	private function full_checkout_order(): WC_Order {
		update_option( 'woocommerce_shipping_tax_class', 'inherit' );
		$this->add_tax_rate( 20.0 );

		$coupon = new WC_Coupon();
		$coupon->set_code( 'tenpercent' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = new WC_Order();
		$order->add_product( $this->make_product( 19.99 ), 3 );
		$order->add_product( $this->make_product( 7.5 ), 2 );
		$order->apply_coupon( 'tenpercent' );
		$this->add_shipping( $order, 6.9 );
		$this->add_fee( $order, 'Gift wrap', 4.0 );
		$order->calculate_totals();

		return $order;
	}

	public function test_mixed_vat_rates_in_one_order_reconcile() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );
		$this->add_tax_rate( 5.5, 'reduced-rate' );

		$reduced = $this->make_product( 50.0 );
		$reduced->set_tax_class( 'reduced-rate' );
		$reduced->save();

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 2 );
		$order->add_product( $reduced, 3 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$codes = array_column( $lines, 'vatCode' );
		$this->assertContains( 'FR_2000', $codes );
		$this->assertContains( 'FR_550', $codes );
		// Each line keeps its own rate, and the two together still reconcile to the order total.
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_vat_exempt_shop_books_exempt_and_reconciles() {
		$this->set_tax_base( false );
		// Franchise en base: the trader charges no VAT at all (taxes off, no tax line).
		update_option( 'woocommerce_calc_taxes', 'no' );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 2 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( 'FR_00HT', $lines[0]['vatCode'] );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}

	public function test_zero_quantity_item_is_skipped() {
		$this->set_tax_base( false );
		$this->add_tax_rate( 20.0 );

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 2 );
		$order->add_product( $this->make_product( 30.0 ), 0 );
		$order->calculate_totals();

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 1, $lines );
		$this->assertSame( $this->charged_cents( $order ), $this->abby_billed_cents( $lines ) );
	}
}
