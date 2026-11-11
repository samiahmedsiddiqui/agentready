( function () {
	'use strict';

	function render( box, status ) {
		var line = box.querySelector( '.agentwelcome-line' );

		line.setAttribute( 'data-agentwelcome-state', status.state );
		box.querySelector( '[data-agentwelcome-label]' ).textContent = status.label;
		box.querySelector( '[data-agentwelcome-detail]' ).textContent = status.detail;
		box.querySelector( '[data-agentwelcome-action="regenerate"]' ).disabled = ! status.can_regenerate;
		box.querySelector( '[data-agentwelcome-action="clear"]' ).disabled = ! status.can_clear;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-agentwelcome-action]' );
		var box = button ? button.closest( '[data-agentwelcome-box]' ) : null;

		if ( ! box || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		var path = box.getAttribute( 'data-path' );
		var label = box.querySelector( '[data-agentwelcome-label]' );
		var previous = label.textContent;
		var buttons = box.querySelectorAll( '[data-agentwelcome-action]' );
		var wasDisabled = Array.prototype.map.call( buttons, function ( item ) {
			return item.disabled;
		} );
		var request = button.getAttribute( 'data-agentwelcome-action' ) === 'regenerate'
			? { path: path + '/regenerate', method: 'POST' }
			: { path: path + '/cache', method: 'DELETE' };

		buttons.forEach( function ( item ) {
			item.disabled = true;
		} );
		label.textContent = box.getAttribute( 'data-working' );

		window.wp.apiFetch( request ).then( function ( status ) {
			render( box, status );
		} ).catch( function ( error ) {
			label.textContent = error && error.message ? error.message : box.getAttribute( 'data-failed' );
			buttons.forEach( function ( item, index ) {
				item.disabled = wasDisabled[ index ];
			} );
			window.setTimeout( function () {
				label.textContent = previous;
			}, 4000 );
		} );
	} );
}() );
