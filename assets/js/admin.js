( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var chip = event.target.closest( '[data-agentready-add]' );
		var copy = event.target.closest( '[data-agentready-copy]' );

		if ( chip ) {
			var field = document.getElementById( chip.getAttribute( 'data-target' ) );
			var value = chip.getAttribute( 'data-agentready-add' );
			var separator = chip.getAttribute( 'data-separator' ) === 'line' ? '\n' : ', ';
			var pattern = separator === '\n' ? /\n/ : /\s*,\s*/;

			if ( ! field ) {
				return;
			}

			var current = field.value.trim();

			if ( current && current.split( pattern ).indexOf( value ) !== -1 ) {
				return;
			}

			field.value = current ? current + separator + value : value;
			field.focus();
		}

		if ( copy ) {
			var source = document.getElementById( copy.getAttribute( 'data-agentready-copy' ) );

			if ( source && navigator.clipboard ) {
				navigator.clipboard.writeText( source.value ).then( function () {
					var label = copy.textContent;
					copy.textContent = copy.getAttribute( 'data-copied' ) || label;
					window.setTimeout( function () {
						copy.textContent = label;
					}, 1500 );
				} );
			}
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var toggle = document.getElementById( 'agentready-http-auth' );
		var fields = document.getElementById( 'agentready-http-auth-fields' );

		if ( ! toggle || ! fields ) {
			return;
		}

		var update = function () {
			fields.style.display = toggle.checked ? '' : 'none';
		};

		toggle.addEventListener( 'change', update );
		update();
	} );
}() );
