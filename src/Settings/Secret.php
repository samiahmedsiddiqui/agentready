<?php
/**
 * Encrypts stored secrets with a key derived from the site's auth salt.
 *
 * @package AgentReady
 */

declare(strict_types=1);

namespace AgentReady\Settings;

defined( 'ABSPATH' ) || exit;

/** Encrypts stored secrets with a key derived from the site's auth salt. */
final class Secret {

	/** Marks a value as encrypted and records the format version. */
	private const PREFIX = 'v1:';

	/**
	 * Encrypts a value.
	 *
	 * @param string $plain Text to encrypt.
	 * @return string Encrypted text, or an empty string when the input is empty.
	 */
	public static function encrypt( string $plain ): string {
		if ( '' === $plain ) {
			return '';
		}

		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );

		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary-safe storage, not obfuscation.
	}

	/**
	 * Decrypts a value made by encrypt().
	 *
	 * @param string $stored Encrypted text.
	 * @return string The original text, or an empty string when it cannot be decrypted.
	 */
	public static function decrypt( string $stored ): string {
		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return '';
		}

		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reverses encrypt().

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Key derived from the auth salt of the site.
	 *
	 * @return string Binary key.
	 */
	private static function key(): string {
		return sodium_crypto_generichash( wp_salt( 'auth' ) . 'agentready', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}
}
