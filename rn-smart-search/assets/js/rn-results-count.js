/* RN Results Count — "Showing X–Y of Z", kept in sync with JetSmartFilters AJAX.
 *
 * Reads the already-computed totals from the listing grid wrapper's data
 * attributes (data-found-posts / data-per-page / data-current-page), so there
 * is no extra query — updating is O(1). A MutationObserver catches the grid
 * being re-rendered by AJAX (filter / search / page change).
 */
( function () {
	'use strict';

	function num( v ) {
		v = parseInt( v, 10 );
		return isNaN( v ) ? null : v;
	}

	function fmt( n ) {
		try { return Number( n ).toLocaleString(); } catch ( e ) { return String( n ); }
	}

	// per_page is constant while filtering, so cache it from the first (correct)
	// read and reuse it — the AJAX-rendered wrapper can carry a stale per-page.
	var cachedPerPage = null;

	function readGrid( gridEl ) {
		if ( ! gridEl ) {
			return null;
		}
		var total    = num( gridEl.getAttribute( 'data-found-posts' ) );
		var perPage  = num( gridEl.getAttribute( 'data-per-page' ) );
		var page     = num( gridEl.getAttribute( 'data-current-page' ) );
		var maxPages = num( gridEl.getAttribute( 'data-max-pages' ) );

		if ( page === null ) {
			page = num( gridEl.getAttribute( 'data-page' ) ) || 1;
		}
		if ( maxPages === null ) {
			maxPages = num( gridEl.getAttribute( 'data-pages' ) );
		}

		// Cache the first per-page we see (correct on initial load).
		if ( perPage && cachedPerPage === null ) {
			cachedPerPage = perPage;
		}
		if ( cachedPerPage ) {
			perPage = cachedPerPage;
		}
		if ( ! perPage ) {
			var items = gridEl.querySelectorAll( '.jet-listing-grid__item' ).length;
			perPage = items || 1;
		}

		// Fallback for total if the found-posts attr is missing.
		if ( total === null ) {
			total = ( maxPages || 1 ) * perPage; // approximate
		}
		return { total: total, perPage: perPage, page: page };
	}

	function compute( d ) {
		var start = d.total > 0 ? ( ( d.page - 1 ) * d.perPage + 1 ) : 0;
		var end   = Math.min( d.page * d.perPage, d.total );
		var pages = d.perPage > 0 ? Math.max( 1, Math.ceil( d.total / d.perPage ) ) : 1;
		return { start: start, end: end, total: d.total, page: d.page, pages: pages };
	}

	function fill( tpl, c ) {
		return tpl
			.replace( /%start%/g, '<b>' + fmt( c.start ) + '</b>' )
			.replace( /%end%/g, '<b>' + fmt( c.end ) + '</b>' )
			.replace( /%total%/g, '<b>' + fmt( c.total ) + '</b>' )
			.replace( /%page%/g, '<b>' + fmt( c.page ) + '</b>' )
			.replace( /%pages%/g, '<b>' + fmt( c.pages ) + '</b>' );
	}

	function updateOne( el ) {
		var selector = el.getAttribute( 'data-grid' ) || '.jet-listing-grid__items';
		var grid     = document.querySelector( selector );
		var data     = readGrid( grid );
		if ( ! data ) {
			return;
		}
		el.innerHTML = fill( el.getAttribute( 'data-template' ) || 'Showing %start%–%end% of %total%', compute( data ) );
	}

	function updateAll() {
		document.querySelectorAll( '.rn-results-count' ).forEach( updateOne );
	}

	var updTimer = null;
	function scheduleUpdate() {
		// A short debounce lets the AJAX-injected markup settle. Uses setTimeout
		// (not requestAnimationFrame) so it still fires when the tab is hidden.
		if ( updTimer ) {
			clearTimeout( updTimer );
		}
		updTimer = setTimeout( updateAll, 80 );
	}

	function observeGrids() {
		document.querySelectorAll( '.rn-results-count' ).forEach( function ( el ) {
			var selector = el.getAttribute( 'data-grid' ) || '.jet-listing-grid__items';
			var grid     = document.querySelector( selector );
			if ( ! grid ) {
				return;
			}
			// Observe the outermost STABLE wrapper (the element with an id, i.e.
			// the grid's CSS ID). JSF replaces the inner .jet-listing-grid on AJAX,
			// so observing that inner node would detach the observer.
			var target = grid.closest( '[id]' ) || grid.parentNode || document.body;
			if ( ! target || target._rnObserved ) {
				return;
			}
			target._rnObserved = true;
			var obs = new MutationObserver( scheduleUpdate );
			obs.observe( target, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'data-found-posts', 'data-current-page', 'data-max-pages', 'data-page' ]
			} );
		} );
	}

	// JSF may initialise after us, so retry subscribing for a short while.
	function subscribeJSF( tries ) {
		try {
			if ( window.JetSmartFilters && window.JetSmartFilters.events && typeof window.JetSmartFilters.events.subscribe === 'function' ) {
				window.JetSmartFilters.events.subscribe( 'ajaxFilters/updated', scheduleUpdate );
				window.JetSmartFilters.events.subscribe( 'ajaxFilters/end', scheduleUpdate );
				return;
			}
		} catch ( e ) {}
		if ( tries > 0 ) {
			setTimeout( function () { subscribeJSF( tries - 1 ); }, 500 );
		}
	}

	function init() {
		updateAll();
		observeGrids();
		subscribeJSF( 20 ); // retry up to ~10s
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
