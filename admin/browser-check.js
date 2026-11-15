/**
 * Runs the browser check page by page: each page is opened in two frames, one
 * at desktop width and one at phone width, the measuring script inside reports
 * to this site's REST endpoint and then tells this screen it is done, and the
 * next page follows. One page at a time, so a shared host is never asked for
 * more than two page views at once.
 */
( function () {
	'use strict';

	var config = window.lynbroA11yBrowser || {};
	var strings = config.strings || {};
	var pages = config.pages || [];

	var startButton = document.getElementById( 'lynbro-a11y-browser-start' );
	var stopButton = document.getElementById( 'lynbro-a11y-browser-stop' );
	if ( ! startButton || ! pages.length ) {
		return;
	}

	var run = document.getElementById( 'lynbro-a11y-browser-run' );
	var progress = document.getElementById( 'lynbro-a11y-browser-progress' );
	var now = document.getElementById( 'lynbro-a11y-browser-now' );
	var status = document.getElementById( 'lynbro-a11y-browser-status' );
	var result = document.getElementById( 'lynbro-a11y-browser-result' );
	var frames = document.querySelector( '.lynbro-a11y-frames' );
	var holders = {
		desktop: document.getElementById( 'lynbro-a11y-frame-desktop' ),
		phone: document.getElementById( 'lynbro-a11y-frame-phone' )
	};

	var index = 0;
	var stopped = false;
	var running = false;
	var current = null;
	var tally = { checked: 0, stored: 0, looked: 0, failed: [], silentInARow: 0 };

	function format( template ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var position = 0;
		return String( template || '' ).replace( /%(\d+\$)?s/g, function ( match, numbered ) {
			var value = numbered ? args[ parseInt( numbered, 10 ) - 1 ] : args[ position++ ];
			return value === undefined ? '' : String( value );
		} );
	}

	function number( value ) {
		try {
			return Number( value ).toLocaleString( document.documentElement.lang || undefined );
		} catch ( e ) {
			return String( value );
		}
	}

	function clearFrames() {
		Object.keys( holders ).forEach( function ( key ) {
			if ( holders[ key ] ) {
				holders[ key ].textContent = '';
			}
		} );
	}

	function openFrame( viewport, url ) {
		var frame = document.createElement( 'iframe' );
		frame.src = url;
		frame.title = viewport;
		frame.tabIndex = -1;
		frame.setAttribute( 'aria-hidden', 'true' );
		frame.className = 'lynbro-a11y-frame__page lynbro-a11y-frame__page--' + viewport;
		// A frame the site refuses to be shown in (X-Frame-Options, CSP
		// frame-ancestors) still fires load, on the browser's own error page,
		// which belongs to no origin we can read. Seen at once, instead of
		// waiting out the timeout on every page.
		frame.addEventListener( 'load', function () {
			if ( ! current || current.frames[ viewport ] !== frame ) {
				return;
			}
			var readable = false;
			try {
				var href = String( frame.contentWindow.location.href );
				if ( href === 'about:blank' ) {
					return; // The empty document a frame starts with, not the page yet.
				}
				readable = href.indexOf( config.homeOrigin ) === 0;
			} catch ( e ) {
				readable = false;
			}
			if ( ! readable && ! current.heard[ viewport ] ) {
				current.heard[ viewport ] = true;
				current.refused[ viewport ] = true;
				if ( current.heard.desktop && current.heard.phone ) {
					finishPage();
				}
			}
		} );
		holders[ viewport ].appendChild( frame );
		return frame;
	}

	function finishPage() {
		if ( ! current ) {
			return;
		}
		window.clearTimeout( current.timer );
		var page = current.page;
		var heard = current.heard;
		var ok = current.ok;
		var refused = current.refused.desktop && current.refused.phone;
		current = null;
		clearFrames();

		// The desktop frame is the one that measures, so it decides whether the
		// page counts as checked.
		if ( ok.desktop ) {
			tally.checked++;
		} else {
			tally.failed.push( page );
		}
		tally.silentInARow = ( refused || ! ( heard.desktop || heard.phone ) ) ? tally.silentInARow + 1 : 0;

		index++;
		progress.value = index;

		// Nothing at all came back from the first pages: the frames are being
		// refused, and carrying on would only fail the same way for every page.
		if ( tally.silentInARow >= 2 && tally.checked === 0 ) {
			end( 'blocked' );
			return;
		}

		// A polite announcement every ten pages, not every page: a status that
		// speaks every four seconds is noise, not information.
		if ( index % 10 === 0 ) {
			status.textContent = format( strings.progress, number( index ), number( pages.length ) );
		}

		if ( stopped ) {
			end( 'stopped' );
		} else {
			next();
		}
	}

	function next() {
		if ( index >= pages.length ) {
			end( 'finished' );
			return;
		}
		var page = pages[ index ];
		now.textContent = format( strings.checking, number( index + 1 ), number( pages.length ), page.label ) +
			' · ' + format( strings.progress, number( tally.checked ), number( pages.length ) );

		current = {
			page: page,
			heard: { desktop: false, phone: false },
			ok: { desktop: false, phone: false },
			refused: { desktop: false, phone: false },
			frames: {},
			timer: window.setTimeout( function () {
				finishPage();
			}, ( parseInt( config.timeout, 10 ) || 60 ) * 1000 )
		};
		current.frames.desktop = openFrame( 'desktop', page.desktop );
		current.frames.phone = openFrame( 'phone', page.phone );
	}

	function link( href, text ) {
		var anchor = document.createElement( 'a' );
		anchor.href = href;
		anchor.textContent = text;
		return anchor;
	}

	function end( how ) {
		running = false;
		stopButton.hidden = true;
		startButton.hidden = true;
		now.textContent = '';
		clearFrames();
		frames.hidden = true;
		result.textContent = '';

		var message;
		if ( how === 'blocked' ) {
			message = strings.blocked;
		} else if ( how === 'stopped' ) {
			message = format( strings.stopped, number( tally.checked ) );
		} else {
			message = format( strings.finished, number( tally.checked ) );
		}
		status.textContent = message;

		if ( how !== 'blocked' ) {
			var summary = document.createElement( 'p' );
			summary.textContent = format( strings.tally, number( tally.stored ), number( tally.looked ) );
			result.appendChild( summary );
		}

		if ( tally.failed.length && how !== 'blocked' ) {
			var head = document.createElement( 'p' );
			head.textContent = strings.failedHead;
			result.appendChild( head );
			var list = document.createElement( 'ul' );
			tally.failed.forEach( function ( page ) {
				var item = document.createElement( 'li' );
				item.appendChild( link( page.report, page.label ) );
				list.appendChild( item );
			} );
			result.appendChild( list );
		}

		var actions = document.createElement( 'p' );
		var single = pages.length === 1;
		var target = link( single ? pages[ 0 ].report : 'admin.php?page=lynbro-accessibility-report', single ? strings.pageReport : strings.report );
		target.className = 'button button-primary';
		actions.appendChild( target );
		result.appendChild( actions );
	}

	window.addEventListener( 'message', function ( event ) {
		if ( ! current || event.origin !== config.homeOrigin ) {
			return;
		}
		var data = event.data;
		if ( ! data || data.source !== 'lynbro-a11y-live' ) {
			return;
		}
		// Only our own two frames, and each once.
		var viewport = null;
		Object.keys( current.frames ).forEach( function ( key ) {
			if ( current.frames[ key ] && current.frames[ key ].contentWindow === event.source ) {
				viewport = key;
			}
		} );
		if ( ! viewport || current.heard[ viewport ] ) {
			return;
		}
		current.heard[ viewport ] = true;
		current.ok[ viewport ] = !! data.ok;
		tally.stored += parseInt( data.stored, 10 ) || 0;
		tally.looked += viewport === 'desktop' ? ( parseInt( data.looked, 10 ) || 0 ) : 0;

		if ( current.heard.desktop && current.heard.phone ) {
			finishPage();
		}
	} );

	function start() {
		if ( running ) {
			return;
		}
		running = true;
		stopped = false;
		run.hidden = false;
		startButton.disabled = true;
		stopButton.hidden = false;
		stopButton.focus();
		status.textContent = '';
		frames.hidden = false;
		next();
	}

	startButton.addEventListener( 'click', start );
	stopButton.addEventListener( 'click', function () {
		stopped = true;
		stopButton.disabled = true;
	} );

	if ( String( config.autostart ) === '1' ) {
		start();
	}
}() );
