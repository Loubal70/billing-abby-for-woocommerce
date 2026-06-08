<?php
/**
 * Main plugin class.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton: wires up hooks and loads the text domain.
 *
 * Feature wiring (settings, API client, order sync) lands in later phases.
 */
final class Plugin {

	/**
	 * Plugin version, kept in sync with the main file header and readme.txt.
	 *
	 * @var string
	 */
	public const VERSION = '0.1.0';

	/**
	 * Single shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Retrieve the shared instance, creating it on first call.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce the singleton.
	 */
	private function __construct() {}

	/**
	 * Register WordPress hooks.
	 */
	private function init(): void {
		// Feature modules are registered here in later phases.
	}
}
