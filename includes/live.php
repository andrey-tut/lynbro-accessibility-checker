<?php
/**
 * The part of the scan that has to happen in a browser.
 *
 * Contrast, a visible focus ring and the size of a tap target are not in the
 * markup. They are the result of a stylesheet, a font, a media query and a
 * viewport, and any attempt to work them out from HTML alone produces confident
 * nonsense. So for these three the plugin does what an auditor does: it opens
 * the page and measures what the browser actually painted.
 *
 * How this is NOT done matters as much:
 *
 *   • The measuring script is never sent to a visitor. It is enqueued only for
 *     a signed-in administrator who has clicked "check this page", carrying a
 *     nonce for that one URL.
 *   • Nothing is added to the page for anybody else. This is not an overlay,
 *     and there is no widget, no toolbar and no third-party script — which is
 *     the whole category the FTC has been fining.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * The query argument that asks for a live check.
 */
define( 'LYNBRO_A11Y_LIVE_ARG', 'lynbro-a11y-check' );

/**
 * The query argument that names the width a batch frame was opened at.
 */
define( 'LYNBRO_A11Y_VIEWPORT_ARG', 'lynbro-a11y-vp' );

/**
 * The query argument that says the page is in a frame of the browser-check screen.
 */
define( 'LYNBRO_A11Y_BATCH_ARG', 'lynbro-a11y-batch' );

/**
 * Below this window width a check opened by hand counts as the phone width.
 * The same breakpoint WordPress itself switches its admin to the phone layout at.
 */
define( 'LYNBRO_A11Y_PHONE_MAX', 782 );

/**
 * scheme://host[:port] of a URL, for postMessage.
 *
 * @param string $url URL.
 * @return string
 */
function lynbro_a11y_origin( $url ) {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return '';
	}
	return strtolower( $parts['scheme'] . '://' . $parts['host'] ) . ( ! empty( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
}

/**
 * The id of a page already in the report, without adding one.
 *
 * @param string $url Page URL.
 * @return int 0 when the page is not in the report.
 */
function lynbro_a11y_find_page_id( $url ) {
	global $wpdb;
	$url = esc_url_raw( $url );
	if ( '' === $url ) {
		return 0;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s', lynbro_a11y_table_pages(), md5( $url ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * A URL that opens a page with the live check running on it.
 *
 * @param string $url Page to check.
 * @return string
 */
function lynbro_a11y_live_check_url( $url ) {
	return add_query_arg(
		array(
			LYNBRO_A11Y_LIVE_ARG => wp_create_nonce( 'lynbro_a11y_live' ),
		),
		$url
	);
}

/**
 * Is this request an administrator asking for a live check?
 *
 * @return bool
 */
function lynbro_a11y_live_check_requested() {
	if ( is_admin() || ! is_user_logged_in() ) {
		return false;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	if ( ! isset( $_GET[ LYNBRO_A11Y_LIVE_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the value IS the nonce, verified next.
		return false;
	}

	$nonce = sanitize_text_field( wp_unslash( $_GET[ LYNBRO_A11Y_LIVE_ARG ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified on the next line.

	return (bool) wp_verify_nonce( $nonce, 'lynbro_a11y_live' );
}

/**
 * Enqueue the measuring script, for that one administrator, on that one view.
 *
 * @return void
 */
function lynbro_a11y_enqueue_live_check() {
	if ( ! lynbro_a11y_live_check_requested() ) {
		return;
	}

	wp_enqueue_script(
		'lynbro-a11y-live',
		LYNBRO_A11Y_URL . 'public/live-check.js',
		array( 'wp-api-fetch' ),
		lynbro_a11y_asset_ver( 'public/live-check.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	// Without our own arguments, so the check lands on the page's own row
	// instead of a new one per run.
	$url = home_url( remove_query_arg( array( LYNBRO_A11Y_LIVE_ARG, LYNBRO_A11Y_VIEWPORT_ARG, LYNBRO_A11Y_BATCH_ARG ), add_query_arg( array() ) ) );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the request was verified in lynbro_a11y_live_check_requested(); these only pick a mode.
	$viewport = isset( $_GET[ LYNBRO_A11Y_VIEWPORT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ LYNBRO_A11Y_VIEWPORT_ARG ] ) ) : '';
	$batch    = ! empty( $_GET[ LYNBRO_A11Y_BATCH_ARG ] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$found   = lynbro_a11y_find_page_id( $url );
	$targets = $found ? lynbro_a11y_render_targets( $found ) : array();

	wp_localize_script(
		'lynbro-a11y-live',
		'lynbroA11yLive',
		array(
			'url'      => $url,
			// desktop | phone when the admin screen opened this page in a frame of
			// that width; empty when an administrator opened it in their own window,
			// and the script then goes by the window's width.
			'viewport' => in_array( $viewport, array( 'desktop', 'phone' ), true ) ? $viewport : '',
			// The phone frame of a batch only looks elements up; contrast, focus and
			// target size are measured once, in the desktop frame.
			// '1' / '0', not true / false: wp_localize_script turns false into an
			// empty string, which the script would read as "not said".
			'measure'  => ( $batch && 'phone' === $viewport ) ? '0' : '1',
			'batch'    => $batch ? '1' : '0',
			'adminOrigin' => lynbro_a11y_origin( admin_url() ),
			'targets'  => $targets,
			'phoneMax' => LYNBRO_A11Y_PHONE_MAX,
			'endpoint' => rest_url( 'lynbro-a11y/v1/live' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'report'   => admin_url( 'admin.php?page=lynbro-accessibility-report' ),
			'strings'  => array(
				'working'  => __( 'Measuring contrast, focus and target sizes…', 'lynbro-accessibility-checker' ),
				'saved'    => __( 'Done. The results are on the report.', 'lynbro-accessibility-checker' ),
				'failed'   => __( 'The results could not be saved. Reload the page to try again.', 'lynbro-accessibility-checker' ),
				'openReport' => __( 'Open the report', 'lynbro-accessibility-checker' ),
				'panel'    => __( 'Lynbro Accessibility — live check', 'lynbro-accessibility-checker' ),
				'close'    => __( 'Close', 'lynbro-accessibility-checker' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'lynbro_a11y_enqueue_live_check', 30 );

/**
 * Register the endpoint the measuring script reports back to.
 *
 * @return void
 */
function lynbro_a11y_register_rest() {
	register_rest_route(
		'lynbro-a11y/v1',
		'/live',
		array(
			'methods'             => 'POST',
			'callback'            => 'lynbro_a11y_rest_live',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
			'args'                => array(
				'url' => array(
					'type'     => 'string',
					'required' => true,
				),
				'findings' => array(
					'type'     => 'array',
					'required' => true,
				),
				'totals'   => array(
					'type'     => 'object',
					'required' => false,
				),
				'viewport' => array(
					'type'     => 'string',
					'required' => false,
				),
				'measure'  => array(
					'type'     => 'boolean',
					'required' => false,
				),
				'render'   => array(
					'type'     => 'object',
					'required' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'lynbro_a11y_register_rest' );

/**
 * Store what the browser measured.
 *
 * The findings arrive from a page we do not control the contents of, so every
 * field is treated as text typed by a stranger: the rule id has to be one of
 * ours, and the rest is sanitised and cut to length before it goes anywhere
 * near the database or a screen.
 *
 * @param WP_REST_Request $request The request.
 * @return WP_REST_Response|WP_Error
 */
function lynbro_a11y_rest_live( $request ) {
	$url = esc_url_raw( (string) $request->get_param( 'url' ) );
	if ( '' === $url ) {
		return new WP_Error( 'lynbro_a11y_bad_url', __( 'That is not a URL.', 'lynbro-accessibility-checker' ), array( 'status' => 400 ) );
	}

	// Only pages on this site. Without this the endpoint would happily record
	// findings against any address an administrator's browser was pointed at.
	$home = wp_parse_url( home_url(), PHP_URL_HOST );
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host || ! $home || strtolower( $host ) !== strtolower( $home ) ) {
		return new WP_Error( 'lynbro_a11y_foreign_url', __( 'That page is not on this site.', 'lynbro-accessibility-checker' ), array( 'status' => 400 ) );
	}

	$page_id = lynbro_a11y_page_id( $url );
	if ( ! $page_id ) {
		return new WP_Error( 'lynbro_a11y_no_page', __( 'That page could not be recorded.', 'lynbro-accessibility-checker' ), array( 'status' => 400 ) );
	}

	global $wpdb;

	// What the browser saw of the markup findings. Recorded first, so the
	// counts written below already leave out what it found off screen.
	$viewport = sanitize_key( (string) $request->get_param( 'viewport' ) );
	$looked   = 0;
	if ( in_array( $viewport, array( 'desktop', 'phone' ), true ) && is_array( $request->get_param( 'render' ) ) ) {
		$states = array();
		foreach ( $request->get_param( 'render' ) as $id => $state ) {
			$states[ (int) $id ] = sanitize_key( (string) $state );
		}
		$looked = lynbro_a11y_record_render( $page_id, $viewport, $states );
		lynbro_a11y_refresh_offscreen( $page_id );
	}

	// The phone frame of a batch measures nothing: it only looked elements up.
	if ( false === $request->get_param( 'measure' ) ) {
		lynbro_a11y_refresh_page_counts( $page_id );
		return rest_ensure_response(
			array(
				'stored'  => 0,
				'looked'  => $looked,
				'page_id' => $page_id,
				'report'  => admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . $page_id ),
			)
		);
	}

	$allowed  = array( 'contrast_text_low', 'focus_not_visible', 'target_too_small' );
	$findings = array();

	foreach ( (array) $request->get_param( 'findings' ) as $raw ) {
		if ( ! is_array( $raw ) ) {
			continue;
		}
		$rule = isset( $raw['rule'] ) ? sanitize_key( $raw['rule'] ) : '';
		if ( ! in_array( $rule, $allowed, true ) ) {
			continue;
		}
		$findings[] = array(
			'rule'     => $rule,
			'selector' => substr( sanitize_text_field( isset( $raw['selector'] ) ? $raw['selector'] : '' ), 0, 255 ),
			'snippet'  => substr( sanitize_text_field( isset( $raw['snippet'] ) ? $raw['snippet'] : '' ), 0, 500 ),
			'context'  => substr( sanitize_text_field( isset( $raw['context'] ) ? $raw['context'] : '' ), 0, 255 ),
		);
	}

	lynbro_a11y_store_findings( $page_id, $findings, 'browser' );

	// The browser sends the full count as well as the listed detail.
	$note = lynbro_a11y_truncation_note( $findings, (array) $request->get_param( 'totals' ), $allowed );
	$page_update = array( 'browser_at' => current_time( 'mysql' ) );
	if ( '' !== $note ) {
		$page_update['note'] = substr( trim( $note ), 0, 255 );
	}
	$wpdb->update( lynbro_a11y_table_pages(), $page_update, array( 'id' => $page_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$counts = lynbro_a11y_page_counts( $page_id );
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		lynbro_a11y_table_pages(),
		array(
			'errors'   => $counts['error'],
			'warnings' => $counts['warning'],
			'reviews'  => $counts['review'],
		),
		array( 'id' => $page_id )
	);

	return rest_ensure_response(
		array(
			'stored'  => count( $findings ),
			'looked'  => $looked,
			'page_id' => $page_id,
			'report'  => admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . $page_id ),
		)
	);
}

/**
 * What to say when a page held more of one fault than we listed.
 *
 * The browser bounds how much detail it sends, because one bad colour pair in a
 * theme produces a finding on every text node of the page. The count is not
 * bounded, and the difference is said out loud: a truncated list that looks
 * complete is the same lie as a compliance percentage, only smaller.
 *
 * Pulled out of the endpoint so it can be tested without a request.
 *
 * @param array<int,array<string,mixed>> $findings What was listed.
 * @param array<string,mixed>            $totals   What was counted, by rule.
 * @param array<int,string>              $allowed  Rules the browser may report.
 * @return string One sentence per truncated rule, or an empty string.
 */
function lynbro_a11y_truncation_note( $findings, $totals, $allowed ) {
	$note = '';

	foreach ( $allowed as $rule ) {
		$counted = isset( $totals[ $rule ] ) ? (int) $totals[ $rule ] : 0;
		$listed  = 0;
		foreach ( (array) $findings as $finding ) {
			if ( isset( $finding['rule'] ) && $finding['rule'] === $rule ) {
				$listed++;
			}
		}
		if ( $counted <= $listed ) {
			continue;
		}
		$definition = lynbro_a11y_rule( $rule );
		$note      .= sprintf(
			/* translators: 1: the problem, 2: how many times it occurs, 3: how many are listed. */
			__( '%1$s: %2$d on this page, the first %3$d listed. ', 'lynbro-accessibility-checker' ),
			isset( $definition['title'] ) ? $definition['title'] : $rule,
			$counted,
			$listed
		);
	}

	return trim( $note );
}
