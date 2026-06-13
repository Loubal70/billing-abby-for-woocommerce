<?php
/**
 * Draft-invoice sync flow.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use Rankea\BillingAbby\Abby\Client;
use Rankea\BillingAbby\Abby\InvoiceMapper;
use Rankea\BillingAbby\Support\ApiKey;
use Rankea\BillingAbby\Support\Money;
use Rankea\BillingAbby\Support\SyncLog;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps a draft Abby invoice in sync with an order in two guarded, resumable steps, so a
 * retry never creates a second invoice: ensure the draft exists, then push its lines.
 */
final class InvoiceSync {

	use ReportsFailure;

	private const INVOICE_ID_META   = '_bafw_abby_invoice_id';
	private const LINES_SYNCED_META = '_bafw_abby_lines_synced';
	private const CONTACT_ID_META   = '_bafw_abby_contact_id';

	public function is_created( WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::INVOICE_ID_META );
	}

	public function invoice_id( WC_Order $order ): string {
		return (string) $order->get_meta( self::INVOICE_ID_META );
	}

	public function has_synced_lines( WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( self::LINES_SYNCED_META );
	}

	public function sync( WC_Order $order ): void {
		$client     = new Client( ApiKey::get() );
		$mapper     = new InvoiceMapper();
		$invoice_id = $this->ensure_invoice( $client, $mapper, $order );

		$this->ensure_lines( $client, $mapper, $order, $invoice_id );
		$this->verify_total( $client, $order, $invoice_id );
	}

	private function verify_total( Client $client, WC_Order $order, string $invoice_id ): void {
		$invoice    = $client->get_invoice( $invoice_id );
		$abby_cents = $invoice['total']['amountWithTaxAfterDiscount'] ?? null;

		// A read-only cross-check: if the invoice can't be read, the sync still succeeded.
		if ( ! is_int( $abby_cents ) ) {
			return;
		}

		$wc_cents = Money::to_cents( (float) $order->get_total() );

		// The invoice must match the order to the cent; flag any difference, never tolerate one.
		if ( $abby_cents === $wc_cents ) {
			return;
		}

		$message = sprintf(
			'Abby invoice total (%1$d cents) does not match order %2$d total (%3$d cents).',
			$abby_cents,
			$order->get_id(),
			$wc_cents
		);

		SyncLog::error( $order->get_id(), $message );
		$order->add_order_note( $message );
	}

	private function ensure_invoice( Client $client, InvoiceMapper $mapper, WC_Order $order ): string {
		$existing = $this->invoice_id( $order );

		if ( '' !== $existing ) {
			return $existing;
		}

		$contact_id = $this->resolve_contact( $client, $mapper, $order );

		if ( null === $contact_id ) {
			$this->fail( $order, 'contact resolution' );
		}

		$invoice_id = $client->create_invoice( $contact_id );

		if ( null === $invoice_id ) {
			$this->fail( $order, 'draft invoice creation' );
		}

		$order->update_meta_data( self::INVOICE_ID_META, $invoice_id );
		$order->save();

		$order->add_order_note( __( 'Abby: draft invoice created.', 'billing-abby-for-woocommerce' ) );

		return $invoice_id;
	}

	private function ensure_lines( Client $client, InvoiceMapper $mapper, WC_Order $order, string $invoice_id ): void {
		if ( $this->has_synced_lines( $order ) ) {
			return;
		}

		try {
			// Log an unmappable VAT rate instead of failing silently out of the async action.
			$lines = $mapper->invoice_lines( $order );
		} catch ( \DomainException $e ) {
			$this->fail( $order, 'invoice line mapping' );
		}

		if ( array() === $lines ) {
			return;
		}

		if ( ! $client->update_invoice_lines( $invoice_id, $lines ) ) {
			$this->fail( $order, 'invoice lines update' );
		}

		$order->update_meta_data( self::LINES_SYNCED_META, 'yes' );
		$order->save();

		$order->add_order_note( __( 'Abby: invoice lines synced.', 'billing-abby-for-woocommerce' ) );
	}

	/**
	 * Resolves the order's Abby contact id. Abby's contact search matches names, not emails (and
	 * has no email filter), so a contact cannot be looked up by email via the API — reuse the id
	 * stored on a previous order for the same customer instead, to avoid creating duplicates.
	 */
	private function resolve_contact( Client $client, InvoiceMapper $mapper, WC_Order $order ): ?string {
		$payload  = $mapper->contact_payload( $order );
		$existing = $this->known_contact_id( $order );

		if ( null !== $existing ) {
			// Keep the contact in sync with the order's latest details (address, phone…).
			$client->update_contact( $existing, $payload );
			$this->store_contact_id( $order, $existing );

			return $existing;
		}

		$contact_id = $client->create_contact( $payload );

		if ( null !== $contact_id ) {
			$this->store_contact_id( $order, $contact_id );
		}

		return $contact_id;
	}

	/**
	 * The Abby contact id known for this customer: the order's own meta first (a prior attempt may
	 * have created the contact before the invoice), then a previous order with the same email.
	 */
	private function known_contact_id( WC_Order $order ): ?string {
		$own = (string) $order->get_meta( self::CONTACT_ID_META );

		if ( '' !== $own ) {
			return $own;
		}

		$email = $order->get_billing_email();

		if ( '' === $email ) {
			return null;
		}

		$prior = wc_get_orders(
			array(
				'billing_email' => $email,
				'exclude'       => array( $order->get_id() ),
				'limit'         => 10,
				'orderby'       => 'date',
				'order'         => 'DESC',
			)
		);

		foreach ( $prior as $other ) {
			$id = (string) $other->get_meta( self::CONTACT_ID_META );

			if ( '' !== $id ) {
				return $id;
			}
		}

		return null;
	}

	private function store_contact_id( WC_Order $order, string $contact_id ): void {
		$order->update_meta_data( self::CONTACT_ID_META, $contact_id );
		$order->save();
	}
}
