<?php
/**
 * Plugin Name:       Billing Abby for WooCommerce
 * Plugin URI:        https://rankea.agency/tools/billing-abby-for-woocommerce
 * Description:       Connector that syncs WooCommerce orders to Abby invoicing. Not affiliated with Abby or Automattic.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Author:            Rankea
 * Author URI:        https://rankea.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       billing-abby-for-woocommerce
 * Domain Path:       /languages
 *
 * @package Rankea\BillingAbby
 */

defined( 'ABSPATH' ) || exit;

define( 'BAFW_VERSION', '0.1.0' );
define( 'BAFW_PLUGIN_FILE', __FILE__ );
define( 'BAFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Composer autoload (PSR-4: Rankea\BillingAbby\).
$bafw_autoload = BAFW_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $bafw_autoload ) ) {
	require $bafw_autoload;
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Bootstrap the plugin once all plugins are loaded.
 *
 * Requires WooCommerce to be active; shows an admin notice and stops otherwise.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__(
							'Billing Abby for WooCommerce requires WooCommerce to be installed and active.',
							'billing-abby-for-woocommerce'
						)
					);
				}
			);
			return;
		}

		if ( class_exists( \Rankea\BillingAbby\Plugin::class ) ) {
			\Rankea\BillingAbby\Plugin::instance();
		}
	}
);
