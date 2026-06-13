<?php
/**
 * Money helpers.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Currency conversions for the Abby API, which expresses every amount in cents.
 */
final class Money {

	/**
	 * Convert a euro amount to integer cents.
	 *
	 * Rounding before the int cast avoids float drift (15.00 * 100 can be 1499.999…).
	 *
	 * @param float $amount Amount in euros.
	 * @return int Amount in cents.
	 */
	public static function to_cents( float $amount ): int {
		return (int) round( $amount * 100 );
	}
}
