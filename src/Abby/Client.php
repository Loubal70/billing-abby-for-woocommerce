<?php
/**
 * Abby API HTTP client.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Abby;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Thin HTTP client for the Abby API (Bearer auth over wp_remote_*).
 *
 * Endpoints confirmed on docs.abby.fr: base https://api.app-abby.com,
 * Authorization: Bearer <key>.
 */
final class Client {

	private const BASE_URL = 'https://api.app-abby.com';

	public function __construct( private readonly string $api_key ) {}

	/**
	 * Check the API key against a lightweight endpoint.
	 *
	 * @return string One of: valid, invalid, forbidden, error.
	 */
	public function validate_key(): string {
		$response = $this->request(
			'GET',
			'/contacts',
			array(
				'query' => array(
					'page'  => 1,
					'limit' => 1,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'error';
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return 'valid';
		}

		if ( 401 === $code ) {
			return 'invalid';
		}

		if ( 403 === $code ) {
			return 'forbidden';
		}

		return 'error';
	}

	private function request( string $method, string $path, array $options = array() ): array|WP_Error {
		// $options accepts optional 'query' and 'body' arrays.
		if ( '' === $this->api_key ) {
			return new WP_Error( 'bafw_missing_key', 'No Abby API key configured.' );
		}

		$url = self::BASE_URL . $path;

		if ( ! empty( $options['query'] ) ) {
			$url = add_query_arg( $options['query'], $url );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept'        => 'application/json',
			),
		);

		if ( ! empty( $options['body'] ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $options['body'] );
		}

		return wp_remote_request( $url, $args );
	}
}
