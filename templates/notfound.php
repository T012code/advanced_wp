<?php
/**
 * Branded 404 page.
 *
 * @package AdvancedWP
 */

defined( 'ABSPATH' ) || exit;

echo AdvancedWP\Modules\Error_Pages\Error_Pages::render( 'notfound' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped during rendering.
