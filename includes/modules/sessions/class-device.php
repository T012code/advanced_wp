<?php
/**
 * Reads the browser, operating system and device type from a user agent.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Sessions;

defined( 'ABSPATH' ) || exit;

/**
 * User agent parser. Deliberately small: it names the common browsers and
 * systems, and falls back to "Unknown" rather than guessing.
 */
class Device {

	/**
	 * Parses a user agent.
	 *
	 * @param string $ua User agent.
	 * @return array { type: desktop|phone|tablet|app|other, browser, os, label }
	 */
	public static function parse( $ua ) {
		$ua = (string) $ua;

		$type    = self::type( $ua );
		$browser = self::browser( $ua );
		$os      = self::os( $ua );

		if ( 'app' === $type ) {
			$label = sprintf(
				/* translators: %s: operating system. */
				__( 'WordPress app on %s', 'admin-studio' ),
				$os
			);
		} elseif ( '' === $ua ) {
			$label = __( 'Unknown device', 'admin-studio' );
		} else {
			$label = sprintf(
				/* translators: 1: browser, 2: operating system. */
				__( '%1$s on %2$s', 'admin-studio' ),
				$browser,
				$os
			);
		}

		return array(
			'type'    => $type,
			'browser' => $browser,
			'os'      => $os,
			'label'   => $label,
		);
	}

	/**
	 * Human name of a device type.
	 *
	 * @param string $type Type key.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'desktop' => __( 'Computer', 'admin-studio' ),
			'phone'   => __( 'Phone', 'admin-studio' ),
			'tablet'  => __( 'Tablet', 'admin-studio' ),
			'app'     => __( 'Mobile app', 'admin-studio' ),
			'other'   => __( 'Other', 'admin-studio' ),
		);

		return isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['other'];
	}

	/**
	 * Device type.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function type( $ua ) {
		if ( preg_match( '/wp-(iphone|android)|WordPress\/[\d.]+ \(/i', $ua ) ) {
			return 'app';
		}

		if ( '' === $ua || preg_match( '/bot|crawl|spider|curl|wget|python|postman/i', $ua ) ) {
			return 'other';
		}

		if ( preg_match( '/iPad|Tablet|Kindle|Silk|PlayBook/i', $ua ) || ( false !== stripos( $ua, 'Android' ) && false === stripos( $ua, 'Mobile' ) ) ) {
			return 'tablet';
		}

		if ( preg_match( '/Mobi|iPhone|iPod|Android|Windows Phone/i', $ua ) ) {
			return 'phone';
		}

		return 'desktop';
	}

	/**
	 * Browser name and major version.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function browser( $ua ) {
		$browsers = array(
			'Edge'             => '/Edg(?:e|A|iOS)?\/(\d+)/',
			'Opera'            => '/(?:OPR|Opera)\/(\d+)/',
			'Samsung Internet' => '/SamsungBrowser\/(\d+)/',
			'Firefox'          => '/(?:Firefox|FxiOS)\/(\d+)/',
			'Chrome'           => '/(?:Chrome|CriOS)\/(\d+)/',
			'Safari'           => '/Version\/(\d+)[\d.]* (?:Mobile\/\S+ )?Safari\//',
		);

		foreach ( $browsers as $name => $pattern ) {
			if ( preg_match( $pattern, $ua, $m ) ) {
				return $name . ' ' . $m[1];
			}
		}

		return __( 'Unknown browser', 'admin-studio' );
	}

	/**
	 * Operating system.
	 *
	 * @param string $ua User agent.
	 * @return string
	 */
	private static function os( $ua ) {
		if ( preg_match( '/iPhone OS (\d+)/', $ua, $m ) ) {
			return 'iOS ' . $m[1];
		}

		if ( preg_match( '/iPad.*OS (\d+)/', $ua, $m ) ) {
			return 'iPadOS ' . $m[1];
		}

		if ( preg_match( '/Android (\d+)/', $ua, $m ) ) {
			return 'Android ' . $m[1];
		}

		$systems = array(
			'Windows'  => '/Windows/',
			'ChromeOS' => '/CrOS/',
			'macOS'    => '/Mac OS X|Macintosh/',
			'iOS'      => '/iPhone|iPod/',
			'Linux'    => '/Linux/',
		);

		foreach ( $systems as $name => $pattern ) {
			if ( preg_match( $pattern, $ua ) ) {
				return $name;
			}
		}

		return __( 'an unknown system', 'admin-studio' );
	}
}
