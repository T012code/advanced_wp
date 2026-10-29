<?php
/**
 * Thin wrapper around WP_Filesystem for the few files the plugin writes.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * File helpers that only work when WordPress can write directly to disk.
 */
class Files {

	/**
	 * Returns a ready filesystem object, or null when direct writes are not possible.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	public static function fs() {
		global $wp_filesystem;

		if ( ! function_exists( 'get_filesystem_method' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( 'direct' !== get_filesystem_method( array(), WP_CONTENT_DIR ) ) {
			return null;
		}

		if ( ! $wp_filesystem && ! WP_Filesystem() ) {
			return null;
		}

		return $wp_filesystem;
	}

	/**
	 * Writes a file.
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents File contents.
	 * @return bool
	 */
	public static function put( $path, $contents ) {
		$fs = self::fs();

		return $fs ? (bool) $fs->put_contents( $path, $contents, FS_CHMOD_FILE ) : false;
	}

	/**
	 * Reads a file.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	public static function get( $path ) {
		$fs = self::fs();

		if ( ! $fs || ! $fs->exists( $path ) ) {
			return '';
		}

		return (string) $fs->get_contents( $path );
	}

	/**
	 * Deletes a file.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function delete( $path ) {
		$fs = self::fs();

		return $fs ? (bool) $fs->delete( $path ) : false;
	}

	/**
	 * Whether files can be written to a directory.
	 *
	 * @param string $dir Absolute directory path.
	 * @return bool
	 */
	public static function writable( $dir ) {
		$fs = self::fs();

		return $fs && $fs->is_writable( $dir );
	}
}
