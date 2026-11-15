<?php
/**
 * The accessibility statement.
 *
 * The European Accessibility Act expects a website that sells to consumers to
 * say, in public, how accessible it is, what is not accessible yet and how to
 * report a barrier. In Danish that page is a "tilgængelighedserklæring". The
 * plugin already knows most of what goes into it — what the scanner found, how
 * many pages it read and when, which manual checks were answered and how — so
 * it writes a first version from those facts and the few things only the owner
 * knows (who to contact, what is out of scope).
 *
 * What it writes is a draft page in the site's own language, built from
 * ordinary blocks, for the owner to read, change and publish. It never claims
 * anything the checks do not support: the compliance status is the owner's
 * choice, and the screen says so when that choice contradicts the last scan.
 * The method line says "our own assessment", because that is what it is; a
 * statement that implies an independent audit that never happened is worse
 * than no statement.
 *
 * Nothing here is shown to visitors until the owner publishes the page, and
 * the page carries no plugin name or link.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_STATEMENT_OPTION', 'lynbro_a11y_statement' );

/**
 * The statement settings with their defaults.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_statement_settings() {
	$stored = get_option( LYNBRO_A11Y_STATEMENT_OPTION, array() );

	return wp_parse_args(
		is_array( $stored ) ? $stored : array(),
		array(
			'org'          => (string) get_bloginfo( 'name' ),
			'email'        => '',
			'phone'        => '',
			'reply'        => '',
			'status'       => 'partial',
			'include_scan' => 1,
			'other'        => '',
			'burden'       => '',
			'scope'        => '',
			'enforcement'  => lynbro_a11y_default_enforcement(),
			'page_id'      => 0,
			'prepared'     => '',
			'reviewed'     => '',
		)
	);
}

/**
 * Ready wording for the enforcement section, by country and kind of site.
 *
 * Only wording we have checked against the authorities' own pages is offered,
 * with the date it was checked. It is a factual pointer ("you can contact the
 * authority that supervises …"), never a statement that the law applies to the
 * owner or that the site complies. The owner sees it in the field, can change
 * it, and publishes it under their own name.
 *
 * Denmark, checked 2026-11-08: Erhvervsstyrelsen supervises the accessibility
 * requirements for products and services (the EAA, tilgængelighedsloven),
 * including e-commerce on behalf of Konkurrence- og Forbrugerstyrelsen, and
 * takes tips from consumers at sik.dk/tip (linked from borger.dk). Public
 * bodies are under a different law and must publish their statement through
 * Digitaliseringsstyrelsen's own solution, so there is no wording for them here.
 *
 * @return array<string,array{label:string,text:string}>
 */
function lynbro_a11y_enforcement_suggestions() {
	return array(
		'dk-consumer' => array(
			'label' => __( 'Denmark: a business that sells to consumers', 'lynbro-accessibility-checker' ),
			'text'  => __( 'If you are not satisfied with our answer, you can send a tip to the Danish Business Authority (Erhvervsstyrelsen), which supervises the accessibility requirements for products and services in Denmark:', 'lynbro-accessibility-checker' )
				. "\nhttps://www.sik.dk/tip",
		),
	);
}

/**
 * One suggestion's text in the site's language, or '' for an unknown key.
 *
 * @param string $key Suggestion key.
 * @return string
 */
function lynbro_a11y_enforcement_suggestion( $key ) {
	$switched = function_exists( 'switch_to_locale' ) && function_exists( 'get_locale' ) && switch_to_locale( get_locale() );
	$all      = lynbro_a11y_enforcement_suggestions();
	if ( $switched ) {
		restore_previous_locale();
	}
	return isset( $all[ $key ] ) ? $all[ $key ]['text'] : '';
}

/**
 * The enforcement text a site starts with before the form is first saved.
 *
 * A Danish-language site starts with the Danish wording, visible in the field;
 * every other site starts empty, because the right authority depends on the
 * country and the sector and we do not guess.
 *
 * @return string
 */
function lynbro_a11y_default_enforcement() {
	if ( ! function_exists( 'get_locale' ) || 0 !== strpos( (string) get_locale(), 'da' ) ) {
		return '';
	}
	return lynbro_a11y_enforcement_suggestion( 'dk-consumer' );
}

/**
 * The three compliance statuses, in the words the EU model statement uses.
 *
 * @return array<string,string>
 */
function lynbro_a11y_statement_statuses() {
	return array(
		'full'    => __( 'Fully meets WCAG 2.2 AA', 'lynbro-accessibility-checker' ),
		'partial' => __( 'Partially meets WCAG 2.2 AA', 'lynbro-accessibility-checker' ),
		'none'    => __( 'Does not meet WCAG 2.2 AA yet', 'lynbro-accessibility-checker' ),
	);
}

/**
 * Clean what the statement form sent.
 *
 * Every field is plain text: the statement is assembled from blocks by this
 * file, and nobody types markup, CSS or script into it.
 *
 * @param array<string,mixed> $input    Raw input (unslashed).
 * @param array<string,mixed> $previous Stored settings.
 * @return array<string,mixed>
 */
function lynbro_a11y_sanitize_statement( $input, $previous ) {
	$input  = is_array( $input ) ? $input : array();
	$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'partial';

	$out = array(
		'org'          => isset( $input['org'] ) ? substr( sanitize_text_field( $input['org'] ), 0, 200 ) : '',
		'email'        => isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '',
		'phone'        => isset( $input['phone'] ) ? substr( sanitize_text_field( $input['phone'] ), 0, 40 ) : '',
		'reply'        => isset( $input['reply'] ) ? substr( sanitize_text_field( $input['reply'] ), 0, 200 ) : '',
		'status'       => array_key_exists( $status, lynbro_a11y_statement_statuses() ) ? $status : 'partial',
		'include_scan' => empty( $input['include_scan'] ) ? 0 : 1,
	);

	foreach ( array( 'other', 'burden', 'scope', 'enforcement' ) as $key ) {
		$out[ $key ] = isset( $input[ $key ] ) ? substr( sanitize_textarea_field( $input[ $key ] ), 0, 4000 ) : '';
	}

	// Bookkeeping is not part of the form; it survives every save.
	foreach ( array( 'page_id', 'prepared', 'reviewed' ) as $key ) {
		$out[ $key ] = isset( $previous[ $key ] ) ? $previous[ $key ] : ( 'page_id' === $key ? 0 : '' );
	}

	return $out;
}

/**
 * When the scanner last read a page, as a MySQL datetime, or '' if never.
 *
 * @return string
 */
function lynbro_a11y_last_scanned_at() {
	global $wpdb;

	$value = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT MAX(scanned_at) FROM %i WHERE scanned_at IS NOT NULL', lynbro_a11y_table_pages() )
	);

	return $value ? (string) $value : '';
}

/**
 * The facts the checks supply, in the current locale.
 *
 * @return array<string,mixed>
 */
function lynbro_a11y_statement_facts() {
	$totals = lynbro_a11y_totals();
	$rules  = lynbro_a11y_rules();

	$problems  = array();
	$documents = false;
	foreach ( lynbro_a11y_issues_by_rule() as $row ) {
		if ( 'link_to_document' === $row['rule'] ) {
			$documents = true;
			continue;
		}
		if ( ! in_array( $row['severity'], array( 'error', 'warning' ), true ) || ! isset( $rules[ $row['rule'] ] ) ) {
			continue;
		}
		$problems[] = array(
			'title' => (string) $rules[ $row['rule'] ]['title'],
			'wcag'  => (string) $rules[ $row['rule'] ]['wcag'],
			'pages' => (int) $row['pages'],
		);
	}

	$manual_fails = array();
	foreach ( lynbro_a11y_manual_items() as $id => $item ) {
		$answer = lynbro_a11y_manual_answer( $id );
		if ( 'fail' === $answer['state'] ) {
			$manual_fails[] = array(
				'title' => (string) $item['title'],
				'wcag'  => (string) $item['wcag'],
			);
		}
	}

	$progress = lynbro_a11y_manual_progress();
	$scanned  = lynbro_a11y_last_scanned_at();

	return array(
		'pages_scanned' => (int) $totals['pages_scanned'],
		'errors'        => (int) $totals['error'],
		'warnings'      => (int) $totals['warning'],
		'scan_date'     => '' !== $scanned ? (string) mysql2date( get_option( 'date_format' ), $scanned ) : '',
		'problems'      => $problems,
		'documents'     => $documents,
		'manual_done'   => (int) ( $progress['total'] - $progress['unknown'] ),
		'manual_total'  => (int) $progress['total'],
		'manual_fails'  => $manual_fails,
	);
}

/**
 * One heading block.
 *
 * @param string $text  Plain text.
 * @param int    $level 2–6.
 * @return string
 */
function lynbro_a11y_block_heading( $text, $level ) {
	$level = max( 2, min( 6, (int) $level ) );
	$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';

	return sprintf(
		"<!-- wp:heading%1\$s -->\n<h%2\$d class=\"wp-block-heading\">%3\$s</h%2\$d>\n<!-- /wp:heading -->\n\n",
		$attrs,
		$level,
		esc_html( $text )
	);
}

/**
 * One paragraph block.
 *
 * @param string $html Already-escaped inline HTML.
 * @return string
 */
function lynbro_a11y_block_paragraph( $html ) {
	return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->\n\n";
}

/**
 * One list block.
 *
 * @param array<int,string> $items Already-escaped inline HTML, one per item.
 * @return string
 */
function lynbro_a11y_block_list( $items ) {
	$out = "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
	foreach ( $items as $item ) {
		$out .= "<!-- wp:list-item -->\n<li>" . $item . "</li>\n<!-- /wp:list-item -->";
	}
	return $out . "</ul>\n<!-- /wp:list -->\n\n";
}

/**
 * Turn web and email addresses in already escaped text into links.
 *
 * Addresses people need to follow (a complaints page, an email) should be
 * links. A web address is shown without "https://" and "www.": a link that
 * reads as a raw address is exactly what this plugin's own scan reports, and
 * the statement page must pass it.
 *
 * @param string $html Escaped text.
 * @return string
 */
function lynbro_a11y_linkify( $html ) {
	$html = preg_replace_callback(
		'#\bhttps?://[^\s<>"]+#i',
		static function ( $m ) {
			$url   = rtrim( $m[0], '.,;:!?)' );
			$tail  = substr( $m[0], strlen( $url ) );
			$label = rtrim( (string) preg_replace( '#^https?://(www\.)?#i', '', $url ), '/' );
			return '<a href="' . $url . '">' . $label . '</a>' . $tail;
		},
		$html
	);
	return (string) preg_replace_callback(
		'#(?<![\w.@/])[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b(?![^<]*</a>)#',
		static function ( $m ) {
			$email = antispambot( $m[0] );
			return '<a href="mailto:' . $email . '">' . $email . '</a>';
		},
		$html
	);
}

/**
 * Plain text from a textarea as paragraph blocks, one per line.
 *
 * @param string $text Plain text.
 * @return string
 */
function lynbro_a11y_block_paragraphs( $text ) {
	$out = '';
	foreach ( lynbro_a11y_lines( $text ) as $line ) {
		$out .= lynbro_a11y_block_paragraph( lynbro_a11y_linkify( esc_html( $line ) ) );
	}
	return $out;
}

/**
 * Assemble the statement as block markup.
 *
 * Pure: everything it needs is passed in, so the tests can build a statement
 * without a database, and the preview and the page are the same text.
 *
 * @param array<string,mixed> $s     Settings, with prepared/reviewed already formatted for display.
 * @param array<string,mixed> $facts What lynbro_a11y_statement_facts() returns.
 * @param int                 $top   Heading level for the sections (2 on the page, lower in a preview).
 * @return string Block markup.
 */
function lynbro_a11y_statement_markup( $s, $facts, $top = 2 ) {
	$org  = '' !== trim( (string) $s['org'] ) ? (string) $s['org'] : (string) get_bloginfo( 'name' );
	$site = isset( $s['site'] ) ? (string) $s['site'] : (string) home_url( '/' );
	$sub  = $top + 1;
	$out  = '';

	$out .= lynbro_a11y_block_paragraph(
		esc_html(
			sprintf(
				/* translators: 1: organisation name, 2: website address. */
				__( '%1$s wants everyone to be able to use %2$s, including people who use a screen reader, only a keyboard, magnification or other assistive technology. This statement describes how accessible the website is today and how to tell us about a problem.', 'lynbro-accessibility-checker' ),
				$org,
				$site
			)
		)
	);

	// Compliance status.
	$out .= lynbro_a11y_block_heading( __( 'Compliance status', 'lynbro-accessibility-checker' ), $top );
	switch ( $s['status'] ) {
		case 'full':
			$status = __( 'The website fully meets WCAG 2.2 level AA, the standard that the European accessibility requirements (EN 301 549) refer to.', 'lynbro-accessibility-checker' );
			break;
		case 'none':
			$status = __( 'The website does not yet meet WCAG 2.2 level AA, the standard that the European accessibility requirements (EN 301 549) refer to. The main problems are listed below.', 'lynbro-accessibility-checker' );
			break;
		default:
			$status = __( 'The website partially meets WCAG 2.2 level AA, the standard that the European accessibility requirements (EN 301 549) refer to. The parts that do not meet it yet are listed below.', 'lynbro-accessibility-checker' );
	}
	$out .= lynbro_a11y_block_paragraph( esc_html( $status ) );

	// What is not accessible.
	$known = array();
	if ( ! empty( $s['include_scan'] ) ) {
		foreach ( (array) $facts['problems'] as $problem ) {
			$known[] = esc_html(
				sprintf(
					/* translators: 1: description of the problem, 2: WCAG success criterion, 3: number of pages. */
					_n( '%1$s (WCAG %2$s), on %3$s page.', '%1$s (WCAG %2$s), on %3$s pages.', (int) $problem['pages'], 'lynbro-accessibility-checker' ),
					$problem['title'],
					$problem['wcag'],
					number_format_i18n( (int) $problem['pages'] )
				)
			);
		}
		foreach ( (array) $facts['manual_fails'] as $fail ) {
			$known[] = esc_html(
				sprintf(
					/* translators: 1: what should be true, 2: WCAG success criterion. */
					__( 'Not met yet: %1$s (WCAG %2$s).', 'lynbro-accessibility-checker' ),
					$fail['title'],
					$fail['wcag']
				)
			);
		}
		if ( ! empty( $facts['documents'] ) ) {
			$known[] = esc_html__( 'Some linked documents (PDF and office files) may not be accessible. Ask us and we will send the content in a form you can use.', 'lynbro-accessibility-checker' );
		}
	}
	foreach ( lynbro_a11y_lines( $s['other'] ) as $line ) {
		$known[] = esc_html( $line );
	}

	$burden = lynbro_a11y_block_paragraphs( (string) $s['burden'] );
	$scope  = lynbro_a11y_block_paragraphs( (string) $s['scope'] );

	if ( $known || '' !== $burden || '' !== $scope ) {
		$out .= lynbro_a11y_block_heading( __( 'Content that is not accessible', 'lynbro-accessibility-checker' ), $top );
		if ( $known ) {
			$out .= lynbro_a11y_block_heading( __( 'Known problems', 'lynbro-accessibility-checker' ), $sub );
			$out .= lynbro_a11y_block_list( $known );
		}
		if ( '' !== $burden ) {
			$out .= lynbro_a11y_block_heading( __( 'Disproportionate burden', 'lynbro-accessibility-checker' ), $sub );
			$out .= $burden;
		}
		if ( '' !== $scope ) {
			$out .= lynbro_a11y_block_heading( __( 'Content outside the scope of the requirements', 'lynbro-accessibility-checker' ), $sub );
			$out .= $scope;
		}
	}

	// How the statement was prepared.
	$out .= lynbro_a11y_block_heading( __( 'How this statement was prepared', 'lynbro-accessibility-checker' ), $top );

	$dates = '';
	if ( '' !== (string) $s['prepared'] && (string) $s['prepared'] !== (string) $s['reviewed'] ) {
		$dates = sprintf(
			/* translators: 1: date first prepared, 2: date last reviewed. */
			__( 'This statement was prepared on %1$s and last reviewed on %2$s.', 'lynbro-accessibility-checker' ),
			$s['prepared'],
			$s['reviewed']
		);
	} elseif ( '' !== (string) $s['reviewed'] ) {
		/* translators: %s: date. */
		$dates = sprintf( __( 'This statement was prepared on %s.', 'lynbro-accessibility-checker' ), $s['reviewed'] );
	}

	$method = __( 'It is based on our own assessment.', 'lynbro-accessibility-checker' );
	if ( (int) $facts['pages_scanned'] > 0 && '' !== (string) $facts['scan_date'] ) {
		$method = sprintf(
			/* translators: 1: number of pages, 2: date of the check. */
			_n(
				'It is based on our own assessment, including an automated check of %1$s published page on %2$s.',
				'It is based on our own assessment, including an automated check of %1$s published pages on %2$s.',
				(int) $facts['pages_scanned'],
				'lynbro-accessibility-checker'
			),
			number_format_i18n( (int) $facts['pages_scanned'] ),
			$facts['scan_date']
		);
	}
	if ( (int) $facts['manual_done'] > 0 ) {
		$method .= ' ' . sprintf(
			/* translators: 1: checks answered, 2: checks in total. */
			__( 'A person has also reviewed %1$s of the %2$s requirements that automated testing cannot check.', 'lynbro-accessibility-checker' ),
			number_format_i18n( (int) $facts['manual_done'] ),
			number_format_i18n( (int) $facts['manual_total'] )
		);
	}
	$out .= lynbro_a11y_block_paragraph( esc_html( trim( $dates . ' ' . $method ) ) );

	// Feedback.
	$out .= lynbro_a11y_block_heading( __( 'Feedback and contact', 'lynbro-accessibility-checker' ), $top );
	$out .= lynbro_a11y_block_paragraph( esc_html__( 'If you meet a barrier on the website, or need content in a different format, please tell us:', 'lynbro-accessibility-checker' ) );

	$contact = array();
	if ( '' !== (string) $s['email'] ) {
		$email     = antispambot( (string) $s['email'] );
		$contact[] = esc_html__( 'Email:', 'lynbro-accessibility-checker' ) . ' <a href="mailto:' . $email . '">' . $email . '</a>';
	}
	if ( '' !== (string) $s['phone'] ) {
		$tel       = preg_replace( '/[^0-9+]/', '', (string) $s['phone'] );
		$contact[] = esc_html__( 'Phone:', 'lynbro-accessibility-checker' ) . ' <a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $s['phone'] ) . '</a>';
	}
	if ( $contact ) {
		$out .= lynbro_a11y_block_list( $contact );
	}
	if ( '' !== (string) $s['reply'] ) {
		$out .= lynbro_a11y_block_paragraph(
			/* translators: %s: how soon the organisation replies, as the owner wrote it. */
			esc_html( sprintf( __( 'We aim to reply %s.', 'lynbro-accessibility-checker' ), $s['reply'] ) )
		);
	}

	// Enforcement.
	$enforcement = lynbro_a11y_block_paragraphs( (string) $s['enforcement'] );
	if ( '' !== $enforcement ) {
		$out .= lynbro_a11y_block_heading( __( 'Enforcement procedure', 'lynbro-accessibility-checker' ), $top );
		$out .= $enforcement;
	}

	/**
	 * Filter the generated accessibility statement before it is shown or written.
	 *
	 * @param string              $out   Block markup.
	 * @param array<string,mixed> $s     Settings.
	 * @param array<string,mixed> $facts Facts from the checks.
	 */
	return (string) apply_filters( 'lynbro_a11y_statement_markup', $out, $s, $facts );
}

/**
 * Build the statement in the site's language.
 *
 * An admin may use WordPress in English while the site is Danish; the statement
 * is for the site's visitors, so it is written in the site's language whatever
 * the language of the person clicking the button.
 *
 * @param array<string,mixed> $s   Settings.
 * @param int                 $top Heading level for the sections.
 * @return array{title:string,markup:string}
 */
function lynbro_a11y_build_statement( $s, $top = 2 ) {
	$switched = function_exists( 'switch_to_locale' ) && switch_to_locale( get_locale() );
	if ( $switched ) {
		lynbro_a11y_rules( true );
	}

	$format = get_option( 'date_format' );
	$s['prepared'] = '' !== (string) $s['prepared'] ? (string) mysql2date( $format, $s['prepared'] . ' 00:00:00' ) : '';
	$s['reviewed'] = '' !== (string) $s['reviewed'] ? (string) mysql2date( $format, $s['reviewed'] . ' 00:00:00' ) : '';

	$result = array(
		'title'  => __( 'Accessibility statement', 'lynbro-accessibility-checker' ),
		'markup' => lynbro_a11y_statement_markup( $s, lynbro_a11y_statement_facts(), $top ),
	);

	if ( $switched ) {
		restore_previous_locale();
		lynbro_a11y_rules( true );
	}

	return $result;
}

/**
 * Write (or rewrite) the statement page.
 *
 * A new page is created as a draft, so nothing goes public until the owner has
 * read it. An existing page keeps its status, title and address; only its text
 * is replaced, and WordPress keeps the previous text as a revision.
 *
 * @return int|WP_Error Page id.
 */
function lynbro_a11y_write_statement_page() {
	$s     = lynbro_a11y_statement_settings();
	$today = current_time( 'Y-m-d' );

	if ( '' === (string) $s['prepared'] ) {
		$s['prepared'] = $today;
	}
	$s['reviewed'] = $today;

	$built   = lynbro_a11y_build_statement( $s );
	$page_id = (int) $s['page_id'];
	$page    = $page_id ? get_post( $page_id ) : null;

	if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
		$result = wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $built['markup'],
			),
			true
		);
	} else {
		$result = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => $built['title'],
				'post_content' => $built['markup'],
			),
			true
		);
	}

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$s['page_id'] = (int) $result;
	update_option( LYNBRO_A11Y_STATEMENT_OPTION, $s, false );

	return (int) $result;
}
