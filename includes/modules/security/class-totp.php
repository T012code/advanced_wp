<?php
/**
 * Time-based one-time passwords (RFC 6238), as used by authenticator apps.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Security;

defined( 'ABSPATH' ) || exit;

/**
 * TOTP helpers: secrets, codes and verification.
 */
class Totp {

	const PERIOD   = 30;
	const DIGITS   = 6;
	const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

	/**
	 * New random secret, base32 encoded (160 bits).
	 *
	 * @return string
	 */
	public static function generate_secret() {
		return self::base32_encode( random_bytes( 20 ) );
	}

	/**
	 * Base32 encoding (RFC 4648, no padding).
	 *
	 * @param string $data Binary data.
	 * @return string
	 */
	public static function base32_encode( $data ) {
		$bits = '';

		foreach ( str_split( $data ) as $char ) {
			$bits .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
		}

		$out = '';

		foreach ( str_split( $bits, 5 ) as $chunk ) {
			$out .= self::ALPHABET[ bindec( str_pad( $chunk, 5, '0' ) ) ];
		}

		return $out;
	}

	/**
	 * Base32 decoding.
	 *
	 * @param string $data Base32 text.
	 * @return string Binary data.
	 */
	public static function base32_decode( $data ) {
		$data = strtoupper( preg_replace( '/[^A-Za-z2-7]/', '', (string) $data ) );
		$bits = '';

		foreach ( str_split( $data ) as $char ) {
			$bits .= str_pad( decbin( strpos( self::ALPHABET, $char ) ), 5, '0', STR_PAD_LEFT );
		}

		$out = '';

		foreach ( str_split( $bits, 8 ) as $byte ) {
			if ( 8 === strlen( $byte ) ) {
				$out .= chr( bindec( $byte ) );
			}
		}

		return $out;
	}

	/**
	 * Code for a time slice.
	 *
	 * @param string $secret Base32 secret.
	 * @param int    $slice  Time slice (Unix time / 30).
	 * @return string
	 */
	public static function code( $secret, $slice ) {
		$key  = self::base32_decode( $secret );
		$msg  = pack( 'N', 0 ) . pack( 'N', $slice );
		$hash = hash_hmac( 'sha1', $msg, $key, true );

		$offset = ord( $hash[19] ) & 0x0f;
		$value  = ( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 )
			| ( ord( $hash[ $offset + 1 ] ) << 16 )
			| ( ord( $hash[ $offset + 2 ] ) << 8 )
			| ord( $hash[ $offset + 3 ] );

		return str_pad( (string) ( $value % pow( 10, self::DIGITS ) ), self::DIGITS, '0', STR_PAD_LEFT );
	}

	/**
	 * Checks a code, allowing one step of clock drift either way.
	 *
	 * @param string $secret     Base32 secret.
	 * @param string $code       Code entered.
	 * @param int    $last_slice Last slice already used, to block replays.
	 * @return int|false The matching slice, or false.
	 */
	public static function verify( $secret, $code, $last_slice = 0 ) {
		$code = preg_replace( '/\D/', '', (string) $code );

		if ( self::DIGITS !== strlen( $code ) ) {
			return false;
		}

		$now = (int) floor( time() / self::PERIOD );

		for ( $i = -1; $i <= 1; $i++ ) {
			$slice = $now + $i;

			if ( $slice > $last_slice && hash_equals( self::code( $secret, $slice ), $code ) ) {
				return $slice;
			}
		}

		return false;
	}

	/**
	 * Builds the otpauth:// URI for the QR code.
	 *
	 * @param string $secret Base32 secret.
	 * @param string $label  Account label, usually the user's email.
	 * @param string $issuer Site name.
	 * @return string
	 */
	public static function uri( $secret, $label, $issuer ) {
		return 'otpauth://totp/' . rawurlencode( $issuer ) . ':' . rawurlencode( $label )
			. '?secret=' . $secret
			. '&issuer=' . rawurlencode( $issuer )
			. '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
	}
}
