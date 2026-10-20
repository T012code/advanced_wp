<?php
/**
 * Picks brand colours out of the logo image.
 *
 * SVG logos are read for the colours they name. PNG, JPEG, GIF and WebP logos
 * are sampled with the GD image library when the server has it.
 *
 * @package AdminStudio
 */

namespace AdminStudio;

defined( 'ABSPATH' ) || exit;

/**
 * Logo colour extraction.
 */
class Logo_Colors {

	/**
	 * Main colours of a logo.
	 *
	 * @param string $url Logo URL.
	 * @return array { primary, secondary } as hex, or an empty array when nothing usable was found.
	 */
	public static function extract( $url ) {
		$path = self::path( $url );

		if ( '' === $path || ! is_readable( $path ) ) {
			return array();
		}

		$type   = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$counts = 'svg' === $type ? self::from_svg( $path ) : self::from_raster( $path );

		return self::choose( $counts );
	}

	/**
	 * Whether raster logos can be read on this server.
	 *
	 * @return bool
	 */
	public static function can_read_images() {
		return function_exists( 'imagecreatefromstring' );
	}

	/**
	 * Local file for a logo URL on this site.
	 *
	 * @param string $url Logo URL.
	 * @return string Path, or ''.
	 */
	private static function path( $url ) {
		if ( '' === $url ) {
			return '';
		}

		$id = attachment_url_to_postid( $url );
		if ( $id ) {
			$file = get_attached_file( $id );
			return $file ? (string) $file : '';
		}

		$url     = strtok( $url, '?' );
		$uploads = wp_upload_dir( null, false );
		$roots   = array(
			$uploads['baseurl'] => $uploads['basedir'],
			content_url()       => WP_CONTENT_DIR,
			site_url()          => ABSPATH,
		);

		foreach ( $roots as $base => $dir ) {
			$base = set_url_scheme( $base );
			$url  = set_url_scheme( $url );
			if ( 0 === strpos( $url, $base ) ) {
				$file = wp_normalize_path( untrailingslashit( $dir ) . substr( $url, strlen( $base ) ) );
				// Stay inside the folder the URL points into.
				return 0 === strpos( $file, wp_normalize_path( untrailingslashit( $dir ) ) ) && false === strpos( $file, '..' ) ? $file : '';
			}
		}

		return '';
	}

	/**
	 * Colours named in an SVG, weighted by how often they appear.
	 *
	 * @param string $path File.
	 * @return array Hex => weight.
	 */
	private static function from_svg( $path ) {
		$svg = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads a local image file.
		$out = array();

		preg_match_all( '/#([0-9a-f]{6}|[0-9a-f]{3})\b/i', $svg, $hex );
		foreach ( $hex[0] as $color ) {
			$color = Color::to_hex( $color );
			if ( '' !== $color ) {
				$out[ $color ] = ( isset( $out[ $color ] ) ? $out[ $color ] : 0 ) + 1;
			}
		}

		preg_match_all( '/rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})/i', $svg, $rgb, PREG_SET_ORDER );
		foreach ( $rgb as $m ) {
			$color         = sprintf( '#%02x%02x%02x', min( 255, (int) $m[1] ), min( 255, (int) $m[2] ), min( 255, (int) $m[3] ) );
			$out[ $color ] = ( isset( $out[ $color ] ) ? $out[ $color ] : 0 ) + 1;
		}

		return $out;
	}

	/**
	 * Colours of a raster image, sampled on a small grid and grouped.
	 *
	 * @param string $path File.
	 * @return array Hex => pixel count.
	 */
	private static function from_raster( $path ) {
		if ( ! self::can_read_images() ) {
			return array();
		}

		$image = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A damaged image should simply give no colours.

		if ( ! $image ) {
			return array();
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );
		$step   = max( 1, (int) floor( max( $width, $height ) / 64 ) );
		$groups = array();

		$step_y = $step;

		for ( $y = 0; $y < $height; $y += $step_y ) {
			for ( $x = 0; $x < $width; $x += $step ) {
				$rgba = imagecolorsforindex( $image, imagecolorat( $image, $x, $y ) );
				if ( $rgba['alpha'] > 90 ) {
					continue; // Mostly transparent.
				}
				// Group close shades together, keeping their real average.
				$key = ( $rgba['red'] >> 4 ) . '-' . ( $rgba['green'] >> 4 ) . '-' . ( $rgba['blue'] >> 4 );
				if ( ! isset( $groups[ $key ] ) ) {
					$groups[ $key ] = array( 0, 0, 0, 0 );
				}
				$groups[ $key ][0] += $rgba['red'];
				$groups[ $key ][1] += $rgba['green'];
				$groups[ $key ][2] += $rgba['blue'];
				++$groups[ $key ][3];
			}
		}

		imagedestroy( $image );

		$out = array();
		foreach ( $groups as $group ) {
			$color         = sprintf( '#%02x%02x%02x', (int) round( $group[0] / $group[3] ), (int) round( $group[1] / $group[3] ), (int) round( $group[2] / $group[3] ) );
			$out[ $color ] = ( isset( $out[ $color ] ) ? $out[ $color ] : 0 ) + $group[3];
		}

		return $out;
	}

	/**
	 * Picks a primary and a secondary colour: the most used colourful shades,
	 * skipping near-white and near-black, which are usually background or text.
	 *
	 * @param array $counts Hex => weight.
	 * @return array
	 */
	private static function choose( array $counts ) {
		if ( ! $counts ) {
			return array();
		}

		arsort( $counts );

		$colorful = array();
		$neutral  = array();

		foreach ( array_keys( $counts ) as $hex ) {
			list( , $s, $l ) = self::hsl( $hex );

			if ( $l > 0.94 || $l < 0.06 ) {
				continue;
			}

			if ( $s > 0.25 ) {
				$colorful[] = $hex;
			} else {
				$neutral[] = $hex;
			}
		}

		$picks = array();

		foreach ( array_merge( $colorful, $neutral ) as $hex ) {
			foreach ( $picks as $chosen ) {
				if ( self::distance( $hex, $chosen ) < 80 ) {
					continue 2;
				}
			}
			$picks[] = $hex;
			if ( count( $picks ) >= 2 ) {
				break;
			}
		}

		// A logo that is only black or white still gives its main shade.
		if ( ! $picks ) {
			$picks[] = (string) key( $counts );
		}

		return array_filter(
			array(
				'primary'   => $picks[0],
				'secondary' => isset( $picks[1] ) ? $picks[1] : '',
			)
		);
	}

	/**
	 * Hue, saturation and lightness of a colour, each 0–1.
	 *
	 * @param string $hex Colour.
	 * @return array
	 */
	private static function hsl( $hex ) {
		$r = hexdec( substr( $hex, 1, 2 ) ) / 255;
		$g = hexdec( substr( $hex, 3, 2 ) ) / 255;
		$b = hexdec( substr( $hex, 5, 2 ) ) / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$l   = ( $max + $min ) / 2;

		if ( $max === $min ) {
			return array( 0, 0, $l );
		}

		$d = $max - $min;
		$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );

		return array( 0, $s, $l );
	}

	/**
	 * Rough distance between two colours.
	 *
	 * @param string $a Colour.
	 * @param string $b Colour.
	 * @return float
	 */
	private static function distance( $a, $b ) {
		$sum = 0;

		foreach ( array( 1, 3, 5 ) as $i ) {
			$sum += pow( hexdec( substr( $a, $i, 2 ) ) - hexdec( substr( $b, $i, 2 ) ), 2 );
		}

		return sqrt( $sum );
	}
}
