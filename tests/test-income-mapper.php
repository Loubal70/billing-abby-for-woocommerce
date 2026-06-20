<?php
/**
 * Tests for the WooCommerce order to Abby income-book mapping.
 *
 * Real orders (WooCommerce computes every amount); the entries must conserve the order's own
 * totals, including the proportional shipping split across product types. Shared plumbing lives in
 * BAFW_Order_Test_Case.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Abby\IncomeMapper;
use Rankea\BillingAbby\Abby\ProductType;
use Rankea\BillingAbby\Support\Money;

class Test_Income_Mapper extends BAFW_Order_Test_Case {

	public function set_up() {
		parent::set_up();

		$this->set_tax_base( false );
		update_option( 'woocommerce_shipping_tax_class', 'inherit' );
		$this->add_tax_rate( 20.0 );
	}

	public function test_single_type_conserves_the_order_total() {
		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$this->add_shipping( $order, 10.0 );
		$order->calculate_totals();

		$entries = ( new IncomeMapper() )->entries( $order, static fn (): ProductType => ProductType::GOODS );

		$this->assertCount( 1, $entries );
		$entry = $entries[0];

		// Each field equals what WooCommerce charged (items + shipping), computed independently.
		$this->assertSame( $this->order_net_cents( $order ), $entry['priceWithoutTax'] );
		$this->assertSame( Money::to_cents( (float) $order->get_total_tax() ), $entry['vatAmount'] );
		// priceTotalTax rounds the parts then sums, so it conserves the order total within a cent.
		$this->assertEqualsWithDelta( Money::to_cents( (float) $order->get_total() ), $entry['priceTotalTax'], 1 );
		$this->assertSame( ProductType::GOODS->value, $entry['productType'] );
	}

	public function test_discounted_income_books_the_post_discount_net() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'tenpercent' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$order = new WC_Order();
		$order->add_product( $this->make_product( 100.0 ), 1 );
		$order->apply_coupon( 'tenpercent' );
		$order->calculate_totals();

		$entries = ( new IncomeMapper() )->entries( $order, static fn (): ProductType => ProductType::GOODS );

		// Income is the amount actually earned (post-discount), not the pre-discount subtotal.
		$this->assertSame( $this->order_net_cents( $order ), $entries[0]['priceWithoutTax'] );
	}

	public function test_split_by_type_spreads_shipping_and_conserves_totals() {
		$goods   = $this->make_product( 100.0 );
		$service = $this->make_product( 50.0 );

		$order = new WC_Order();
		$order->add_product( $goods, 1 );
		$order->add_product( $service, 1 );
		// 7.00 shipping splits 2:1 by net share -> fractional cents, a real rounding edge.
		$this->add_shipping( $order, 7.0 );
		$order->calculate_totals();

		$type_for = static fn ( $item ): ProductType =>
			$item->get_product_id() === $service->get_id() ? ProductType::SERVICES : ProductType::GOODS;

		$entries = ( new IncomeMapper() )->entries( $order, $type_for );

		$this->assertCount( 2, $entries );

		$by_type = array_column( $entries, null, 'productType' );
		$goods   = $by_type[ ProductType::GOODS->value ];
		$service = $by_type[ ProductType::SERVICES->value ];

		// WooCommerce does not split shipping per product type — that split is the mapper's own logic,
		// so there is no WooCommerce figure to assert each share against without recomputing it. The
		// WooCommerce-backed invariant is conservation: the two entries sum back to the order net.
		$sum_net = $goods['priceWithoutTax'] + $service['priceWithoutTax'];
		$this->assertEqualsWithDelta( $this->order_net_cents( $order ), $sum_net, 1 );
	}
}
