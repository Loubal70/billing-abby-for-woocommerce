<?php
/**
 * REST endpoints backing the settings panel.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Admin;

use Rankea\BillingAbby\Abby\Client;
use Rankea\BillingAbby\Abby\ProductType;
use Rankea\BillingAbby\Support\ApiKey;
use Rankea\BillingAbby\Support\SyncLog;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the Abby settings (API key + default income type), capability + nonce
 * protected, and tests the API connection.
 */
final class SettingsRestController {

	private const CAPABILITY     = 'manage_woocommerce';
	private const REST_NAMESPACE = 'bafw/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
						'api_key'      => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'product_type' => array(
							'type'              => 'integer',
							'required'          => false,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/test-connection',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'show_in_index'       => false,
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'show_in_index'       => false,
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 20,
						'sanitize_callback' => 'absint',
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
		$api_key = $request->get_param( 'api_key' );

		if ( is_string( $api_key ) && '' !== trim( $api_key ) ) {
			ApiKey::save( trim( $api_key ) );
		}

		$product_type = $request->get_param( 'product_type' );

		if ( null !== $product_type && null !== ProductType::tryFrom( (int) $product_type ) ) {
			update_option( ProductType::OPTION, (int) $product_type );
		}

		return rest_ensure_response( $this->current_state() );
	}

	public function test_connection(): WP_REST_Response {
		$status = ( new Client( ApiKey::get() ) )->validate_key();

		return rest_ensure_response( array( 'status' => $status->value ) );
	}

	public function get_logs( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = (int) $request->get_param( 'per_page' );

		return rest_ensure_response( SyncLog::get( $page, $per_page ) );
	}

	private function current_state(): array {
		$api_key = ApiKey::get();

		return array(
			'api_key_set'          => '' !== $api_key,
			'api_key_masked'       => $this->mask( $api_key ),
			'product_type'         => (int) get_option( ProductType::OPTION, ProductType::GOODS->value ),
			'product_type_options' => ProductType::options(),
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
