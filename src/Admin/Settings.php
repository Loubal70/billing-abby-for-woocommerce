<?php
/**
 * Admin settings screen (React panel + REST endpoint).
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Rankea\BillingAbby\Plugin;
use Rankea\BillingAbby\Support\Secret;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce sub-page + REST endpoint for the Abby API key (capability + nonce protected).
 */
final class Settings {

	private const OPTION         = 'bafw_abby_api_key';
	private const CAPABILITY     = 'manage_woocommerce';
	private const PAGE_SLUG      = 'bafw-settings';
	private const REST_NAMESPACE = 'bafw/v1';
	private const SCRIPT_HANDLE  = 'bafw-settings';

	private string $hook_suffix = '';

	public function __construct( private readonly Plugin $plugin ) {}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'show_in_index'       => false,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'show_in_index'       => false,
					'args'                => array(
						'api_key' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	public function check_permission(): bool {
		return current_user_can( self::CAPABILITY );
	}

	public function get_settings(): WP_REST_Response {
		return rest_ensure_response( $this->current_state() );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$api_key = trim( (string) $request->get_param( 'api_key' ) );

		update_option( self::OPTION, Secret::encrypt( $api_key ), false );

		return rest_ensure_response( $this->current_state() );
	}

	private function current_state(): array {
		$api_key = Secret::decrypt( (string) get_option( self::OPTION, '' ) );

		return array(
			'api_key_set'    => '' !== $api_key,
			'api_key_masked' => $this->mask( $api_key ),
		);
	}

	private function mask( string $api_key ): string {
		$length = strlen( $api_key );

		if ( 0 === $length ) {
			return '';
		}

		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		// Fixed-length placeholder so the real key length is never disclosed.
		return '****' . substr( $api_key, -4 );
	}
}
