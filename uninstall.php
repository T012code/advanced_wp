<?php
/**
 * Removes Advanced WP data on uninstall, only if the owner opted in.
 *
 * Drop-in files are removed on deactivation, before this runs.
 *
 * @package AdvancedWP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$advwp_settings = get_option( 'advwp_settings', array() );

if ( empty( $advwp_settings['general']['remove_data'] ) ) {
	return;
}

delete_option( 'advwp_settings' );
delete_option( 'advwp_version' );
delete_option( 'advwp_fonts' );
delete_option( 'advwp_lockouts' );

foreach ( array( 'advwp_2fa_method', 'advwp_2fa_secret', 'advwp_2fa_pending', 'advwp_2fa_backup', 'advwp_2fa_last_slice', 'advwp_2fa_login', 'advwp_2fa_email_code', 'advwp_2fa_required_since' ) as $advwp_meta_key ) {
	delete_metadata( 'user', 0, $advwp_meta_key, '', true );
}

// Users on the brand admin colour scheme go back to the default.
delete_metadata( 'user', 0, 'admin_color', 'advwp', true );

// Downloaded fonts.
$advwp_uploads = wp_upload_dir( null, false );
$advwp_dir     = trailingslashit( $advwp_uploads['basedir'] ) . 'advanced-wp';

if ( is_dir( $advwp_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $advwp_dir, true );
	}
}
