/**
 * Admin Studio login helpers: placeholders when labels are hidden, a paused
 * background video for visitors who prefer reduced motion, and the layout of
 * the links under the form.
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

	arrangeLinks( window.adminstudioLogin );

	/**
	 * Moves the links WordPress prints under the form into the chosen layout
	 * and positions. Without JavaScript the links simply stay where they are.
	 *
	 * @param {Object} config Layout and position of each link.
	 */
	function arrangeLinks( config ) {
		var form = document.querySelector( '#login form#loginform, #login form#registerform, #login form#lostpasswordform, #login form#resetpassform' ),
			nav = document.querySelector( '#login p#nav' ),
			back = document.querySelector( '#login #backtoblog' ),
			links = [],
			moving = false;

		if ( ! config || ! form ) {
			return;
		}

		if ( nav ) {
			nav.querySelectorAll( 'a' ).forEach( function ( link ) {
				var key = link.classList.contains( 'wp-login-lost-password' ) ? 'lost' : link.classList.contains( 'wp-login-register' ) ? 'register' : link.classList.contains( 'wp-login-log-in' ) ? 'login' : 'other';
				links.push( { key: key, el: link } );
			} );
		}

		if ( back && back.querySelector( 'a' ) ) {
			links.push( { key: 'back', el: back.querySelector( 'a' ) } );
		}

		links.forEach( function ( link ) {
			var place = config.places[ link.key ] || 'below';
			moving = moving || 'password' === place || 'button' === place;
		} );

		// The standard arrangement needs no changes.
		if ( 'stacked' === config.layout && ! moving ) {
			return;
		}

		var passLabel = form.querySelector( '.user-pass-wrap > label' ),
			submit = form.querySelector( 'p.submit' ),
			below = document.createElement( 'nav' ),
			aboveButton = null;

		below.className = 'adminstudio-links adminstudio-links--' + config.layout;
		below.setAttribute( 'aria-label', config.label );

		links.forEach( function ( link ) {
			var place = config.places[ link.key ] || 'below';

			if ( 'hidden' === place ) {
				link.el.remove();
				return;
			}

			if ( 'password' === place && passLabel ) {
				var row = document.createElement( 'div' );
				row.className = 'adminstudio-label-row';
				passLabel.parentNode.insertBefore( row, passLabel );
				row.appendChild( passLabel );
				row.appendChild( link.el );
				passLabel = null;
				return;
			}

			if ( 'button' === place && submit ) {
				if ( ! aboveButton ) {
					aboveButton = document.createElement( 'p' );
					aboveButton.className = 'adminstudio-links adminstudio-links--button';
					submit.parentNode.insertBefore( aboveButton, submit );
				}
				aboveButton.appendChild( wrap( link.el ) );
				return;
			}

			// Everything else, including a link meant for the password label on a
			// screen without a password field, goes below the form.
			below.appendChild( wrap( link.el ) );
		} );

		if ( below.children.length ) {
			if ( 'card' === config.layout ) {
				form.appendChild( below );
			} else {
				form.parentNode.insertBefore( below, nav || back || form.nextSibling );
			}
		}

		// The old containers now hold only separators.
		[ nav, back ].forEach( function ( el ) {
			if ( el && ! el.querySelector( 'a, button, form, input' ) ) {
				el.remove();
			}
		} );

		// Links that wrap onto a new line should not start with a separator dot.
		if ( 'inline' === config.layout || 'card' === config.layout ) {
			markLineStarts( below );
			window.addEventListener( 'resize', function () {
				markLineStarts( below );
			} );
		}
	}

	function markLineStarts( container ) {
		var top = null;

		container.querySelectorAll( '.adminstudio-links__item' ).forEach( function ( item ) {
			item.classList.remove( 'is-line-start' );
		} );

		container.querySelectorAll( '.adminstudio-links__item' ).forEach( function ( item ) {
			if ( null !== top && item.offsetTop > top + 2 ) {
				item.classList.add( 'is-line-start' );
			}
			top = item.offsetTop;
		} );
	}

	function wrap( link ) {
		var item = document.createElement( 'span' );
		item.className = 'adminstudio-links__item';
		item.appendChild( link );
		return item;
	}
} )();
