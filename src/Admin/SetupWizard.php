<?php
/**
 * First-run setup wizard (full-screen onboarding).
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Rankea\BillingAbby\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a hidden full-screen onboarding page, enqueues its React bundle, and redirects to it
 * once after activation until the merchant completes or skips it.
 */
final class SetupWizard {

	private const CAPABILITY         = 'manage_woocommerce';
	private const PAGE_SLUG          = 'bafw-settings-setup';
	private const SETTINGS_SLUG      = 'bafw-settings';
	private const SCRIPT_HANDLE      = 'bafw-setup';
	private const COMPLETE_OPTION    = 'bafw_setup_complete';
	private const REDIRECT_TRANSIENT = 'bafw_setup_redirect';

	private string $hook_suffix = '';

	public function __construct( private readonly Plugin $plugin ) {}

	public static function on_activation(): void {
		if ( ! self::is_complete() ) {
			set_transient( self::REDIRECT_TRANSIENT, '1', MINUTE_IN_SECONDS );
		}
	}

	public static function mark_complete(): void {
		update_option( self::COMPLETE_OPTION, '1', false );
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'redirect_after_activation' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Registers the hidden wizard page. 'options.php' is not a rendered menu, so the page stays out
	 * of every menu while keeping a native title and capability check (an empty parent would lose both).
	 */
	public function add_page(): void {
		$this->hook_suffix = (string) add_submenu_page(
			'options.php',
			__( 'Billing Abby setup', 'billing-abby-for-woocommerce' ),
			__( 'Billing Abby setup', 'billing-abby-for-woocommerce' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		add_action( 'load-' . $this->hook_suffix, array( $this, 'redirect_when_complete' ) );
	}

	public function render_page(): void {
		echo '<div id="bafw-setup-root"></div>';
	}

	public function redirect_when_complete(): void {
		if ( self::is_complete() ) {
			$this->redirect_to_settings();
		}
	}

	public function redirect_after_activation(): void {
		if ( ! $this->should_open_wizard() ) {
			return;
		}

		if ( ! get_transient( self::REDIRECT_TRANSIENT ) ) {
			return;
		}

		delete_transient( self::REDIRECT_TRANSIENT );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function add_body_class( string $classes ): string {
		if ( ! $this->is_setup_screen() ) {
			return $classes;
		}

		return $classes . ' bafw-setup-fullscreen';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix || '' === $this->hook_suffix ) {
			return;
		}

		$asset_path = $this->plugin->dir() . 'build/setup.asset.php';

		if ( ! is_readable( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			$this->plugin->url() . 'build/setup.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( self::SCRIPT_HANDLE, 'billing-abby-for-woocommerce' );
		wp_enqueue_style(
			self::SCRIPT_HANDLE,
			$this->plugin->url() . 'build/style-setup.css',
			array( 'wp-components' ),
			$asset['version']
		);
		wp_style_add_data( self::SCRIPT_HANDLE, 'rtl', 'replace' );
	}

	private function should_open_wizard(): bool {
		if ( wp_doing_ajax() || is_network_admin() ) {
			return false;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return false;
		}

		return ! self::is_complete();
	}

	private function is_setup_screen(): bool {
		$screen = get_current_screen();

		return '' !== $this->hook_suffix && null !== $screen && $screen->id === $this->hook_suffix;
	}

	private function redirect_to_settings(): void {
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SLUG ) );
		exit;
	}

	private static function is_complete(): bool {
		return '' !== (string) get_option( self::COMPLETE_OPTION, '' );
	}
}
