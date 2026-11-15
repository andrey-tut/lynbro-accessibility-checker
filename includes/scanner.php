<?php
/**
 * Fetching a page and reading it.
 *
 * The scanner asks the site for its own page over HTTP rather than rendering it
 * internally, and that is a considered choice with a cost. Rendering the_content
 * would be faster and would need no network at all — but it would only ever see
 * the post body. Almost every failure that matters lives in the theme: the menu
 * with no landmark, the header image with no alt, the skip link that is not
 * there, the search field with a placeholder and no label. A scanner that
 * cannot see them reports a clean site that fails its first real audit.
 *
 * When the loopback request cannot be made — some hosts block a site from
 * calling itself — we say so on the page's row rather than quietly falling back
 * to something weaker and calling it a scan.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_ACCESS_OPTION', 'lynbro_a11y_site_access' );

/**
 * The username and password for a site behind basic auth, if the owner set them.
 *
 * Kept in their own option, not autoloaded, and never printed back on the
 * settings screen.
 *
 * @return array{user:string,pass:string}
 */
function lynbro_a11y_site_access() {
	$stored = get_option( LYNBRO_A11Y_ACCESS_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	return array(
		'user' => isset( $stored['user'] ) ? (string) $stored['user'] : '',
		'pass' => isset( $stored['pass'] ) ? (string) $stored['pass'] : '',
	);
}

/**
 * The Authorization header for one request, or '' when none should be sent.
 *
 * Credentials go only to the site's own host: an extra URL on another host,
 * or a redirect away from the site, never receives them.
 *
 * @param string                       $url    URL being fetched.
 * @param array{user:string,pass:string} $access Saved credentials.
 * @param string                       $home   The site's home URL.
 * @return string
 */
function lynbro_a11y_access_header( $url, $access, $home ) {
	if ( '' === $access['user'] ) {
		return '';
	}
	$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	if ( '' === $host || strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) ) !== $host ) {
		return '';
	}
	// Basic auth is defined as base64 of "user:password" (RFC 7617); this is not obfuscation.
	return 'Basic ' . base64_encode( $access['user'] . ':' . $access['pass'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
}

/**
 * What to tell the owner about a page that did not answer with a page.
 *
 * The common blockers on a site that is being built — basic auth, maintenance
 * mode — get an explanation of what to do, not just a number.
 *
 * @param int  $status     HTTP status.
 * @param bool $has_access Whether credentials are saved.
 * @return string
 */
function lynbro_a11y_http_note( $status, $has_access ) {
	if ( 401 === $status ) {
		return $has_access
			? __( 'The page still asks for a password (HTTP 401). Check the username and password under Settings, “A site behind a password”.', 'lynbro-accessibility-checker' )
			: __( 'The page asks for a username and password (HTTP 401). If this is a staging site behind basic auth, enter them under Settings, “A site behind a password”.', 'lynbro-accessibility-checker' );
	}
	if ( 503 === $status ) {
		return __( 'The site answered HTTP 503, which is what maintenance mode sends. Switch maintenance mode off while you scan, or let requests with the X-Lynbro-Accessibility header through it.', 'lynbro-accessibility-checker' );
	}
	/* translators: %d: HTTP status code. */
	return sprintf( __( 'The page answered with HTTP %d, so there was nothing to check.', 'lynbro-accessibility-checker' ), $status );
}

/**
 * Is this the password form WordPress shows for a password-protected post?
 *
 * @param string $html Page HTML.
 * @return bool
 */
function lynbro_a11y_is_password_form( $html ) {
	return false !== strpos( $html, 'post-password-form' ) && false !== strpos( $html, 'action=postpass' );
}

/**
 * A redirect's Location as an absolute URL.
 *
 * @param string $location Location header.
 * @param string $current  URL that answered with the redirect.
 * @return string
 */
function lynbro_a11y_absolute_location( $location, $current ) {
	if ( preg_match( '#^https?://#i', $location ) ) {
		return $location;
	}
	$parts  = wp_parse_url( $current );
	$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
	$origin = $scheme . '://' . ( isset( $parts['host'] ) ? $parts['host'] : '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	if ( 0 === strpos( $location, '//' ) ) {
		return $scheme . ':' . $location;
	}
	if ( 0 === strpos( $location, '/' ) ) {
		return $origin . $location;
	}
	$path = isset( $parts['path'] ) ? $parts['path'] : '/';
	return $origin . substr( $path, 0, (int) strrpos( $path, '/' ) + 1 ) . $location;
}

/**
 * Fetch one URL as an ordinary visitor would see it.
 *
 * @param string $url Absolute URL on this site.
 * @return array{status:int,body:string,error:string}
 */
function lynbro_a11y_fetch( $url ) {
	$args = array(
		'timeout'     => (int) lynbro_a11y_option( 'request_timeout' ),
		'redirection' => 3,
		'sslverify'   => false, // A staging site with a self-signed certificate is still ours to scan.
		'headers'     => array(
			// Named plainly, so an owner reading their access log knows what this is.
			'X-Lynbro-Accessibility' => 'scan',
		),
		'user-agent'  => 'LynbroAccessibility/' . LYNBRO_A11Y_VERSION . '; ' . home_url( '/' ),
	);

	/**
	 * Filter the request arguments used to fetch a page for scanning.
	 *
	 * Basic auth has its own setting; this filter is for anything else a gate
	 * needs, such as a cookie or a header a maintenance plugin lets through.
	 *
	 * @param array<string,mixed> $args Arguments for wp_remote_get().
	 * @param string              $url  URL being fetched.
	 */
	$args = (array) apply_filters( 'lynbro_a11y_request_args', $args, $url );

	$access = lynbro_a11y_site_access();
	if ( '' !== $access['user'] ) {
		// With a password saved, redirects are followed here, one at a time, so the
		// password is sent again only while the next address is on this site.
		$args['redirection'] = 0;
		$hops                = 0;
		do {
			$auth = lynbro_a11y_access_header( $url, $access, home_url( '/' ) );
			if ( '' !== $auth ) {
				$args['headers']['Authorization'] = $auth;
			} else {
				unset( $args['headers']['Authorization'] );
			}
			$response = wp_remote_get( $url, $args );
			$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			$location = $code >= 300 && $code < 400 ? (string) wp_remote_retrieve_header( $response, 'location' ) : '';
			if ( '' !== $location ) {
				$url = lynbro_a11y_absolute_location( $location, $url );
			}
			++$hops;
		} while ( '' !== $location && $hops <= 3 );
	} else {
		$response = wp_remote_get( $url, $args );
	}

	if ( is_wp_error( $response ) ) {
		return array(
			'status' => 0,
			'body'   => '',
			'error'  => $response->get_error_message(),
		);
	}

	return array(
		'status' => (int) wp_remote_retrieve_response_code( $response ),
		'body'   => (string) wp_remote_retrieve_body( $response ),
		'error'  => '',
	);
}

/**
 * Scan one target and store what it found.
 *
 * @param array<string,mixed> $target url, object_type, object_id, label.
 * @return array<string,mixed> page_id, status, counts, note.
 */
function lynbro_a11y_scan_target( $target ) {
	$url = isset( $target['url'] ) ? (string) $target['url'] : '';

	$page_id = lynbro_a11y_page_id(
		$url,
		array(
			'object_type' => isset( $target['object_type'] ) ? $target['object_type'] : 'url',
			'object_id'   => isset( $target['object_id'] ) ? $target['object_id'] : 0,
			'label'       => isset( $target['label'] ) ? $target['label'] : '',
		)
	);

	if ( ! $page_id ) {
		return array( 'page_id' => 0, 'status' => 0, 'counts' => array(), 'note' => __( 'That is not a URL we can scan.', 'lynbro-accessibility-checker' ) );
	}

	if ( ! lynbro_a11y_dom_available() ) {
		lynbro_a11y_update_page_summary( $page_id, 0, __( 'This server has no DOM extension for PHP, so pages cannot be parsed. Ask your host to enable ext-dom.', 'lynbro-accessibility-checker' ) );
		return array( 'page_id' => $page_id, 'status' => 0, 'counts' => array(), 'note' => __( 'PHP has no DOM extension on this server.', 'lynbro-accessibility-checker' ) );
	}

	$response = lynbro_a11y_fetch( $url );

	if ( '' !== $response['error'] ) {
		lynbro_a11y_update_page_summary(
			$page_id,
			0,
			sprintf( /* translators: %s: the error the HTTP request returned. */ __( 'The page could not be fetched: %s', 'lynbro-accessibility-checker' ), $response['error'] )
		);
		return array( 'page_id' => $page_id, 'status' => 0, 'counts' => array(), 'note' => $response['error'] );
	}

	if ( $response['status'] < 200 || $response['status'] >= 400 ) {
		lynbro_a11y_update_page_summary(
			$page_id,
			$response['status'],
			lynbro_a11y_http_note( $response['status'], '' !== lynbro_a11y_site_access()['user'] )
		);
		return array( 'page_id' => $page_id, 'status' => $response['status'], 'counts' => array(), 'note' => '' );
	}

	if ( lynbro_a11y_is_password_form( $response['body'] ) ) {
		lynbro_a11y_update_page_summary( $page_id, $response['status'], __( 'This page is protected with a password, so the scanner only sees the password form. It was not checked.', 'lynbro-accessibility-checker' ) );
		return array( 'page_id' => $page_id, 'status' => $response['status'], 'counts' => array(), 'note' => '' );
	}

	$doc = lynbro_a11y_load_document( $response['body'] );
	if ( ! $doc ) {
		lynbro_a11y_update_page_summary( $page_id, $response['status'], __( 'The page came back empty or could not be parsed as HTML.', 'lynbro-accessibility-checker' ) );
		return array( 'page_id' => $page_id, 'status' => $response['status'], 'counts' => array(), 'note' => '' );
	}

	$findings = lynbro_a11y_analyse( $doc );
	$counts   = lynbro_a11y_store_findings( $page_id, $findings, 'markup' );

	lynbro_a11y_update_page_summary( $page_id, $response['status'] );

	/**
	 * Fires after one page has been scanned and its findings stored.
	 *
	 * @param int                            $page_id  Page id.
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @param string                         $url      URL scanned.
	 */
	do_action( 'lynbro_a11y_page_scanned', $page_id, $findings, $url );

	return array( 'page_id' => $page_id, 'status' => $response['status'], 'counts' => $counts, 'note' => '' );
}

/**
 * Scan an HTML string directly, without fetching anything.
 *
 * This is what the tests use, and what the "check this markup" tool on the
 * Tools screen uses. It is also how our own admin screens get scanned by our
 * own scanner — see PLUGIN.md.
 *
 * @param string $html HTML to analyse.
 * @return array<int,array<string,mixed>> Findings.
 */
function lynbro_a11y_scan_html( $html ) {
	$doc = lynbro_a11y_load_document( $html );
	if ( ! $doc ) {
		return array();
	}
	return lynbro_a11y_analyse( $doc );
}
