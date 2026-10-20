/**
 * Advanced WP two-factor setup on the profile screen.
 */
( function ( $, data ) {
	'use strict';

	var $app = $( '#advwp-2fa-app' );

	if ( ! $app.length || ! data ) {
		return;
	}

	var mode = 'totp',
		$message = $app.find( '.advwp-2fa-message' ),
		codes = [];

	function panel( name, show ) {
		$app.find( '[data-panel="' + name + '"]' ).prop( 'hidden', ! show );
	}

	function say( text ) {
		$message.text( text || '' );
	}

	function post( action, extra ) {
		return wp.ajax.post( 'advwp_2fa_' + action, $.extend( { nonce: data.nonce }, extra || {} ) );
	}

	function busy( $button, on ) {
		$button.prop( 'disabled', on ).attr( 'aria-busy', on ? 'true' : null );
	}

	function showCodes( list ) {
		codes = list || [];
		var $list = $app.find( '.advwp-2fa-codes' ).empty();
		codes.forEach( function ( code ) {
			$( '<li>' ).text( code ).appendTo( $list );
		} );
		panel( 'codes', true );
	}

	function drawQr( uri ) {
		var qr = window.qrcode( 0, 'M' );
		qr.addData( uri );
		qr.make();
		$app.find( '.advwp-2fa-qr' ).html( qr.createSvgTag( { cellSize: 4, margin: 2, scalable: false } ) );
	}

	$app.on( 'click', '[data-advwp-2fa]', function () {
		var $button = $( this ),
			action = $button.data( 'advwp-2fa' );

		say( '' );

		if ( 'setup' === action ) {
			mode = 'totp';
			busy( $button, true );
			post( 'setup' )
				.done( function ( res ) {
					drawQr( res.uri );
					$app.find( '.advwp-2fa-secret' ).text( res.secret );
					panel( 'setup', true );
					panel( 'verify', true );
					$( '#advwp-2fa-code' ).trigger( 'focus' );
				} )
				.fail( function () {
					say( data.i18n.error );
				} )
				.always( function () {
					busy( $button, false );
				} );
		}

		if ( 'email_start' === action ) {
			mode = 'email';
			busy( $button, true );
			post( 'email_start' )
				.done( function () {
					panel( 'setup', false );
					panel( 'verify', true );
					say( data.i18n.sent );
					$( '#advwp-2fa-code' ).trigger( 'focus' );
				} )
				.fail( function () {
					say( data.i18n.error );
				} )
				.always( function () {
					busy( $button, false );
				} );
		}

		if ( 'confirm' === action ) {
			busy( $button, true );
			post( 'totp' === mode ? 'verify' : 'email_verify', { code: $( '#advwp-2fa-code' ).val() } )
				.done( function ( res ) {
					panel( 'setup', false );
					panel( 'verify', false );
					$app.find( '.advwp-2fa-actions' ).prop( 'hidden', true );
					showCodes( res.codes );
					say( data.i18n.enabled );
				} )
				.fail( function () {
					say( data.i18n.invalid );
					$( '#advwp-2fa-code' ).trigger( 'focus' ).trigger( 'select' );
				} )
				.always( function () {
					busy( $button, false );
				} );
		}

		if ( 'backup' === action ) {
			busy( $button, true );
			post( 'backup' )
				.done( function ( res ) {
					showCodes( res.codes );
				} )
				.fail( function () {
					say( data.i18n.error );
				} )
				.always( function () {
					busy( $button, false );
				} );
		}

		if ( 'disable' === action ) {
			if ( ! window.confirm( data.i18n.confirm ) ) {
				return;
			}
			busy( $button, true );
			post( 'disable' )
				.done( function () {
					say( data.i18n.disabled );
					window.location.reload();
				} )
				.fail( function () {
					say( data.i18n.error );
					busy( $button, false );
				} );
		}

		if ( 'copy' === action && navigator.clipboard ) {
			navigator.clipboard.writeText( codes.join( '\n' ) ).then( function () {
				$button.text( data.i18n.copied );
			} );
		}
	} );

	// Submit the code with Enter without submitting the whole profile form.
	$( '#advwp-2fa-code' ).on( 'keydown', function ( event ) {
		if ( 'Enter' === event.key ) {
			event.preventDefault();
			$app.find( '[data-advwp-2fa="confirm"]' ).trigger( 'click' );
		}
	} );
} )( window.jQuery, window.advwp2fa );
