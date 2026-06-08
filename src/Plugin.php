<?php
/**
 * Main plugin class.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby;

use Rankea\BillingAbby\Admin\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton: wires up feature modules.
 */
final class Plugin {

	/** Plugin version, kept in sync with the main file header and readme.txt. */
	public const VERSION = '0.1.0';

	/**
	 * Shared instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Create the instance on first call and boot it.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public static function instance( string $plugin_file ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $plugin_file );
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Enforce the singleton.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	private function __construct( private readonly string $plugin_file ) {}

	/**
	 * Absolute path to the main plugin file.
	 */
	public function file(): string {
		return $this->plugin_file;
	}

	/**
	 * Filesystem path to the plugin directory (trailing slash).
	 */
	public function dir(): string {
		return plugin_dir_path( $this->plugin_file );
	}

	/**
	 * URL to the plugin directory (trailing slash).
	 */
	public function url(): string {
		return plugin_dir_url( $this->plugin_file );
	}

	/**
	 * Register feature modules.
	 */
	private function init(): void {
		( new Settings( $this ) )->register();
	}
}
