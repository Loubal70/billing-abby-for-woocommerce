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
		$response = $this->get_contacts();

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

	public function find_contact_id( string $email ): ?string {
		$data = $this->decode( $this->get_contacts( $email ) );
		$id   = $data['docs'][0]['id'] ?? null;

		return is_string( $id ) ? $id : null;
	}

	public function create_contact( array $payload ): ?string {
		return $this->create_abby_resource( '/contact', $payload );
	}

	public function create_invoice( string $customer_id ): ?string {
		return $this->create_abby_resource( '/v2/billing/invoice/' . rawurlencode( $customer_id ) );
	}

	public function update_invoice_lines( string $invoice_id, array $lines ): bool {
		$response = $this->request(
			'PATCH',
			'/v2/billing/' . rawurlencode( $invoice_id ) . '/lines',
			array( 'body' => array( 'lines' => $lines ) )
		);

		return null !== $this->decode( $response );
	}

	private function get_contacts( ?string $search = null ): array|WP_Error {
		$query = array(
			'page'  => 1,
			'limit' => 1,
		);

		if ( null !== $search ) {
			$query['search'] = $search;
		}

		return $this->request( 'GET', '/contacts', array( 'query' => $query ) );
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

	private function decode( array|WP_Error $response ): ?array {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * Create a resource on Abby via POST and return its id.
	 *
	 * @param string                    $path Abby endpoint path.
	 * @param array<string, mixed>|null $body Optional JSON request body.
	 * @return string|null The created resource id, or null on failure.
	 */
	private function create_abby_resource( string $path, ?array $body = null ): ?string {
		$options = null !== $body ? array( 'body' => $body ) : array();
		$data    = $this->decode( $this->request( 'POST', $path, $options ) );
		$id      = $data['id'] ?? null;

		return is_string( $id ) ? $id : null;
	}
}
