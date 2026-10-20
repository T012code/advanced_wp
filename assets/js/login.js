/**
 * Admin Studio login helpers: placeholders when labels are hidden, and a
 * paused background video for visitors who prefer reduced motion.
 */
( function () {
	'use strict';

	var body = document.body;

	if ( body.classList.contains( 'adminstudio-nolabels' ) ) {
		document.querySelectorAll( '#login form label[for]' ).forEach( function ( label ) {
			var input = document.getElementById( label.getAttribute( 'for' ) );

			if ( input && ! input.placeholder && /^(text|email|password)$/.test( input.type ) ) {
				input.placeholder = label.textContent.trim();
			}
		} );
	}

	var video = document.querySelector( '.adminstudio-bg-video' );

	if ( video && window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		video.removeAttribute( 'autoplay' );
		video.pause();
	}
} )();
