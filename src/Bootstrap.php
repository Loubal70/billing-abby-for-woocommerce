<?php
/**
 * Plugin bootstrap.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

defined( 'ABSPATH' ) || exit;

/**
 * Start-up wiring: HPOS compatibility, the WooCommerce dependency check, then
 * hand-off to Plugin. No business logic lives here.
 */
final class Bootstrap {

	/**
	 * Keep the plugin file path for compatibility flags and module wiring.
	 *
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( private readonly string $plugin_file ) {}

	/**
	 * Register the hooks that start the plugin.
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Declare compatibility with WooCommerce High-Performance Order Storage.
	 */
	public function declare_hpos_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->plugin_file, true );
	}

	/**
	 * Start the plugin, or warn the administrator when WooCommerce is missing.
	 */
	public function boot(): void {
		if ( $this->is_woocommerce_missing() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );

			return;
		}

		Plugin::instance( $this->plugin_file );
	}

	/**
	 * Render the admin notice shown when WooCommerce is not active.
	 */
	public function render_missing_woocommerce_notice(): void {
		$message = __(
			'Billing Abby for WooCommerce requires WooCommerce to be installed and active.',
			'billing-abby-for-woocommerce'
		);

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html( $message )
		);
	}

	/**
	 * Whether WooCommerce is unavailable in the current request.
	 */
	private function is_woocommerce_missing(): bool {
		return ! class_exists( 'WooCommerce' );
	}
}
