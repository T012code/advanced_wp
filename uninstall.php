<?php
/**
 * Removes Layò Dashboard Toolkit data on uninstall, only if the owner opted in.
 *
 * Drop-in files are removed on deactivation, before this runs.
 *
 * @package LayoDT
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$layodt_settings = get_option( 'layodt_settings', array() );

if ( empty( $layodt_settings['general']['remove_data'] ) ) {
	return;
}

delete_option( 'layodt_settings' );
delete_option( 'layodt_version' );
delete_option( 'layodt_fonts' );
delete_option( 'layodt_lockouts' );
delete_option( 'layodt_access' );
delete_option( 'layodt_toolbar_nodes' );
delete_option( 'layodt_role_backups' );
delete_option( 'layodt_audit' );
delete_option( 'layodt_look' );
delete_option( 'layodt_widgets' );
delete_option( 'layodt_widgets_seen' );
delete_option( 'layodt_signins' );
delete_option( 'layodt_brand_palette' );
delete_option( 'layodt_admin_url' );
delete_option( 'layodt_htaccess_backup' );
delete_option( 'layodt_reapply_admin_url' );

foreach ( array( 'layodt_2fa_method', 'layodt_2fa_secret', 'layodt_2fa_pending', 'layodt_2fa_backup', 'layodt_2fa_last_slice', 'layodt_2fa_login', 'layodt_2fa_email_code', 'layodt_2fa_required_since', 'layodt_toolbar', 'layodt_look_admin', 'layodt_look_self', 'layodt_look_try', 'layodt_look_mode', 'layodt_blocked', 'layodt_login_redirect' ) as $layodt_meta_key ) {
	delete_metadata( 'user', 0, $layodt_meta_key, '', true );
}

// Users on the brand admin colour scheme go back to the default.
delete_metadata( 'user', 0, 'admin_color', 'layodt', true );

// Downloaded fonts.
$layodt_uploads = wp_upload_dir( null, false );
$layodt_dir     = trailingslashit( $layodt_uploads['basedir'] ) . 'layo-dashboard-toolkit';

if ( is_dir( $layodt_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $layodt_dir, true );
	}
}
