/**
 * Admin Studio Customizer controls: preview routing and contrast warnings.
 */
( function ( api, $, data ) {
	'use strict';

	if ( ! api || ! data ) {
		return;
	}

	function hexToRgb( hex ) {
		var m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec( hex || '' );
		return m ? [ parseInt( m[ 1 ], 16 ), parseInt( m[ 2 ], 16 ), parseInt( m[ 3 ], 16 ) ] : null;
	}

	function luminance( hex ) {
		var rgb = hexToRgb( hex );
		if ( ! rgb ) {
			return null;
		}
		var c = rgb.map( function ( v ) {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * c[ 0 ] + 0.7152 * c[ 1 ] + 0.0722 * c[ 2 ];
	}

	function contrast( a, b ) {
		var la = luminance( a ),
			lb = luminance( b );
		if ( null === la || null === lb ) {
			return null;
		}
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	function readableOn( bg ) {
		return ( contrast( bg, '#ffffff' ) || 0 ) >= ( contrast( bg, '#111111' ) || 0 ) ? '#ffffff' : '#111111';
	}

	// Resolves a setting value, falling back to another setting, then a fixed value.
	function resolve( id, fallback ) {
		var own = api( id ) ? api( id ).get() : '';
		if ( own ) {
			return own;
		}
		if ( fallback[ 0 ] && api( fallback[ 0 ] ) && api( fallback[ 0 ] ).get() ) {
			return api( fallback[ 0 ] ).get();
		}
		return fallback[ 1 ];
	}

	function checkPair( pair ) {
		var control = api.control( pair.control );
		if ( ! control ) {
			return;
		}

		var bg = resolve( pair.bg, pair.bgFall ),
			fg = resolve( pair.fg, pair.fgFall );

		if ( 'auto' === fg ) {
			fg = readableOn( bg );
		}

		var ratio = contrast( fg, bg ),
			code = 'adminstudio_contrast';

		control.notifications.remove( code );

		if ( null !== ratio && ratio < 4.5 ) {
			control.notifications.add(
				new api.Notification( code, {
					type: 'warning',
					message: data.i18n.lowContrast.replace( '%s', ratio.toFixed( 1 ) ),
				} )
			);
		}
	}

	api.bind( 'ready', function () {
		// Open the matching preview page when a section expands.
		Object.keys( data.previews ).forEach( function ( sectionId ) {
			api.section( sectionId, function ( section ) {
				section.expanded.bind( function ( expanded ) {
					if ( expanded ) {
						api.previewer.previewUrl.set( data.previews[ sectionId ] );
					}
				} );
			} );
		} );

		$( document ).on( 'change', '[data-adminstudio-picker]', function () {
			api.previewer.previewUrl.set( this.value );
		} );

		// Contrast warnings, re-checked whenever a related colour changes.
		data.contrast.forEach( function ( pair ) {
			var ids = [ pair.fg, pair.bg, pair.fgFall[ 0 ], pair.bgFall[ 0 ] ].filter( Boolean );

			ids.forEach( function ( id ) {
				api( id, function ( setting ) {
					setting.bind( function () {
						checkPair( pair );
					} );
				} );
			} );

			api.control( pair.control, function () {
				checkPair( pair );
			} );
		} );
	} );
} )( window.wp && window.wp.customize, window.jQuery, window.adminstudioCustomizer );
