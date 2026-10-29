<?php
/**
 * The brand kit: logo, colours and fonts shared by every area.
 *
 * Colours come from one source the site owner picks: the active theme, the
 * logo, the colours visitors see on the front end, or custom colours. Anything
 * a source does not provide falls back to the theme, then a neutral palette.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves brand values and turns them into CSS variables.
 */
class Brand_Kit {

	const COLORS = array( 'primary', 'secondary', 'text', 'background', 'surface', 'border', 'success', 'error' );

	const SOURCES = array( 'theme', 'logo', 'frontend', 'custom' );

	/**
	 * Colours read from the logo and the front end.
	 */
	const PALETTES = 'adminora_brand_palette';

	/**
	 * Cached theme detection.
	 *
	 * @var array|null
	 */
	private static $detected = null;

	/**
	 * Neutral fallback palette, close to the WordPress admin colours.
	 *
	 * @return array
	 */
	public static function fallback() {
		return array(
			'primary'    => '#2271b1',
			'secondary'  => '#135e96',
			'text'       => '#1e1e1e',
			'background' => '#f0f0f1',
			'surface'    => '#ffffff',
			'border'     => '#dcdcde',
			'success'    => '#00a32a',
			'error'      => '#d63638',
		);
	}

	/**
	 * Colours and fonts detected from the active theme.
	 *
	 * Keys hold '' when nothing was found. Font values are CSS font-family strings.
	 *
	 * @return array
	 */
	public static function detected() {
		if ( null !== self::$detected ) {
			return self::$detected;
		}

		$found                 = array_fill_keys( self::COLORS, '' );
		$found['font_heading'] = '';
		$found['font_body']    = '';

		if ( function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json() ) {
			$found = array_merge( $found, self::detect_theme_json() );
		} else {
			$found = array_merge( $found, self::detect_classic() );
		}

		self::$detected = $found;

		return self::$detected;
	}

	/**
	 * Reads colours and fonts from theme.json (block themes and hybrid themes).
	 *
	 * @return array
	 */
	private static function detect_theme_json() {
		$palette = wp_get_global_settings( array( 'color', 'palette', 'theme' ) );
		$by_slug = array();

		foreach ( (array) $palette as $entry ) {
			if ( isset( $entry['slug'], $entry['color'] ) ) {
				$hex = Color::to_hex( $entry['color'] );
				if ( '' !== $hex ) {
					$by_slug[ $entry['slug'] ] = $hex;
				}
			}
		}

		$resolve = static function ( $value ) use ( $by_slug ) {
			if ( ! is_string( $value ) ) {
				return '';
			}
			if ( preg_match( '/var(?::preset\|color\||\(--wp--preset--color--)([a-z0-9-]+)/', $value, $m ) ) {
				return isset( $by_slug[ $m[1] ] ) ? $by_slug[ $m[1] ] : '';
			}
			return Color::to_hex( $value );
		};

		$pick = static function ( array $slugs ) use ( $by_slug ) {
			foreach ( $slugs as $slug ) {
				if ( isset( $by_slug[ $slug ] ) ) {
					return $by_slug[ $slug ];
				}
			}
			return '';
		};

		$styles = wp_get_global_styles();
		$get    = static function ( array $path ) use ( $styles ) {
			$value = $styles;
			foreach ( $path as $key ) {
				if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
					return '';
				}
				$value = $value[ $key ];
			}
			return $value;
		};

		$button = $resolve( $get( array( 'elements', 'button', 'color', 'background' ) ) );
		$link   = $resolve( $get( array( 'elements', 'link', 'color', 'text' ) ) );

		$colors = array(
			'background' => $resolve( $get( array( 'color', 'background' ) ) ) ? $resolve( $get( array( 'color', 'background' ) ) ) : $pick( array( 'base', 'background', 'white' ) ),
			'text'       => $resolve( $get( array( 'color', 'text' ) ) ) ? $resolve( $get( array( 'color', 'text' ) ) ) : $pick( array( 'contrast', 'foreground', 'text', 'black' ) ),
			'primary'    => $button ? $button : $pick( array( 'primary', 'accent', 'accent-1', 'brand' ) ),
			'secondary'  => $pick( $button ? array( 'primary', 'accent', 'accent-1', 'secondary' ) : array( 'secondary', 'accent-2', 'contrast-2' ) ),
			'surface'    => $pick( array( 'surface', 'base-2', 'tertiary' ) ),
			'border'     => $pick( array( 'border', 'base-3', 'contrast-3' ) ),
		);

		if ( '' === $colors['primary'] ) {
			$colors['primary'] = $button ? $button : $link;
		}

		$families = wp_get_global_settings( array( 'typography', 'fontFamilies', 'theme' ) );
		$fonts    = array();

		foreach ( (array) $families as $family ) {
			if ( isset( $family['slug'], $family['fontFamily'] ) ) {
				$fonts[ $family['slug'] ] = $family['fontFamily'];
			}
		}

		$font = static function ( $value ) use ( $fonts ) {
			if ( ! is_string( $value ) || '' === $value ) {
				return '';
			}
			if ( preg_match( '/var(?::preset\|font-family\||\(--wp--preset--font-family--)([a-z0-9-]+)/', $value, $m ) ) {
				return isset( $fonts[ $m[1] ] ) ? $fonts[ $m[1] ] : '';
			}
			return $value;
		};

		$body    = $font( $get( array( 'typography', 'fontFamily' ) ) );
		$heading = $font( $get( array( 'elements', 'heading', 'typography', 'fontFamily' ) ) );

		return array_merge(
			$colors,
			array(
				'font_body'    => $body,
				'font_heading' => $heading ? $heading : $body,
			)
		);
	}

	/**
	 * Reads common theme mods used by classic themes.
	 *
	 * @return array
	 */
	private static function detect_classic() {
		$mod = static function ( array $keys ) {
			foreach ( $keys as $key ) {
				$value = get_theme_mod( $key, '' );
				if ( is_string( $value ) && '' !== $value ) {
					$hex = Color::to_hex( '#' === $value[0] ? $value : '#' . $value );
					if ( '' !== $hex ) {
						return $hex;
					}
				}
			}
			return '';
		};

		return array(
			'primary'    => $mod( array( 'primary_color', 'accent_color', 'link_color', 'theme_color', 'main_color' ) ),
			'secondary'  => $mod( array( 'secondary_color', 'link_hover_color' ) ),
			'text'       => $mod( array( 'text_color', 'body_text_color', 'body_color' ) ),
			'background' => $mod( array( 'background_color' ) ),
		);
	}

	/**
	 * Where brand colours come from. Sites set up before the choice existed
	 * keep their custom colours.
	 *
	 * @return string theme, logo, frontend or custom.
	 */
	public static function source() {
		$raw    = Settings::raw();
		$source = isset( $raw['brand']['source'] ) ? $raw['brand']['source'] : null;

		if ( in_array( $source, self::SOURCES, true ) ) {
			return $source;
		}

		foreach ( array( 'primary', 'secondary', 'text', 'background', 'surface', 'border' ) as $key ) {
			if ( '' !== (string) Settings::get( 'brand.' . $key ) ) {
				return 'custom';
			}
		}

		return 'theme';
	}

	/**
	 * Colours a source provides. Keys it does not provide are absent.
	 *
	 * @param string $source Source key.
	 * @return array
	 */
	public static function palette_for( $source ) {
		switch ( $source ) {
			case 'logo':
				return self::logo_palette();

			case 'frontend':
				$stored = get_option( self::PALETTES, array() );
				return isset( $stored['frontend']['colors'] ) ? (array) $stored['frontend']['colors'] : array();

			case 'custom':
				$out = array();
				foreach ( self::COLORS as $key ) {
					$value = (string) Settings::get( 'brand.' . $key );
					if ( '' !== $value ) {
						$out[ $key ] = $value;
					}
				}
				return $out;

			default:
				return array_filter( array_intersect_key( self::detected(), array_flip( self::COLORS ) ) );
		}
	}

	/**
	 * Colours picked out of the logo, cached until the logo changes.
	 *
	 * @return array
	 */
	public static function logo_palette() {
		$logo   = self::logo_url();
		$stored = get_option( self::PALETTES, array() );
		$stored = is_array( $stored ) ? $stored : array();

		if ( isset( $stored['logo']['url'] ) && $stored['logo']['url'] === $logo ) {
			return (array) $stored['logo']['colors'];
		}

		$colors         = Logo_Colors::extract( $logo );
		$stored['logo'] = array(
			'url'    => $logo,
			'colors' => $colors,
		);
		update_option( self::PALETTES, $stored, false );

		return $colors;
	}

	/**
	 * Saves the colours read from the front end.
	 *
	 * @param array $colors Colour name => hex.
	 * @return array The cleaned colours.
	 */
	public static function save_frontend_palette( array $colors ) {
		$clean = array();

		foreach ( array( 'primary', 'secondary', 'text', 'background', 'surface' ) as $key ) {
			$hex = isset( $colors[ $key ] ) ? Color::to_hex( (string) $colors[ $key ] ) : '';
			if ( '' !== $hex ) {
				$clean[ $key ] = $hex;
			}
		}

		$stored             = get_option( self::PALETTES, array() );
		$stored             = is_array( $stored ) ? $stored : array();
		$stored['frontend'] = array(
			'time'   => time(),
			'colors' => $clean,
		);
		update_option( self::PALETTES, $stored, false );

		return $clean;
	}

	/**
	 * Resolved brand values.
	 *
	 * @param array $overrides Per-area overrides. Empty values are ignored.
	 * @return array
	 */
	public static function get( array $overrides = array() ) {
		$detected = self::detected();
		$fallback = self::fallback();
		$source   = self::palette_for( self::source() );
		$from     = static function ( $key ) use ( $source ) {
			return isset( $source[ $key ] ) ? $source[ $key ] : '';
		};
		$values   = array();

		foreach ( array( 'primary', 'secondary', 'text', 'background' ) as $key ) {
			$values[ $key ] = self::first( $from( $key ), $detected[ $key ], $fallback[ $key ] );
		}

		// Derived colours follow the main ones so dark themes get dark surfaces.
		$light = Color::is_light( $values['background'] );

		$values['surface'] = self::first(
			$from( 'surface' ),
			$detected['surface'],
			$light ? '#ffffff' : Color::mix( $values['background'], '#ffffff', 0.08 )
		);
		$values['border']  = self::first( $from( 'border' ), $detected['border'], Color::mix( $values['surface'], $values['text'], 0.18 ) );
		$values['success'] = self::first( $from( 'success' ), $fallback['success'] );
		$values['error']   = self::first( $from( 'error' ), $fallback['error'] );

		foreach ( $overrides as $key => $value ) {
			if ( '' !== $value && null !== $value ) {
				$values[ $key ] = $value;
			}
		}

		$heading_key = Settings::get( 'brand.font_heading' );
		$body_key    = Settings::get( 'brand.font_body' );

		$values['on_primary']   = Color::readable_on( $values['primary'] );
		$values['muted']        = Color::mix( $values['text'], $values['surface'], 0.35 );
		$values['font_heading'] = Fonts::stack( $heading_key, $detected['font_heading'] );
		$values['font_body']    = Fonts::stack( $body_key, $detected['font_body'] );
		$values['font_keys']    = array( $heading_key, $body_key );
		$values['radius']       = (int) Settings::get( 'brand.radius' );
		$values['button_style'] = Settings::get( 'brand.button_style' );
		$values['logo_width']   = isset( $overrides['logo_width'] ) && '' !== $overrides['logo_width'] ? (int) $overrides['logo_width'] : (int) Settings::get( 'brand.logo_width' );
		$values['logo']         = self::first( isset( $overrides['logo'] ) ? $overrides['logo'] : '', self::logo_url() );
		$values['site_name']    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$values['favicon']      = get_site_icon_url( 64 );

		return $values;
	}

	/**
	 * Logo URL: plugin setting, then theme custom logo, then site icon.
	 *
	 * @return string
	 */
	public static function logo_url() {
		$logo = Settings::get( 'brand.logo' );

		if ( '' !== $logo ) {
			return $logo;
		}

		$custom = get_theme_mod( 'custom_logo' );

		if ( $custom ) {
			$url = wp_get_attachment_image_url( $custom, 'full' );
			if ( $url ) {
				return $url;
			}
		}

		return (string) get_site_icon_url( 192 );
	}

	/**
	 * CSS custom properties for a set of resolved values.
	 *
	 * @param array $v Values from get().
	 * @return string Declarations without a selector.
	 */
	public static function css_vars( array $v ) {
		$vars = array(
			'primary'       => $v['primary'],
			'on-primary'    => $v['on_primary'],
			'secondary'     => $v['secondary'],
			'text'          => $v['text'],
			'muted'         => $v['muted'],
			'background'    => $v['background'],
			'surface'       => $v['surface'],
			'border'        => $v['border'],
			'success'       => $v['success'],
			'error'         => $v['error'],
			'font-heading'  => $v['font_heading'],
			'font-body'     => $v['font_body'],
			'radius'        => $v['radius'] . 'px',
			'button-radius' => 'pill' === $v['button_style'] ? '999px' : $v['radius'] . 'px',
			'logo-width'    => $v['logo_width'] . 'px',
		);

		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= '--adminora-' . $name . ':' . self::css_value( $value ) . ';';
		}

		return $css;
	}

	/**
	 * Strips characters that could break out of a CSS declaration.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	public static function css_value( $value ) {
		return str_replace( array( '{', '}', ';', '<', '>' ), '', (string) $value );
	}

	/**
	 * Font-face rules for the downloaded brand fonts.
	 *
	 * @param array $v Values from get().
	 * @return string
	 */
	public static function font_faces( array $v ) {
		return Fonts::face_css( $v['font_keys'] );
	}

	/**
	 * Prints theme.json font faces if the brand uses the theme's fonts.
	 *
	 * @param array $v Values from get().
	 * @return void
	 */
	public static function print_theme_fonts( array $v ) {
		if ( in_array( 'theme', $v['font_keys'], true ) && function_exists( 'wp_print_font_faces' ) ) {
			wp_print_font_faces();
		}
	}

	/**
	 * Returns the first non-empty argument.
	 *
	 * @param string ...$values Candidates.
	 * @return string
	 */
	private static function first( ...$values ) {
		foreach ( $values as $value ) {
			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}

		return '';
	}
}
