<?php
/**
 * The scan queue.
 *
 * A site with two thousand pages must not be a request that either finishes in
 * four minutes or dies. So a scan is a long series of very short jobs:
 *
 *   1. A cursor into a stored list of URLs. A tick can stop anywhere.
 *   2. Every tick has a wall-clock budget and a memory ceiling, both checked
 *      between pages rather than once at the top.
 *   3. Nothing ever runs on a visitor's request. Scanning is admin and cron.
 *
 * The batch size is a setting, not a limit: raising it scans faster, and there
 * is no number of pages at which the scan refuses to continue.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_STATE', 'lynbro_a11y_scan_state' );
define( 'LYNBRO_A11Y_LIST', 'lynbro_a11y_scan_list' );
define( 'LYNBRO_A11Y_LOCK', 'lynbro_a11y_scan_lock' );

/**
 * The scan's state.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_scan_state() {
	$state = get_option( LYNBRO_A11Y_STATE, array() );
	return wp_parse_args(
		is_array( $state ) ? $state : array(),
		array(
			'state'       => 'idle',   // idle | running | stopping | done.
			'cursor'      => 0,
			'total'       => 0,
			'scanned'     => 0,
			'failed'      => 0,
			'started_at'  => 0,
			'finished_at' => 0,
			'last_tick'   => 0,
			'note'        => '',
		)
	);
}

/**
 * Write the scan's state.
 *
 * @param array<string,mixed> $state New state.
 * @return void
 */
function lynbro_a11y_set_scan_state( $state ) {
	update_option( LYNBRO_A11Y_STATE, $state, false );
}

/**
 * Is a scan under way?
 *
 * @return bool
 */
function lynbro_a11y_scan_is_running() {
	$state = lynbro_a11y_scan_state();
	return in_array( $state['state'], array( 'running', 'stopping' ), true );
}

/**
 * Begin a scan: build the list of URLs and reset the cursor.
 *
 * @return array<string,mixed> The new state.
 */
function lynbro_a11y_start_scan() {
	lynbro_a11y_forget_unpublished();

	$targets = lynbro_a11y_collect_urls();

	update_option( LYNBRO_A11Y_LIST, $targets, false );

	$state = array(
		'state'       => 'running',
		'cursor'      => 0,
		'total'       => count( $targets ),
		'scanned'     => 0,
		'failed'      => 0,
		'started_at'  => time(),
		'finished_at' => 0,
		'last_tick'   => 0,
		'note'        => '',
	);

	if ( ! lynbro_a11y_dom_available() ) {
		$state['state'] = 'done';
		$state['note']  = __( 'PHP on this server has no DOM extension, so pages cannot be parsed. Ask your host to enable ext-dom.', 'lynbro-accessibility-checker' );
	}

	lynbro_a11y_set_scan_state( $state );

	if ( 'running' === $state['state'] && ! wp_next_scheduled( 'lynbro_a11y_cron_tick' ) ) {
		// The browser normally drives the scan. This is the safety net for the
		// tab that gets closed halfway through.
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'lynbro_a11y_cron_tick' );
	}

	return $state;
}

/**
 * Ask a running scan to stop after the page it is on.
 *
 * @return void
 */
function lynbro_a11y_stop_scan() {
	$state = lynbro_a11y_scan_state();
	if ( 'running' === $state['state'] ) {
		$state['state'] = 'stopping';
		lynbro_a11y_set_scan_state( $state );
	}
}

/**
 * Run one tick of the scan.
 *
 * @param int $budget_seconds How long this tick may take.
 * @return array<string,mixed> The state after the tick.
 */
function lynbro_a11y_tick( $budget_seconds = 15 ) {
	$state = lynbro_a11y_scan_state();

	if ( ! in_array( $state['state'], array( 'running', 'stopping' ), true ) ) {
		return $state;
	}

	// One tick at a time. Two overlapping ticks would scan the same pages twice
	// and race each other's cursor.
	if ( ! lynbro_a11y_acquire_lock() ) {
		$state['note'] = __( 'Another scan tick is still running.', 'lynbro-accessibility-checker' );
		return $state;
	}

	$targets = get_option( LYNBRO_A11Y_LIST, array() );
	$targets = is_array( $targets ) ? $targets : array();

	$started  = microtime( true );
	$budget   = max( 3, (int) $budget_seconds );
	$per_tick = max( 1, (int) lynbro_a11y_option( 'batch_pages' ) );
	$done     = 0;

	while ( $done < $per_tick ) {
		if ( $state['cursor'] >= count( $targets ) ) {
			$state['state']       = 'done';
			$state['finished_at'] = time();
			// A full scan finished: the shared review request may ask later.
			if ( function_exists( 'lynbro_core_note_success' ) ) {
				lynbro_core_note_success( 'lynbro-accessibility-checker' );
			}
			break;
		}

		if ( 'stopping' === $state['state'] ) {
			break;
		}

		if ( ( microtime( true ) - $started ) >= $budget ) {
			break;
		}

		if ( lynbro_a11y_memory_exhausted() ) {
			$state['note'] = __( 'Paused: this tick was close to the PHP memory limit. The scan continues on the next tick.', 'lynbro-accessibility-checker' );
			break;
		}

		$target = $targets[ $state['cursor'] ];
		$result = lynbro_a11y_scan_target( $target );

		if ( empty( $result['status'] ) || $result['status'] >= 400 ) {
			$state['failed']++;
		}

		$state['cursor']++;
		$state['scanned']++;
		$done++;
	}

	if ( 'stopping' === $state['state'] ) {
		$state['state']       = 'idle';
		$state['finished_at'] = time();
		$state['note']        = __( 'Stopped. Starting again picks up from the beginning.', 'lynbro-accessibility-checker' );
	}

	$state['last_tick'] = time();
	lynbro_a11y_set_scan_state( $state );
	lynbro_a11y_release_lock();

	if ( 'running' === $state['state'] && ! wp_next_scheduled( 'lynbro_a11y_cron_tick' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'lynbro_a11y_cron_tick' );
	}
	if ( 'running' !== $state['state'] ) {
		delete_option( LYNBRO_A11Y_LIST );
	}

	return $state;
}

/**
 * Claim the right to run a tick.
 *
 * A transient rather than a database lock: it expires by itself, so a tick that
 * dies with the request cannot wedge the queue shut for ever.
 *
 * @return bool
 */
function lynbro_a11y_acquire_lock() {
	if ( get_transient( LYNBRO_A11Y_LOCK ) ) {
		return false;
	}
	set_transient( LYNBRO_A11Y_LOCK, time(), 2 * MINUTE_IN_SECONDS );
	return true;
}

/**
 * Let go of the tick lock.
 *
 * @return void
 */
function lynbro_a11y_release_lock() {
	delete_transient( LYNBRO_A11Y_LOCK );
}

/**
 * Are we close enough to the memory limit to stop for this tick?
 *
 * @return bool
 */
function lynbro_a11y_memory_exhausted() {
	$limit = lynbro_a11y_memory_limit_bytes();
	if ( $limit <= 0 ) {
		return false;
	}
	$ceiling = (int) lynbro_a11y_option( 'memory_ceiling_pct' );
	return memory_get_usage( true ) > ( $limit * ( $ceiling / 100 ) );
}

/**
 * The PHP memory limit in bytes, or 0 when there is none.
 *
 * @return int
 */
function lynbro_a11y_memory_limit_bytes() {
	$limit = ini_get( 'memory_limit' );
	if ( false === $limit || '' === $limit || '-1' === (string) $limit ) {
		return 0;
	}
	return (int) wp_convert_hr_to_bytes( $limit );
}

/**
 * Progress, shaped for a screen.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_progress() {
	$state   = lynbro_a11y_scan_state();
	$percent = $state['total'] > 0 ? min( 100, (int) floor( ( $state['cursor'] / $state['total'] ) * 100 ) ) : 0;

	return array(
		'state'   => $state['state'],
		'done'    => (int) $state['cursor'],
		'total'   => (int) $state['total'],
		'failed'  => (int) $state['failed'],
		'percent' => $percent,
		'note'    => (string) $state['note'],
	);
}

/**
 * The cron tick, for when nobody is watching the progress bar.
 *
 * @return void
 */
function lynbro_a11y_cron_tick() {
	lynbro_a11y_tick( 20 );
}
add_action( 'lynbro_a11y_cron_tick', 'lynbro_a11y_cron_tick' );

/**
 * The scheduled scan.
 *
 * @return void
 */
function lynbro_a11y_cron_scan() {
	if ( lynbro_a11y_scan_is_running() ) {
		return;
	}
	lynbro_a11y_start_scan();
	lynbro_a11y_tick( 20 );
}
add_action( 'lynbro_a11y_cron_scan', 'lynbro_a11y_cron_scan' );
