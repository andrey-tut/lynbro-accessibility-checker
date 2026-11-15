<?php
/**
 * Re-check a page when it is saved.
 *
 * A full scan tells you where the site stands; this keeps it there. When a
 * published post or page is saved, that one URL is checked again a few seconds
 * later in the background, so the report and the "Accessibility" column in the
 * post list show what is on the page now, not what was there at the last full
 * scan.
 *
 * The check runs from WP-Cron rather than inside the save request: the scanner
 * fetches the page over HTTP, and doing that while the editor waits would make
 * every save slower. Saving the same post several times in a row queues one
 * check, not several.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Seconds between a save and the check. Long enough for caches and the
 * "update" redirect to settle, short enough that the result is there when the
 * editor looks.
 */
const LYNBRO_A11Y_SAVE_DELAY = 10;

/**
 * Queue a check for a post that has just been saved.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Old status.
 * @param WP_Post $post       Post.
 * @return void
 */
function lynbro_a11y_on_status_change( $new_status, $old_status, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return;
	}
	if ( 'publish' === $old_status && 'publish' !== $new_status ) {
		lynbro_a11y_forget_post( $post->ID );
		return;
	}
	if ( 'publish' !== $new_status || ! lynbro_a11y_option( 'scan_on_save' ) ) {
		return;
	}
	if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
		return;
	}
	if ( ! in_array( $post->post_type, lynbro_a11y_scan_post_types(), true ) ) {
		return;
	}
	if ( ! lynbro_a11y_post_target( $post->ID ) ) {
		return;
	}

	$args = array( (int) $post->ID );
	if ( ! wp_next_scheduled( 'lynbro_a11y_scan_post', $args ) ) {
		wp_schedule_single_event( time() + LYNBRO_A11Y_SAVE_DELAY, 'lynbro_a11y_scan_post', $args );
	}
}
add_action( 'transition_post_status', 'lynbro_a11y_on_status_change', 20, 3 );

/**
 * A deleted post leaves the report with it.
 *
 * @param int $post_id Post id.
 * @return void
 */
function lynbro_a11y_on_delete_post( $post_id ) {
	lynbro_a11y_forget_post( (int) $post_id );
}
add_action( 'deleted_post', 'lynbro_a11y_on_delete_post' );

/**
 * The scan target for one post, or null when it should not be scanned.
 *
 * Applies the same rules as the full scan: published, public, has a permalink,
 * not excluded on the settings screen.
 *
 * @param int $post_id Post id.
 * @return array<string,mixed>|null
 */
function lynbro_a11y_post_target( $post_id ) {
	$post_id = (int) $post_id;
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return null;
	}
	$url = get_permalink( $post_id );
	if ( ! $url ) {
		return null;
	}

	$target = array(
		'url'         => $url,
		'object_type' => 'post',
		'object_id'   => $post_id,
		'label'       => get_the_title( $post_id ),
	);

	return lynbro_a11y_apply_exclusions( array( $target ) ) ? $target : null;
}

/**
 * Check one post now. Runs from WP-Cron.
 *
 * @param int $post_id Post id.
 * @return array<string,mixed>|null What lynbro_a11y_scan_target() returned.
 */
function lynbro_a11y_scan_post( $post_id ) {
	$target = lynbro_a11y_post_target( $post_id );
	if ( ! $target ) {
		return null;
	}
	return lynbro_a11y_scan_target( $target );
}
add_action( 'lynbro_a11y_scan_post', 'lynbro_a11y_scan_post' );

/**
 * The stored result for a post, if it has been scanned.
 *
 * @param int $post_id Post id.
 * @return array<string,mixed>|null Row from the pages table.
 */
function lynbro_a11y_page_for_post( $post_id ) {
	global $wpdb;

	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT * FROM %i WHERE object_type = %s AND object_id = %d ORDER BY scanned_at DESC LIMIT 1',
			lynbro_a11y_table_pages(),
			'post',
			(int) $post_id
		),
		ARRAY_A
	);

	return $row ? $row : null;
}
