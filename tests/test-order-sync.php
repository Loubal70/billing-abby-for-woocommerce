<?php
/**
 * Tests for the order synchronization wiring.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Sync\OrderSync;

/**
 * Verifies OrderSync registers its hooks.
 */
class Test_Order_Sync extends WP_UnitTestCase {

	/**
	 * register() hooks both flows: invoice creation and the paid update.
	 */
	public function test_register_hooks() {
		$sync = new OrderSync();
		$sync->register();

		$this->assertNotFalse(
			has_action( 'woocommerce_new_order', array( $sync, 'on_order_placed' ) )
		);
		$this->assertNotFalse(
			has_action( 'woocommerce_payment_complete', array( $sync, 'on_payment_complete' ) )
		);
		$this->assertNotFalse(
			has_action( 'bafw_create_invoice', array( $sync, 'create_invoice' ) )
		);
		$this->assertNotFalse(
			has_action( 'bafw_mark_paid', array( $sync, 'mark_paid' ) )
		);
	}
}
