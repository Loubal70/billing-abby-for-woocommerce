<?php
/**
 * Order-screen Abby invoice panel.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Automattic\WooCommerce\Utilities\OrderUtil;
use Rankea\BillingAbby\Abby\Client;
use Rankea\BillingAbby\Support\ApiKey;
use Rankea\BillingAbby\Support\SyncLog;
use Rankea\BillingAbby\Sync\IncomeSync;
use Rankea\BillingAbby\Sync\InvoiceSync;
use Rankea\BillingAbby\Sync\OrderSync;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the Abby sync status of an order, lets the merchant retry a failed sync, and streams the
 * invoice PDF straight from Abby (the API key never reaches the browser).
 */
final class OrderInvoicePanel {

	private const CAPABILITY      = 'manage_woocommerce';
	private const RESYNC_ACTION   = 'bafw_resync_invoice';
	private const DOWNLOAD_ACTION = 'bafw_download_invoice';

	private readonly InvoiceSync $invoices;
	private readonly IncomeSync $income;

	public function __construct( private readonly OrderSync $orders ) {
		$this->invoices = new InvoiceSync();
		$this->income   = new IncomeSync();
	}

	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_post_' . self::RESYNC_ACTION, array( $this, 'handle_resync' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'bafw-order-invoice',
			__( 'Abby invoice', 'billing-abby-for-woocommerce' ),
			array( $this, 'render' ),
			$this->order_screen_id(),
			'normal',
			'high'
		);
	}

	public function render( $post_or_order ): void {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( '' === ApiKey::get() ) {
			$this->render_missing_key();

			return;
		}

		$status = $this->status( $order );

		$this->render_status_notice( $status['state'], $status['message'], $status['date'] );
		$this->render_actions( $order, $status['state'] );
	}

	public function handle_resync(): void {
		$this->deny_unless_allowed();
		check_admin_referer( self::RESYNC_ACTION );

		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;

		$this->orders->request_resync( $order_id );

		wp_safe_redirect( $this->order_url( $order_id ) );
		exit;
	}

	public function handle_download(): void {
		$this->deny_unless_allowed();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only, capability-gated download, intentionally shareable between authorized staff (no per-user nonce).
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'billing-abby-for-woocommerce' ) );
		}

		$invoice_id = $this->invoices->invoice_id( $order );

		if ( '' === $invoice_id ) {
			wp_safe_redirect( $this->order_url( $order_id ) );
			exit;
		}

		$pdf = ( new Client( ApiKey::get() ) )->download_invoice( $invoice_id );

		if ( null === $pdf ) {
			wp_die( esc_html__( 'The invoice could not be downloaded from Abby.', 'billing-abby-for-woocommerce' ) );
		}

		$this->stream_pdf( $pdf, $order_id );
	}

	private function deny_unless_allowed(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'billing-abby-for-woocommerce' ) );
		}
	}

	private function status( WC_Order $order ): array {
		$created = $this->invoices->is_created( $order );
		$lines   = $this->invoices->has_synced_lines( $order );

		// A paid order is only "complete" once its income entry is recorded too.
		$complete = $created && $lines && ( ! $order->is_paid() || $this->income->is_recorded( $order ) );

		if ( $complete ) {
			return array(
				'state'   => OrderSyncState::Synced,
				'message' => '',
				'date'    => '',
			);
		}

		// A queued action wins over a stale error so a fresh retry reads as "in progress".
		if ( $this->orders->has_queued_sync( $order->get_id() ) ) {
			return array(
				'state'   => OrderSyncState::InProgress,
				'message' => '',
				'date'    => '',
			);
		}

		$error = SyncLog::last_for_order( $order->get_id() );

		if ( null !== $error ) {
			return array(
				'state'   => OrderSyncState::Failed,
				'message' => (string) $error['message'],
				'date'    => (string) $error['date'],
			);
		}

		return array(
			'state'   => $created ? OrderSyncState::InProgress : OrderSyncState::NotSynced,
			'message' => '',
			'date'    => '',
		);
	}

	private function render_status_notice( OrderSyncState $state, string $message, string $date ): void {
		printf(
			'<div class="notice notice-%1$s inline"><p><strong>%2$s</strong></p>%3$s</div>',
			esc_attr( OrderSyncState::notice_type( $state ) ),
			esc_html( OrderSyncState::label( $state ) ),
			wp_kses_post( $this->status_detail( $state, $message, $date ) )
		);
	}

	private function status_detail( OrderSyncState $state, string $message, string $date ): string {
		if ( OrderSyncState::InProgress === $state ) {
			return '<p class="description">' . esc_html__(
				'Abby is processing this in the background. Refresh in a moment.',
				'billing-abby-for-woocommerce'
			) . '</p>';
		}

		if ( OrderSyncState::Failed === $state && '' !== $message ) {
			return sprintf(
				'<p>%1$s<br><span class="description">%2$s</span></p>',
				esc_html( $message ),
				esc_html( $date )
			);
		}

		return '';
	}

	private function render_actions( WC_Order $order, OrderSyncState $state ): void {
		echo '<p>';

		if ( $this->invoices->is_created( $order ) ) {
			$this->render_download_link( $order );
		}

		if ( OrderSyncState::NotSynced === $state || OrderSyncState::Failed === $state ) {
			$this->render_retry_link( $order, $state );
		}

		$this->render_refresh_link( $order );

		echo '</p>';
	}

	private function render_refresh_link( WC_Order $order ): void {
		printf(
			'<a href="%1$s" class="button" aria-label="%2$s" title="%2$s"><span class="dashicons dashicons-update" aria-hidden="true"></span> %3$s</a>',
			esc_url( $this->order_url( $order->get_id() ) ),
			esc_attr__( 'Refresh sync status', 'billing-abby-for-woocommerce' ),
			esc_html__( 'Refresh', 'billing-abby-for-woocommerce' )
		);
	}

	/**
	 * Renders the invoice-download button. The link carries no nonce: it is a read-only document
	 * link gated by capability, so it stays usable and shareable between authorized staff.
	 */
	private function render_download_link( WC_Order $order ): void {
		$url = admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION . '&order_id=' . $order->get_id() );

		printf(
			'<a class="button button-primary" href="%1$s" target="_blank" rel="noopener">%2$s</a> ',
			esc_url( $url ),
			esc_html__( 'View invoice (PDF)', 'billing-abby-for-woocommerce' )
		);
	}

	/**
	 * Renders the retry button as a nonce'd link, not a nested <form>: the meta box is rendered
	 * inside the order's own form, where a nested form would not submit.
	 */
	private function render_retry_link( WC_Order $order, OrderSyncState $state ): void {
		$label = OrderSyncState::NotSynced === $state
			? __( 'Sync now', 'billing-abby-for-woocommerce' )
			: __( 'Retry sync', 'billing-abby-for-woocommerce' );

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::RESYNC_ACTION . '&order_id=' . $order->get_id() ),
			self::RESYNC_ACTION
		);

		printf(
			'<a class="button" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private function render_missing_key(): void {
		printf(
			'<p>%1$s</p><p><a href="%2$s" class="button">%3$s</a></p>',
			esc_html__( 'Add your Abby API key to start syncing orders.', 'billing-abby-for-woocommerce' ),
			esc_url( admin_url( 'admin.php?page=bafw-settings' ) ),
			esc_html__( 'Open settings', 'billing-abby-for-woocommerce' )
		);
	}

	private function stream_pdf( string $pdf, int $order_id ): never {
		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="abby-invoice-' . $order_id . '.pdf"' );
		header( 'Content-Length: ' . strlen( $pdf ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw binary PDF stream from Abby.
		echo $pdf;
		exit;
	}

	private function order_screen_id(): string {
		return $this->hpos_enabled() ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
	}

	private function order_url( int $order_id ): string {
		if ( $this->hpos_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}

		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	private function hpos_enabled(): bool {
		return class_exists( OrderUtil::class ) && OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
