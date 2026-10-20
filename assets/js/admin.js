/**
 * Admin Studio settings page: conditional rows, media picker, tag insertion,
 * confirmations and colour swatches.
 */
( function ( $, data ) {
	'use strict';

	// Rows that only apply when another field has a given value.
	function fieldValue( name ) {
		var $fields = $( '[name="' + name + '"]' );

		if ( $fields.filter( ':checkbox' ).length ) {
			return $fields.filter( ':checkbox' ).is( ':checked' ) ? '1' : '0';
		}

		if ( $fields.filter( ':radio' ).length ) {
			return $fields.filter( ':checked' ).val();
		}

		return $fields.last().val();
	}

	function updateRows() {
		$( '[data-adminstudio-show-if]' ).each( function () {
			var $row = $( this );
			$row.prop( 'hidden', fieldValue( $row.data( 'adminstudio-show-if' ) ) !== String( $row.data( 'adminstudio-show-value' ) ) );
		} );
	}

	$( document ).on( 'change', '.adminstudio-wrap :input, .adminstudio-destination select', updateRows );
	updateRows();

	// Media picker for image URL fields.
	$( document ).on( 'click', '[data-adminstudio-media]', function ( event ) {
		event.preventDefault();

		var $input = $( '#' + $( this ).data( 'adminstudio-media' ) ),
			frame = wp.media( {
				title: data.chooseImage,
				button: { text: data.useImage },
				library: { type: 'image' },
				multiple: false,
			} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			$input.val( attachment.url ).trigger( 'change' );
		} );

		frame.open();
	} );

	// Insert a {tag} into the email body, in either editor mode.
	$( document ).on( 'click', '[data-adminstudio-tag]', function () {
		var tag = $( this ).data( 'adminstudio-tag' ),
			editor = window.tinymce && window.tinymce.get( 'adminstudio_email_body' );

		if ( editor && ! editor.isHidden() ) {
			editor.execCommand( 'mceInsertContent', false, tag );
			editor.focus();
			return;
		}

		var textarea = document.getElementById( 'adminstudio_email_body' );

		if ( ! textarea ) {
			return;
		}

		var start = textarea.selectionStart || 0,
			end = textarea.selectionEnd || 0;

		textarea.value = textarea.value.slice( 0, start ) + tag + textarea.value.slice( end );
		textarea.selectionStart = textarea.selectionEnd = start + tag.length;
		textarea.focus();
	} );

	// Confirm before destructive forms.
	$( document ).on( 'submit', 'form[data-adminstudio-confirm]', function ( event ) {
		if ( ! window.confirm( data.confirm ) ) {
			event.preventDefault();
		}
	} );

	$( document ).on( 'click', '[data-adminstudio-confirm-click]', function ( event ) {
		if ( ! window.confirm( data.confirm ) ) {
			event.preventDefault();
		}
	} );

	// Template cards: mark the chosen one.
	$( document ).on( 'change', '.adminstudio-template input[type="radio"]', function () {
		$( '.adminstudio-template' ).removeClass( 'is-selected' );
		$( this ).closest( '.adminstudio-template' ).addClass( 'is-selected' );
	} );

	// Sortable lists: up and down buttons, plus drag and drop.
	$( document ).on( 'click', '[data-adminstudio-move]', function () {
		var $row = $( this ).closest( '.adminstudio-sortable__row' );

		if ( '-1' === String( $( this ).data( 'adminstudio-move' ) ) ) {
			$row.prev( '.adminstudio-sortable__row' ).before( $row );
		} else {
			$row.next( '.adminstudio-sortable__row' ).after( $row );
		}

		$( this ).trigger( 'focus' );
	} );

	var dragged = null;

	$( document ).on( 'dragstart', '[data-adminstudio-sortable] > .adminstudio-sortable__row', function ( event ) {
		if ( $( event.target ).is( 'input, select, button' ) ) {
			return;
		}

		dragged = this;
		$( this ).addClass( 'is-dragging' );
		event.originalEvent.dataTransfer.effectAllowed = 'move';
		event.originalEvent.dataTransfer.setData( 'text/plain', '' );
	} );

	$( document ).on( 'dragover', '[data-adminstudio-sortable] > .adminstudio-sortable__row', function ( event ) {
		if ( ! dragged || dragged === this || dragged.parentNode !== this.parentNode ) {
			return;
		}

		event.preventDefault();

		var box = this.getBoundingClientRect(),
			after = event.originalEvent.clientY > box.top + box.height / 2;

		this.parentNode.insertBefore( dragged, after ? this.nextSibling : this );
	} );

	$( document ).on( 'dragend drop', '[data-adminstudio-sortable] > .adminstudio-sortable__row', function ( event ) {
		event.preventDefault();
		$( dragged ).removeClass( 'is-dragging' );
		dragged = null;
	} );

	// Selects that reload the page with a new choice.
	$( document ).on( 'change', 'select[data-adminstudio-autosubmit]', function () {
		this.form.submit();
	} );

	// Capability search: hides capabilities and groups that do not match.
	$( document ).on( 'input', '[data-adminstudio-cap-search]', function () {
		var term = this.value.toLowerCase().trim();

		$( '[data-adminstudio-cap-group]' ).each( function () {
			var $group = $( this ),
				visible = 0;

			$group.find( '.adminstudio-cap' ).each( function () {
				var match = ! term || this.textContent.toLowerCase().indexOf( term ) !== -1;
				$( this ).prop( 'hidden', ! match );
				visible += match ? 1 : 0;
			} );

			$group.prop( 'hidden', 0 === visible );
		} );
	} );

	// Select or clear every capability in a group.
	$( document ).on( 'click', '[data-adminstudio-cap-toggle]', function () {
		var $boxes = $( this ).closest( '[data-adminstudio-cap-group]' ).find( 'input[type="checkbox"]:visible' ),
			allOn = $boxes.length === $boxes.filter( ':checked' ).length;

		$boxes.prop( 'checked', ! allOn );
	} );

	// Brand kit: mark the chosen source.
	$( document ).on( 'change', '.adminstudio-brand__option input', function () {
		$( '.adminstudio-brand__option' ).removeClass( 'is-selected' );
		$( this ).closest( '.adminstudio-brand__option' ).addClass( 'is-selected' );
	} );

	// Brand kit: read the colours visitors see on the home page.
	function toHex( value ) {
		var m = /rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?/.exec( value || '' );

		if ( ! m || ( undefined !== m[ 4 ] && parseFloat( m[ 4 ] ) < 0.5 ) ) {
			return '';
		}

		return '#' + [ m[ 1 ], m[ 2 ], m[ 3 ] ].map( function ( n ) {
			return ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	function readColors( doc, win ) {
		var css = function ( el ) {
				return win.getComputedStyle( el );
			},
			visible = function ( el ) {
				var box;
				if ( ! el || el.closest( '#wpadminbar' ) ) {
					return false;
				}
				box = el.getBoundingClientRect();
				return box.width > 20 && box.height > 10;
			},
			first = function ( selector, read, skip ) {
				var list = doc.querySelectorAll( selector ), i, value;
				for ( i = 0; i < list.length; i++ ) {
					if ( visible( list[ i ] ) ) {
						value = read( css( list[ i ] ) );
						if ( value && value !== skip ) {
							return value;
						}
					}
				}
				return '';
			},
			background = toHex( css( doc.body ).backgroundColor ) || toHex( css( doc.documentElement ).backgroundColor ) || '#ffffff',
			text = toHex( css( doc.querySelector( 'main p, article p, p' ) || doc.body ).color ) || toHex( css( doc.body ).color ),
			button = first( '.wp-element-button, .wp-block-button__link, .elementor-button, a.button, button, input[type="submit"], .btn', function ( s ) {
				return toHex( s.backgroundColor );
			}, background ),
			link = first( 'main a, article a, .entry-content a, a', function ( s ) {
				return toHex( s.color );
			}, text ),
			heading = toHex( css( doc.querySelector( 'h1, h2' ) || doc.body ).color ),
			primary = button || link;

		return {
			primary: primary,
			secondary: link && link !== primary ? link : ( heading && heading !== text ? heading : '' ),
			text: text,
			background: background,
		};
	}

	$( document ).on( 'click', '[data-adminstudio-capture]', function () {
		var $button = $( this ),
			$status = $button.siblings( '.adminstudio-brand__status' ),
			settings = data.capture || {},
			frame = document.createElement( 'iframe' ),
			timer;

		function finish( message ) {
			clearTimeout( timer );
			$status.text( message );
			$button.prop( 'disabled', false );
			frame.remove();
		}

		$button.prop( 'disabled', true );
		$status.text( settings.reading );

		frame.className = 'adminstudio-capture-frame';
		frame.setAttribute( 'aria-hidden', 'true' );
		frame.setAttribute( 'tabindex', '-1' );
		frame.src = settings.url;

		timer = setTimeout( function () {
			finish( settings.failed );
		}, 20000 );

		frame.addEventListener( 'load', function () {
			setTimeout( function () {
				var colors;

				try {
					colors = readColors( frame.contentDocument, frame.contentWindow );
				} catch ( e ) {
					finish( settings.failed );
					return;
				}

				$.post( settings.ajax, { action: 'adminstudio_brand_capture', nonce: settings.nonce, colors: colors } ).done( function ( response ) {
					var $row = $( '[data-adminstudio-source="frontend"]' ),
						$swatches = $row.find( '[data-adminstudio-swatches]' ).empty();

					if ( ! response || ! response.success ) {
						finish( settings.failed );
						return;
					}

					$.each( [ 'primary', 'secondary', 'text', 'background' ], function ( i, key ) {
						if ( response.data.colors[ key ] ) {
							$( '<span class="adminstudio-swatch__chip">' ).attr( 'title', key + ' ' + response.data.colors[ key ] ).css( 'background-color', response.data.colors[ key ] ).appendTo( $swatches );
						}
					} );

					$row.find( '[data-adminstudio-source-note]' ).text( settings.note );
					$row.find( 'input' ).prop( 'checked', true ).trigger( 'change' );
					finish( settings.done );
				} ).fail( function () {
					finish( settings.failed );
				} );
			}, 1200 );
		} );

		document.body.appendChild( frame );
	} );

	// Colour swatches on the status table.
	$( '.adminstudio-swatch__chip[data-color]' ).each( function () {
		this.style.backgroundColor = this.getAttribute( 'data-color' );
	} );
} )( window.jQuery, window.adminstudioAdmin || {} );
