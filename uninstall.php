<?php
/**
 * Removes Admin Studio data on uninstall, only if the owner opted in.
 *
 * Drop-in files are removed on deactivation, before this runs.
 *
 * @package AdminStudio
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$adminstudio_settings = get_option( 'adminstudio_settings', array() );

if ( empty( $adminstudio_settings['general']['remove_data'] ) ) {
	return;
}

delete_option( 'adminstudio_settings' );
delete_option( 'adminstudio_version' );
delete_option( 'adminstudio_fonts' );
delete_option( 'adminstudio_lockouts' );
delete_option( 'adminstudio_access' );
delete_option( 'adminstudio_toolbar_nodes' );
delete_option( 'adminstudio_role_backups' );
delete_option( 'adminstudio_audit' );
delete_option( 'adminstudio_look' );
delete_option( 'adminstudio_signins' );
delete_option( 'adminstudio_brand_palette' );
delete_option( 'adminstudio_admin_url' );
delete_option( 'adminstudio_htaccess_backup' );

foreach ( array( 'adminstudio_2fa_method', 'adminstudio_2fa_secret', 'adminstudio_2fa_pending', 'adminstudio_2fa_backup', 'adminstudio_2fa_last_slice', 'adminstudio_2fa_login', 'adminstudio_2fa_email_code', 'adminstudio_2fa_required_since', 'adminstudio_toolbar', 'adminstudio_look_admin', 'adminstudio_look_self', 'adminstudio_look_try', 'adminstudio_look_mode', 'adminstudio_blocked', 'adminstudio_login_redirect' ) as $adminstudio_meta_key ) {
	delete_metadata( 'user', 0, $adminstudio_meta_key, '', true );
}

// Users on the brand admin colour scheme go back to the default.
delete_metadata( 'user', 0, 'admin_color', 'adminstudio', true );

// Downloaded fonts.
$adminstudio_uploads = wp_upload_dir( null, false );
$adminstudio_dir     = trailingslashit( $adminstudio_uploads['basedir'] ) . 'admin-studio';

if ( is_dir( $adminstudio_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $adminstudio_dir, true );
	}
}
