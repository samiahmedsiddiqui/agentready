( function () {
	'use strict';

	document.addEventListener( 'click', function ( event ) {
		var chip = event.target.closest( '[data-agentwelcome-add]' );
		var copy = event.target.closest( '[data-agentwelcome-copy]' );
		var confirmButton = event.target.closest( '[data-agentwelcome-confirm]' );

		if ( confirmButton && ! window.confirm( confirmButton.getAttribute( 'data-agentwelcome-confirm' ) ) ) {
			event.preventDefault();
		}

		if ( chip ) {
			var field = document.getElementById( chip.getAttribute( 'data-target' ) );
			var value = chip.getAttribute( 'data-agentwelcome-add' );
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
			var source = document.getElementById( copy.getAttribute( 'data-agentwelcome-copy' ) );

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

	function initTerms( root ) {
		var chosen = root.querySelector( '[data-agentwelcome-chosen]' );
		var search = root.querySelector( '[data-agentwelcome-term-search]' );

		function renderChips() {
			var checked = root.querySelectorAll( 'input[type="checkbox"]:checked' );

			chosen.textContent = '';
			chosen.hidden = checked.length === 0;

			if ( ! checked.length ) {
				return;
			}

			var label = document.createElement( 'span' );
			label.className = 'agentwelcome-chips-label';
			label.textContent = chosen.getAttribute( 'data-label' ) || '';
			chosen.appendChild( label );

			checked.forEach( function ( box ) {
				var chip = document.createElement( 'button' );
				var name = box.getAttribute( 'data-name' ) + ' (' + box.getAttribute( 'data-group' ) + ')';

				chip.type = 'button';
				chip.className = 'agentwelcome-chip';
				chip.setAttribute( 'data-term', box.value );
				chip.setAttribute( 'aria-label', name );
				chip.textContent = name + ' ×';
				chosen.appendChild( chip );
			} );
		}

		root.addEventListener( 'change', renderChips );

		root.addEventListener( 'click', function ( event ) {
			var chip = event.target.closest( '.agentwelcome-chip' );

			if ( ! chip ) {
				return;
			}

			var box = root.querySelector( 'input[type="checkbox"][value="' + chip.getAttribute( 'data-term' ) + '"]' );

			if ( box ) {
				box.checked = false;
				renderChips();
			}
		} );

		search.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
			}
		} );

		search.addEventListener( 'input', function () {
			var query = search.value.trim().toLowerCase();

			root.querySelectorAll( '.agentwelcome-term-group' ).forEach( function ( group ) {
				var visible = 0;

				group.querySelectorAll( 'li' ).forEach( function ( item ) {
					var show = ! query || item.getAttribute( 'data-name' ).indexOf( query ) !== -1;

					item.hidden = ! show;
					visible += show ? 1 : 0;
				} );

				group.hidden = visible === 0;

				if ( query && visible ) {
					group.open = true;
				}
			} );
		} );

		renderChips();
	}

	function initEditor( editor ) {
		var area = editor.querySelector( 'textarea' );
		var custom = editor.querySelector( '[data-agentwelcome-custom]' );
		var load = editor.querySelector( '[data-agentwelcome-load]' );
		var file = editor.querySelector( '[data-agentwelcome-file]' );
		var count = editor.querySelector( '[data-agentwelcome-count]' );
		var encoder = window.TextEncoder ? new window.TextEncoder() : null;

		function update() {
			var used = encoder ? encoder.encode( area.value ).length : area.value.length;

			count.textContent = count.getAttribute( 'data-template' )
				.replace( '{used}', used.toLocaleString() )
				.replace( '{max}', Number( count.getAttribute( 'data-max' ) ).toLocaleString() );
		}

		editor.addEventListener( 'change', function ( event ) {
			if ( event.target.hasAttribute( 'data-agentwelcome-mode' ) ) {
				custom.hidden = event.target.value === 'automatic';
			}
		} );

		load.addEventListener( 'click', function () {
			file.click();
		} );

		file.addEventListener( 'change', function () {
			var chosen = file.files[ 0 ];
			var reader;

			if ( ! chosen ) {
				return;
			}

			if ( chosen.size > Number( load.getAttribute( 'data-max' ) ) ) {
				window.alert( load.getAttribute( 'data-too-big' ) );
				file.value = '';
				return;
			}

			reader = new window.FileReader();
			reader.onload = function () {
				area.value = String( reader.result );
				file.value = '';
				update();
			};
			reader.readAsText( chosen );
		} );

		area.addEventListener( 'input', update );
		update();
	}

	function initLlms( form ) {
		Array.prototype.forEach.call( form.querySelectorAll( '[data-agentwelcome-editor]' ), initEditor );
	}

	function initTabs( form ) {
		var panels = form.querySelectorAll( '[data-agentwelcome-panel]' );
		var tabs = document.querySelectorAll( '[data-agentwelcome-tab]' );
		var referer = form.querySelector( 'input[name="_wp_http_referer"]' );

		function show() {
			var slug = window.location.hash.replace( '#', '' );

			if ( ! form.querySelector( '[data-agentwelcome-panel="' + slug + '"]' ) ) {
				slug = 'content';
			}

			form.classList.add( 'is-tabbed' );

			panels.forEach( function ( panel ) {
				panel.classList.toggle( 'is-active', panel.getAttribute( 'data-agentwelcome-panel' ) === slug );
			} );

			tabs.forEach( function ( tab ) {
				tab.classList.toggle( 'nav-tab-active', tab.getAttribute( 'data-agentwelcome-tab' ) === slug );
			} );

			if ( referer ) {
				referer.value = referer.value.split( '#' )[ 0 ] + '#' + slug;
			}
		}

		window.addEventListener( 'hashchange', show );
		show();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var tabbed = document.querySelector( '[data-agentwelcome-tabs]' );
		var toggle = document.getElementById( 'agentwelcome-http-auth' );
		var fields = document.getElementById( 'agentwelcome-http-auth-fields' );
		var terms = document.querySelector( '[data-agentwelcome-terms]' );
		var llms = document.querySelector( '.agentwelcome-llms' );

		if ( llms ) {
			initLlms( llms );
		}

		if ( tabbed ) {
			initTabs( tabbed );
		}

		if ( terms ) {
			initTerms( terms );
		}

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
