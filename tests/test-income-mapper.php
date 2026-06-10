<?php
/**
 * Tests for the WooCommerce order to Abby income-book mapping.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Abby\IncomeMapper;
use Rankea\BillingAbby\Abby\ProductType;

/**
 * Verifies IncomeMapper groups order lines by product type and books amounts in cents.
 */
class Test_Income_Mapper extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WC_Order' ) ) {
			$this->markTestSkipped( 'WooCommerce is not loaded in the test environment.' );
		}
	}

	/**
	 * One product type yields a single income entry; shipping joins it (net + tax in cents).
	 */
	public function test_single_type_absorbs_shipping() {
		$order = new WC_Order();

		$item = new WC_Order_Item_Product();
		$item->set_name( 'Good' );
		$item->set_quantity( 1 );
		$item->set_total( 100.0 );
		$item->set_total_tax( 20.0 );
		$order->add_item( $item );

		$order->set_shipping_total( 10.0 );
		$order->set_shipping_tax( 2.0 );

		$entries = ( new IncomeMapper() )->entries(
			$order,
			static fn (): ProductType => ProductType::GOODS
		);

		$this->assertCount( 1, $entries );
		$this->assertSame( ProductType::GOODS->value, $entries[0]['productType'] );
		$this->assertSame( 11000, $entries[0]['priceWithoutTax'] );
		$this->assertSame( 13200, $entries[0]['priceTotalTax'] );
		$this->assertSame( 2200, $entries[0]['vatAmount'] );
	}

	/**
	 * Mixed product types split the order into one income entry per type.
	 */
	public function test_split_by_type() {
		$order = new WC_Order();

		$goods = new WC_Order_Item_Product();
		$goods->set_name( 'Good' );
		$goods->set_quantity( 1 );
		$goods->set_total( 100.0 );
		$goods->set_total_tax( 20.0 );
		$order->add_item( $goods );

		$service = new WC_Order_Item_Product();
		$service->set_name( 'Service' );
		$service->set_quantity( 1 );
		$service->set_total( 50.0 );
		$service->set_total_tax( 0.0 );
		$order->add_item( $service );

		$entries = ( new IncomeMapper() )->entries(
			$order,
			static fn ( $item ): ProductType =>
				str_contains( $item->get_name(), 'Service' ) ? ProductType::SERVICES : ProductType::GOODS
		);

		$this->assertCount( 2, $entries );

		$by_type = array_column( $entries, null, 'productType' );
		$this->assertSame( 10000, $by_type[ ProductType::GOODS->value ]['priceWithoutTax'] );
		$this->assertSame( 5000, $by_type[ ProductType::SERVICES->value ]['priceWithoutTax'] );
	}
}
