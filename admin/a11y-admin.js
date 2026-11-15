/**
 * The scan runner.
 *
 * One tick at a time, each one a separate request. The browser is the clock,
 * not the worker: if this tab closes mid-scan a cron tick picks it up, which is
 * why nothing here has to hold a connection open.
 */
( function () {
	'use strict';

	var config = window.lynbroA11y || {};
	var strings = config.strings || {};

	var startButton = document.getElementById( 'lynbro-a11y-start' );
	var stopButton = document.getElementById( 'lynbro-a11y-stop' );
	var status = document.getElementById( 'lynbro-a11y-status' );
	var wrapper = document.querySelector( '.lynbro-a11y-scan' );
	var bar = wrapper ? wrapper.querySelector( '.bar' ) : null;
	var meter = wrapper ? wrapper.querySelector( '.lynbro-a11y-progress' ) : null;

	if ( ! startButton || ! stopButton || ! wrapper ) {
		return;
	}

	var running = false;

	function post( action, done ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );

		var request = new XMLHttpRequest();
		request.open( 'POST', config.ajaxUrl, true );
		request.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		request.onload = function () {
			var reply = null;
			try {
				reply = JSON.parse( request.responseText );
			} catch ( e ) {
				reply = null;
			}
			if ( reply && reply.success ) {
				done( reply.data );
			} else {
				running = false;
				setButtons( false );
				say( strings.failed || 'The scan could not continue.' );
			}
		};
		request.onerror = function () {
			running = false;
			setButtons( false );
			say( strings.failed || 'The scan could not continue.' );
		};
		request.send( body.toString() );
	}

	function say( message ) {
		if ( status ) {
			status.textContent = message;
		}
	}

	function setButtons( isRunning ) {
		startButton.disabled = isRunning;
		stopButton.disabled = ! isRunning;
	}

	function format( template, done, total ) {
		return String( template ).replace( '%1$s', done ).replace( '%2$s', total );
	}

	function render( progress ) {
		if ( bar ) {
			bar.style.width = progress.percent + '%';
		}
		if ( meter ) {
			meter.setAttribute( 'aria-valuenow', String( progress.percent ) );
		}
		say( format( strings.progress || '%1$s / %2$s', progress.done, progress.total ) );
	}

	function step( progress ) {
		render( progress );

		if ( progress.state === 'running' && running ) {
			post( 'lynbro_a11y_tick', step );
			return;
		}

		running = false;
		setButtons( false );

		if ( progress.state === 'done' ) {
			say( strings.finished || 'Finished.' );
		} else if ( progress.note ) {
			say( progress.note );
		} else {
			say( strings.stopped || 'Stopped.' );
		}
	}

	startButton.addEventListener( 'click', function () {
		running = true;
		setButtons( true );
		say( strings.starting || 'Starting…' );
		post( 'lynbro_a11y_start', function ( progress ) {
			post( 'lynbro_a11y_tick', step );
			render( progress );
		} );
	} );

	stopButton.addEventListener( 'click', function () {
		running = false;
		post( 'lynbro_a11y_stop', function ( progress ) {
			setButtons( false );
			render( progress );
			say( strings.stopped || 'Stopped.' );
		} );
	} );

	/* A scan that was already going when the screen loaded keeps going. */
	if ( wrapper.getAttribute( 'data-state' ) === 'running' ) {
		running = true;
		setButtons( true );
		post( 'lynbro_a11y_tick', step );
	}

	/* Confirmation dialogs, declared in markup rather than inline handlers, so
	   the same page passes a content security policy. */
	var confirmables = document.querySelectorAll( '[data-lynbro-confirm]' );
	for ( var i = 0; i < confirmables.length; i++ ) {
		confirmables[ i ].addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( this.getAttribute( 'data-lynbro-confirm' ) ) ) {
				event.preventDefault();
			}
		} );
	}
}() );
