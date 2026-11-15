<?php
/**
 * Exporting the report.
 *
 * The whole report, every issue, no row limit. A capped export is how the same
 * plugin family gets rejected by the plugin review team, and it is also just
 * bad manners: the data is the site owner's.
 *
 * Registered on admin_init rather than from a page callback, because by the
 * time a screen renders WordPress has already sent its headers and a download
 * cannot be started any more.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stream the report as CSV or JSON.
 *
 * @return void
 */
function lynbro_a11y_handle_export() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}
	check_admin_referer( 'lynbro_a11y_export' );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked above.
	$format = isset( $_GET['format'] ) && 'json' === $_GET['format'] ? 'json' : 'csv';

	$rows = lynbro_a11y_export_rows();

	nocache_headers();
	$filename = 'accessibility-report-' . gmdate( 'Y-m-d' ) . '.' . $format;

	if ( 'json' === $format ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		echo wp_json_encode(
			array(
				'site'      => home_url( '/' ),
				'generated' => current_time( 'c' ),
				'standard'  => 'WCAG 2.2 AA',
				'note'      => __( 'Automated testing covers roughly a third of WCAG. The manual checks recorded alongside this report are the rest.', 'lynbro-accessibility-checker' ),
				'manual'    => lynbro_a11y_export_manual(),
				'issues'    => $rows,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
		exit;
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$out = fopen( 'php://output', 'w' );

	// Excel opens a UTF-8 CSV as Latin-1 unless it finds a byte order mark, and
	// a Danish report full of Ã¦ is not a report anybody will thank you for.
	fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

	fputcsv(
		$out,
		array(
			__( 'Page', 'lynbro-accessibility-checker' ),
			__( 'Address', 'lynbro-accessibility-checker' ),
			__( 'Severity', 'lynbro-accessibility-checker' ),
			__( 'WCAG', 'lynbro-accessibility-checker' ),
			__( 'Level', 'lynbro-accessibility-checker' ),
			__( 'Problem', 'lynbro-accessibility-checker' ),
			__( 'Detail', 'lynbro-accessibility-checker' ),
			__( 'Element', 'lynbro-accessibility-checker' ),
			__( 'Markup', 'lynbro-accessibility-checker' ),
			__( 'How to fix it', 'lynbro-accessibility-checker' ),
			__( 'First seen', 'lynbro-accessibility-checker' ),
		)
	);

	foreach ( $rows as $row ) {
		fputcsv(
			$out,
			array_map(
				'lynbro_a11y_csv_cell',
				array(
					$row['page'],
					$row['url'],
					$row['severity_label'],
					$row['wcag'],
					$row['level'],
					$row['title'],
					$row['context'],
					$row['selector'],
					$row['snippet'],
					$row['how'],
					$row['first_seen'],
				)
			)
		);
	}

	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	exit;
}
add_action( 'admin_post_lynbro_a11y_export', 'lynbro_a11y_handle_export' );

/**
 * Neutralise a cell that a spreadsheet would treat as a formula.
 *
 * Some of what goes into this export — alt text, link text, a page title — was
 * typed by whoever fills in the site, and a leading =, +, - or @ turns the cell
 * into a formula when the file is opened. The lesson came from another plugin
 * in this suite, at some cost.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function lynbro_a11y_csv_cell( $value ) {
	$value = (string) $value;
	if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
		return "'" . $value;
	}
	return $value;
}

/**
 * Every issue on the site, flattened for export.
 *
 * @return array<int,array<string,string>>
 */
function lynbro_a11y_export_rows() {
	global $wpdb;

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT i.*, p.url, p.label FROM %i i INNER JOIN %i p ON p.id = i.page_id
			 WHERE i.ignored = 0 AND i.offscreen = 0
			 ORDER BY FIELD(i.severity,"error","warning","review"), p.url ASC, i.rule ASC',
			lynbro_a11y_table_issues(),
			lynbro_a11y_table_pages()
		),
		ARRAY_A
	);

	$ignored = lynbro_a11y_ignored_rules();
	$out     = array();

	foreach ( (array) $rows as $row ) {
		if ( in_array( $row['rule'], $ignored, true ) ) {
			continue;
		}
		$rule  = lynbro_a11y_rule( $row['rule'] );
		$out[] = array(
			'page'           => '' !== $row['label'] ? $row['label'] : $row['url'],
			'url'            => $row['url'],
			'rule'           => $row['rule'],
			'severity'       => lynbro_a11y_severity_of( $row['rule'], $row['severity'] ),
			'severity_label' => lynbro_a11y_severity_label( lynbro_a11y_severity_of( $row['rule'], $row['severity'] ) ),
			'wcag'           => isset( $rule['wcag'] ) ? $rule['wcag'] : '',
			'level'          => isset( $rule['level'] ) ? $rule['level'] : '',
			'title'          => isset( $rule['title'] ) ? $rule['title'] : $row['rule'],
			'why'            => isset( $rule['why'] ) ? $rule['why'] : '',
			'how'            => isset( $rule['how'] ) ? $rule['how'] : '',
			'context'        => $row['context'],
			'selector'       => $row['selector'],
			'snippet'        => $row['snippet'],
			'first_seen'     => (string) $row['first_seen'],
		);
	}

	return $out;
}

/**
 * The manual checklist, flattened for export.
 *
 * @return array<int,array<string,string>>
 */
function lynbro_a11y_export_manual() {
	$out = array();
	foreach ( lynbro_a11y_manual_items() as $id => $item ) {
		$answer = lynbro_a11y_manual_answer( $id );
		$out[]  = array(
			'id'      => $id,
			'wcag'    => $item['wcag'],
			'level'   => $item['level'],
			'title'   => $item['title'],
			'answer'  => $answer['state'],
			'note'    => $answer['note'],
			'updated' => $answer['updated'],
		);
	}
	return $out;
}
