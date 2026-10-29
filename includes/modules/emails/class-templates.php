<?php
/**
 * The list of core emails Adminora can restyle.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Email template registry.
 */
class Templates {

	/**
	 * Template keys, with no translated strings so they can be read early.
	 *
	 * @return string[]
	 */
	public static function keys() {
		return array(
			'welcome',
			'password_reset',
			'password_changed',
			'email_change_confirm',
			'email_changed',
			'data_export',
			'data_erased',
			'admin_new_user',
			'admin_password_changed',
			'comment_notify',
			'comment_moderation',
			'admin_email_change_confirm',
			'admin_email_changed',
			'auto_update',
			'recovery_mode',
		);
	}

	/**
	 * Tags available in every email.
	 *
	 * @return string[]
	 */
	public static function common_tags() {
		return array( 'site_name', 'site_url', 'login_url', 'admin_url', 'admin_email', 'date', 'year' );
	}

	/**
	 * Full template definitions for the admin screens.
	 *
	 * `link` names the tag the button points to, if the email has a button.
	 *
	 * @return array
	 */
	public static function all() {
		$user = array( 'user_name', 'user_email', 'display_name' );

		return array(
			'welcome'                    => array(
				'group' => 'user',
				'label' => __( 'New account / welcome', 'adminora' ),
				'tags'  => array_merge( $user, array( 'set_password_link' ) ),
				'link'  => 'set_password_link',
			),
			'password_reset'             => array(
				'group' => 'user',
				'label' => __( 'Password reset request', 'adminora' ),
				'tags'  => array_merge( $user, array( 'reset_link', 'ip_address' ) ),
				'link'  => 'reset_link',
			),
			'password_changed'           => array(
				'group' => 'user',
				'label' => __( 'Password changed', 'adminora' ),
				'tags'  => $user,
				'link'  => 'login_url',
			),
			'email_change_confirm'       => array(
				'group' => 'user',
				'label' => __( 'Email address change confirmation', 'adminora' ),
				'tags'  => array_merge( $user, array( 'new_email', 'confirm_link' ) ),
				'link'  => 'confirm_link',
			),
			'email_changed'              => array(
				'group' => 'user',
				'label' => __( 'Email address changed notice', 'adminora' ),
				'tags'  => array_merge( $user, array( 'old_email', 'new_email' ) ),
				'link'  => '',
			),
			'data_export'                => array(
				'group' => 'user',
				'label' => __( 'Personal data export ready', 'adminora' ),
				'tags'  => array( 'user_email', 'download_link', 'expiry_date' ),
				'link'  => 'download_link',
			),
			'data_erased'                => array(
				'group' => 'user',
				'label' => __( 'Personal data erased', 'adminora' ),
				'tags'  => array( 'user_email', 'privacy_policy_url' ),
				'link'  => '',
			),
			'admin_new_user'             => array(
				'group' => 'admin',
				'label' => __( 'New user registered', 'adminora' ),
				'tags'  => array_merge( $user, array( 'user_edit_link' ) ),
				'link'  => 'user_edit_link',
			),
			'admin_password_changed'     => array(
				'group' => 'admin',
				'label' => __( 'User changed their password', 'adminora' ),
				'tags'  => $user,
				'link'  => '',
			),
			'comment_notify'             => array(
				'group' => 'admin',
				'label' => __( 'New comment', 'adminora' ),
				'tags'  => array( 'post_title', 'post_link', 'comment_author', 'comment_author_email', 'comment_content', 'comment_link' ),
				'link'  => 'comment_link',
			),
			'comment_moderation'         => array(
				'group' => 'admin',
				'label' => __( 'Comment awaiting moderation', 'adminora' ),
				'tags'  => array( 'post_title', 'post_link', 'comment_author', 'comment_author_email', 'comment_content', 'moderate_link', 'pending_count' ),
				'link'  => 'moderate_link',
			),
			'admin_email_change_confirm' => array(
				'group' => 'admin',
				'label' => __( 'Site admin email change confirmation', 'adminora' ),
				'tags'  => array( 'new_email', 'confirm_link' ),
				'link'  => 'confirm_link',
			),
			'admin_email_changed'        => array(
				'group' => 'admin',
				'label' => __( 'Site admin email changed notice', 'adminora' ),
				'tags'  => array( 'old_email', 'new_email' ),
				'link'  => '',
			),
			'auto_update'                => array(
				'group' => 'admin',
				'label' => __( 'Automatic update results', 'adminora' ),
				'tags'  => array( 'update_subject', 'update_details', 'updates_link' ),
				'link'  => 'updates_link',
			),
			'recovery_mode'              => array(
				'group' => 'admin',
				'label' => __( 'Recovery mode (site error)', 'adminora' ),
				'tags'  => array( 'recovery_link', 'technical_details' ),
				'link'  => 'recovery_link',
			),
		);
	}

	/**
	 * Returns one template definition.
	 *
	 * @param string $key Template key.
	 * @return array|null
	 */
	public static function get( $key ) {
		$all = self::all();

		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}
}
