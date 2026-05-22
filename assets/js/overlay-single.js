/**
 * LaunchOverlay – Single product overlay injector.
 * Gorrie Technology Group, Inc.
 * Uses MutationObserver to watch for gallery render — works with ANY theme.
 */
( function () {
	'use strict';
	if ( ! window.loSingleConfig ) return;

	var cfg      = window.loSingleConfig;
	var injected = false;

	function buildOverlay() {
		var ov       = document.createElement( 'span' );
		ov.className = cfg.classes || ( 'lo-overlay lo-pos-' + cfg.position );
		ov.setAttribute( 'aria-hidden', 'true' );
		ov.style.cssText = 'position:absolute;z-index:9999;pointer-events:none;top:0;right:0;';
		if ( cfg.customBg ) {
			ov.style.background = cfg.customBg;
			ov.style.setProperty( '--lo-bg',    cfg.customBg );
			ov.style.setProperty( '--lo-color', cfg.customTextColor || '#fff' );
		}
		var lb       = document.createElement( 'span' );
		lb.className = 'lo-label';
		lb.textContent = cfg.text;
		ov.appendChild( lb );
		return ov;
	}

	function inject() {
		if ( injected ) return;

		var wrap = document.querySelector( '.woocommerce-product-gallery__image' );
		if ( ! wrap ) return;

		// Make sure the image inside is actually visible (opacity > 0)
		var img = wrap.querySelector( 'img' );
		if ( img ) {
			var opacity = window.getComputedStyle( img ).opacity;
			// Ohio theme sets opacity:0 then fades to 1 — wait until visible
			if ( parseFloat( opacity ) < 0.5 ) return;
		}

		injected = true;
		wrap.style.position = 'relative';
		wrap.style.overflow = 'hidden';
		wrap.appendChild( buildOverlay() );
	}

	// MutationObserver watches entire page for DOM changes
	// Fires the moment gallery HTML or styles change
	var observer = new MutationObserver( function() {
		inject();
	});

	observer.observe( document.body, {
		childList:  true,
		subtree:    true,
		attributes: true,
		attributeFilter: [ 'style', 'class', 'opacity' ]
	});

	// Also try at timed intervals as backup
	var delays = [ 300, 600, 1000, 1500, 2000, 3000, 4000, 5000 ];
	delays.forEach( function( d ) {
		setTimeout( function() {
			inject();
			// Stop observer once injected
			if ( injected ) observer.disconnect();
		}, d );
	});

	// Stop observer after 6s no matter what to save memory
	setTimeout( function() {
		observer.disconnect();
	}, 6000 );

} )();
