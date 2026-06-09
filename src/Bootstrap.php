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
 * Start-up wiring: HPOS compatibility, WooCommerce dependency check, then hand-off to Plugin.
 */
final class Bootstrap {

	public function __construct( private readonly string $plugin_file ) {}

	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	public function declare_hpos_compatibility(): void {
		if ( ! class_exists( FeaturesUtil::class ) ) {
			return;
		}

		FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->plugin_file, true );
	}

	public function boot(): void {
		if ( $this->is_woocommerce_missing() ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );

			return;
		}

		Plugin::instance( $this->plugin_file );
	}

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

	private function is_woocommerce_missing(): bool {
		return ! class_exists( 'WooCommerce' );
	}
}
