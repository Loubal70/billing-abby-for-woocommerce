<?php
/**
 * Abby API key validation outcome.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

defined( 'ABSPATH' ) || exit;

/**
 * Result of checking an Abby API key. The backing values cross the REST boundary to the
 * settings panel, so they must stay in sync with the JS that maps them to a message.
 */
enum KeyStatus: string {

	case VALID     = 'valid';
	case INVALID   = 'invalid';
	case FORBIDDEN = 'forbidden';
	case ERROR     = 'error';
}
