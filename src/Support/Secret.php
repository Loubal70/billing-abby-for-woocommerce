<?php
/**
 * Encryption helper for secrets stored at rest.
 *
 * @package Rankea\BillingAbby
 */

declare(strict_types=1);

namespace Rankea\BillingAbby\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts and decrypts short secrets (e.g. the Abby API key) so they are never
 * persisted in clear text.
 *
 * Uses authenticated encryption (XChaCha20-Poly1305 IETF) with the plugin
 * context as associated data, so a ciphertext cannot be tampered with or reused
 * elsewhere. The key is derived from the BAFW_ENCRYPTION_KEY constant when the
 * site defines one (recommended, keeps the key out of the database), otherwise
 * from the site's authentication salt.
 */
final class Secret {

	/**
	 * Associated data binding a ciphertext to this plugin and purpose.
	 *
	 * @var string
	 */
	private const CONTEXT = 'billing-abby-for-woocommerce:api-key';

	/**
	 * Encrypt a plaintext value for storage.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded ciphertext, or an empty string for empty input.
	 */
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

	/**
	 * Decrypt a value produced by encrypt().
	 *
	 * @param string $stored Stored base64-encoded ciphertext.
	 * @return string The plaintext, or an empty string when input is empty, invalid, or tampered with.
	 */
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

	/**
	 * Derive the 32-byte symmetric key.
	 *
	 * Prefers a site-defined BAFW_ENCRYPTION_KEY constant (kept out of the
	 * database); falls back to the WordPress authentication salt.
	 */
	private static function key(): string {
		$material = defined( 'BAFW_ENCRYPTION_KEY' ) && '' !== (string) BAFW_ENCRYPTION_KEY
			? (string) BAFW_ENCRYPTION_KEY
			: wp_salt( 'auth' );

		return substr( hash( 'sha256', $material, true ), 0, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES );
	}
}
