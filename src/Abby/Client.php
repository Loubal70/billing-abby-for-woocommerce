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

	private const BASE_URL      = 'https://api.app-abby.com';
	private const CONTACTS_PATH = '/contacts';
	private const CONTACT_PATH  = '/contact';
	private const BILLING_PATH  = '/v2/billing';
	private const INCOME_PATH   = '/incomeBook';

	public function __construct( private readonly string $api_key ) {}

	public function validate_key(): KeyStatus {
		$response = $this->get_contacts();

		if ( is_wp_error( $response ) ) {
			return KeyStatus::ERROR;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return KeyStatus::VALID;
		}

		if ( 401 === $code ) {
			return KeyStatus::INVALID;
		}

		if ( 403 === $code ) {
			return KeyStatus::FORBIDDEN;
		}

		return KeyStatus::ERROR;
	}

	public function create_contact( array $payload ): ?string {
		return $this->create_abby_resource( self::CONTACT_PATH, $payload );
	}

	/**
	 * Overwrite a contact with the latest details (Abby uses PUT, not PATCH).
	 *
	 * @param string               $id      Abby contact id.
	 * @param array<string, mixed> $payload Full contact payload.
	 */
	public function update_contact( string $id, array $payload ): bool {
		$response = $this->request(
			'PUT',
			self::CONTACT_PATH . '/' . rawurlencode( $id ),
			array( 'body' => $payload )
		);

		return null !== $this->decode( $response );
	}

	public function create_invoice( string $customer_id ): ?string {
		return $this->create_abby_resource( self::BILLING_PATH . '/invoice/' . rawurlencode( $customer_id ) );
	}

	public function update_invoice_lines( string $invoice_id, array $lines ): bool {
		$response = $this->request(
			'PATCH',
			self::BILLING_PATH . '/' . rawurlencode( $invoice_id ) . '/lines',
			array( 'body' => array( 'lines' => $lines ) )
		);

		return null !== $this->decode( $response );
	}

	public function get_invoice( string $invoice_id ): ?array {
		return $this->decode( $this->request( 'GET', self::BILLING_PATH . '/' . rawurlencode( $invoice_id ) ) );
	}

	public function download_invoice( string $invoice_id ): ?string {
		return $this->body( $this->request( 'GET', self::BILLING_PATH . '/' . rawurlencode( $invoice_id ) . '/download' ) );
	}

	/**
	 * Records an income-book entry. Abby returns the new entry under `_id`, not `id`.
	 */
	public function record_income( array $payload ): ?string {
		return $this->create_abby_resource( self::INCOME_PATH, $payload, '_id' );
	}

	private function get_contacts(): array|WP_Error {
		return $this->request(
			'GET',
			self::CONTACTS_PATH,
			array(
				'query' => array(
					'page'  => 1,
					'limit' => 1,
				),
			)
		);
	}

	/**
	 * Sends an authenticated Abby request. $options accepts optional 'query' and 'body' arrays.
	 */
	private function request( string $method, string $path, array $options = array() ): array|WP_Error {
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
		$body = $this->body( $response );

		if ( null === $body ) {
			return null;
		}

		$data = json_decode( $body, true );

		return is_array( $data ) ? $data : null;
	}

	private function body( array|WP_Error $response ): ?string {
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' === $body ? null : $body;
	}

	/**
	 * Create a resource on Abby via POST and return its id.
	 *
	 * @param string                    $path   Abby endpoint path.
	 * @param array<string, mixed>|null $body   Optional JSON request body.
	 * @param string                    $id_key Response key holding the new id ('id', or '_id' for the income book).
	 * @return string|null The created resource id, or null on failure.
	 */
	private function create_abby_resource( string $path, ?array $body = null, string $id_key = 'id' ): ?string {
		$options = null !== $body ? array( 'body' => $body ) : array();
		$data    = $this->decode( $this->request( 'POST', $path, $options ) );
		$id      = $data[ $id_key ] ?? null;

		return is_string( $id ) ? $id : null;
	}
}
