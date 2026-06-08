<?php
/**
 * Smoke tests for the plugin foundations.
 *
 * @package Rankea\BillingAbby
 */

use Rankea\BillingAbby\Autoloader;
use Rankea\BillingAbby\Bootstrap;
use Rankea\BillingAbby\Plugin;

/**
 * Verifies the custom autoloader exposes the plugin classes.
 */
class Test_Plugin extends WP_UnitTestCase {

	/**
	 * The namespaced classes load through the custom autoloader.
	 */
	public function test_plugin_classes_are_autoloaded() {
		$this->assertTrue( class_exists( Autoloader::class ) );
		$this->assertTrue( class_exists( Bootstrap::class ) );
		$this->assertTrue( class_exists( Plugin::class ) );
	}

	/**
	 * The version constant matches the declared plugin version.
	 */
	public function test_version_constant() {
		$this->assertSame( '0.1.0', Plugin::VERSION );
	}
}
