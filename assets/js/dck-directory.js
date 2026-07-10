/* DCK Directory — front-end interactions (vanilla JS, no dependencies). */
(function () {
	'use strict';

	var CFG = window.DCK_DIR || {};

	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', CFG.nonce );
		Object.keys( data ).forEach( function ( k ) { body.append( k, data[ k ] ); } );
		return fetch( CFG.ajax, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( r ) { return r.json(); } );
	}

	/* ---------------- Open / closed status ---------------- */
	function computeStatus( hours ) {
		var DAYS = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
		function toMins( s ) { var p = s.split( ':' ); return ( +p[ 0 ] ) * 60 + ( +p[ 1 ] ); }
		function fmt( m ) { var h = Math.floor( m / 60 ), mm = m % 60, ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ( mm ? ':' + String( mm ).padStart( 2, '0' ) : '' ) + ' ' + ap; }
		var now = new Date(), day = now.getDay(), mins = now.getHours() * 60 + now.getMinutes();
		var today = hours[ day ];
		var open = !! today && mins >= toMins( today[ 0 ] ) && mins < toMins( today[ 1 ] );
		var text;
		if ( open ) {
			text = '<b>Open</b> · Closes ' + fmt( toMins( today[ 1 ] ) );
		} else {
			var next = '';
			for ( var i = 0; i < 7; i++ ) {
				var d = ( day + i ) % 7, hrs = hours[ d ];
				if ( ! hrs ) { continue; }
				if ( i === 0 && mins >= toMins( hrs[ 0 ] ) ) { continue; }
				next = 'Opens ' + fmt( toMins( hrs[ 0 ] ) ) + ( i === 0 ? '' : i === 1 ? ' tomorrow' : ' ' + DAYS[ d ] );
				break;
			}
			text = '<b>Closed</b>' + ( next ? ' · ' + next : '' );
		}
		return { open: open, text: text };
	}

	function initHours() {
		document.querySelectorAll( '[data-dck-hours]' ).forEach( function ( el ) {
			try {
				var s = computeStatus( JSON.parse( el.getAttribute( 'data-dck-hours' ) ) );
				el.innerHTML = s.text;
				el.classList.add( s.open ? 'open' : 'closed' );
			} catch ( e ) {}
		} );
		document.querySelectorAll( '[data-dck-hours-pill]' ).forEach( function ( el ) {
			try {
				var s = computeStatus( JSON.parse( el.getAttribute( 'data-dck-hours-pill' ) ) );
				el.textContent = s.open ? 'Open now' : 'Closed';
				if ( ! s.open ) { el.classList.add( 'closed' ); }
			} catch ( e ) {}
		} );
	}

	/* ---------------- Sticky sidebar (pin by bottom if tall) ---------------- */
	function initSidebar() {
		var side = document.querySelector( '.dck-side' );
		if ( ! side ) { return; }
		function adjust() {
			var overflow = side.offsetHeight + 40 - window.innerHeight;
			side.style.top = overflow > 0 ? ( -overflow + 20 ) + 'px' : '20px';
		}
		window.addEventListener( 'resize', adjust );
		adjust();
	}

	/* ---------------- Lead form ---------------- */
	function initLeads() {
		document.querySelectorAll( '[data-dck-lead]' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var msg = form.querySelector( '.dck-form-msg' );
				var btn = form.querySelector( 'button[type=submit]' );
				var data = {};
				new FormData( form ).forEach( function ( v, k ) { data[ k ] = v; } );
				if ( btn ) { btn.disabled = true; }
				post( 'dck_lead', data ).then( function ( res ) {
					if ( btn ) { btn.disabled = false; }
					if ( ! msg ) { return; }
					if ( res && res.success ) {
						msg.textContent = res.data.message;
						msg.className = 'dck-form-msg ok';
						form.reset();
					} else {
						msg.textContent = ( res && res.data && res.data.message ) || 'Something went wrong.';
						msg.className = 'dck-form-msg err';
					}
				} ).catch( function () {
					if ( btn ) { btn.disabled = false; }
					if ( msg ) { msg.textContent = 'Network error. Please try again.'; msg.className = 'dck-form-msg err'; }
				} );
			} );
		} );
	}

	/* ---------------- Directory search ---------------- */
	function initDirectory() {
		var root = document.querySelector( '[data-dck-directory]' );
		if ( ! root ) { return; }

		var form      = root.querySelector( '[data-dck-search]' );
		var serviceEl = root.querySelector( '[data-search-service]' );
		var areaEl    = root.querySelector( '[data-search-area]' );
		var locEl     = root.querySelector( '[data-search-location]' );
		var kwEl      = root.querySelector( '[data-search-keyword]' );
		var results   = root.querySelector( '[data-results]' );
		var countEl   = root.querySelector( '[data-results-count]' );
		var emptyEl   = root.querySelector( '[data-results-empty]' );
		var loadmore  = root.querySelector( '[data-loadmore]' );
		var paged = 1, maxPages = 1;

		function params( p ) {
			return {
				service: serviceEl ? serviceEl.value : '',
				area: areaEl ? areaEl.value : '',
				location: locEl ? locEl.value : '',
				keyword: kwEl ? kwEl.value : '',
				paged: p
			};
		}

		function run( append ) {
			if ( ! append ) { paged = 1; results.classList.add( 'dck-is-loading' ); }
			post( 'dck_search', params( paged ) ).then( function ( res ) {
				results.classList.remove( 'dck-is-loading' );
				if ( ! res || ! res.success ) { return; }
				if ( append ) { results.insertAdjacentHTML( 'beforeend', res.data.html ); }
				else { results.innerHTML = res.data.html; }
				maxPages = res.data.pages;
				if ( countEl ) { countEl.textContent = res.data.found + ( res.data.found === 1 ? ' contractor' : ' contractors' ); }
				if ( emptyEl ) { emptyEl.hidden = res.data.found !== 0; }
				if ( loadmore ) { loadmore.hidden = paged >= maxPages; }
				initHours();
			} );
		}

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) { e.preventDefault(); run( false ); } );
		}
		[ serviceEl, areaEl, locEl ].forEach( function ( el ) { if ( el ) { el.addEventListener( 'change', function () { run( false ); } ); } } );

		root.querySelectorAll( '[data-service]' ).forEach( function ( tile ) {
			tile.addEventListener( 'click', function () {
				root.querySelectorAll( '.dck-tile' ).forEach( function ( t ) { t.classList.remove( 'active' ); } );
				tile.classList.add( 'active' );
				if ( serviceEl ) { serviceEl.value = tile.getAttribute( 'data-service' ); }
				run( false );
				var rw = root.querySelector( '.dck-results-wrap' );
				if ( rw ) { rw.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			} );
		} );

		if ( loadmore ) {
			loadmore.addEventListener( 'click', function () { if ( paged < maxPages ) { paged++; run( true ); } } );
		}

		run( false ); // initial load
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initHours();
		initSidebar();
		initLeads();
		initDirectory();
	} );
})();
