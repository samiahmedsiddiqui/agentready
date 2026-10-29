( function () {
	'use strict';

	function render( box, status ) {
		var line = box.querySelector( '.agentready-line' );

		line.setAttribute( 'data-agentready-state', status.state );
		box.querySelector( '[data-agentready-label]' ).textContent = status.label;
		box.querySelector( '[data-agentready-detail]' ).textContent = status.detail;
		box.querySelector( '[data-agentready-action="regenerate"]' ).disabled = ! status.can_regenerate;
		box.querySelector( '[data-agentready-action="clear"]' ).disabled = ! status.can_clear;
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-agentready-action]' );
		var box = button ? button.closest( '[data-agentready-box]' ) : null;

		if ( ! box || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		var path = box.getAttribute( 'data-path' );
		var label = box.querySelector( '[data-agentready-label]' );
		var previous = label.textContent;
		var buttons = box.querySelectorAll( '[data-agentready-action]' );
		var wasDisabled = Array.prototype.map.call( buttons, function ( item ) {
			return item.disabled;
		} );
		var request = button.getAttribute( 'data-agentready-action' ) === 'regenerate'
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
