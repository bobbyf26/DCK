/* DCK Directory — admin: media gallery picker + repeatable rows. */
(function () {
	'use strict';

	/* ---- Gallery via WP media frame ---- */
	document.querySelectorAll( '[data-dck-gallery]' ).forEach( function ( wrap ) {
		var input   = wrap.querySelector( '[data-gallery-input]' );
		var preview = wrap.querySelector( '[data-gallery-preview]' );
		var addBtn  = wrap.querySelector( '[data-gallery-add]' );

		function ids() { return input.value ? input.value.split( ',' ).filter( Boolean ) : []; }
		function setIds( arr ) { input.value = arr.join( ',' ); }

		function addThumb( id, url ) {
			var span = document.createElement( 'span' );
			span.setAttribute( 'data-id', id );
			span.innerHTML = '<img src="' + url + '" alt=""><button type="button" class="dck-remove" aria-label="Remove">&times;</button>';
			preview.appendChild( span );
		}

		if ( addBtn && window.wp && window.wp.media ) {
			addBtn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var frame = window.wp.media( { title: 'Select photos', multiple: true, library: { type: 'image' } } );
				frame.on( 'select', function () {
					var sel = frame.state().get( 'selection' ).toJSON();
					var cur = ids();
					sel.forEach( function ( a ) {
						if ( cur.indexOf( String( a.id ) ) === -1 ) {
							cur.push( String( a.id ) );
							var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
							addThumb( a.id, url );
						}
					} );
					setIds( cur );
				} );
				frame.open();
			} );
		}

		preview.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'dck-remove' ) ) {
				var span = e.target.closest( '[data-id]' );
				var id = span.getAttribute( 'data-id' );
				setIds( ids().filter( function ( x ) { return x !== id; } ) );
				span.remove();
			}
		} );
	} );

	/* ---- Repeatable rows (FAQ, reviews) ---- */
	document.querySelectorAll( '[data-repeater]' ).forEach( function ( rep ) {
		var items = rep.querySelector( '[data-items]' );
		var tpl   = rep.querySelector( '[data-template]' );
		var add   = rep.querySelector( '[data-add]' );
		var i     = items.querySelectorAll( '.dck-repeater-item' ).length;

		if ( add && tpl ) {
			add.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var html = tpl.innerHTML.replace( /__i__/g, 'new' + i );
				var div = document.createElement( 'div' );
				div.innerHTML = html;
				items.appendChild( div.firstElementChild );
				i++;
			} );
		}
		items.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'dck-remove-row' ) ) {
				e.preventDefault();
				e.target.closest( '.dck-repeater-item' ).remove();
			}
		} );
	} );
})();
