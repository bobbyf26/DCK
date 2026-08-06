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

	/* ---------------- Directory search + map ---------------- */
	function initDirectory() {
		var root = document.querySelector( '[data-dck-directory]' );
		if ( ! root ) { return; }

		var form     = root.querySelector( '[data-dck-search]' );
		var locEl    = root.querySelector( '[data-search-location]' );
		var kwEl      = root.querySelector( '[data-search-keyword]' );
		var results  = root.querySelector( '[data-results]' );
		var countEl  = root.querySelector( '[data-results-count]' );
		var emptyEl  = root.querySelector( '[data-results-empty]' );
		var loadmore = root.querySelector( '[data-loadmore]' );
		var mapEl    = root.querySelector( '[data-dck-map]' );
		var splitview = root.querySelector( '[data-splitview]' );
		var typeahead = root.querySelector( '[data-typeahead]' );
		var paged = 1, maxPages = 1;

		function escapeHtml( s ) {
			return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
			} );
		}
		function checkedValues( sel ) {
			return Array.prototype.map.call( root.querySelectorAll( sel + ':checked' ), function ( c ) { return c.value; } );
		}

		function params( p ) {
			// A picked location suggestion filters by term slug; otherwise the
			// Where box is a free-text keyword. The State select is the fallback.
			var pick = ( kwEl && kwEl.dataset.locSlug ) ? kwEl.dataset.locSlug : '';
			var d = {
				location: pick || ( locEl ? locEl.value : '' ),
				keyword: pick ? '' : ( kwEl ? kwEl.value : '' ),
				paged: p
			};
			checkedValues( '[data-filter-service]' ).forEach( function ( v, i ) { d[ 'services[' + i + ']' ] = v; } );
			checkedValues( '[data-filter-area]' ).forEach( function ( v, i ) { d[ 'areas[' + i + ']' ] = v; } );
			return d;
		}

		/* --- Leaflet map --- */
		var map = null, markerLayer = null, pending = null, waited = 0;
		function ensureMap() {
			if ( map ) { return map; }
			if ( ! mapEl || typeof window.L === 'undefined' ) { return null; }
			map = window.L.map( mapEl, { scrollWheelZoom: false } ).setView( [ 39.5, -98.35 ], 4 );
			window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' } ).addTo( map );
			markerLayer = window.L.layerGroup().addTo( map );
			return map;
		}
		function renderMarkers( markers, append ) {
			var m = ensureMap();
			if ( ! m ) {
				// Leaflet not ready yet — retry briefly.
				pending = markers;
				if ( waited < 20 ) { waited++; setTimeout( function () { renderMarkers( pending, false ); }, 250 ); }
				return;
			}
			if ( ! append ) { markerLayer.clearLayers(); }
			var pts = [];
			( markers || [] ).forEach( function ( mk ) {
				if ( typeof mk.lat !== 'number' || typeof mk.lng !== 'number' ) { return; }
				var mark = window.L.marker( [ mk.lat, mk.lng ] ).addTo( markerLayer );
				var stars = mk.rating ? ( Number( mk.rating ).toFixed( 1 ) + ' ★' ) : '';
				mark.bindPopup( '<a href="' + encodeURI( mk.url ) + '" style="font-weight:700">' + escapeHtml( mk.name ) + '</a>' + ( mk.city ? '<br>' + escapeHtml( mk.city ) : '' ) + ( stars ? '<br>' + stars : '' ) );
				pts.push( [ mk.lat, mk.lng ] );
			} );
			if ( pts.length ) {
				try { m.fitBounds( pts, { padding: [ 40, 40 ], maxZoom: 12 } ); } catch ( e ) {}
			}
			setTimeout( function () { m.invalidateSize(); }, 60 );
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
				renderMarkers( res.data.markers || [], append );
			} );
		}

		if ( form ) {
			form.addEventListener( 'submit', function ( e ) { e.preventDefault(); run( false ); } );
		}
		if ( locEl ) { locEl.addEventListener( 'change', function () { run( false ); } ); }
		root.querySelectorAll( '[data-filter-service], [data-filter-area]' ).forEach( function ( c ) {
			c.addEventListener( 'change', function () { run( false ); } );
		} );
		var clearBtn = root.querySelector( '[data-filter-clear]' );
		if ( clearBtn ) {
			clearBtn.addEventListener( 'click', function () {
				root.querySelectorAll( '[data-filter-service]:checked, [data-filter-area]:checked' ).forEach( function ( c ) { c.checked = false; } );
				run( false );
			} );
		}
		if ( loadmore ) {
			loadmore.addEventListener( 'click', function () { if ( paged < maxPages ) { paged++; run( true ); } } );
		}
		// Mobile map toggle.
		var mapToggle = root.querySelector( '[data-map-toggle]' );
		if ( mapToggle && splitview ) {
			mapToggle.addEventListener( 'click', function () {
				var on = splitview.classList.toggle( 'dck-show-map' );
				mapToggle.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				mapToggle.textContent = on ? 'List' : 'Map';
				if ( on && map ) { setTimeout( function () { map.invalidateSize(); }, 60 ); }
			} );
		}

		/* --- "Where" location typeahead (geocoded suggestions) --- */
		if ( kwEl && typeahead ) {
			var taTimer = null, taSeq = 0;

			function closeTA() { typeahead.hidden = true; typeahead.innerHTML = ''; }
			function setActive( items, idx ) {
				items.forEach( function ( it, i ) { it.classList.toggle( 'active', i === idx ); } );
				if ( items[ idx ] ) { items[ idx ].scrollIntoView( { block: 'nearest' } ); }
			}
			function choose( m ) {
				kwEl.value = m.label;
				if ( m.loc ) {
					kwEl.dataset.locSlug = m.loc;
					if ( locEl ) { locEl.value = m.loc; }
				} else {
					delete kwEl.dataset.locSlug;
				}
				closeTA();
				run( false );
			}
			function openTA( matches ) {
				typeahead.innerHTML = '';
				if ( ! matches || ! matches.length ) { typeahead.hidden = true; return; }
				matches.forEach( function ( m ) {
					var li = document.createElement( 'li' );
					li.className = 'dck-ta-item';
					li.setAttribute( 'role', 'option' );
					li.textContent = m.label;
					li._m = m;
					li.addEventListener( 'mousedown', function ( e ) { e.preventDefault(); choose( m ); } );
					typeahead.appendChild( li );
				} );
				typeahead.hidden = false;
			}

			kwEl.addEventListener( 'input', function () {
				delete kwEl.dataset.locSlug; // typing invalidates a prior pick
				var q = kwEl.value.trim();
				if ( q.length < 3 ) { closeTA(); return; }
				clearTimeout( taTimer );
				taTimer = setTimeout( function () {
					var seq = ++taSeq;
					post( 'dck_geo_suggest', { q: q } ).then( function ( res ) {
						if ( seq !== taSeq ) { return; } // ignore out-of-order responses
						if ( ! res || ! res.success ) { closeTA(); return; }
						openTA( res.data.items || [] );
					} ).catch( function () { closeTA(); } );
				}, 320 );
			} );
			kwEl.addEventListener( 'keydown', function ( e ) {
				if ( typeahead.hidden ) { return; }
				var items = Array.prototype.slice.call( typeahead.querySelectorAll( '.dck-ta-item' ) );
				if ( ! items.length ) { return; }
				var idx = items.indexOf( typeahead.querySelector( '.dck-ta-item.active' ) );
				if ( 'ArrowDown' === e.key ) { e.preventDefault(); setActive( items, Math.min( items.length - 1, idx + 1 ) ); }
				else if ( 'ArrowUp' === e.key ) { e.preventDefault(); setActive( items, Math.max( 0, idx - 1 ) ); }
				else if ( 'Enter' === e.key ) { var act = typeahead.querySelector( '.dck-ta-item.active' ); if ( act ) { e.preventDefault(); choose( act._m ); } }
				else if ( 'Escape' === e.key ) { closeTA(); }
			} );
			kwEl.addEventListener( 'blur', function () { setTimeout( closeTA, 150 ); } );
		}

		run( false ); // initial load
	}

	/* ---------------- Profile tabs ---------------- */
	function initTabs() {
		var main = document.querySelector( '.dck-main.dck-tabbed' );
		var tabs = document.querySelectorAll( '.dck-tab' );
		if ( ! main || ! tabs.length ) { return; }
		var panes = main.querySelectorAll( '.dck-pane' );
		function activate( name ) {
			tabs.forEach( function ( t ) { t.classList.toggle( 'active', t.getAttribute( 'data-tab' ) === name ); } );
			panes.forEach( function ( p ) { p.classList.toggle( 'active', p.getAttribute( 'data-pane' ) === name ); } );
		}
		tabs.forEach( function ( t ) { t.addEventListener( 'click', function () { activate( t.getAttribute( 'data-tab' ) ); } ); } );
		// Deep link: the header rating link (and any #dck-reviews link) opens the Reviews tab first, then the browser scrolls.
		document.querySelectorAll( 'a[href="#dck-reviews"]' ).forEach( function ( a ) { a.addEventListener( 'click', function () { activate( 'reviews' ); } ); } );
		activate( tabs[ 0 ].getAttribute( 'data-tab' ) );
	}

	/* ---------------- Photo lightbox ---------------- */
	function initLightbox() {
		var mosaic = document.querySelector( '.dck-mosaic[data-dck-lightbox]' );
		var lb = document.querySelector( '.dck-lb' );
		if ( ! mosaic || ! lb ) { return; }
		var urls;
		try { urls = JSON.parse( mosaic.getAttribute( 'data-dck-lightbox' ) ); } catch ( e ) { return; }
		if ( ! urls || ! urls.length ) { return; }
		var img = lb.querySelector( '.dck-lb-img' );
		var cnt = lb.querySelector( '.dck-lb-count' );
		var idx = 0, n = urls.length;
		function show( i ) { idx = ( i + n ) % n; img.src = urls[ idx ]; cnt.textContent = ( idx + 1 ) + ' / ' + n; }
		function open( i ) { show( i ); lb.classList.add( 'open' ); document.body.style.overflow = 'hidden'; }
		function close() { lb.classList.remove( 'open' ); document.body.style.overflow = ''; }
		var all = mosaic.querySelector( '.dck-all-photos' );
		if ( all ) { all.addEventListener( 'click', function () { open( 0 ); } ); }
		mosaic.querySelectorAll( '.dck-photo' ).forEach( function ( p, k ) { p.addEventListener( 'click', function () { open( k ); } ); } );
		lb.querySelector( '.dck-lb-close' ).addEventListener( 'click', close );
		lb.querySelector( '.dck-lb-prev' ).addEventListener( 'click', function () { show( idx - 1 ); } );
		lb.querySelector( '.dck-lb-next' ).addEventListener( 'click', function () { show( idx + 1 ); } );
		lb.addEventListener( 'click', function ( e ) { if ( e.target === lb ) { close(); } } );
		document.addEventListener( 'keydown', function ( e ) {
			if ( ! lb.classList.contains( 'open' ) ) { return; }
			if ( e.key === 'Escape' ) { close(); }
			if ( e.key === 'ArrowLeft' ) { show( idx - 1 ); }
			if ( e.key === 'ArrowRight' ) { show( idx + 1 ); }
		} );
	}

	/* ---------------- Sitewide login modal ---------------- */
	function initLoginModal() {
		var modal = document.getElementById( 'dck-login' );
		if ( ! modal ) { return; }
		function open() {
			modal.classList.add( 'open' );
			modal.setAttribute( 'aria-hidden', 'false' );
			document.body.style.overflow = 'hidden';
			var f = modal.querySelector( 'input[name="log"]' );
			if ( f ) { f.focus(); }
		}
		function close() {
			modal.classList.remove( 'open' );
			modal.setAttribute( 'aria-hidden', 'true' );
			document.body.style.overflow = '';
		}
		// Intercept links to wp-login.php (open the popup) — but not action=
		// links (logout, lost password, etc.), which must follow through.
		document.addEventListener( 'click', function ( e ) {
			var a = e.target && e.target.closest ? e.target.closest( 'a[href*="wp-login.php"]' ) : null;
			if ( ! a ) { return; }
			if ( a.href.indexOf( 'action=' ) !== -1 ) { return; }
			e.preventDefault();
			open();
		} );
		var closeBtn = modal.querySelector( '.dck-login-close' );
		if ( closeBtn ) { closeBtn.addEventListener( 'click', close ); }
		modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) { close(); } } );
		document.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' && modal.classList.contains( 'open' ) ) { close(); } } );
		if ( /[?&]login=1(&|$)/.test( window.location.search ) ) { open(); }
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initHours();
		initSidebar();
		initLeads();
		initDirectory();
		initTabs();
		initLightbox();
		initLoginModal();
	} );
})();
/* SITE CHROME (appended) — swap site-title text for the DCK logo and
   mark the header dark on every page, including contractor profiles. */
(function(){
	var hls = document.querySelectorAll('header a');
	for (var i = 0; i < hls.length; i++) {
		if (hls[i].textContent.trim() === 'DCK 2.0') {
			hls[i].innerHTML = '<img src="https://incrediblecontracting.com/wp-content/uploads/2026/07/dck-logo-primary-600.png" alt="Decorative Concrete Kingdom" style="height:54px;width:auto;display:block">';
		}
	}
	var lg = document.querySelector('img[alt="Decorative Concrete Kingdom"]');
	if (lg) {
		var hd = lg.closest('header');
		if (hd) hd.classList.add('dckx-dark-header');
	}
})();
