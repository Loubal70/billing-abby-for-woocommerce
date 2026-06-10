<?php
/**
 * Shared sync failure helper.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Sync;

use WC_Order;

defined( 'ABSPATH' ) || exit;

/**
 * Fails a sync step loudly. A thrown handler is recorded as "failed" by Action Scheduler,
 * and the order's metas let a later run resume from where it stopped.
 */
trait ReportsFailure {

	private function fail( WC_Order $order, string $step ): never {
		throw new \RuntimeException(
			esc_html( sprintf( 'Abby sync failed at %s for order %d.', $step, $order->get_id() ) )
		);
	}
}
