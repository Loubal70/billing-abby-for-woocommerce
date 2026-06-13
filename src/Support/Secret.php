<?php
/**
 * Secret encryption helper.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts/decrypts short secrets (e.g. the Abby API key) so they are never stored
 * in clear text. Uses authenticated XChaCha20-Poly1305 with the plugin context as
 * associated data; the key comes from the BAFW_ENCRYPTION_KEY constant when defined
 * (kept out of the database), otherwise from the WordPress auth salt.
 */
final class Secret {

	private const CONTEXT = 'billing-abby-for-woocommerce:api-key';

	public static function encrypt( string $plaintext ): string {
		if ( '' === $plaintext ) {
			return '';
		}

		$key        = self::key();
		$nonce      = random_bytes( SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt( $plaintext, self::CONTEXT, $nonce, $key );

		sodium_memzero( $key );

		return base64_encode( $nonce . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding ciphertext, not obfuscating code.
	}

	public static function decrypt( string $stored ): string {
		if ( '' === $stored ) {
			return '';
		}

		$decoded = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding ciphertext, not obfuscating code.

		if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES ) {
			return '';
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$ciphertext = substr( $decoded, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES );
		$key        = self::key();
		$plaintext  = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt( $ciphertext, self::CONTEXT, $nonce, $key );

		sodium_memzero( $key );

		return is_string( $plaintext ) ? $plaintext : '';
	}

	private static function key(): string {
		$material = defined( 'BAFW_ENCRYPTION_KEY' ) && '' !== (string) BAFW_ENCRYPTION_KEY
			? (string) BAFW_ENCRYPTION_KEY
			: wp_salt( 'auth' );

		return substr( hash( 'sha256', $material, true ), 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES );
	}
}
