/**
 * The in-page measuring pass.
 *
 * Three things are measured here and nowhere else, because nowhere else are
 * they knowable: the contrast a visitor actually sees, whether keyboard focus
 * leaves a visible mark, and how big the tap targets are.
 *
 * This script only ever runs for a logged-in administrator who asked for it.
 * It changes nothing on the page except a small panel reporting what it found,
 * and it is removed the moment the page is reloaded without the request.
 */
( function () {
	'use strict';

	var config = window.lynbroA11yLive || {};
	var strings = config.strings || {};
	var batch = String( config.batch ) === '1';

	/* A page can hold thousands of text nodes, and a theme with one bad colour
	   pair produces a finding on every one of them. So the detail we send is
	   bounded — but the COUNT is not, and when the bound bites we say so instead
	   of quietly shortening the list. A tool that silently truncates is a tool
	   that silently tells you the problem is smaller than it is. */
	var MAX_PER_RULE = 200;
	var totals = { contrast_text_low: 0, focus_not_visible: 0, target_too_small: 0 };

	/* ---------------------------------------------------------------- utils */

	function parseColour( value ) {
		if ( ! value ) {
			return null;
		}
		var match = value.match( /rgba?\(\s*([0-9.]+)[,\s]+([0-9.]+)[,\s]+([0-9.]+)(?:[,/\s]+([0-9.]+))?/i );
		if ( ! match ) {
			return null;
		}
		return {
			r: parseFloat( match[ 1 ] ),
			g: parseFloat( match[ 2 ] ),
			b: parseFloat( match[ 3 ] ),
			a: match[ 4 ] === undefined ? 1 : parseFloat( match[ 4 ] )
		};
	}

	function luminance( colour ) {
		var channels = [ colour.r, colour.g, colour.b ].map( function ( value ) {
			var c = value / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * channels[ 0 ] + 0.7152 * channels[ 1 ] + 0.0722 * channels[ 2 ];
	}

	function contrast( a, b ) {
		var la = luminance( a );
		var lb = luminance( b );
		return ( Math.max( la, lb ) + 0.05 ) / ( Math.min( la, lb ) + 0.05 );
	}

	/* Blend a translucent colour over what is behind it. Without this a heading
	   on a semi-transparent overlay measures against the wrong background and
	   the whole finding is wrong. */
	function blend( top, bottom ) {
		var alpha = top.a;
		return {
			r: top.r * alpha + bottom.r * ( 1 - alpha ),
			g: top.g * alpha + bottom.g * ( 1 - alpha ),
			b: top.b * alpha + bottom.b * ( 1 - alpha ),
			a: 1
		};
	}

	/* The colour actually behind an element: walk up until something is opaque,
	   ending at white, which is what a browser paints over nothing. */
	function backgroundBehind( element ) {
		var node = element;
		var stack = [];

		while ( node && node.nodeType === 1 ) {
			var colour = parseColour( window.getComputedStyle( node ).backgroundColor );
			if ( colour && colour.a > 0 ) {
				stack.push( colour );
				if ( colour.a === 1 ) {
					break;
				}
			}
			node = node.parentElement;
		}

		var result = { r: 255, g: 255, b: 255, a: 1 };
		for ( var i = stack.length - 1; i >= 0; i-- ) {
			result = stack[ i ].a === 1 ? stack[ i ] : blend( stack[ i ], result );
		}
		return result;
	}

	function isVisible( element ) {
		var style = window.getComputedStyle( element );
		if ( style.display === 'none' || style.visibility === 'hidden' || parseFloat( style.opacity ) === 0 ) {
			return false;
		}
		var rect = element.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0;
	}

	/* Screen-reader-only text — the clip-rect technique WordPress core and every
	   theme uses. It is on the page and it has a size, so isVisible() is quite
	   right about it; it is simply not something a person aims a finger at, and
	   reporting core's own skip link as an undersized target is the kind of
	   false positive that teaches people to ignore the report. */
	function isVisuallyHidden( element ) {
		var style = window.getComputedStyle( element );
		if ( ( style.clip && style.clip !== 'auto' ) || ( style.clipPath && style.clipPath !== 'none' ) ) {
			return true;
		}
		var rect = element.getBoundingClientRect();
		return rect.width <= 4 && rect.height <= 4;
	}

	/* Inside something that has been taken out of the accessibility tree or out
	   of the document entirely — a closed menu drawer, say. Not reachable, so
	   not measurable. */
	function isInert( element ) {
		for ( var node = element; node; node = node.parentElement ) {
			if ( node.hasAttribute && ( node.getAttribute( 'aria-hidden' ) === 'true' || node.hasAttribute( 'inert' ) ) ) {
				return true;
			}
			/* The WordPress admin bar is on the page only because the person
			   running the check is signed in. It is not part of the site the
			   report is about, and it is not theirs to fix. */
			if ( node.id === 'wpadminbar' ) {
				return true;
			}
		}
		return false;
	}

	/* A short, readable path to the element — the same shape the PHP side
	   produces, so a person reading the report cannot tell which half of the
	   scan found what. */
	function selectorFor( element ) {
		var parts = [];
		var node = element;
		var depth = 0;

		while ( node && node.nodeType === 1 && depth < 5 ) {
			var tag = node.tagName.toLowerCase();
			if ( tag === 'html' ) {
				break;
			}
			if ( node.id && /^[A-Za-z][\w:.-]*$/.test( node.id ) ) {
				parts.unshift( '#' + node.id );
				return parts.join( ' > ' );
			}
			var piece = tag;
			if ( node.classList && node.classList.length && /^[A-Za-z][\w-]*$/.test( node.classList[ 0 ] ) ) {
				piece += '.' + node.classList[ 0 ];
			}
			parts.unshift( piece );
			if ( tag === 'body' ) {
				break;
			}
			node = node.parentElement;
			depth++;
		}
		return parts.join( ' > ' );
	}

	function textSample( element ) {
		var text = ( element.textContent || '' ).replace( /\s+/g, ' ' ).trim();
		return text.length > 80 ? text.slice( 0, 80 ) + ' …' : text;
	}

	/* ------------------------------------------------------------- contrast */

	function checkContrast( findings ) {
		var elements = document.body.querySelectorAll( 'p, li, a, span, h1, h2, h3, h4, h5, h6, td, th, label, button, figcaption, blockquote, dd, dt' );
		var found = 0;

		for ( var i = 0; i < elements.length; i++ ) {
			var element = elements[ i ];

			/* Only elements with their own text. A <div> wrapping a paragraph
			   reports the same failure a second time and buries the first. */
			var ownText = '';
			for ( var n = 0; n < element.childNodes.length; n++ ) {
				if ( element.childNodes[ n ].nodeType === 3 ) {
					ownText += element.childNodes[ n ].textContent;
				}
			}
			if ( ! ownText.trim() || ! isVisible( element ) || isVisuallyHidden( element ) || isInert( element ) ) {
				continue;
			}

			var style = window.getComputedStyle( element );
			var foreground = parseColour( style.color );
			if ( ! foreground ) {
				continue;
			}
			var background = backgroundBehind( element );
			if ( foreground.a < 1 ) {
				foreground = blend( foreground, background );
			}

			/* Text sitting on an image is not something a colour pair can
			   settle, so it is left for a person rather than guessed at. */
			if ( style.backgroundImage && style.backgroundImage !== 'none' ) {
				continue;
			}

			var size = parseFloat( style.fontSize ) || 16;
			var weight = parseInt( style.fontWeight, 10 ) || 400;
			var large = size >= 24 || ( weight >= 700 && size >= 18.66 );
			var required = large ? 3 : 4.5;
			var ratio = contrast( foreground, background );

			if ( ratio < required ) {
				totals.contrast_text_low++;
				if ( found >= MAX_PER_RULE ) {
					continue;
				}
				findings.push( {
					rule: 'contrast_text_low',
					selector: selectorFor( element ),
					snippet: textSample( element ),
					context: ratio.toFixed( 2 ) + ':1 (needs ' + required + ':1) — ' + style.color + ' on rgb(' +
						Math.round( background.r ) + ', ' + Math.round( background.g ) + ', ' + Math.round( background.b ) + ')'
				} );
				found++;
			}
		}
	}

	/* ---------------------------------------------------------------- focus */

	/* Focus each control in turn and compare what the browser paints before and
	   after. A theme that sets outline:none and puts nothing back produces no
	   difference at all, and that is the failure. */
	function checkFocus( findings ) {
		var controls = document.body.querySelectorAll( 'a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]' );
		var active = document.activeElement;
		var found = 0;
		var checked = 0;

		for ( var i = 0; i < controls.length && checked < 400; i++ ) {
			var control = controls[ i ];
			if ( ! isVisible( control ) || control.disabled || isInert( control ) ) {
				continue;
			}
			checked++;

			var before = window.getComputedStyle( control );
			var snapshot = {
				outlineWidth: before.outlineWidth,
				outlineStyle: before.outlineStyle,
				outlineColor: before.outlineColor,
				boxShadow: before.boxShadow,
				borderColor: before.borderColor,
				backgroundColor: before.backgroundColor,
				color: before.color,
				textDecoration: before.textDecorationLine
			};

			try {
				control.focus( { preventScroll: true } );
			} catch ( e ) {
				continue;
			}
			if ( document.activeElement !== control ) {
				continue;
			}

			var after = window.getComputedStyle( control );
			var changed = false;
			for ( var key in snapshot ) {
				if ( Object.prototype.hasOwnProperty.call( snapshot, key ) && snapshot[ key ] !== after[ key ] ) {
					changed = true;
					break;
				}
			}

			/* An outline of zero width is the same as no outline, whatever the
			   stylesheet claims. */
			if ( changed && after.outlineStyle !== 'none' && parseFloat( after.outlineWidth ) === 0 && after.boxShadow === snapshot.boxShadow ) {
				changed = false;
			}

			if ( ! changed ) {
				totals.focus_not_visible++;
				if ( found >= MAX_PER_RULE ) {
					continue;
				}
				findings.push( {
					rule: 'focus_not_visible',
					selector: selectorFor( control ),
					snippet: textSample( control ) || control.tagName.toLowerCase(),
					context: 'Nothing visible changes when this gets keyboard focus.'
				} );
				found++;
			}
		}

		if ( active && active.focus ) {
			try {
				active.focus( { preventScroll: true } );
			} catch ( e ) {}
		} else if ( document.activeElement && document.activeElement.blur ) {
			document.activeElement.blur();
		}
	}

	/* --------------------------------------------------------- target sizes */

	function checkTargets( findings ) {
		var controls = document.body.querySelectorAll( 'a[href], button, input[type="checkbox"], input[type="radio"], input[type="submit"], [role="button"]' );
		var found = 0;

		for ( var i = 0; i < controls.length; i++ ) {
			var control = controls[ i ];
			if ( ! isVisible( control ) || isVisuallyHidden( control ) || isInert( control ) ) {
				continue;
			}

			/* WCAG 2.2 exempts a target that is "in a sentence or its size is
			   otherwise constrained by the line-height of non-target text". Both
			   halves of that are checked: a link with text around it, and a link
			   the browser lays out inline, whose height is its line box and not
			   a size anybody chose. */
			var controlStyle = window.getComputedStyle( control );
			var parent = control.parentElement;
			if ( control.tagName === 'A' && parent && /^(P|LI|TD|SPAN|H1|H2|H3|H4|H5|H6|BLOCKQUOTE|DD)$/.test( parent.tagName ) ) {
				var siblingText = ( parent.textContent || '' ).replace( ( control.textContent || '' ), '' ).trim();
				if ( siblingText.length > 0 ) {
					continue;
				}
			}
			if ( control.tagName === 'A' && controlStyle.display === 'inline' ) {
				continue;
			}

			var rect = control.getBoundingClientRect();
			if ( rect.width >= 24 && rect.height >= 24 ) {
				continue;
			}

			totals.target_too_small++;
			if ( found >= MAX_PER_RULE ) {
				continue;
			}
			findings.push( {
				rule: 'target_too_small',
				selector: selectorFor( control ),
				snippet: textSample( control ) || control.tagName.toLowerCase(),
				context: Math.round( rect.width ) + ' × ' + Math.round( rect.height ) + ' pixels'
			} );
			found++;
		}
	}

	/* ------------------------------------------------- what is on screen */

	/* The markup pass reads HTML and cannot see a stylesheet. Here each of its
	   findings is looked up in the painted page and reported as shown or hidden
	   at this width. An element is hidden when it has no box at all (display:none
	   on it or on an ancestor, a closed <details>) or is visibility:hidden — the
	   same test browsers use to leave it out of the accessibility tree. Clipped
	   screen-reader text counts as shown: it is read out. A selector that matches
	   nothing says nothing, and a selector that matches several elements is
	   hidden only if every one of them is. */
	function rendered( element ) {
		if ( typeof element.checkVisibility === 'function' ) {
			return element.checkVisibility( { visibilityProperty: true } );
		}
		if ( ! element.getClientRects().length ) {
			return window.getComputedStyle( element ).display === 'contents';
		}
		return window.getComputedStyle( element ).visibility !== 'hidden';
	}

	function lookUp() {
		var states = {};
		var targets = config.targets || [];
		for ( var i = 0; i < targets.length; i++ ) {
			var matches;
			try {
				matches = document.querySelectorAll( targets[ i ].selector );
			} catch ( e ) {
				continue;
			}
			if ( ! matches.length ) {
				continue;
			}
			var shown = false;
			for ( var m = 0; m < matches.length && ! shown; m++ ) {
				shown = rendered( matches[ m ] );
			}
			states[ targets[ i ].id ] = shown ? 'shown' : 'hidden';
		}
		return states;
	}

	function viewport() {
		if ( config.viewport === 'desktop' || config.viewport === 'phone' ) {
			return config.viewport;
		}
		return window.innerWidth < ( config.phoneMax || 782 ) ? 'phone' : 'desktop';
	}

	/* In a frame of the admin screen, the result goes to that screen and the
	   page itself shows nothing. */
	function tellParent( ok, reply ) {
		if ( ! batch || window.parent === window || ! config.adminOrigin ) {
			return;
		}
		window.parent.postMessage( {
			source: 'lynbro-a11y-live',
			viewport: viewport(),
			ok: ok,
			stored: reply && reply.stored ? reply.stored : 0,
			looked: reply && reply.looked ? reply.looked : 0
		}, config.adminOrigin );
	}

	/* ----------------------------------------------------------------- run */

	function panel( message, link ) {
		var box = document.getElementById( 'lynbro-a11y-live-panel' );
		if ( ! box ) {
			box = document.createElement( 'div' );
			box.id = 'lynbro-a11y-live-panel';
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'aria-live', 'polite' );
			box.style.cssText = 'position:fixed;z-index:999999;left:16px;bottom:16px;max-width:22rem;padding:14px 16px;' +
				'background:#1d2327;color:#fff;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;' +
				'border-radius:6px;box-shadow:0 4px 18px rgba(0,0,0,.35);';
			document.body.appendChild( box );
		}
		box.textContent = '';

		var title = document.createElement( 'strong' );
		title.style.cssText = 'display:block;margin-bottom:4px;';
		title.textContent = strings.panel || 'Live check';
		box.appendChild( title );

		var text = document.createElement( 'span' );
		text.textContent = message;
		box.appendChild( text );

		if ( link ) {
			box.appendChild( document.createElement( 'br' ) );
			var anchor = document.createElement( 'a' );
			anchor.href = link;
			anchor.textContent = strings.openReport || 'Open the report';
			anchor.style.cssText = 'color:#8fd3b6;display:inline-block;margin-top:8px;';
			box.appendChild( anchor );
		}
	}

	function run() {
		if ( ! batch ) {
			panel( strings.working || 'Measuring…' );
		}

		var render = lookUp();
		var findings = [];
		var measure = String( config.measure ) !== '0';
		if ( measure ) {
			checkContrast( findings );
			checkTargets( findings );
			checkFocus( findings ); // Last: it moves focus about, so nothing else should measure after it.
		}

		var request = new XMLHttpRequest();
		request.open( 'POST', config.endpoint, true );
		request.setRequestHeader( 'Content-Type', 'application/json' );
		request.setRequestHeader( 'X-WP-Nonce', config.nonce );
		request.onload = function () {
			if ( request.status >= 200 && request.status < 300 ) {
				var reply = {};
				try {
					reply = JSON.parse( request.responseText );
				} catch ( e ) {}
				if ( batch ) {
					tellParent( true, reply );
				} else {
					panel( strings.saved || 'Done.', reply.report || config.report );
				}
			} else if ( batch ) {
				tellParent( false );
			} else {
				panel( strings.failed || 'Could not save the results.' );
			}
		};
		request.onerror = function () {
			if ( batch ) {
				tellParent( false );
			} else {
				panel( strings.failed || 'Could not save the results.' );
			}
		};
		request.send( JSON.stringify( {
			url: config.url,
			findings: findings,
			totals: totals,
			viewport: viewport(),
			measure: measure,
			render: render
		} ) );
	}

	/* Measure only once the web fonts are in. Sizes and line boxes taken with
	   the fallback font are a few pixels off: a 25-pixel menu link measures 23
	   and is reported as too small. The load event does not wait for fonts. */
	function whenSettled() {
		var go = function () {
			window.setTimeout( run, 300 );
		};
		if ( document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function' ) {
			var done = false;
			var once = function () {
				if ( ! done ) {
					done = true;
					go();
				}
			};
			document.fonts.ready.then( once, once );
			window.setTimeout( once, 5000 ); // A font that never arrives does not stop the check.
		} else {
			go();
		}
	}

	if ( document.readyState === 'complete' ) {
		whenSettled();
	} else {
		window.addEventListener( 'load', whenSettled );
	}
}() );
