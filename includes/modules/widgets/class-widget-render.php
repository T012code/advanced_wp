<?php
/**
 * Prints the content of custom dashboard widgets.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Widgets;

use Adminora\Modules\Admin_Look\Icons;
use Adminora\Modules\Emails\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Custom widget output.
 */
class Widget_Render {

	/**
	 * Dashboard callback.
	 *
	 * @param mixed $item Unused.
	 * @param array $box  Meta box, with the widget settings in `args`.
	 * @return void
	 */
	public static function render( $item, $box ) {
		$widget = isset( $box['args'] ) ? (array) $box['args'] : array();
		$method = 'type_' . ( isset( $widget['type'] ) ? $widget['type'] : 'text' );

		echo '<div class="adminora-widget adminora-widget--' . esc_attr( $widget['type'] ) . '">';
		if ( method_exists( __CLASS__, $method ) ) {
			self::$method( $widget );
		}
		echo '</div>';
	}

	/**
	 * Text with placeholders replaced.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function text( $text ) {
		return Renderer::replace( (string) $text, Renderer::common_context(), true );
	}

	/**
	 * Optional button.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function button( array $widget ) {
		if ( '' !== $widget['button'] && '' !== $widget['button_url'] ) {
			printf( '<p class="adminora-widget__button"><a class="button button-primary" href="%s">%s</a></p>', esc_url( self::link( $widget['button_url'] ) ), esc_html( $widget['button'] ) );
		}
	}

	/**
	 * Turns an admin screen such as "edit.php" into a full address.
	 *
	 * @param string $url Address or admin screen.
	 * @return string
	 */
	public static function link( $url ) {
		return preg_match( '/^[a-z0-9-]+\.php/i', $url ) ? admin_url( $url ) : $url;
	}

	/**
	 * Rich text.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_text( array $widget ) {
		echo wp_kses_post( wpautop( self::text( $widget['content'] ) ) );
		self::button( $widget );
	}

	/**
	 * Quick links.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_links( array $widget ) {
		if ( ! $widget['links'] ) {
			echo '<p>' . esc_html__( 'No links yet.', 'adminora' ) . '</p>';
			return;
		}

		echo '<ul class="adminora-qlinks">';
		foreach ( $widget['links'] as $link ) {
			$url      = self::link( $link['url'] );
			$external = 0 !== strpos( $url, home_url() ) && 0 !== strpos( $url, admin_url() ) && preg_match( '#^https?://#i', $url );
			$icon     = '' !== $link['icon'] ? $link['icon'] : ( $external ? 'external' : 'link' );

			printf(
				'<li><a class="adminora-qlink" href="%s"%s><span class="adminora-qlink__icon" aria-hidden="true">%s</span><span>%s</span></a></li>',
				esc_url( $url ),
				$external ? ' target="_blank" rel="noopener"' : '',
				wp_kses( Icons::svg( $icon, 20 ), Icons::kses() ),
				esc_html( $link['label'] )
			);
		}
		echo '</ul>';
	}

	/**
	 * Support card.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_support( array $widget ) {
		if ( '' !== $widget['content'] ) {
			echo wp_kses_post( wpautop( self::text( $widget['content'] ) ) );
		}

		if ( '' !== $widget['email'] || '' !== $widget['phone'] ) {
			echo '<ul class="adminora-contact">';
			if ( '' !== $widget['email'] ) {
				printf( '<li><span aria-hidden="true">%s</span><a href="%s">%s</a></li>', wp_kses( Icons::svg( 'mail', 18 ), Icons::kses() ), esc_url( 'mailto:' . $widget['email'] ), esc_html( $widget['email'] ) );
			}
			if ( '' !== $widget['phone'] ) {
				printf( '<li><span aria-hidden="true">%s</span><a href="%s">%s</a></li>', wp_kses( Icons::svg( 'smartphone', 18 ), Icons::kses() ), esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $widget['phone'] ) ), esc_html( $widget['phone'] ) );
			}
			echo '</ul>';
		}

		self::button( $widget );
	}

	/**
	 * Embedded video. The embed code is fetched once from the provider and kept for a day.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_video( array $widget ) {
		if ( '' === $widget['video'] ) {
			echo '<p>' . esc_html__( 'No video address yet.', 'adminora' ) . '</p>';
			return;
		}

		$key  = 'adminora_embed_' . md5( $widget['video'] );
		$html = get_transient( $key );

		if ( false === $html ) {
			$html = wp_oembed_get( $widget['video'], array( 'width' => 640 ) );
			$html = is_string( $html ) ? $html : '';
			set_transient( $key, $html, '' !== $html ? DAY_IN_SECONDS : HOUR_IN_SECONDS );
		}

		if ( '' === $html ) {
			printf( '<p><a href="%s" target="_blank" rel="noopener">%s</a></p>', esc_url( $widget['video'] ), esc_html__( 'Watch the video', 'adminora' ) );
		} else {
			echo '<div class="adminora-video">' . wp_kses(
				$html,
				array(
					'iframe' => array(
						'src'             => true,
						'width'           => true,
						'height'          => true,
						'title'           => true,
						'frameborder'     => true,
						'allow'           => true,
						'allowfullscreen' => true,
						'referrerpolicy'  => true,
						'loading'         => true,
					),
				)
			) . '</div>';
		}

		if ( '' !== $widget['content'] ) {
			echo wp_kses_post( wpautop( self::text( $widget['content'] ) ) );
		}
	}

	/**
	 * Latest posts from a category.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_posts( array $widget ) {
		$posts = get_posts(
			array(
				'numberposts'      => $widget['number'],
				'category'         => $widget['category'],
				'post_status'      => 'publish',
				'suppress_filters' => false,
			)
		);

		if ( ! $posts ) {
			echo '<p>' . esc_html__( 'Nothing published yet.', 'adminora' ) . '</p>';
			return;
		}

		echo '<ul class="adminora-feed">';
		foreach ( $posts as $post ) {
			printf(
				'<li><a href="%s">%s</a><time datetime="%s">%s</time><p>%s</p></li>',
				esc_url( get_permalink( $post ) ),
				esc_html( get_the_title( $post ) ),
				esc_attr( get_the_date( 'c', $post ) ),
				esc_html( get_the_date( '', $post ) ),
				esc_html( wp_trim_words( wp_strip_all_tags( has_excerpt( $post ) ? $post->post_excerpt : strip_shortcodes( $post->post_content ) ), 20 ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Newest accounts.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_signups( array $widget ) {
		$users = get_users(
			array(
				'number'  => $widget['number'],
				'orderby' => 'registered',
				'order'   => 'DESC',
			)
		);

		if ( ! $users ) {
			echo '<p>' . esc_html__( 'Nobody yet.', 'adminora' ) . '</p>';
			return;
		}

		$names = wp_roles()->get_names();

		echo '<ul class="adminora-people">';
		foreach ( $users as $user ) {
			$role = $user->roles ? reset( $user->roles ) : '';
			$name = esc_html( $user->display_name );
			if ( current_user_can( 'edit_user', $user->ID ) ) {
				$name = '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . $name . '</a>';
			}

			printf(
				'<li>%s<span><strong>%s</strong><small>%s</small></span></li>',
				get_avatar( $user->ID, 32 ),
				wp_kses( $name, array( 'a' => array( 'href' => true ) ) ),
				esc_html(
					trim(
						( isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) . ' · ' : '' ) .
						/* translators: %s: time span such as "3 days". */
						sprintf( __( 'joined %s ago', 'adminora' ), human_time_diff( strtotime( $user->user_registered . ' UTC' ), time() ) )
					)
				)
			);
		}
		echo '</ul>';
	}

	/**
	 * Content counts.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_counts( array $widget ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Same signature as the other types.
		$tiles = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part' ), true ) || ! $type->public ) {
				continue;
			}

			$count = wp_count_posts( $type->name );

			$tiles[] = array(
				(int) $count->publish,
				$type->labels->name,
				current_user_can( $type->cap->edit_posts ) ? admin_url( 'edit.php?post_type=' . $type->name ) : '',
			);
		}

		$comments = wp_count_comments();
		$tiles[]  = array( (int) $comments->approved, __( 'Comments', 'adminora' ), current_user_can( 'moderate_comments' ) ? admin_url( 'edit-comments.php' ) : '' );

		$users   = count_users();
		$tiles[] = array( (int) $users['total_users'], __( 'Users', 'adminora' ), current_user_can( 'list_users' ) ? admin_url( 'users.php' ) : '' );

		echo '<ul class="adminora-tiles">';
		foreach ( $tiles as $tile ) {
			$inner = '<strong>' . esc_html( number_format_i18n( $tile[0] ) ) . '</strong><span>' . esc_html( $tile[1] ) . '</span>';
			echo '<li>' . ( '' !== $tile[2] ? '<a href="' . esc_url( $tile[2] ) . '">' . wp_kses_post( $inner ) . '</a>' : wp_kses_post( $inner ) ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Latest comments.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_comments( array $widget ) {
		$moderate = current_user_can( 'moderate_comments' );
		$comments = get_comments(
			array(
				'number' => $widget['number'],
				'status' => $moderate ? 'all' : 'approve',
				'type'   => 'comment',
			)
		);

		if ( ! $comments ) {
			echo '<p>' . esc_html__( 'No comments yet.', 'adminora' ) . '</p>';
			return;
		}

		echo '<ul class="adminora-feed">';
		foreach ( $comments as $comment ) {
			$pending = '0' === $comment->comment_approved;

			printf(
				'<li><span><strong>%s</strong> %s <a href="%s">%s</a>%s</span><time datetime="%s">%s</time><p>%s</p></li>',
				esc_html( $comment->comment_author ),
				esc_html__( 'on', 'adminora' ),
				esc_url( $moderate ? admin_url( 'comment.php?action=editcomment&c=' . $comment->comment_ID ) : get_comment_link( $comment ) ),
				esc_html( get_the_title( $comment->comment_post_ID ) ),
				$pending ? ' <span class="adminora-pill">' . esc_html__( 'Waiting for approval', 'adminora' ) . '</span>' : '',
				esc_attr( mysql2date( 'c', $comment->comment_date_gmt ) ),
				/* translators: %s: time span such as "5 minutes". */
				esc_html( sprintf( __( '%s ago', 'adminora' ), human_time_diff( strtotime( $comment->comment_date_gmt . ' UTC' ), time() ) ) ),
				esc_html( wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 20 ) )
			);
		}
		echo '</ul>';

		if ( $moderate ) {
			echo '<p><a href="' . esc_url( admin_url( 'edit-comments.php' ) ) . '">' . esc_html__( 'All comments', 'adminora' ) . '</a></p>';
		}
	}

	/**
	 * Site Health summary from the last check.
	 *
	 * @param array $widget Widget.
	 * @return void
	 */
	private static function type_health( array $widget ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Same signature as the other types.
		$result = json_decode( (string) get_transient( 'health-check-site-status-result' ), true );
		$link   = current_user_can( 'view_site_health_checks' ) ? admin_url( 'site-health.php' ) : '';

		if ( ! is_array( $result ) ) {
			echo '<p>' . esc_html__( 'No results yet. Site Health runs its checks when someone opens it.', 'adminora' ) . '</p>';
		} else {
			$critical    = isset( $result['critical'] ) ? (int) $result['critical'] : 0;
			$recommended = isset( $result['recommended'] ) ? (int) $result['recommended'] : 0;
			$good        = isset( $result['good'] ) ? (int) $result['good'] : 0;

			if ( $critical ) {
				$state = array( 'bad', __( 'Needs attention', 'adminora' ) );
			} elseif ( $recommended > 2 ) {
				$state = array( 'fair', __( 'Could be better', 'adminora' ) );
			} else {
				$state = array( 'good', __( 'Good', 'adminora' ) );
			}

			echo '<p class="adminora-health adminora-health--' . esc_attr( $state[0] ) . '"><strong>' . esc_html( $state[1] ) . '</strong></p>';
			echo '<ul class="adminora-tiles">';
			foreach (
				array(
					array( $critical, __( 'Critical issues', 'adminora' ) ),
					array( $recommended, __( 'Recommended improvements', 'adminora' ) ),
					array( $good, __( 'Passed checks', 'adminora' ) ),
				) as $tile
			) {
				echo '<li><strong>' . esc_html( number_format_i18n( $tile[0] ) ) . '</strong><span>' . esc_html( $tile[1] ) . '</span></li>';
			}
			echo '</ul>';
		}

		if ( '' !== $link ) {
			echo '<p><a href="' . esc_url( $link ) . '">' . esc_html__( 'Open Site Health', 'adminora' ) . '</a></p>';
		}
	}
}
