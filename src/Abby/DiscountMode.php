<?php
/**
 * Abby discount modes.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

defined( 'ABSPATH' ) || exit;

/**
 * Abby DiscountDto modes (confirmed live: the API rejects numeric modes, and reads an AMOUNT
 * discount in cents like unitPrice, not euros despite the docs).
 */
enum DiscountMode: string {

	case PERCENTAGE = 'PERCENTAGE';
	case AMOUNT     = 'AMOUNT';
}
