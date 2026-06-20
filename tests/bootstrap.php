<?php
/**
 * PHPUnit bootstrap file.
 *
 * Development-only file (excluded from the distributed build via .distignore).
 * It runs before WordPress is loaded, so an ABSPATH guard is not applicable, and
 * the test-suite globals below cannot use the namespace. Plugin Check derives the
 * expected prefix from the slug and does not recognise the `bafw` acronym, so the
 * naming sniff is disabled for this file only.
 *
 * @package Rankea\BillingAbby
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

$bafw_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $bafw_tests_dir ) {
	$bafw_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to the PHPUnit bootstrap file.
$bafw_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $bafw_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $bafw_phpunit_polyfills_path );
}

if ( ! file_exists( "{$bafw_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$bafw_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$bafw_tests_dir}/includes/functions.php";

/**
 * Manually load WooCommerce (a hard dependency) and the plugin being tested.
 */
function bafw_manually_load_plugin() {
	// Set the base before WC boots: it freezes WC_TAX_ROUNDING_MODE from this option, so the
	// inclusive run (BAFW_PRICES_INCLUDE_TAX=1) rounds tax like a real inclusive shop.
	if ( '1' === getenv( 'BAFW_PRICES_INCLUDE_TAX' ) ) {
		update_option( 'woocommerce_prices_include_tax', 'yes' );
	}

	// Local (DDEV): WooCommerce sits next to this plugin. CI provides WC_PLUGIN_PATH instead.
	$bafw_woocommerce = getenv( 'WC_PLUGIN_PATH' );

	if ( ! $bafw_woocommerce ) {
		$bafw_woocommerce = dirname( __DIR__, 2 ) . '/woocommerce/woocommerce.php';
	}

	if ( file_exists( $bafw_woocommerce ) ) {
		require $bafw_woocommerce;
	}

	require dirname( __DIR__ ) . '/billing-abby-for-woocommerce.php';
}

tests_add_filter( 'muplugins_loaded', 'bafw_manually_load_plugin' );

// WooCommerce needs its tables installed in the test database before the suite runs.
tests_add_filter(
	'setup_theme',
	function () {
		if ( class_exists( 'WC_Install' ) ) {
			WC_Install::install();
		}
	}
);

// Start up the WP testing environment.
require "{$bafw_tests_dir}/includes/bootstrap.php";

// Shared base test case (not auto-loaded: it is not a "test-" prefixed file).
require __DIR__ . '/includes/class-bafw-order-test-case.php';

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals
