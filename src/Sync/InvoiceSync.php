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
	}

	private function resolve_contact( Client $client, InvoiceMapper $mapper, WC_Order $order ): ?string {
		$email = $order->get_billing_email();

		if ( '' !== $email ) {
			$existing = $client->find_contact_id( $email );

			if ( null !== $existing ) {
				return $existing;
			}
		}

		return $client->create_contact( $mapper->contact_payload( $order ) );
	}
}
