/**
 * Moves the social sign-in buttons next to the login form, above or below
 * it as chosen. Without JavaScript they stay at the bottom of the page.
 */
( function () {
	'use strict';

	var box = document.querySelector( '.layodt-social' ),
		form = document.getElementById( 'loginform' );

	if ( ! box || ! form ) {
		return;
	}

	if ( 'above' === box.getAttribute( 'data-place' ) ) {
		form.parentNode.insertBefore( box, form );
	} else {
		form.parentNode.insertBefore( box, form.nextSibling );
	}
}() );
