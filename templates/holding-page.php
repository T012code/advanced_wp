<?php
/**
 * Coming soon or maintenance page built with the editor or a page builder.
 *
 * The page fills the whole screen without the theme's header and footer.
 * Page builders such as Elementor print their content through the_content().
 *
 * @package Adminora
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'adminora-holding-page' ); ?>>
<?php
wp_body_open();

while ( have_posts() ) {
	the_post();
	the_content();
}

wp_footer();
?>
</body>
</html>
