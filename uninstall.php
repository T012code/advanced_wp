<?php
/**
 * Removes Adminora data on uninstall, only if the owner opted in.
 *
 * Drop-in files are removed on deactivation, before this runs.
 *
 * @package Adminora
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$adminora_settings = get_option( 'adminora_settings', array() );

if ( empty( $adminora_settings['general']['remove_data'] ) ) {
	return;
}

delete_option( 'adminora_settings' );
delete_option( 'adminora_version' );
delete_option( 'adminora_fonts' );
delete_option( 'adminora_lockouts' );
delete_option( 'adminora_access' );
delete_option( 'adminora_toolbar_nodes' );
delete_option( 'adminora_role_backups' );
delete_option( 'adminora_audit' );
delete_option( 'adminora_look' );
delete_option( 'adminora_widgets' );
delete_option( 'adminora_widgets_seen' );
delete_option( 'adminora_signins' );
delete_option( 'adminora_brand_palette' );
delete_option( 'adminora_admin_url' );
delete_option( 'adminora_htaccess_backup' );
delete_option( 'adminora_reapply_admin_url' );

foreach ( array( 'adminora_2fa_method', 'adminora_2fa_secret', 'adminora_2fa_pending', 'adminora_2fa_backup', 'adminora_2fa_last_slice', 'adminora_2fa_login', 'adminora_2fa_email_code', 'adminora_2fa_required_since', 'adminora_toolbar', 'adminora_look_admin', 'adminora_look_self', 'adminora_look_try', 'adminora_look_mode', 'adminora_blocked', 'adminora_login_redirect' ) as $adminora_meta_key ) {
	delete_metadata( 'user', 0, $adminora_meta_key, '', true );
}

// Users on the brand admin colour scheme go back to the default.
delete_metadata( 'user', 0, 'admin_color', 'adminora', true );

// Downloaded fonts.
$adminora_uploads = wp_upload_dir( null, false );
$adminora_dir     = trailingslashit( $adminora_uploads['basedir'] ) . 'adminora';

if ( is_dir( $adminora_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	if ( WP_Filesystem() ) {
		global $wp_filesystem;
		$wp_filesystem->delete( $adminora_dir, true );
	}
}
