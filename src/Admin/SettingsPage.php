<?php
/**
 * Admin settings screen (menu + React mount point).
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Rankea\BillingAbby\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the WooCommerce sub-page and enqueues the React settings panel on it.
 */
final class SettingsPage {

	private const CAPABILITY    = 'manage_woocommerce';
	private const PAGE_SLUG     = 'bafw-settings';
	private const SCRIPT_HANDLE = 'bafw-settings';

	private string $hook_suffix = '';

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu_page(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'woocommerce',
			__( 'Billing Abby', 'billing-abby-for-woocommerce' ),
			__( 'Billing Abby', 'billing-abby-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		printf(
			'<div class="wrap"><h1>%s</h1><div id="bafw-settings-root"></div></div>',
			esc_html__( 'Billing Abby for WooCommerce', 'billing-abby-for-woocommerce' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$asset_path = $this->plugin->dir() . 'build/settings.asset.php';

		if ( ! is_readable( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->plugin->url() . 'build/settings.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'billing-abby-for-woocommerce' );
		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			$this->plugin->url() . 'build/style-settings.css',
			array( 'wp-components' ),
			$asset['version']
		);
		wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
	}
}
