<?php
/**
 * Abby API key storage.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Single source for reading/writing the encrypted Abby API key option.
 */
final class ApiKey {

	private const OPTION = 'bafw_abby_api_key';

	public static function get(): string {
		return Secret::decrypt( (string) get_option( self::OPTION, '' ) );
	}

	public static function save( string $key ): void {
		// Autoload disabled: the key is only read in admin / async contexts.
		update_option( self::OPTION, Secret::encrypt( $key ), false );
	}
}
