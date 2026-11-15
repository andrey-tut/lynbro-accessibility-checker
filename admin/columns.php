<?php
/**
 * The "Accessibility" column in the post and page lists.
 *
 * The report answers "what is wrong with the site"; an editor working through
 * their own pages asks "is this one all right". The column answers that where
 * they already are: the number of failures and likely failures on each page
 * from its last check, linked to that page's report, and a plain "not checked"
 * when the scanner could not read it.
 *
 * One query per screen, not one per row: the results for every post on the
 * screen are read together the first time the column is drawn.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the column to every post type the scan covers.
 *
 * @return void
 */
function lynbro_a11y_register_columns() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	foreach ( lynbro_a11y_scan_post_types() as $type ) {
		add_filter( "manage_{$type}_posts_columns", 'lynbro_a11y_add_column' );
		add_action( "manage_{$type}_posts_custom_column", 'lynbro_a11y_render_column', 10, 2 );
	}
}
add_action( 'admin_init', 'lynbro_a11y_register_columns' );

/**
 * Insert the column before the date.
 *
 * @param array<string,string> $columns Columns.
 * @return array<string,string>
 */
function lynbro_a11y_add_column( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$out['lynbro_a11y'] = __( 'Accessibility', 'lynbro-accessibility-checker' );
		}
		$out[ $key ] = $label;
	}
	if ( ! isset( $out['lynbro_a11y'] ) ) {
		$out['lynbro_a11y'] = __( 'Accessibility', 'lynbro-accessibility-checker' );
	}
	return $out;
}

/**
 * Results for every post on the current list screen, read once.
 *
 * @return array<int,array<string,mixed>> Keyed by post id.
 */
function lynbro_a11y_column_rows() {
	static $rows = null;
	if ( null !== $rows ) {
		return $rows;
	}

	global $wp_query, $wpdb;
	$rows = array();

	$ids = array();
	if ( $wp_query instanceof WP_Query ) {
		foreach ( (array) $wp_query->posts as $post ) {
			$ids[] = (int) ( is_object( $post ) ? $post->ID : $post );
		}
	}
	$ids = array_values( array_filter( array_unique( $ids ) ) );
	if ( ! $ids ) {
		return $rows;
	}

	$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
	$results      = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// One %d per post on the screen, built above; the sniff cannot count a dynamic list.
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			"SELECT id, object_id, scanned_at, http_status, errors, warnings, note FROM %i WHERE object_type = %s AND object_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			array_merge( array( lynbro_a11y_table_pages(), 'post' ), $ids )
		),
		ARRAY_A
	);

	foreach ( (array) $results as $row ) {
		$post_id = (int) $row['object_id'];
		// Keep the newest when a post has been scanned under two URLs (a changed slug).
		if ( ! isset( $rows[ $post_id ] ) || (string) $row['scanned_at'] > (string) $rows[ $post_id ]['scanned_at'] ) {
			$rows[ $post_id ] = $row;
		}
	}

	return $rows;
}

/**
 * Draw one cell.
 *
 * @param string $column  Column key.
 * @param int    $post_id Post id.
 * @return void
 */
function lynbro_a11y_render_column( $column, $post_id ) {
	if ( 'lynbro_a11y' !== $column ) {
		return;
	}

	$rows = lynbro_a11y_column_rows();
	$row  = isset( $rows[ (int) $post_id ] ) ? $rows[ (int) $post_id ] : null;

	if ( ! $row || empty( $row['scanned_at'] ) ) {
		if ( 'publish' === get_post_status( $post_id ) && wp_next_scheduled( 'lynbro_a11y_scan_post', array( (int) $post_id ) ) ) {
			echo '<span class="lynbro-a11y-col lynbro-a11y-col--wait">' . esc_html__( 'Checking…', 'lynbro-accessibility-checker' ) . '</span>';
		} else {
			echo '<span class="lynbro-a11y-col lynbro-a11y-col--none" aria-label="' . esc_attr__( 'Not scanned yet', 'lynbro-accessibility-checker' ) . '">—</span>';
		}
		return;
	}

	$url = admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . (int) $row['id'] );

	$status = (int) $row['http_status'];
	if ( $status < 200 || $status >= 400 || '' !== (string) $row['note'] ) {
		printf(
			'<a class="lynbro-a11y-col lynbro-a11y-col--failed" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Not checked', 'lynbro-accessibility-checker' )
		);
		return;
	}

	$errors   = (int) $row['errors'];
	$warnings = (int) $row['warnings'];

	if ( 0 === $errors && 0 === $warnings ) {
		printf(
			'<a class="lynbro-a11y-col lynbro-a11y-col--ok" href="%1$s">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'No findings', 'lynbro-accessibility-checker' )
		);
		return;
	}

	$parts = array();
	if ( $errors ) {
		/* translators: %s: number of failures. */
		$parts[] = sprintf( _n( '%s failure', '%s failures', $errors, 'lynbro-accessibility-checker' ), number_format_i18n( $errors ) );
	}
	if ( $warnings ) {
		/* translators: %s: number of likely failures. */
		$parts[] = sprintf( _n( '%s likely', '%s likely', $warnings, 'lynbro-accessibility-checker' ), number_format_i18n( $warnings ) );
	}

	printf(
		'<a class="lynbro-a11y-col %1$s" href="%2$s">%3$s</a>',
		esc_attr( $errors ? 'lynbro-a11y-col--error' : 'lynbro-a11y-col--warning' ),
		esc_url( $url ),
		esc_html( implode( ' · ', $parts ) )
	);
}

/**
 * A few lines of style for the column, only on post list screens.
 *
 * @param string $hook Admin page hook.
 * @return void
 */
function lynbro_a11y_column_styles( $hook ) {
	if ( ! in_array( $hook, array( 'edit.php', 'post.php' ), true ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_register_style( 'lynbro-a11y-columns', false, array(), LYNBRO_A11Y_VERSION );
	wp_enqueue_style( 'lynbro-a11y-columns' );
	wp_add_inline_style(
		'lynbro-a11y-columns',
		'.column-lynbro_a11y{width:9em}.lynbro-a11y-col--error{color:#b32d2e;font-weight:600}.lynbro-a11y-col--warning{color:#8a4b00}.lynbro-a11y-col--ok{color:#14532d}.lynbro-a11y-col--failed{color:#50575e;font-style:italic}.lynbro-a11y-col--none,.lynbro-a11y-col--wait{color:#646970}.lynbro-a11y-box{margin:0 0 8px 1.2em;list-style:disc}'
	);
}
add_action( 'admin_enqueue_scripts', 'lynbro_a11y_column_styles' );

/**
 * The same result in the editor, in a small box at the side.
 *
 * Shown in the block editor and the classic editor alike. It says what the
 * last check found on the published page and links to the report; for an
 * unpublished post it says that the check happens on publish.
 *
 * @param string $post_type Post type of the screen.
 * @return void
 */
function lynbro_a11y_register_meta_box( $post_type ) {
	if ( ! current_user_can( 'manage_options' ) || ! in_array( $post_type, lynbro_a11y_scan_post_types(), true ) ) {
		return;
	}
	add_meta_box(
		'lynbro-a11y-result',
		__( 'Accessibility', 'lynbro-accessibility-checker' ),
		'lynbro_a11y_render_meta_box',
		$post_type,
		'side',
		'low'
	);
}
add_action( 'add_meta_boxes', 'lynbro_a11y_register_meta_box' );

/**
 * Draw the editor box.
 *
 * @param WP_Post $post Post being edited.
 * @return void
 */
function lynbro_a11y_render_meta_box( $post ) {
	if ( 'publish' !== $post->post_status ) {
		echo '<p>' . esc_html__( 'This page is checked when it is published.', 'lynbro-accessibility-checker' ) . '</p>';
		return;
	}

	$row = lynbro_a11y_page_for_post( $post->ID );

	if ( wp_next_scheduled( 'lynbro_a11y_scan_post', array( (int) $post->ID ) ) ) {
		echo '<p>' . esc_html__( 'Being checked after your last save. Reload in a moment to see the result.', 'lynbro-accessibility-checker' ) . '</p>';
	}

	if ( ! $row || empty( $row['scanned_at'] ) ) {
		echo '<p>' . esc_html__( 'Not checked yet.', 'lynbro-accessibility-checker' ) . '</p>';
		return;
	}

	$status = (int) $row['http_status'];
	if ( $status < 200 || $status >= 400 || '' !== (string) $row['note'] ) {
		echo '<p>' . esc_html__( 'The last check could not read this page.', 'lynbro-accessibility-checker' ) . '</p>';
	} else {
		echo '<ul class="lynbro-a11y-box">';
		printf(
			'<li>%s</li>',
			/* translators: %s: number of failures. */
			esc_html( sprintf( _n( '%s failure', '%s failures', (int) $row['errors'], 'lynbro-accessibility-checker' ), number_format_i18n( (int) $row['errors'] ) ) )
		);
		printf(
			'<li>%s</li>',
			/* translators: %s: number of likely failures. */
			esc_html( sprintf( _n( '%s likely failure', '%s likely failures', (int) $row['warnings'], 'lynbro-accessibility-checker' ), number_format_i18n( (int) $row['warnings'] ) ) )
		);
		printf(
			'<li>%s</li>',
			/* translators: %s: number of items that need a person to look. */
			esc_html( sprintf( _n( '%s for a person to look at', '%s for a person to look at', (int) $row['reviews'], 'lynbro-accessibility-checker' ), number_format_i18n( (int) $row['reviews'] ) ) )
		);
		echo '</ul>';
	}

	printf(
		'<p class="lynbro-muted">%s</p>',
		esc_html(
			sprintf(
				/* translators: %s: date and time of the last check. */
				__( 'Last checked %s.', 'lynbro-accessibility-checker' ),
				mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['scanned_at'] )
			)
		)
	);
	printf(
		'<p><a href="%1$s">%2$s</a></p>',
		esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . (int) $row['id'] ) ),
		esc_html__( 'Open the report for this page', 'lynbro-accessibility-checker' )
	);
}
