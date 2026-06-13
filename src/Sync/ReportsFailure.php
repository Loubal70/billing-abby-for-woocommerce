<?php
/**
 * Shared sync failure helper.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use Rankea\BillingAbby\Support\SyncLog;
use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Fails a sync step loudly: log the error, then throw so Action Scheduler records the action
 * as failed. The order's metas let a later run resume from where it stopped.
 */
trait ReportsFailure {

	private function fail( WC_Order $order, string $step ): never {
		$message = sprintf( 'Abby sync failed at %s for order %d.', $step, $order->get_id() );

		SyncLog::error( $order->get_id(), $message );

		throw new \RuntimeException( esc_html( $message ) );
	}
}
