<?php
/**
 * Small colour helpers: parsing, mixing and contrast.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Colour maths on #rrggbb strings.
 */
class Color {

	/**
	 * Normalises #rgb, #rrggbb, #rrggbbaa and rgb()/rgba() to #rrggbb.
	 *
	 * @param string $color Input colour.
	 * @return string Hex colour, or '' when it cannot be parsed.
	 */
	public static function to_hex( $color ) {
		$color = strtolower( trim( (string) $color ) );

		if ( preg_match( '/^#([0-9a-f]{3})$/', $color, $m ) ) {
			return '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}

		if ( preg_match( '/^#([0-9a-f]{6})([0-9a-f]{2})?$/', $color, $m ) ) {
			return '#' . $m[1];
		}

		if ( preg_match( '/^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})/', $color, $m ) ) {
			return sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
		}

		return '';
	}

	/**
	 * Splits a hex colour into RGB integers.
	 *
	 * @param string $hex Hex colour.
	 * @return int[]
	 */
	public static function rgb( $hex ) {
		$hex = ltrim( self::to_hex( $hex ), '#' );

		if ( '' === $hex ) {
			return array( 0, 0, 0 );
		}

		return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
	}

	/**
	 * WCAG relative luminance.
	 *
	 * @param string $hex Hex colour.
	 * @return float 0 (black) to 1 (white).
	 */
	public static function luminance( $hex ) {
		$channels = array_map(
			static function ( $c ) {
				$c /= 255;
				return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
			},
			self::rgb( $hex )
		);

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	}

	/**
	 * WCAG contrast ratio between two colours.
	 *
	 * @param string $a First colour.
	 * @param string $b Second colour.
	 * @return float 1 to 21.
	 */
	public static function contrast( $a, $b ) {
		$la = self::luminance( $a );
		$lb = self::luminance( $b );

		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	/**
	 * Picks near-black or white, whichever reads better on the colour.
	 *
	 * @param string $hex Background colour.
	 * @return string
	 */
	public static function readable_on( $hex ) {
		return self::contrast( $hex, '#ffffff' ) >= self::contrast( $hex, '#111111' ) ? '#ffffff' : '#111111';
	}

	/**
	 * Mixes two colours.
	 *
	 * @param string $a      First colour.
	 * @param string $b      Second colour.
	 * @param float  $weight Share of the second colour, 0 to 1.
	 * @return string
	 */
	public static function mix( $a, $b, $weight ) {
		$ra  = self::rgb( $a );
		$rb  = self::rgb( $b );
		$out = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$out[] = (int) round( $ra[ $i ] * ( 1 - $weight ) + $rb[ $i ] * $weight );
		}

		return vsprintf( '#%02x%02x%02x', $out );
	}

	/**
	 * Whether a colour is light.
	 *
	 * @param string $hex Hex colour.
	 * @return bool
	 */
	public static function is_light( $hex ) {
		return self::luminance( $hex ) > 0.4;
	}

	/**
	 * Returns an rgba() string.
	 *
	 * @param string $hex   Hex colour.
	 * @param float  $alpha Opacity, 0 to 1.
	 * @return string
	 */
	public static function rgba( $hex, $alpha ) {
		list( $r, $g, $b ) = self::rgb( $hex );

		return sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, round( $alpha, 2 ) );
	}
}
