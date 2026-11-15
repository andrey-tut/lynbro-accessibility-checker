<?php
/**
 * The scan runner and the small admin-post actions.
 *
 * Pressing "Scan the site" does not run a scan in one request. It runs a series
 * of short ticks, each of which leaves the queue in a state the cron could pick
 * up if the tab were closed. That is why the button cannot time out and cannot
 * leave half a scan behind.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Refuse anything that is not an administrator with a fresh nonce.
 *
 * @return void
 */
function lynbro_a11y_ajax_guard() {
	check_ajax_referer( 'lynbro_a11y_scan', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'lynbro-accessibility-checker' ) ), 403 );
	}
}

/**
 * Start a scan.
 *
 * @return void
 */
function lynbro_a11y_ajax_start() {
	lynbro_a11y_ajax_guard();
	lynbro_a11y_start_scan();
	wp_send_json_success( lynbro_a11y_progress() );
}
add_action( 'wp_ajax_lynbro_a11y_start', 'lynbro_a11y_ajax_start' );

/**
 * Run one tick.
 *
 * @return void
 */
function lynbro_a11y_ajax_tick() {
	lynbro_a11y_ajax_guard();
	$budget = max( 3, min( 30, (int) lynbro_a11y_option( 'tick_seconds' ) ) );
	lynbro_a11y_tick( $budget );
	wp_send_json_success( lynbro_a11y_progress() );
}
add_action( 'wp_ajax_lynbro_a11y_tick', 'lynbro_a11y_ajax_tick' );

/**
 * Stop a scan.
 *
 * @return void
 */
function lynbro_a11y_ajax_stop() {
	lynbro_a11y_ajax_guard();
	lynbro_a11y_stop_scan();
	wp_send_json_success( lynbro_a11y_progress() );
}
add_action( 'wp_ajax_lynbro_a11y_stop', 'lynbro_a11y_ajax_stop' );

/**
 * Hide, or stop hiding, one issue.
 *
 * @return void
 */
function lynbro_a11y_handle_ignore() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce for this exact issue is checked next.
	$issue_id = isset( $_POST['issue'] ) ? absint( $_POST['issue'] ) : 0;
	check_admin_referer( 'lynbro_a11y_ignore_' . $issue_id );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
	$ignored    = ! empty( $_POST['ignored'] );
	$everywhere = isset( $_POST['scope'] ) && 'everywhere' === sanitize_key( wp_unslash( $_POST['scope'] ) );
	$note       = isset( $_POST['note'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['note'] ) ), 0, 255 ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( $issue_id ) {
		lynbro_a11y_set_issue_ignored( $issue_id, $ignored, $note, $everywhere );
	}

	$referer = wp_get_referer();
	wp_safe_redirect( $referer ? $referer : admin_url( 'admin.php?page=lynbro-accessibility-report' ) );
	exit;
}
add_action( 'admin_post_lynbro_a11y_ignore', 'lynbro_a11y_handle_ignore' );

/**
 * Hide, or report again, one rule on every page.
 *
 * @return void
 */
function lynbro_a11y_handle_ignore_rule() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce for this exact rule is checked next.
	$rule = isset( $_POST['rule'] ) ? sanitize_key( wp_unslash( $_POST['rule'] ) ) : '';
	check_admin_referer( 'lynbro_a11y_ignore_rule_' . $rule );

	if ( lynbro_a11y_rule( $rule ) ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked above.
		lynbro_a11y_set_rule_hidden( $rule, ! empty( $_POST['hidden'] ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=lynbro-accessibility-report&rule=' . rawurlencode( $rule ) ) );
	exit;
}
add_action( 'admin_post_lynbro_a11y_ignore_rule', 'lynbro_a11y_handle_ignore_rule' );

/**
 * Delete every scan result.
 *
 * @return void
 */
function lynbro_a11y_handle_clear() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}
	check_admin_referer( 'lynbro_a11y_clear' );

	lynbro_a11y_clear_results();
	delete_option( LYNBRO_A11Y_STATE );
	delete_option( LYNBRO_A11Y_LIST );

	wp_safe_redirect( admin_url( 'admin.php?page=lynbro-accessibility-settings&lynbro_a11y_cleared=1' ) );
	exit;
}
add_action( 'admin_post_lynbro_a11y_clear', 'lynbro_a11y_handle_clear' );

/**
 * Check one page again, now.
 *
 * @return void
 */
function lynbro_a11y_handle_rescan_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the nonce for this exact page is checked next.
	$page_id = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
	check_admin_referer( 'lynbro_a11y_rescan_' . $page_id );

	$page = $page_id ? lynbro_a11y_get_page( $page_id ) : null;
	if ( $page ) {
		lynbro_a11y_scan_target(
			array(
				'url'         => (string) $page['url'],
				'object_type' => (string) $page['object_type'],
				'object_id'   => (int) $page['object_id'],
				'label'       => (string) $page['label'],
			)
		);
	}

	wp_safe_redirect( admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . $page_id . '&lynbro_a11y_rechecked=1' ) );
	exit;
}
add_action( 'admin_post_lynbro_a11y_rescan_page', 'lynbro_a11y_handle_rescan_page' );
