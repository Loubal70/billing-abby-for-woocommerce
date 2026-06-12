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

	public function sync( WC_Order $order ): void {
		$client     = new Client( ApiKey::get() );
		$mapper     = new InvoiceMapper();
		$invoice_id = $this->ensure_invoice( $client, $mapper, $order );

		$this->ensure_lines( $client, $mapper, $order, $invoice_id );
	}

	private function ensure_invoice( Client $client, InvoiceMapper $mapper, WC_Order $order ): string {
		$existing = (string) $order->get_meta( self::INVOICE_ID_META );

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
		if ( '' !== (string) $order->get_meta( self::LINES_SYNCED_META ) ) {
			return;
		}

		$lines = $mapper->invoice_lines( $order );

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

	private function resolve_contact( Client $client, InvoiceMapper $mapper, WC_Order $order ): ?string {
		// Abby's contact search matches names, not emails (and has no email filter), so a
		// contact cannot be looked up by email via the API. Reuse the id we stored on a
		// previous order for the same customer instead, to avoid creating duplicates.
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

	private function known_contact_id( WC_Order $order ): ?string {
		// This order's own id first, in case a previous attempt created the contact but failed
		// before the invoice was created.
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
