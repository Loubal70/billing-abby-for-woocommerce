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

	/**
	 * Queues a draft for a newly placed order. The creation hooks pass either an order id or the
	 * order object, so normalize it first.
	 */
	public function on_order_placed( int|WC_Order $order ): void {
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

		if ( $order instanceof WC_Order ) {
			$this->queue_income_if_due( $order );
		}
	}

	/**
	 * Re-queues the full sync for a manual retry. create_invoice re-runs idempotently and chains
	 * the income entry itself, so re-queuing it is all that is needed.
	 */
	public function request_resync( int $order_id ): void {
		$this->enqueue( self::CREATE_HOOK, $order_id );
	}

	public function has_queued_sync( int $order_id ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		$args = array( 'order_id' => $order_id );

		return as_has_scheduled_action( self::CREATE_HOOK, $args, self::ACTION_GROUP )
			|| as_has_scheduled_action( self::PAID_HOOK, $args, self::ACTION_GROUP );
	}

	public function create_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$this->invoices->sync( $order );
		$this->queue_income_if_due( $order );
	}

	/**
	 * Single owner of the paid → income-book transition: reached both when payment completes and
	 * right after a draft is created for an already-paid order. Idempotent via is_recorded().
	 */
	private function queue_income_if_due( WC_Order $order ): void {
		if ( $this->invoices->is_created( $order ) && $order->is_paid() && ! $this->income->is_recorded( $order ) ) {
			$this->enqueue( self::PAID_HOOK, $order->get_id() );
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
