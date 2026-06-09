<?php
/**
 * Order synchronization.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Syncs each WooCommerce order to an Abby invoice (create draft, then mark paid)
 * asynchronously via Action Scheduler.
 */
final class OrderSync {

	private const CREATE_HOOK      = 'bafw_create_invoice';
	private const PAID_HOOK        = 'bafw_mark_paid';
	private const ACTION_GROUP     = 'billing-abby';
	private const INVOICE_ID_META  = '_bafw_abby_invoice_id';
	private const PAID_SYNCED_META = '_bafw_abby_paid_synced';
	private const DRAFT_STATUSES   = array( 'auto-draft', 'checkout-draft', 'trash' );

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

		if ( $this->is_draft_order( $order ) ) {
			return;
		}

		if ( $this->already_created( $order ) ) {
			return;
		}

		$this->enqueue( self::CREATE_HOOK, $order->get_id() );
	}

	public function on_payment_complete( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		// No invoice yet: creation runs asynchronously and handles the paid state itself.
		if ( ! $this->already_created( $order ) ) {
			return;
		}

		if ( $this->already_marked_paid( $order ) ) {
			return;
		}

		$this->enqueue( self::PAID_HOOK, $order_id );
	}

	public function create_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order || $this->already_created( $order ) ) {
			return;
		}

		// TODO: confirm endpoints on docs.abby.fr — create the draft invoice, store its
		// id in INVOICE_ID_META, and mark it paid here when the order is already paid.
	}

	public function mark_paid( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->already_created( $order ) || $this->already_marked_paid( $order ) ) {
			return;
		}

		// TODO: confirm endpoints on docs.abby.fr — mark the invoice paid, then set
		// PAID_SYNCED_META (feeds Abby's "livre de recettes").
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

	private function already_created( WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::INVOICE_ID_META );
	}

	private function already_marked_paid( WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::PAID_SYNCED_META );
	}

	private function is_draft_order( WC_Order $order ): bool {
		return in_array( $order->get_status(), self::DRAFT_STATUSES, true );
	}
}
