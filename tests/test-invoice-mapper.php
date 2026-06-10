<?php
/**
 * Tests for the WooCommerce order to Abby payload mapping.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Abby\InvoiceMapper;

/**
 * Verifies InvoiceMapper builds net, cents-based lines with the right vatCode.
 */
class Test_Invoice_Mapper extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WC_Order' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded in the test environment.' );
		}
	}

	/**
	 * Product lines keep their own VAT rate; amounts are net per unit in cents.
	 */
	public function test_invoice_lines_map_per_line_vat() {
		$order = new WC_Order();

		$standard = new WC_Order_Item_Product();
		$standard->set_name( 'Standard rate good' );
		$standard->set_quantity( 2 );
		$standard->set_total( 100.0 );
		$standard->set_total_tax( 20.0 );
		$order->add_item( $standard );

		$reduced = new WC_Order_Item_Product();
		$reduced->set_name( 'Reduced rate good' );
		$reduced->set_quantity( 1 );
		$reduced->set_total( 200.0 );
		$reduced->set_total_tax( 11.0 );
		$order->add_item( $reduced );

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );

		$this->assertSame( 5000, $lines[0]['unitPrice'] );
		$this->assertSame( 'FR_2000', $lines[0]['vatCode'] );
		$this->assertFalse( $lines[0]['isTaxIncluded'] );

		$this->assertSame( 20000, $lines[1]['unitPrice'] );
		$this->assertSame( 'FR_550', $lines[1]['vatCode'] );
	}

	/**
	 * A zero-tax line (franchise en base / exonéré) maps to FR_00HT.
	 */
	public function test_zero_tax_line_is_exempt() {
		$order = new WC_Order();

		$item = new WC_Order_Item_Product();
		$item->set_name( 'Exempt good' );
		$item->set_quantity( 1 );
		$item->set_total( 50.0 );
		$item->set_total_tax( 0.0 );
		$order->add_item( $item );

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertSame( 'FR_00HT', $lines[0]['vatCode'] );
	}

	/**
	 * An unsupported VAT rate is refused rather than mislabelled.
	 */
	public function test_unsupported_vat_rate_is_refused() {
		$order = new WC_Order();

		$item = new WC_Order_Item_Product();
		$item->set_name( 'Odd rate good' );
		$item->set_quantity( 1 );
		$item->set_total( 100.0 );
		$item->set_total_tax( 21.0 );
		$order->add_item( $item );

		$this->expectException( DomainException::class );

		( new InvoiceMapper() )->invoice_lines( $order );
	}

	/**
	 * Shipping becomes its own line, with its own VAT.
	 */
	public function test_shipping_is_its_own_line() {
		$order = new WC_Order();

		$item = new WC_Order_Item_Product();
		$item->set_name( 'Good' );
		$item->set_quantity( 1 );
		$item->set_total( 100.0 );
		$item->set_total_tax( 20.0 );
		$order->add_item( $item );

		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Flat rate' );
		$shipping->set_total( 10.0 );
		$shipping->set_total_tax( 2.0 );
		$order->add_item( $shipping );

		$lines = ( new InvoiceMapper() )->invoice_lines( $order );

		$this->assertCount( 2, $lines );
		$this->assertSame( 'Flat rate', $lines[1]['designation'] );
		$this->assertSame( 1000, $lines[1]['unitPrice'] );
		$this->assertSame( 'FR_2000', $lines[1]['vatCode'] );
	}
}
