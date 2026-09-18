( function ( $, config ) {
	'use strict';

	function updateVisibleAltText( attachmentId, altText, $button ) {
		var fieldName = 'attachments[' + attachmentId + '][image_alt]';
		$( 'input, textarea' ).filter( function () {
			return this.name === fieldName;
		} ).val( altText ).trigger( 'change' );

		$button.closest( '.attachment-details, .compat-item, form' )
			.find( '.setting[data-setting="alt"] input, .setting[data-setting="alt"] textarea' )
			.val( altText )
			.trigger( 'change' );

		if ( window.wp && wp.media && wp.media.attachment ) {
			wp.media.attachment( attachmentId ).set( 'alt', altText );
		}
	}

	$( document ).on( 'click', '.wki-update-alt-text', function () {
		var $button = $( this );
		var $status = $button.siblings( '.wki-alt-text-status' );
		var attachmentId = parseInt( $button.data( 'attachment-id' ), 10 );

		$button.prop( 'disabled', true );
		$status.text( ' ' + config.working );

		$.post( config.ajaxUrl, {
			action: 'wki_update_alt_text',
			nonce: config.nonce,
			attachment_id: attachmentId
		} ).done( function ( response ) {
			if ( ! response || ! response.success ) {
				$status.text( ' ' + ( response && response.data && response.data.message ? response.data.message : config.error ) );
				return;
			}
			updateVisibleAltText( attachmentId, response.data.altText, $button );
			$status.text( ' ' + response.data.message );
		} ).fail( function ( xhr ) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
			$status.text( ' ' + ( message || config.error ) );
		} ).always( function () {
			$button.prop( 'disabled', false );
		} );
	} );
}( jQuery, window.wkiAltText || {} ) );
