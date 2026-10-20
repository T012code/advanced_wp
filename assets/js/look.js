/**
 * Admin Studio templates: section headings, icons, the sidebar footer, the
 * colour mode switch and the dashboard greeting.
 */
( function ( data ) {
	'use strict';

	if ( ! data ) {
		return;
	}

	var body = document.body,
		menu = document.getElementById( 'adminmenu' ),
		modes = [ 'light', 'dark', 'system' ];

	function el( tag, className, text ) {
		var node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}

	// Section headings go before the first item of each section.
	function addHeadings() {
		( data.sections || [] ).forEach( function ( section ) {
			var first = null;

			section.items.some( function ( id ) {
				first = document.getElementById( id );
				return !! first;
			} );

			if ( ! first || ! first.parentNode ) {
				return;
			}

			var heading = el( 'li', 'adminstudio-menu-heading', section.label );
			heading.setAttribute( 'aria-hidden', 'true' );
			first.parentNode.insertBefore( heading, first );
		} );
	}

	// Outline icons replace the menu's dashicons. The markup is built by the
	// plugin on the server from its own icon set.
	function addIcons() {
		Object.keys( data.icons || {} ).forEach( function ( id ) {
			var item = document.getElementById( id ),
				image = item ? item.querySelector( '.wp-menu-image' ) : null;

			if ( ! image ) {
				return;
			}

			image.className = image.className.replace( /\bdashicons-[\w-]+/g, '' ).replace( /\bsvg\b/, '' ) + ' adminstudio-icon';
			image.removeAttribute( 'style' );
			image.innerHTML = data.icons[ id ];
		} );
	}

	function setMode( mode ) {
		modes.forEach( function ( name ) {
			body.classList.remove( 'adminstudio-mode-' + name );
		} );
		body.classList.add( 'adminstudio-mode-' + mode );
		body.classList.toggle( 'adminstudio-dark', 'dark' === mode );
		data.mode = mode;
	}

	function updateModeButton( button ) {
		var label = data.i18n.modes[ data.mode ];

		button.innerHTML = data.foot.iconMode[ data.mode ];
		button.appendChild( el( 'span', '', label ) );
		button.setAttribute( 'aria-label', data.i18n.modeLabel.replace( '%s', label ) );
	}

	function saveMode( mode ) {
		var form = new window.FormData();

		form.append( 'action', 'adminstudio_look_mode' );
		form.append( 'nonce', data.nonce );
		form.append( 'mode', mode );

		if ( window.fetch ) {
			window.fetch( data.ajax, { method: 'POST', body: form, credentials: 'same-origin' } );
		}
	}

	function link( className, href, icon, label ) {
		var a = el( 'a', className );

		a.href = href;
		a.innerHTML = icon;
		a.appendChild( el( 'span', '', label ) );

		return a;
	}

	function addFooter() {
		var wrap = document.getElementById( 'adminmenuwrap' ),
			foot = data.foot;

		if ( ! wrap || ! foot ) {
			return;
		}

		var box = el( 'div', 'adminstudio-foot' ),
			user = el( 'a', 'adminstudio-foot__user' ),
			names = el( 'span' ),
			mode = el( 'button', 'adminstudio-foot__mode' );

		user.href = foot.profile;

		if ( foot.avatar ) {
			var img = el( 'img' );
			img.src = foot.avatar;
			img.alt = '';
			img.width = 32;
			img.height = 32;
			user.appendChild( img );
		}

		names.appendChild( el( 'strong', '', foot.name ) );
		names.appendChild( el( 'small', '', foot.email ) );
		user.appendChild( names );

		mode.type = 'button';
		updateModeButton( mode );
		mode.addEventListener( 'click', function () {
			var next = modes[ ( modes.indexOf( data.mode ) + 1 ) % modes.length ];

			setMode( next );
			updateModeButton( mode );
			saveMode( next );
		} );

		box.appendChild( user );
		box.appendChild( link( 'adminstudio-foot__link', foot.site, foot.iconSite, data.i18n.viewSite ) );
		box.appendChild( mode );
		box.appendChild( link( 'adminstudio-foot__link', foot.logout, foot.iconOut, data.i18n.signOut ) );

		wrap.appendChild( box );
	}

	// The dashboard home gets a greeting in place of the plain heading.
	function addGreeting() {
		if ( ! body.classList.contains( 'index-php' ) || ! data.greeting ) {
			return;
		}

		var heading = document.querySelector( '#wpbody-content > .wrap > h1' );

		if ( ! heading ) {
			return;
		}

		var box = el( 'div', 'adminstudio-greeting' );

		box.appendChild( el( 'h1', '', data.greeting.title ) );
		box.appendChild( el( 'p', '', data.greeting.date ) );
		heading.parentNode.replaceChild( box, heading );
	}

	function run() {
		if ( menu ) {
			addHeadings();
			addIcons();
		}

		addFooter();
		addGreeting();
		body.classList.add( 'adminstudio-ready' );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )( window.adminstudioLook );
