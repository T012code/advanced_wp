<?php
/**
 * Font choices and local hosting for Google Fonts.
 *
 * Google Fonts are downloaded once, when the font is first chosen and saved,
 * and then served from the uploads folder. Visitors' browsers never contact
 * Google.
 *
 * @package AdminStudio
 */

namespace AdminStudio;

defined( 'ABSPATH' ) || exit;

/**
 * Font registry and downloader.
 */
class Fonts {

	const OPTION = 'adminstudio_fonts';

	/**
	 * Google fonts offered: key => [family, generic fallback].
	 *
	 * @return array
	 */
	public static function google() {
		return array(
			'inter'            => array( 'Inter', 'sans-serif' ),
			'roboto'           => array( 'Roboto', 'sans-serif' ),
			'open-sans'        => array( 'Open Sans', 'sans-serif' ),
			'lato'             => array( 'Lato', 'sans-serif' ),
			'montserrat'       => array( 'Montserrat', 'sans-serif' ),
			'poppins'          => array( 'Poppins', 'sans-serif' ),
			'nunito'           => array( 'Nunito', 'sans-serif' ),
			'dm-sans'          => array( 'DM Sans', 'sans-serif' ),
			'work-sans'        => array( 'Work Sans', 'sans-serif' ),
			'manrope'          => array( 'Manrope', 'sans-serif' ),
			'outfit'           => array( 'Outfit', 'sans-serif' ),
			'space-grotesk'    => array( 'Space Grotesk', 'sans-serif' ),
			'playfair-display' => array( 'Playfair Display', 'serif' ),
			'merriweather'     => array( 'Merriweather', 'serif' ),
			'lora'             => array( 'Lora', 'serif' ),
			'source-serif-4'   => array( 'Source Serif 4', 'serif' ),
		);
	}

	/**
	 * All font keys, untranslated.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array_merge( array( 'theme', 'system', 'serif' ), array_keys( self::google() ) );
	}

	/**
	 * Font key => label, for select controls.
	 *
	 * @return array
	 */
	public static function choices() {
		$choices = array(
			'theme'  => __( 'Same as theme', 'admin-studio' ),
			'system' => __( 'System sans-serif', 'admin-studio' ),
			'serif'  => __( 'System serif', 'admin-studio' ),
		);

		foreach ( self::google() as $key => $font ) {
			$choices[ $key ] = $font[0];
		}

		return $choices;
	}

	/**
	 * System font stacks.
	 *
	 * @param string $generic `sans-serif` or `serif`.
	 * @return string
	 */
	public static function system_stack( $generic = 'sans-serif' ) {
		if ( 'serif' === $generic ) {
			return 'Georgia, Cambria, "Times New Roman", Times, serif';
		}

		return '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif';
	}

	/**
	 * CSS font-family value for a key.
	 *
	 * @param string $key          Font key.
	 * @param string $theme_family Family detected from the theme, used for `theme`.
	 * @return string
	 */
	public static function stack( $key, $theme_family = '' ) {
		$google = self::google();

		if ( isset( $google[ $key ] ) ) {
			return '"' . $google[ $key ][0] . '", ' . self::system_stack( $google[ $key ][1] );
		}

		if ( 'serif' === $key ) {
			return self::system_stack( 'serif' );
		}

		if ( 'theme' === $key && '' !== $theme_family ) {
			return $theme_family;
		}

		return self::system_stack();
	}

	/**
	 * Returns @font-face rules for downloaded Google fonts among the given keys.
	 *
	 * @param string[] $keys Font keys.
	 * @return string
	 */
	public static function face_css( array $keys ) {
		$stored = get_option( self::OPTION, array() );
		$base   = self::base_url();
		$css    = '';

		foreach ( array_unique( $keys ) as $key ) {
			if ( ! empty( $stored[ $key ] ) ) {
				$css .= str_replace( '{base}', $base, $stored[ $key ] );
			}
		}

		return $css;
	}

	/**
	 * Downloads any chosen Google font that is not stored yet.
	 *
	 * @return void
	 */
	public static function sync() {
		$keys = array(
			Settings::get( 'brand.font_heading' ),
			Settings::get( 'brand.font_body' ),
		);

		$stored  = get_option( self::OPTION, array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$changed = false;

		foreach ( array_unique( $keys ) as $key ) {
			if ( isset( self::google()[ $key ] ) && empty( $stored[ $key ] ) ) {
				$css = self::download( $key );

				if ( '' !== $css ) {
					$stored[ $key ] = $css;
					$changed        = true;
				}
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $stored, false );
		}
	}

	/**
	 * Downloads one font's Latin files and returns its @font-face CSS.
	 *
	 * @param string $key Font key.
	 * @return string CSS with `{base}` in place of the fonts folder URL, or ''.
	 */
	private static function download( $key ) {
		$family = self::google()[ $key ][0];
		$url    = 'https://fonts.googleapis.com/css2?family=' . rawurlencode( $family ) . ':wght@400;500;600;700&display=swap';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				// A modern browser user agent makes Google return woff2 files.
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$dir = self::base_dir() . $key . '/';

		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}

		// Keep only the Latin subsets to limit downloads.
		preg_match_all( '#/\*\s*(latin|latin-ext)\s*\*/\s*(@font-face\s*{[^}]+})#', wp_remote_retrieve_body( $response ), $blocks );

		$css   = '';
		$saved = array();

		foreach ( $blocks[2] as $block ) {
			if ( ! preg_match( '#url\((https://fonts\.gstatic\.com/[^)]+\.woff2)\)#', $block, $match ) ) {
				continue;
			}

			$remote = $match[1];
			$file   = md5( $remote ) . '.woff2';

			if ( ! isset( $saved[ $remote ] ) ) {
				$font = wp_remote_get( $remote, array( 'timeout' => 15 ) );

				if ( is_wp_error( $font ) || ! Files::put( $dir . $file, wp_remote_retrieve_body( $font ) ) ) {
					continue;
				}

				$saved[ $remote ] = true;
			}

			$css .= str_replace( $remote, '{base}' . $key . '/' . $file, $block ) . "\n";
		}

		return $css;
	}

	/**
	 * Folder where fonts are stored.
	 *
	 * @return string
	 */
	private static function base_dir() {
		$uploads = wp_upload_dir( null, false );

		return trailingslashit( $uploads['basedir'] ) . 'admin-studio/fonts/';
	}

	/**
	 * Public URL of the fonts folder.
	 *
	 * @return string
	 */
	private static function base_url() {
		$uploads = wp_upload_dir( null, false );

		return set_url_scheme( trailingslashit( $uploads['baseurl'] ) . 'admin-studio/fonts/' );
	}
}
