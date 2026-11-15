/**
 * The three fixes that can only be made in the browser.
 *
 * Each one is here for the same reason: by the time WordPress could tell that
 * the theme got it wrong, the markup has already been sent. Nothing in this
 * file runs unless the matching setting is on — the flags come from PHP, so a
 * switched-off fix costs a visitor nothing but the bytes of this file, and the
 * file is not loaded at all when all three are off.
 */
( function () {
	'use strict';

	var config = window.lynbroA11yFixes || {};

	/* The page language. A screen reader picks its pronunciation from this, and
	   Danish read with English pronunciation is close to unusable. */
	if ( config.lang ) {
		var html = document.documentElement;
		if ( html && ! html.getAttribute( 'lang' ) ) {
			html.setAttribute( 'lang', config.lang );
		}
		if ( html && config.dir && ! html.getAttribute( 'dir' ) ) {
			html.setAttribute( 'dir', config.dir );
		}
	}

	/* Pinch to zoom. Removing the whole viewport tag would break the layout, so
	   only the two declarations that block zooming are taken out. */
	if ( config.viewport ) {
		var viewport = document.querySelector( 'meta[name="viewport"]' );
		if ( viewport ) {
			var content = viewport.getAttribute( 'content' ) || '';
			var cleaned = content
				.split( ',' )
				.map( function ( part ) {
					return part.trim();
				} )
				.filter( function ( part ) {
					var lower = part.toLowerCase().replace( /\s+/g, '' );
					if ( lower === 'user-scalable=no' || lower === 'user-scalable=0' ) {
						return false;
					}
					var max = lower.match( /^maximum-scale=([0-9.]+)$/ );
					return ! ( max && parseFloat( max[ 1 ] ) < 2 );
				} )
				.join( ', ' );

			if ( cleaned !== content ) {
				viewport.setAttribute( 'content', cleaned );
			}
		}
	}

	/* Forced tab order. tabindex="1" and up pull an element to the front of the
	   tab order for the entire page; zero keeps it focusable in its own place. */
	if ( config.tabindex ) {
		var forced = document.querySelectorAll( '[tabindex]' );
		for ( var i = 0; i < forced.length; i++ ) {
			var value = parseInt( forced[ i ].getAttribute( 'tabindex' ), 10 );
			if ( ! isNaN( value ) && value > 0 ) {
				forced[ i ].setAttribute( 'tabindex', '0' );
			}
		}
	}
}() );
