<?php
/**
 * Order synchronization coordinator.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Wires WooCommerce order hooks to the Abby sync flows, enqueuing the work asynchronously
 * via Action Scheduler (never calling Abby during checkout or a page load).
 */
final class OrderSync {

	private const CREATE_HOOK    = 'bafw_create_invoice';
	private const PAID_HOOK      = 'bafw_mark_paid';
	private const ACTION_GROUP   = 'billing-abby';
	private const DRAFT_STATUSES = array( 'auto-draft', 'checkout-draft', 'trash' );

	private readonly InvoiceSync $invoices;
	private readonly IncomeSync $income;

	public function __construct() {
		$this->invoices = new InvoiceSync();
		$this->income   = new IncomeSync();
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_order_placed' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_order_placed' ) );
		add_action( 'woocommerce_new_order', array( $this, 'on_order_placed' ) );
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ) );

		add_action( self::CREATE_HOOK, array( $this, 'create_invoice' ) );
		add_action( self::PAID_HOOK, array( $this, 'mark_paid' ) );
	}

	public function on_order_placed( int|WC_Order $order ): void {
		// Creation hooks pass either an order id or the order object.
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $this->is_draft_order( $order ) || $this->invoices->is_created( $order ) ) {
			return;
		}

		$this->enqueue( self::CREATE_HOOK, $order->get_id() );
	}

	public function on_payment_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->invoices->is_created( $order ) || $this->income->is_recorded( $order ) ) {
			return;
		}

		$this->enqueue( self::PAID_HOOK, $order_id );
	}

	public function create_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( $order instanceof WC_Order ) {
			$this->invoices->sync( $order );
		}
	}

	public function mark_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( $this->invoices->is_created( $order ) && ! $this->income->is_recorded( $order ) ) {
			$this->income->record( $order );
		}
	}

	private function enqueue( string $hook, int $order_id ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		$args = array( 'order_id' => $order_id );

		if ( as_has_scheduled_action( $hook, $args, self::ACTION_GROUP ) ) {
			return;
		}

		as_enqueue_async_action( $hook, $args, self::ACTION_GROUP );
	}

	private function is_draft_order( WC_Order $order ): bool {
		return in_array( $order->get_status(), self::DRAFT_STATUSES, true );
	}
}
