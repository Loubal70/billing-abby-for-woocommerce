<?php
/**
 * PSR-4 autoloader for the plugin's own classes.
 *
 * Composer is used for development tooling (WPCS, PHPUnit), but the plugin has
 * no runtime dependencies, so it ships without a vendor/ directory and loads
 * its classes with this lightweight autoloader instead.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the Rankea\BillingAbby\ namespace to the src/ directory.
 */
final class Autoloader {

	/**
	 * Namespace prefix handled by this autoloader.
	 *
	 * @var string
	 */
	private const PREFIX = 'Rankea\\BillingAbby\\';

	/**
	 * Register the autoloader with the SPL stack.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'autoload' ) );
	}

	/**
	 * Load the file backing a class when it belongs to this plugin.
	 *
	 * @param string $class_name Fully qualified class name requested by PHP.
	 */
	public static function autoload( string $class_name ): void {
		if ( ! self::owns( $class_name ) ) {
			return;
		}

		$file = self::path_for( $class_name );

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Whether the class belongs to this plugin's namespace.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	private static function owns( string $class_name ): bool {
		return str_starts_with( $class_name, self::PREFIX );
	}

	/**
	 * Resolve the source file path for a plugin class.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	private static function path_for( string $class_name ): string {
		$relative_class = substr( $class_name, strlen( self::PREFIX ) );

		return __DIR__ . '/' . str_replace( '\\', '/', $relative_class ) . '.php';
	}
}
