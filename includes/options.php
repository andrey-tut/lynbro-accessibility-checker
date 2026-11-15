<?php
/**
 * Settings schema, defaults and sanitising.
 *
 * One option array holds everything. Two rules shape the defaults:
 *
 *   1. Nothing here is a ceiling. There is no "pages per scan" limit, no
 *      "issues shown" limit and no quota. The batch numbers exist so a scan
 *      never brings a shared host down — the scan still processes every page,
 *      it just does it in short bites.
 *   2. Every fix that changes what a visitor sees is OFF until the owner turns
 *      it on. A plugin that starts rewriting a theme's markup on activation is
 *      a plugin that will be blamed for the next thing that breaks.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_OPTION', 'lynbro_a11y_options' );

/**
 * Default settings.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_default_options() {
	return array(
		// --- The scan loop ---------------------------------------------------
		'tick_seconds'         => 15,
		'batch_pages'          => 5,
		'memory_ceiling_pct'   => 80,
		'request_timeout'      => 15,
		'scan_schedule'        => 'off',   // off | daily | weekly.
		'scan_on_save'         => 1,       // Re-check a published page a few seconds after it is saved.

		// --- What gets scanned ------------------------------------------------
		'post_types'           => '',      // Empty means every public type.
		'include_front_page'   => 1,
		'include_terms'        => 0,
		'include_archives'     => 0,
		'extra_urls'           => '',
		'exclude_urls'         => '',
		'ignored_rules'        => '',      // One rule id per line.

		// --- Fixes (all off until asked for) ------------------------------------
		'fix_alt_from_library'  => 0,
		'fix_skip_link'         => 0,
		'fix_skip_target'       => 'main',
		'fix_focus_outline'     => 0,
		'fix_focus_colour'      => '#1a7f5a',
		'fix_underline_links'   => 0,
		'fix_new_window_notice' => 0,
		'fix_iframe_titles'     => 0,
		'fix_core_form_labels'  => 0,
		'fix_lang_attribute'    => 0,
		'fix_viewport_zoom'     => 0,
		'fix_tabindex_cleanup'  => 0,

		// --- Housekeeping --------------------------------------------------------
		'keep_history_days'    => 0,       // 0 = keep the latest result per page only.
	);
}

/**
 * Current settings, with defaults filled in for keys a stored option predates.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_options() {
	if ( ! isset( $GLOBALS['lynbro_a11y_options_cache'] ) || ! is_array( $GLOBALS['lynbro_a11y_options_cache'] ) ) {
		$stored = get_option( LYNBRO_A11Y_OPTION, array() );
		$GLOBALS['lynbro_a11y_options_cache'] = wp_parse_args(
			is_array( $stored ) ? $stored : array(),
			lynbro_a11y_default_options()
		);
	}
	return $GLOBALS['lynbro_a11y_options_cache'];
}

/**
 * Forget the cached settings, so the next read comes from the database.
 *
 * @return void
 */
function lynbro_a11y_flush_options_cache() {
	unset( $GLOBALS['lynbro_a11y_options_cache'] );
}

/**
 * One setting.
 *
 * @param string $key     Setting name.
 * @param mixed  $default Value when the setting is unknown.
 * @return mixed
 */
function lynbro_a11y_option( $key, $default = null ) {
	$options = lynbro_a11y_options();
	return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
}

/**
 * Clamp and clean a settings array coming from the settings form.
 *
 * Every numeric setting is clamped to a range the site can survive; a value
 * outside it is a typo, not an instruction.
 *
 * @param array<string,mixed> $input Raw input.
 * @return array<string,mixed>
 */
function lynbro_a11y_sanitize_options( $input ) {
	$defaults = lynbro_a11y_default_options();
	$out      = array();
	$input    = is_array( $input ) ? $input : array();

	$ints = array(
		'tick_seconds'       => array( 3, 60 ),
		'batch_pages'        => array( 1, 50 ),
		'memory_ceiling_pct' => array( 50, 95 ),
		'request_timeout'    => array( 3, 60 ),
		'keep_history_days'  => array( 0, 3650 ),
	);
	foreach ( $ints as $key => $range ) {
		$value        = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $defaults[ $key ];
		$out[ $key ]  = max( $range[0], min( $range[1], $value ) );
	}

	$out['scan_schedule'] = in_array( ( isset( $input['scan_schedule'] ) ? $input['scan_schedule'] : '' ), array( 'off', 'daily', 'weekly' ), true )
		? $input['scan_schedule']
		: $defaults['scan_schedule'];

	foreach ( array( 'scan_on_save', 'include_front_page', 'include_terms', 'include_archives' ) as $key ) {
		$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}

	foreach ( array_keys( lynbro_a11y_fix_definitions() ) as $key ) {
		$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}

	$out['fix_skip_target'] = isset( $input['fix_skip_target'] )
		? substr( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $input['fix_skip_target'] ), 0, 64 )
		: $defaults['fix_skip_target'];
	if ( '' === $out['fix_skip_target'] ) {
		$out['fix_skip_target'] = $defaults['fix_skip_target'];
	}

	$colour                    = isset( $input['fix_focus_colour'] ) ? sanitize_hex_color( $input['fix_focus_colour'] ) : '';
	$out['fix_focus_colour']   = $colour ? $colour : $defaults['fix_focus_colour'];

	$out['post_types'] = isset( $input['post_types'] )
		? implode( "\n", array_filter( array_map( 'sanitize_key', lynbro_a11y_lines( $input['post_types'] ) ) ) )
		: '';

	foreach ( array( 'extra_urls', 'exclude_urls' ) as $key ) {
		$lines = lynbro_a11y_lines( isset( $input[ $key ] ) ? $input[ $key ] : '' );
		$clean = array();
		foreach ( $lines as $line ) {
			$line = esc_url_raw( $line );
			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}
		$out[ $key ] = implode( "\n", $clean );
	}

	$known   = array_keys( lynbro_a11y_rules() );
	$ignored = array();
	foreach ( lynbro_a11y_lines( isset( $input['ignored_rules'] ) ? $input['ignored_rules'] : '' ) as $line ) {
		$line = sanitize_key( $line );
		if ( in_array( $line, $known, true ) ) {
			$ignored[] = $line;
		}
	}
	$out['ignored_rules'] = implode( "\n", array_unique( $ignored ) );

	return $out;
}

/**
 * Split a textarea into trimmed, non-empty lines.
 *
 * @param mixed $value Raw textarea value.
 * @return array<int,string>
 */
function lynbro_a11y_lines( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( "\n", $value );
	}
	$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	$lines = array_map( 'trim', explode( "\n", $value ) );
	return array_values( array_filter( $lines, static function ( $line ) {
		return '' !== $line;
	} ) );
}

/**
 * Rule ids the owner has chosen not to be told about.
 *
 * Ignoring a rule hides it from the report; it never changes what the scanner
 * looks for, so switching it back on shows the issues that were there all along
 * without a rescan being required for the counts to make sense.
 *
 * @return array<int,string>
 */
function lynbro_a11y_ignored_rules() {
	return lynbro_a11y_lines( lynbro_a11y_option( 'ignored_rules' ) );
}

/**
 * Make sure the scheduled scan matches the setting.
 *
 * @return void
 */
function lynbro_a11y_schedule_events() {
	$schedule = (string) lynbro_a11y_option( 'scan_schedule' );

	if ( 'off' === $schedule ) {
		wp_clear_scheduled_hook( 'lynbro_a11y_cron_scan' );
	} else {
		$existing = wp_get_scheduled_event( 'lynbro_a11y_cron_scan' );
		if ( ! $existing || $existing->schedule !== $schedule ) {
			wp_clear_scheduled_hook( 'lynbro_a11y_cron_scan' );
			wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, 'lynbro_a11y_cron_scan' );
		}
	}

	// The tick that carries a running scan forward when nobody is watching the
	// progress bar. Cleared by the queue itself once the scan is finished.
	if ( lynbro_a11y_scan_is_running() && ! wp_next_scheduled( 'lynbro_a11y_cron_tick' ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'lynbro_a11y_cron_tick' );
	}
}
