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

require_once __DIR__ . '/src/Autoloader.php';

\Rankea\BillingAbby\Autoloader::register();

( new \Rankea\BillingAbby\Bootstrap( __FILE__ ) )->register();
