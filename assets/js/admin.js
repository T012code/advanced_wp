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

		return $fields.last().val();
	}

	function updateRows() {
		$( '[data-adminstudio-show-if]' ).each( function () {
			var $row = $( this );
			$row.prop( 'hidden', fieldValue( $row.data( 'adminstudio-show-if' ) ) !== String( $row.data( 'adminstudio-show-value' ) ) );
		} );
	}

	$( document ).on( 'change', '.adminstudio-wrap :input', updateRows );
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

	// Colour swatches on the status table.
	$( '.adminstudio-swatch__chip[data-color]' ).each( function () {
		this.style.backgroundColor = this.getAttribute( 'data-color' );
	} );
} )( window.jQuery, window.adminstudioAdmin || {} );
