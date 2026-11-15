<?php
/**
 * Reading and writing scan results.
 *
 * Every query in here goes through $wpdb->prepare(), including the table names,
 * which use the %i identifier placeholder added in WordPress 6.2. Nothing in
 * this file is ever assembled from a request.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * The severity a rule carries right now.
 *
 * The issues table stores a severity as well, and it is kept — it makes the
 * ordering and the indexes work. But what a report SHOWS comes from the rule
 * catalogue, so that changing a rule's severity takes effect immediately
 * instead of waiting for the next full scan. Stored numbers that disagree with
 * the current rules are how a report quietly starts lying.
 *
 * @param string $rule   Rule id.
 * @param string $stored Severity recorded on the row.
 * @return string
 */
function lynbro_a11y_severity_of( $rule, $stored = 'error' ) {
	$definition = lynbro_a11y_rule( $rule );
	return isset( $definition['severity'] ) ? $definition['severity'] : $stored;
}

/**
 * The row for a URL, creating it the first time we see it.
 *
 * @param string              $url  Absolute URL.
 * @param array<string,mixed> $meta Optional object_type, object_id, label.
 * @return int Page id, or 0 on failure.
 */
function lynbro_a11y_page_id( $url, $meta = array() ) {
	global $wpdb;

	$url  = esc_url_raw( $url );
	if ( '' === $url ) {
		return 0;
	}
	$hash  = md5( $url );
	$table = lynbro_a11y_table_pages();

	$id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s', $table, $hash ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $id ) {
		if ( $meta ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'object_type' => isset( $meta['object_type'] ) ? substr( (string) $meta['object_type'], 0, 20 ) : 'url',
					'object_id'   => isset( $meta['object_id'] ) ? (int) $meta['object_id'] : 0,
					'label'       => isset( $meta['label'] ) ? substr( (string) $meta['label'], 0, 255 ) : '',
				),
				array( 'id' => (int) $id )
			);
		}
		return (int) $id;
	}

	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		array(
			'url_hash'    => $hash,
			'url'         => $url,
			'object_type' => isset( $meta['object_type'] ) ? substr( (string) $meta['object_type'], 0, 20 ) : 'url',
			'object_id'   => isset( $meta['object_id'] ) ? (int) $meta['object_id'] : 0,
			'label'       => isset( $meta['label'] ) ? substr( (string) $meta['label'], 0, 255 ) : '',
		)
	);

	return (int) $wpdb->insert_id;
}

/**
 * One page row.
 *
 * @param int $page_id Page id.
 * @return array<string,mixed>|null
 */
function lynbro_a11y_get_page( $page_id ) {
	global $wpdb;
	$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', lynbro_a11y_table_pages(), (int) $page_id ),
		ARRAY_A
	);
	return $row ? $row : null;
}

/**
 * Replace one page's findings with a fresh set.
 *
 * Findings that are still there keep their first_seen date and their ignored
 * flag; findings that are gone are deleted. That is what makes "this appeared
 * after the theme update" a question the report can answer.
 *
 * @param int                            $page_id  Page id.
 * @param array<int,array<string,mixed>> $findings Findings from the checks.
 * @param string                         $source   markup | browser.
 * @return array<string,int> Counts by severity.
 */
function lynbro_a11y_store_findings( $page_id, $findings, $source = 'markup' ) {
	global $wpdb;

	$page_id = (int) $page_id;
	$table   = lynbro_a11y_table_issues();
	$now     = current_time( 'mysql' );
	$ignored    = lynbro_a11y_ignored_rules();
	$everywhere = lynbro_a11y_ignored_everywhere();

	// What we already knew about this page from this source, so an issue that
	// survives a rescan keeps its history.
	$existing = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT id, fingerprint, first_seen, ignored FROM %i WHERE page_id = %d AND source = %s',
			$table,
			$page_id,
			$source
		),
		ARRAY_A
	);
	$known = array();
	foreach ( (array) $existing as $row ) {
		$known[ $row['fingerprint'] ] = $row;
	}

	$counts = array( 'error' => 0, 'warning' => 0, 'review' => 0 );
	$seen   = array();

	foreach ( (array) $findings as $finding ) {
		$rule = isset( $finding['rule'] ) ? (string) $finding['rule'] : '';
		$def  = lynbro_a11y_rule( $rule );
		if ( ! $def ) {
			continue;
		}

		$selector    = isset( $finding['selector'] ) ? substr( (string) $finding['selector'], 0, 255 ) : '';
		$context     = isset( $finding['context'] ) ? substr( (string) $finding['context'], 0, 255 ) : '';
		$snippet     = isset( $finding['snippet'] ) ? (string) $finding['snippet'] : '';
		$fingerprint = md5( $page_id . '|' . $source . '|' . $rule . '|' . $selector . '|' . $context );

		if ( isset( $seen[ $fingerprint ] ) ) {
			// The same rule on the same element, found twice in one pass. One row.
			continue;
		}
		$seen[ $fingerprint ] = true;

		$severity = isset( $def['severity'] ) ? $def['severity'] : 'error';
		if ( ! in_array( $rule, $ignored, true ) && ! ( '' !== $selector && isset( $everywhere[ lynbro_a11y_everywhere_key( $rule, $selector ) ] ) ) ) {
			$counts[ $severity ] = isset( $counts[ $severity ] ) ? $counts[ $severity ] + 1 : 1;
		}

		$data = array(
			'page_id'    => $page_id,
			'fingerprint' => $fingerprint,
			'rule'       => substr( $rule, 0, 64 ),
			'severity'   => $severity,
			'source'     => $source,
			'selector'   => $selector,
			'snippet'    => $snippet,
			'context'    => $context,
			'last_seen'  => $now,
		);

		if ( isset( $known[ $fingerprint ] ) ) {
			$wpdb->update( $table, $data, array( 'id' => (int) $known[ $fingerprint ]['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$data['first_seen'] = $now;
			$data['ignored']    = 0;
			// An element the owner hid on every page stays hidden on a page it newly appears on.
			$key = lynbro_a11y_everywhere_key( $rule, $selector );
			if ( '' !== $selector && isset( $everywhere[ $key ] ) ) {
				$data['ignored']      = 1;
				$data['ignored_note'] = (string) $everywhere[ $key ]['note'];
				$data['ignored_by']   = (int) $everywhere[ $key ]['by'];
				$data['ignored_at']   = (string) $everywhere[ $key ]['at'];
			}
			$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	// Anything from this source we did not see this time is fixed. Delete it.
	foreach ( $known as $fingerprint => $row ) {
		if ( ! isset( $seen[ $fingerprint ] ) ) {
			$wpdb->delete( $table, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	// A markup rescan can add or remove one half of a desktop/phone pair, so
	// which copy counts is worked out again. What the browser saw is kept on the
	// rows that survived; it is a property of the stylesheet, not of this pass.
	if ( 'browser' !== $source ) {
		lynbro_a11y_refresh_offscreen( $page_id );
	}

	return $counts;
}

/*
 * What the browser saw of each markup finding.
 *
 * The markup pass reads the HTML, so it cannot know that a stylesheet hides a
 * block (an Elementor section switched off on every device, a closed drawer) or
 * that a builder prints the same block twice, once for desktop and once for
 * phones, and lets a media query show one of them. The browser check can: it
 * looks each finding's element up in the painted page at a desktop width and at
 * a phone width and reports whether it is on screen. Four bits per issue:
 */
define( 'LYNBRO_A11Y_RENDER_DESKTOP_SEEN', 1 );
define( 'LYNBRO_A11Y_RENDER_DESKTOP_HIDDEN', 2 );
define( 'LYNBRO_A11Y_RENDER_PHONE_SEEN', 4 );
define( 'LYNBRO_A11Y_RENDER_PHONE_HIDDEN', 8 );

/**
 * The offscreen column: why a finding is not counted, if it is not.
 *
 * 0  counted.
 * 1  not on screen when the page loads, at desktop width nor at phone width.
 *    Browsers leave such an element out of the accessibility tree and axe skips
 *    it too. It may still be a closed menu, tab or pop-up that a visitor opens,
 *    so the report keeps it in the page's list and says so; it only stops
 *    counting.
 * 2  the phone copy of a block that is also printed for desktop with the same
 *    problem. The desktop copy is counted; this one is the same fault twice.
 */
define( 'LYNBRO_A11Y_OFFSCREEN_NONE', 0 );
define( 'LYNBRO_A11Y_OFFSCREEN_HIDDEN', 1 );
define( 'LYNBRO_A11Y_OFFSCREEN_TWIN', 2 );

/**
 * What makes two findings "the same problem in another copy of the block".
 *
 * The rule, the detail line and the element's markup without the attributes a
 * builder makes unique per copy (id, class, style, data-*, aria-controls and
 * aria-labelledby, which point at those ids).
 *
 * @param array<string,mixed> $issue Issue row.
 * @return string
 */
function lynbro_a11y_twin_signature( $issue ) {
	$snippet = isset( $issue['snippet'] ) ? (string) $issue['snippet'] : '';
	$snippet = (string) preg_replace( '/\s(?:id|class|style|data-[\w-]+|aria-controls|aria-labelledby|aria-describedby|for|tabindex)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $snippet );
	$snippet = (string) preg_replace( '/\s+/', ' ', $snippet );
	return md5(
		( isset( $issue['rule'] ) ? (string) $issue['rule'] : '' ) . '|' .
		( isset( $issue['context'] ) ? (string) $issue['context'] : '' ) . '|' .
		trim( $snippet )
	);
}

/**
 * Which findings stop counting, from what the browser saw. Pure, for the tests.
 *
 * Only a finding that was looked up at BOTH widths can be called hidden: an
 * element the browser could not find, or saw at one width only, keeps counting.
 * A phone copy is a twin only when a desktop copy with the same signature is on
 * the same page; each desktop copy pairs with one phone copy at most.
 *
 * @param array<int,array<string,mixed>> $rows Issue rows: id, rule, context, snippet, render.
 * @return array<int,int> Issue id => offscreen value.
 */
function lynbro_a11y_offscreen_states( $rows ) {
	$both    = LYNBRO_A11Y_RENDER_DESKTOP_SEEN | LYNBRO_A11Y_RENDER_PHONE_SEEN;
	$states  = array();
	$desktop = array(); // signature => how many unpaired desktop copies.
	$phone   = array(); // issue id => signature, for phone copies.

	foreach ( (array) $rows as $row ) {
		$id     = (int) $row['id'];
		$render = (int) $row['render'];

		$states[ $id ] = LYNBRO_A11Y_OFFSCREEN_NONE;
		if ( ( $render & $both ) !== $both ) {
			continue;
		}
		$hidden_desktop = (bool) ( $render & LYNBRO_A11Y_RENDER_DESKTOP_HIDDEN );
		$hidden_phone   = (bool) ( $render & LYNBRO_A11Y_RENDER_PHONE_HIDDEN );

		if ( $hidden_desktop && $hidden_phone ) {
			$states[ $id ] = LYNBRO_A11Y_OFFSCREEN_HIDDEN;
		} elseif ( $hidden_desktop ) {
			$phone[ $id ] = lynbro_a11y_twin_signature( $row );
		} elseif ( $hidden_phone ) {
			$signature             = lynbro_a11y_twin_signature( $row );
			$desktop[ $signature ] = isset( $desktop[ $signature ] ) ? $desktop[ $signature ] + 1 : 1;
		}
	}

	foreach ( $phone as $id => $signature ) {
		if ( ! empty( $desktop[ $signature ] ) ) {
			$states[ $id ] = LYNBRO_A11Y_OFFSCREEN_TWIN;
			$desktop[ $signature ]--;
		}
	}

	return $states;
}

/**
 * Work the offscreen column out again for one page and write what changed.
 *
 * @param int $page_id Page id.
 * @return int Rows changed.
 */
function lynbro_a11y_refresh_offscreen( $page_id ) {
	global $wpdb;

	$table = lynbro_a11y_table_issues();
	$rows  = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT id, rule, context, snippet, render, offscreen FROM %i WHERE page_id = %d AND source <> %s', $table, (int) $page_id, 'browser' ),
		ARRAY_A
	);

	$states  = lynbro_a11y_offscreen_states( $rows );
	$changed = 0;
	foreach ( $rows as $row ) {
		$id = (int) $row['id'];
		if ( isset( $states[ $id ] ) && (int) $row['offscreen'] !== $states[ $id ] ) {
			$wpdb->update( $table, array( 'offscreen' => $states[ $id ] ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$changed++;
		}
	}
	return $changed;
}

/**
 * Record what the browser saw at one width.
 *
 * Each width writes only its own two bits, in one statement per state, so the
 * desktop and the phone pass of the same page can report at the same moment
 * without one overwriting the other. Ids that are not markup findings of this
 * page are dropped: the list comes from a browser, so it is input.
 *
 * @param int                  $page_id  Page id.
 * @param string               $viewport desktop | phone.
 * @param array<string,string> $states   Issue id => shown | hidden.
 * @return int Findings recorded.
 */
function lynbro_a11y_record_render( $page_id, $viewport, $states ) {
	global $wpdb;

	if ( 'desktop' === $viewport ) {
		$seen   = LYNBRO_A11Y_RENDER_DESKTOP_SEEN;
		$hidden = LYNBRO_A11Y_RENDER_DESKTOP_HIDDEN;
	} elseif ( 'phone' === $viewport ) {
		$seen   = LYNBRO_A11Y_RENDER_PHONE_SEEN;
		$hidden = LYNBRO_A11Y_RENDER_PHONE_HIDDEN;
	} else {
		return 0;
	}

	$table = lynbro_a11y_table_issues();
	$own   = array_map(
		'intval',
		(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT id FROM %i WHERE page_id = %d AND source <> %s', $table, (int) $page_id, 'browser' )
		)
	);
	$own = array_flip( $own );

	$groups = array( 'shown' => array(), 'hidden' => array() );
	foreach ( (array) $states as $id => $state ) {
		$id = (int) $id;
		if ( isset( $own[ $id ] ) && isset( $groups[ $state ] ) ) {
			$groups[ $state ][] = $id;
		}
	}

	$keep = 255 & ~( $seen | $hidden );
	foreach ( $groups as $state => $ids ) {
		if ( ! $ids ) {
			continue;
		}
		$value = 'hidden' === $state ? ( $seen | $hidden ) : $seen;
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%d' ) );
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- one %d per id, built from the same array.
					"UPDATE %i SET render = ( render & %d ) | %d WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					array_merge( array( $table, $keep, $value ), $chunk )
				)
			);
		}
	}

	return count( $groups['shown'] ) + count( $groups['hidden'] );
}

/**
 * The markup findings of one page the browser should look up: id and selector.
 *
 * @param int $page_id Page id.
 * @param int $limit   At most this many.
 * @return array<int,array{id:int,selector:string}>
 */
function lynbro_a11y_render_targets( $page_id, $limit = 500 ) {
	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT id, selector FROM %i WHERE page_id = %d AND source <> %s AND selector <> %s ORDER BY id ASC LIMIT %d',
			lynbro_a11y_table_issues(),
			(int) $page_id,
			'browser',
			'',
			(int) $limit
		),
		ARRAY_A
	);

	$out = array();
	foreach ( $rows as $row ) {
		$out[] = array(
			'id'       => (int) $row['id'],
			'selector' => lynbro_a11y_strict_selector( (string) $row['selector'] ),
		);
	}
	return $out;
}

/**
 * The stored selector with the position the scanner left implicit.
 *
 * lynbro_a11y_selector() writes :nth-of-type(n) only from the second element
 * of a tag on; no index means "the first". For a person that is easier to read,
 * but in a browser "div.widget > img" then matches the image in EVERY widget,
 * and the desktop and phone copies of a block cannot be told apart. So for the
 * look-up each step without an index gets :nth-of-type(1), which is what the
 * scanner meant. Steps that are an id, and body, are left alone.
 *
 * @param string $selector Selector as stored.
 * @return string
 */
function lynbro_a11y_strict_selector( $selector ) {
	$parts = explode( ' > ', (string) $selector );
	foreach ( $parts as $index => $part ) {
		$part = trim( $part );
		if ( '' === $part || '#' === $part[0] || 'body' === $part || 0 === strpos( $part, 'body.' ) || false !== strpos( $part, ':nth-of-type(' ) ) {
			continue;
		}
		$parts[ $index ] = $part . ':nth-of-type(1)';
	}
	return implode( ' > ', $parts );
}

/**
 * Write the summary of a scanned page.
 *
 * The counts are read back from the issues table rather than taken from the
 * caller, so the number on the overview can never drift away from the number of
 * rows the report will actually show.
 *
 * @param int    $page_id Page id.
 * @param int    $status  HTTP status the page answered with.
 * @param string $note    A sentence explaining an unusual result.
 * @return void
 */
function lynbro_a11y_update_page_summary( $page_id, $status = 200, $note = '' ) {
	global $wpdb;

	$totals = lynbro_a11y_page_counts( (int) $page_id );

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		lynbro_a11y_table_pages(),
		array(
			'scanned_at'  => current_time( 'mysql' ),
			'http_status' => (int) $status,
			'errors'      => (int) $totals['error'],
			'warnings'    => (int) $totals['warning'],
			'reviews'     => (int) $totals['review'],
			'note'        => substr( (string) $note, 0, 255 ),
		),
		array( 'id' => (int) $page_id )
	);
}

/**
 * Live counts for a page, straight from the issues table.
 *
 * Ignored rules and individually ignored issues are left out on purpose: the
 * number on screen has to match the list under it.
 *
 * @param int $page_id Page id.
 * @return array<string,int>
 */
function lynbro_a11y_page_counts( $page_id ) {
	global $wpdb;

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT severity, rule, COUNT(*) AS total FROM %i WHERE page_id = %d AND ignored = 0 AND offscreen = 0 GROUP BY severity, rule',
			lynbro_a11y_table_issues(),
			(int) $page_id
		),
		ARRAY_A
	);

	$ignored = lynbro_a11y_ignored_rules();
	$counts  = array( 'error' => 0, 'warning' => 0, 'review' => 0 );

	foreach ( (array) $rows as $row ) {
		if ( in_array( $row['rule'], $ignored, true ) ) {
			continue;
		}
		$severity = lynbro_a11y_severity_of( $row['rule'], $row['severity'] );
		$severity = isset( $counts[ $severity ] ) ? $severity : 'error';
		$counts[ $severity ] += (int) $row['total'];
	}

	return $counts;
}

/**
 * Site-wide totals for the overview.
 *
 * @return array<string,int>
 */
function lynbro_a11y_totals() {
	global $wpdb;

	$pages  = lynbro_a11y_table_pages();
	$issues = lynbro_a11y_table_issues();

	$totals = array(
		'pages_known'   => 0,
		'pages_scanned' => 0,
		'pages_clean'   => 0,
		'pages_failed'  => 0,
		'error'         => 0,
		'warning'       => 0,
		'review'        => 0,
	);

	$totals['pages_known']   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $pages ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	// A page counts as scanned only when it was fetched and parsed. One that
	// answered with an error, timed out or came back empty was not checked, and
	// counting it as scanned (or worse, as clean) would report a site as fine
	// when the scanner never saw it.
	$totals['pages_scanned'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s', $pages, '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$totals['pages_failed']  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND ( http_status < 200 OR http_status >= 400 OR note <> %s )', $pages, '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT severity, rule, COUNT(*) AS total FROM %i WHERE ignored = 0 AND offscreen = 0 GROUP BY severity, rule', $issues ),
		ARRAY_A
	);
	$ignored = lynbro_a11y_ignored_rules();
	foreach ( (array) $rows as $row ) {
		if ( in_array( $row['rule'], $ignored, true ) ) {
			continue;
		}
		$severity = lynbro_a11y_severity_of( $row['rule'], $row['severity'] );
		if ( isset( $totals[ $severity ] ) ) {
			$totals[ $severity ] += (int) $row['total'];
		}
	}

	$totals['pages_clean'] = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s AND errors = 0 AND warnings = 0', $pages, '' )
	);

	return $totals;
}

/**
 * Issues grouped by rule, worst first — the "what to fix" list.
 *
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_issues_by_rule() {
	global $wpdb;

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT rule, severity, COUNT(*) AS total, COUNT(DISTINCT page_id) AS pages
			 FROM %i WHERE ignored = 0 AND offscreen = 0 GROUP BY rule, severity',
			lynbro_a11y_table_issues()
		),
		ARRAY_A
	);

	$ignored = lynbro_a11y_ignored_rules();
	$out     = array();
	foreach ( (array) $rows as $row ) {
		if ( in_array( $row['rule'], $ignored, true ) ) {
			continue;
		}
		$out[] = array(
			'rule'     => $row['rule'],
			'severity' => lynbro_a11y_severity_of( $row['rule'], $row['severity'] ),
			'total'    => (int) $row['total'],
			'pages'    => (int) $row['pages'],
		);
	}

	$order = array( 'error' => 0, 'warning' => 1, 'review' => 2 );
	usort(
		$out,
		static function ( $a, $b ) use ( $order ) {
			$sa = isset( $order[ $a['severity'] ] ) ? $order[ $a['severity'] ] : 3;
			$sb = isset( $order[ $b['severity'] ] ) ? $order[ $b['severity'] ] : 3;
			if ( $sa === $sb ) {
				return $b['total'] <=> $a['total'];
			}
			return $sa <=> $sb;
		}
	);

	return $out;
}

/**
 * Pages, worst first, for the report table.
 *
 * @param array<string,mixed> $args offset, number, only_problems.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_list_pages( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args(
		$args,
		array(
			'offset'        => 0,
			'number'        => 50,
			'only_problems' => false,
		)
	);

	$table = lynbro_a11y_table_pages();

	if ( $args['only_problems'] ) {
		$sql = $wpdb->prepare(
			'SELECT * FROM %i WHERE scanned_at IS NOT NULL AND ( errors > 0 OR warnings > 0 )
			 ORDER BY errors DESC, warnings DESC, id ASC LIMIT %d OFFSET %d',
			$table,
			(int) $args['number'],
			(int) $args['offset']
		);
	} else {
		$sql = $wpdb->prepare(
			'SELECT * FROM %i ORDER BY errors DESC, warnings DESC, id ASC LIMIT %d OFFSET %d',
			$table,
			(int) $args['number'],
			(int) $args['offset']
		);
	}

	return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * How many pages the report would list.
 *
 * @param bool $only_problems Count only pages with something on them.
 * @return int
 */
function lynbro_a11y_count_pages( $only_problems = false ) {
	global $wpdb;
	$table = lynbro_a11y_table_pages();

	if ( $only_problems ) {
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND ( errors > 0 OR warnings > 0 )', $table )
		);
	}
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * The issues found on one page.
 *
 * @param int  $page_id         Page id.
 * @param bool $include_ignored Include issues the owner has silenced.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_page_issues( $page_id, $include_ignored = false ) {
	global $wpdb;

	$table = lynbro_a11y_table_issues();

	if ( $include_ignored ) {
		$sql = $wpdb->prepare(
			'SELECT * FROM %i WHERE page_id = %d ORDER BY FIELD(severity,"error","warning","review"), rule ASC, id ASC',
			$table,
			(int) $page_id
		);
	} else {
		$sql = $wpdb->prepare(
			'SELECT * FROM %i WHERE page_id = %d AND ignored = 0 AND offscreen = 0 ORDER BY FIELD(severity,"error","warning","review"), rule ASC, id ASC',
			$table,
			(int) $page_id
		);
	}

	$rows    = (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	$ignored = lynbro_a11y_ignored_rules();

	$rows = array_values(
		array_filter(
			$rows,
			static function ( $row ) use ( $ignored, $include_ignored ) {
				return $include_ignored || ! in_array( $row['rule'], $ignored, true );
			}
		)
	);

	foreach ( $rows as $index => $row ) {
		$rows[ $index ]['severity'] = lynbro_a11y_severity_of( $row['rule'], $row['severity'] );
	}

	return $rows;
}

/**
 * Every issue for one rule, across the site.
 *
 * @param string $rule   Rule id.
 * @param int    $number How many rows.
 * @param int    $offset Where to start.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_issues_for_rule( $rule, $number = 100, $offset = 0 ) {
	global $wpdb;

	return (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT i.*, p.url, p.label FROM %i i
			 INNER JOIN %i p ON p.id = i.page_id
			 WHERE i.rule = %s AND i.ignored = 0 AND i.offscreen = 0
			 ORDER BY p.url ASC, i.id ASC LIMIT %d OFFSET %d',
			lynbro_a11y_table_issues(),
			lynbro_a11y_table_pages(),
			(string) $rule,
			(int) $number,
			(int) $offset
		),
		ARRAY_A
	);
}

define( 'LYNBRO_A11Y_EVERYWHERE', 'lynbro_a11y_ignore_everywhere' );

/**
 * The key of one element-and-rule pair in the "hidden on every page" list.
 *
 * @param string $rule     Rule id.
 * @param string $selector Element selector.
 * @return string
 */
function lynbro_a11y_everywhere_key( $rule, $selector ) {
	return md5( $rule . '|' . $selector );
}

/**
 * Elements the owner hid on every page: a theme footer, an embedded search.
 *
 * @return array<string,array{rule:string,selector:string,note:string,by:int,at:string}>
 */
function lynbro_a11y_ignored_everywhere() {
	$list = get_option( LYNBRO_A11Y_EVERYWHERE, array() );
	return is_array( $list ) ? $list : array();
}

/**
 * Bring one page's counts in line with its rows.
 *
 * @param int $page_id Page id.
 * @return void
 */
function lynbro_a11y_refresh_page_counts( $page_id ) {
	global $wpdb;
	$counts = lynbro_a11y_page_counts( (int) $page_id );
	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		lynbro_a11y_table_pages(),
		array(
			'errors'   => $counts['error'],
			'warnings' => $counts['warning'],
			'reviews'  => $counts['review'],
		),
		array( 'id' => (int) $page_id )
	);
}

/**
 * Silence, or stop silencing, one issue — on its page, or on every page.
 *
 * Every dismissal records why, who and when, so a colleague or an assessor can
 * see that a hidden issue was a decision and not an accident. "Every page"
 * applies to the same rule on the same element wherever it is, now and on
 * pages it appears on later.
 *
 * @param int    $issue_id   Issue id.
 * @param bool   $ignored    New state.
 * @param string $note       Why it is hidden (plain text).
 * @param bool   $everywhere Apply to the same element on every page.
 * @return int Number of issue rows changed.
 */
function lynbro_a11y_set_issue_ignored( $issue_id, $ignored, $note = '', $everywhere = false ) {
	global $wpdb;

	$table = lynbro_a11y_table_issues();
	$issue = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT id, page_id, rule, selector FROM %i WHERE id = %d', $table, (int) $issue_id ),
		ARRAY_A
	);
	if ( ! $issue ) {
		return 0;
	}

	$fields = $ignored
		? array(
			'ignored'      => 1,
			'ignored_note' => substr( (string) $note, 0, 255 ),
			'ignored_by'   => (int) get_current_user_id(),
			'ignored_at'   => current_time( 'mysql' ),
		)
		: array(
			'ignored'      => 0,
			'ignored_note' => '',
			'ignored_by'   => 0,
			'ignored_at'   => null,
		);

	$everywhere = $everywhere && '' !== (string) $issue['selector'];

	if ( ! $everywhere ) {
		$wpdb->update( $table, $fields, array( 'id' => (int) $issue['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		lynbro_a11y_refresh_page_counts( (int) $issue['page_id'] );
		return 1;
	}

	$list = lynbro_a11y_ignored_everywhere();
	$key  = lynbro_a11y_everywhere_key( (string) $issue['rule'], (string) $issue['selector'] );
	if ( $ignored ) {
		$list[ $key ] = array(
			'rule'     => (string) $issue['rule'],
			'selector' => (string) $issue['selector'],
			'note'     => $fields['ignored_note'],
			'by'       => $fields['ignored_by'],
			'at'       => $fields['ignored_at'],
		);
	} else {
		unset( $list[ $key ] );
	}
	update_option( LYNBRO_A11Y_EVERYWHERE, $list, false );

	$pages = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT DISTINCT page_id FROM %i WHERE rule = %s AND selector = %s', $table, (string) $issue['rule'], (string) $issue['selector'] )
	);
	$changed = (int) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$table,
		$fields,
		array(
			'rule'     => (string) $issue['rule'],
			'selector' => (string) $issue['selector'],
		)
	);
	foreach ( (array) $pages as $page_id ) {
		lynbro_a11y_refresh_page_counts( (int) $page_id );
	}
	return $changed;
}

/**
 * Is this issue's element hidden on every page?
 *
 * @param array<string,mixed> $issue Issue row.
 * @return bool
 */
function lynbro_a11y_issue_hidden_everywhere( $issue ) {
	if ( '' === (string) $issue['selector'] ) {
		return false;
	}
	$list = lynbro_a11y_ignored_everywhere();
	return isset( $list[ lynbro_a11y_everywhere_key( (string) $issue['rule'], (string) $issue['selector'] ) ] );
}

/**
 * Hide, or show again, one rule on every page.
 *
 * The same list as the "Rules to hide" setting; this is the button for it on
 * the rule's own page.
 *
 * @param string $rule   Rule id.
 * @param bool   $hidden New state.
 * @return void
 */
function lynbro_a11y_set_rule_hidden( $rule, $hidden ) {
	$options = lynbro_a11y_options();
	$list    = lynbro_a11y_lines( $options['ignored_rules'] );
	$list    = $hidden ? array_values( array_unique( array_merge( $list, array( $rule ) ) ) ) : array_values( array_diff( $list, array( $rule ) ) );
	$options['ignored_rules'] = implode( "\n", $list );
	update_option( LYNBRO_A11Y_OPTION, lynbro_a11y_sanitize_options( $options ) );
	lynbro_a11y_flush_options_cache();
}

/**
 * Forget everything and start again.
 *
 * @return void
 */
function lynbro_a11y_clear_results() {
	global $wpdb;
	foreach ( array( lynbro_a11y_table_issues(), lynbro_a11y_table_pages() ) as $table ) {
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

/**
 * Forget the results for one post.
 *
 * Called when a post stops being public (unpublished, made private, trashed
 * or deleted): a page nobody can visit should not keep counting against the
 * site, and an owner who removed a page to fix it should see it gone.
 *
 * @param int $post_id Post id.
 * @return int Number of page rows removed.
 */
function lynbro_a11y_forget_post( $post_id ) {
	global $wpdb;

	$pages   = lynbro_a11y_table_pages();
	$page_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT id FROM %i WHERE object_type = %s AND object_id = %d', $pages, 'post', (int) $post_id )
	);

	foreach ( (array) $page_ids as $page_id ) {
		$wpdb->delete( lynbro_a11y_table_issues(), array( 'page_id' => (int) $page_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $pages, array( 'id' => (int) $page_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	return count( (array) $page_ids );
}

/**
 * Forget every post result whose post is no longer published.
 *
 * Catches what the hooks cannot: posts removed while the plugin was switched
 * off, or by a direct database edit. Runs at the start of every full scan.
 *
 * @return int Number of page rows removed.
 */
function lynbro_a11y_forget_unpublished() {
	global $wpdb;

	$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT DISTINCT p.object_id FROM %i p LEFT JOIN %i w ON w.ID = p.object_id WHERE p.object_type = %s AND ( w.ID IS NULL OR w.post_status <> %s )',
			lynbro_a11y_table_pages(),
			$wpdb->posts,
			'post',
			'publish'
		)
	);

	$removed = 0;
	foreach ( (array) $ids as $post_id ) {
		$removed += lynbro_a11y_forget_post( (int) $post_id );
	}
	return $removed;
}
