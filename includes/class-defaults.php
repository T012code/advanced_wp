<?php
/**
 * Translatable default text for settings.
 *
 * Kept apart from the schema so translations only load after `init`.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Default copy for emails, error pages and other text settings.
 */
class Defaults {

	/**
	 * Cached map of key => text.
	 *
	 * @var array|null
	 */
	private static $texts = null;

	/**
	 * Returns the default text for a dot path, or '' if none is defined.
	 *
	 * @param string $key Dot path.
	 * @return string
	 */
	public static function text( $key ) {
		if ( null === self::$texts ) {
			self::$texts = array_merge( self::pages(), self::emails() );
		}

		return isset( self::$texts[ $key ] ) ? self::$texts[ $key ] : '';
	}

	/**
	 * Defaults for error pages, maintenance mode and admin branding.
	 *
	 * @return array
	 */
	private static function pages() {
		return array(
			'error_pages.critical_title'      => __( 'Something went wrong', 'adminora' ),
			'error_pages.critical_message'    => __( 'This page ran into a problem and could not load. The site team has been notified. Please try again in a few minutes.', 'adminora' ),
			'error_pages.critical_button'     => __( 'Back to the home page', 'adminora' ),
			'error_pages.critical_admin_note' => __( 'Only administrators see this: a plugin or theme stopped this page from loading. WordPress emails a recovery link to {admin_email}; open it to sign in safely and turn off whatever failed. The details are in the error log.', 'adminora' ),
			'error_pages.db_title'            => __( 'We will be right back', 'adminora' ),
			'error_pages.db_message'          => __( 'The site cannot reach its database at the moment. This is usually temporary. Please refresh the page in a minute or two.', 'adminora' ),
			'error_pages.maintenance_title'   => __( 'Updating, back shortly', 'adminora' ),
			'error_pages.maintenance_message' => __( 'The site is installing updates. This normally takes less than a minute.', 'adminora' ),
			'error_pages.wp_die_title'        => __( 'Something needs your attention', 'adminora' ),
			'error_pages.wp_die_button'       => __( 'Go back', 'adminora' ),
			'error_pages.notfound_title'      => __( 'Page not found', 'adminora' ),
			'error_pages.notfound_message'    => __( 'The page you were looking for has moved or no longer exists. Try a search, or head back to the home page.', 'adminora' ),
			'error_pages.notfound_button'     => __( 'Back to the home page', 'adminora' ),
			'error_pages.denied_title'        => __( 'You do not have access to this page', 'adminora' ),
			'error_pages.denied_message'      => __( 'Your account is not allowed to open this page. If you think this is a mistake, contact the site administrator.', 'adminora' ),
			'error_pages.denied_button'       => __( 'Go to the dashboard', 'adminora' ),
			'error_pages.expired_title'       => __( 'This link has expired', 'adminora' ),
			'error_pages.expired_message'     => __( 'For your security, links like this only work for a while. Go back, refresh the page and try again.', 'adminora' ),
			'maintenance.headline'            => __( 'Something new is on the way', 'adminora' ),
			'maintenance.message'             => __( 'We are putting the finishing touches on the site. Check back soon.', 'adminora' ),
			'admin_branding.footer_left_text' => __( 'Managed by {site_name}', 'adminora' ),
			'admin_branding.welcome_title'    => __( 'Welcome to {site_name}', 'adminora' ),
			'admin_branding.welcome_content'  => __( 'Use the menu on the left to manage your content. If you get stuck, contact your site administrator.', 'adminora' ),
			'emails.footer_text'              => __( '© {year} {site_name}. You are receiving this email because of activity on {site_url}.', 'adminora' ),
		);
	}

	/**
	 * Defaults for every email template.
	 *
	 * @return array
	 */
	private static function emails() {
		$t = array(
			'welcome'                    => array(
				__( 'Welcome to {site_name}', 'adminora' ),
				__( 'Your account is ready', 'adminora' ),
				__( '<p>Hi {display_name},</p><p>An account has been created for you on {site_name}. Your username is <strong>{user_name}</strong>.</p><p>Use the button below to choose your password and sign in.</p>', 'adminora' ),
				__( 'Set your password', 'adminora' ),
			),
			'password_reset'             => array(
				__( '[{site_name}] Reset your password', 'adminora' ),
				__( 'Reset your password', 'adminora' ),
				__( '<p>Hi {display_name},</p><p>Someone asked to reset the password for the account <strong>{user_name}</strong> on {site_name}.</p><p>If this was you, use the button below to choose a new password. If not, you can ignore this email and nothing will change.</p><p>This request came from IP address {ip_address}.</p>', 'adminora' ),
				__( 'Reset password', 'adminora' ),
			),
			'password_changed'           => array(
				__( '[{site_name}] Your password was changed', 'adminora' ),
				__( 'Password changed', 'adminora' ),
				__( '<p>Hi {display_name},</p><p>The password for your account on {site_name} was just changed.</p><p>If you did not make this change, contact the site administrator straight away at {admin_email}.</p>', 'adminora' ),
				__( 'Log in', 'adminora' ),
			),
			'email_change_confirm'       => array(
				__( '[{site_name}] Confirm your new email address', 'adminora' ),
				__( 'Confirm your new email address', 'adminora' ),
				__( '<p>Hi {display_name},</p><p>You asked to change the email address on your {site_name} account to <strong>{new_email}</strong>.</p><p>Use the button below to confirm. If you did not ask for this, ignore this email.</p>', 'adminora' ),
				__( 'Confirm new address', 'adminora' ),
			),
			'email_changed'              => array(
				__( '[{site_name}] Your email address was changed', 'adminora' ),
				__( 'Email address changed', 'adminora' ),
				__( '<p>Hi {display_name},</p><p>The email address on your {site_name} account was changed from {old_email} to {new_email}.</p><p>If you did not make this change, contact the site administrator straight away at {admin_email}.</p>', 'adminora' ),
				'',
			),
			'data_export'                => array(
				__( '[{site_name}] Your personal data export', 'adminora' ),
				__( 'Your data is ready', 'adminora' ),
				__( '<p>Your request for an export of your personal data on {site_name} has been completed.</p><p>The download link stays active until {expiry_date}.</p>', 'adminora' ),
				__( 'Download your data', 'adminora' ),
			),
			'data_erased'                => array(
				__( '[{site_name}] Your personal data has been erased', 'adminora' ),
				__( 'Erasure complete', 'adminora' ),
				__( '<p>Your request to erase your personal data on {site_name} has been completed.</p><p>You can read more about how we handle data in our privacy policy: {privacy_policy_url}</p>', 'adminora' ),
				'',
			),
			'admin_new_user'             => array(
				__( '[{site_name}] New user registration', 'adminora' ),
				__( 'New user registered', 'adminora' ),
				__( '<p>A new user has registered on {site_name}.</p><p>Username: <strong>{user_name}</strong><br>Email: {user_email}</p>', 'adminora' ),
				__( 'View user', 'adminora' ),
			),
			'admin_password_changed'     => array(
				__( '[{site_name}] Password changed for {user_name}', 'adminora' ),
				__( 'A user changed their password', 'adminora' ),
				'<p>' . __( 'The user <strong>{user_name}</strong> ({user_email}) changed their password on {site_name}.', 'adminora' ) . '</p>',
				'',
			),
			'comment_notify'             => array(
				__( '[{site_name}] New comment on "{post_title}"', 'adminora' ),
				__( 'New comment', 'adminora' ),
				__( '<p><strong>{comment_author}</strong> left a comment on <a href="{post_link}">{post_title}</a>:</p><blockquote>{comment_content}</blockquote>', 'adminora' ),
				__( 'View comment', 'adminora' ),
			),
			'comment_moderation'         => array(
				__( '[{site_name}] Please moderate: "{post_title}"', 'adminora' ),
				__( 'A comment is waiting for approval', 'adminora' ),
				__( '<p><strong>{comment_author}</strong> ({comment_author_email}) left a comment on <a href="{post_link}">{post_title}</a>:</p><blockquote>{comment_content}</blockquote><p>There are {pending_count} comments waiting for approval.</p>', 'adminora' ),
				__( 'Moderate comment', 'adminora' ),
			),
			'admin_email_change_confirm' => array(
				__( '[{site_name}] Confirm the new admin email address', 'adminora' ),
				__( 'Confirm the new admin email', 'adminora' ),
				__( '<p>Someone with administrator access asked to change the admin email address of {site_name} to <strong>{new_email}</strong>.</p><p>Use the button below to confirm. If you did not ask for this, ignore this email.</p>', 'adminora' ),
				__( 'Confirm new address', 'adminora' ),
			),
			'admin_email_changed'        => array(
				__( '[{site_name}] Admin email address changed', 'adminora' ),
				__( 'Admin email changed', 'adminora' ),
				__( '<p>The admin email address of {site_name} was changed from {old_email} to {new_email}.</p><p>If you did not make this change, sign in and check your site settings.</p>', 'adminora' ),
				'',
			),
			'auto_update'                => array(
				'{update_subject}',
				__( 'Update report', 'adminora' ),
				__( '<p>WordPress ran automatic updates on {site_name}. Here are the details:</p>{update_details}', 'adminora' ),
				__( 'Open updates', 'adminora' ),
			),
			'recovery_mode'              => array(
				__( '[{site_name}] Your site is having a technical problem', 'adminora' ),
				__( 'Your site ran into a problem', 'adminora' ),
				__( '<p>A plugin or theme caused an error on {site_name}. Visitors may see an error page until it is fixed.</p><p>Recovery mode lets you sign in safely and switch off the cause. Use the button below to start.</p><h3>Technical details</h3>{technical_details}', 'adminora' ),
				__( 'Enter recovery mode', 'adminora' ),
			),
		);

		$out = array();

		foreach ( $t as $key => $parts ) {
			$prefix                     = 'emails.templates.' . $key . '.';
			$out[ $prefix . 'subject' ] = $parts[0];
			$out[ $prefix . 'heading' ] = $parts[1];
			$out[ $prefix . 'body' ]    = $parts[2];
			$out[ $prefix . 'button' ]  = $parts[3];
		}

		return $out;
	}
}
