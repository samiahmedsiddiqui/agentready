( function () {
	'use strict';

	var SAFE_LINK = /^(https?:\/\/|\/|#|mailto:)/i;
	var INLINE = /(`+)([\s\S]+?)\1|\*\*([\s\S]+?)\*\*|\*([^*\s][^*]*?)\*|!\[([^\]]*)\]\(([^)\s]*)[^)]*\)|\[([^\]]*)\]\(([^)\s]*)[^)]*\)|\\([\\`*_{}\[\]()#+\-.!|>~])/g;
	var FENCE = /^\s*(`{3,}|~{3,})\s*([\w+-]*)\s*$/;
	var RULE = /^\s*([-*_])(\s*\1){2,}\s*$/;
	var ITEM = /^(\s*)([-*+]|\d+[.)])\s+(.*)$/;
	var DIVIDER = /^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)*\|?\s*$/;

	function el( tag, parent, text ) {
		var node = document.createElement( tag );

		if ( text ) {
			node.textContent = text;
		}

		if ( parent ) {
			parent.appendChild( node );
		}

		return node;
	}

	function inline( parent, text ) {
		var pattern = new RegExp( INLINE.source, 'g' );
		var match;
		var last = 0;
		var node;

		while ( ( match = pattern.exec( text ) ) !== null ) {
			if ( match.index > last ) {
				parent.appendChild( document.createTextNode( text.slice( last, match.index ) ) );
			}

			if ( match[ 1 ] ) {
				el( 'code', parent, match[ 2 ] );
			} else if ( match[ 3 ] !== undefined ) {
				inline( el( 'strong', parent ), match[ 3 ] );
			} else if ( match[ 4 ] !== undefined ) {
				inline( el( 'em', parent ), match[ 4 ] );
			} else if ( match[ 6 ] !== undefined ) {
				node = el( 'span', parent, '[image: ' + match[ 5 ] + ']' );
				node.className = 'agentready-img';
			} else if ( match[ 8 ] !== undefined ) {
				node = el( 'a', parent );
				inline( node, match[ 7 ] );

				if ( SAFE_LINK.test( match[ 8 ] ) ) {
					node.setAttribute( 'href', match[ 8 ] );
					node.setAttribute( 'target', '_blank' );
					node.setAttribute( 'rel', 'noopener noreferrer' );
				}
			} else if ( match[ 9 ] ) {
				parent.appendChild( document.createTextNode( match[ 9 ] ) );
			}

			last = pattern.lastIndex;
		}

		if ( last < text.length ) {
			parent.appendChild( document.createTextNode( text.slice( last ) ) );
		}
	}

	function listItem( line ) {
		var match = ITEM.exec( line );

		return match ? { indent: match[ 1 ].replace( /\t/g, '    ' ).length, ordered: /\d/.test( match[ 2 ] ), text: match[ 3 ] } : null;
	}

	function blockStart( line ) {
		return FENCE.test( line ) || /^#{1,6}\s/.test( line ) || RULE.test( line ) || /^\s*>/.test( line ) || ITEM.test( line );
	}

	function cells( line ) {
		return line.trim().replace( /^\|/, '' ).replace( /\|$/, '' ).split( /(?<!\\)\|/ ).map( function ( cell ) {
			return cell.trim().replace( /\\\|/g, '|' );
		} );
	}

	function list( lines, i, parent ) {
		var first = listItem( lines[ i ] );
		var root = el( first.ordered ? 'ol' : 'ul', parent );
		var current = null;
		var item;

		while ( i < lines.length ) {
			item = listItem( lines[ i ] );

			if ( item && item.indent === first.indent ) {
				current = el( 'li', root );
				inline( current, item.text );
				i++;
			} else if ( item && item.indent > first.indent && current ) {
				i = list( lines, i, current );
			} else if ( ! item && current && /^\s+\S/.test( lines[ i ] ) ) {
				current.appendChild( document.createTextNode( ' ' ) );
				inline( current, lines[ i ].trim() );
				i++;
			} else {
				break;
			}
		}

		return i;
	}

	function table( lines, i, parent ) {
		var root = el( 'table', parent );
		var head = el( 'tr', el( 'thead', root ) );
		var body = el( 'tbody', root );
		var row;

		cells( lines[ i ] ).forEach( function ( text ) {
			inline( el( 'th', head ), text );
		} );

		i += 2;

		while ( i < lines.length && lines[ i ].indexOf( '|' ) !== -1 && ! /^\s*$/.test( lines[ i ] ) ) {
			row = el( 'tr', body );
			cells( lines[ i ] ).forEach( function ( text ) {
				inline( el( 'td', row ), text );
			} );
			i++;
		}

		return i;
	}

	function blocks( lines, parent ) {
		var i = 0;
		var line;
		var match;
		var buffer;
		var inner;

		while ( i < lines.length ) {
			line = lines[ i ];

			if ( /^\s*$/.test( line ) ) {
				i++;
			} else if ( ( match = FENCE.exec( line ) ) !== null ) {
				buffer = [];
				i++;

				while ( i < lines.length && ! new RegExp( '^\\s*' + match[ 1 ].charAt( 0 ) + '{' + match[ 1 ].length + ',}\\s*$' ).test( lines[ i ] ) ) {
					buffer.push( lines[ i ] );
					i++;
				}

				i++;
				el( 'code', el( 'pre', parent ), buffer.join( '\n' ) );
			} else if ( ( match = /^(#{1,6})\s+(.*?)\s*#*\s*$/.exec( line ) ) !== null ) {
				inline( el( 'h' + match[ 1 ].length, parent ), match[ 2 ] );
				i++;
			} else if ( RULE.test( line ) ) {
				el( 'hr', parent );
				i++;
			} else if ( /^\s*>/.test( line ) ) {
				inner = [];

				while ( i < lines.length && /^\s*>/.test( lines[ i ] ) ) {
					inner.push( lines[ i ].replace( /^\s*>\s?/, '' ) );
					i++;
				}

				blocks( inner, el( 'blockquote', parent ) );
			} else if ( line.indexOf( '|' ) !== -1 && i + 1 < lines.length && DIVIDER.test( lines[ i + 1 ] ) ) {
				i = table( lines, i, parent );
			} else if ( ITEM.test( line ) ) {
				i = list( lines, i, parent );
			} else {
				buffer = [];

				while ( i < lines.length && ! /^\s*$/.test( lines[ i ] ) && ( buffer.length === 0 || ! blockStart( lines[ i ] ) ) ) {
					buffer.push( lines[ i ].trim() );
					i++;
				}

				inline( el( 'p', parent ), buffer.join( ' ' ) );
			}
		}
	}

	function render( markdown, root ) {
		var lines = markdown.replace( /\r\n?/g, '\n' ).split( '\n' );
		var end = -1;
		var details;
		var k;

		root.textContent = '';

		if ( lines[ 0 ] === '---' ) {
			for ( k = 1; k < lines.length; k++ ) {
				if ( lines[ k ] === '---' ) {
					end = k;
					break;
				}
			}
		}

		if ( end > 0 ) {
			details = el( 'details', root );
			el( 'summary', details, 'Metadata' );
			el( 'pre', details, lines.slice( 1, end ).join( '\n' ) );
			lines = lines.slice( end + 1 );
		}

		blocks( lines, root );
	}

	function initPanes( root ) {
		var tabs = root.querySelectorAll( '[data-agentready-pane-tab]' );
		var panes = root.querySelectorAll( '[data-agentready-pane]' );

		function show( name ) {
			panes.forEach( function ( pane ) {
				pane.hidden = pane.getAttribute( 'data-agentready-pane' ) !== name;
			} );

			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-agentready-pane-tab' ) === name;

				tab.classList.toggle( 'button-primary', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
			} );
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				show( tab.getAttribute( 'data-agentready-pane-tab' ) );
			} );
		} );

		show( 'preview' );
	}

	function initScan( root ) {
		var path = root.getAttribute( 'data-path' );

		if ( root.getAttribute( 'data-running' ) !== '1' || ! window.wp || ! window.wp.apiFetch ) {
			return;
		}

		function count( name, value ) {
			var node = root.querySelector( '[data-agentready-count="' + name + '"]' );

			if ( node ) {
				node.textContent = node.textContent.replace( /[\d., ]+/, String( value ) );
			}
		}

		var timer = window.setInterval( function () {
			window.wp.apiFetch( { path: path } ).then( function ( status ) {
				count( 'checked', status.checked );
				count( 'in_sync', status.in_sync );
				count( 'differs', status.differs );

				if ( ! status.running ) {
					window.clearInterval( timer );
					window.location.reload();
				}
			} ).catch( function () {} );
		}, 5000 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var panes = document.querySelector( '[data-agentready-panes]' );
		var source = document.getElementById( 'agentready-markdown' );
		var target = document.getElementById( 'agentready-rendered' );
		var scan = document.querySelector( '[data-agentready-scan]' );

		if ( source && target ) {
			render( source.value, target );
		}

		if ( panes ) {
			initPanes( panes );
		}

		if ( scan ) {
			initScan( scan );
		}
	} );

	window.agentreadyRenderMarkdown = render;
}() );
