<?php
/**
 * The browser check for every page: contrast, focus and target size, and which
 * markup findings are actually on screen at desktop and at phone width.
 *
 * Contrast cannot be read from HTML; it has to be measured where the page is
 * painted. Until now that took one click per page, so a site of three hundred
 * pages never had its contrast checked. This screen opens each page of the
 * report in two frames of this admin page, one 1280 pixels wide and one 390,
 * lets the same measuring script run in each, and moves on to the next page.
 * Nothing runs on a server and nothing is sent anywhere but this site's own
 * REST endpoint; it is the administrator's browser doing what it does when
 * they click "Check in browser", once per page, while this tab stays open.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages the browser check can open: scanned, answered, not behind a password.
 *
 * @param string $scope   all | unchecked | page.
 * @param int    $page_id With scope page: that page.
 * @param int    $limit   At most this many.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_browser_queue( $scope, $page_id = 0, $limit = 5000 ) {
	global $wpdb;

	$table = lynbro_a11y_table_pages();

	// The stalest first, so a run that was stopped and started again carries on
	// where it got to instead of starting over. Three plain queries rather than
	// one assembled WHERE: each can be read, and prepared, as it stands.
	if ( 'page' === $scope ) {
		$sql = $wpdb->prepare(
			'SELECT id, url, label, object_type, object_id, browser_at FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s AND id = %d LIMIT 1',
			$table,
			'',
			(int) $page_id
		);
	} elseif ( 'unchecked' === $scope ) {
		$sql = $wpdb->prepare(
			'SELECT id, url, label, object_type, object_id, browser_at FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s AND browser_at IS NULL ORDER BY id ASC LIMIT %d',
			$table,
			'',
			(int) $limit
		);
	} else {
		$sql = $wpdb->prepare(
			'SELECT id, url, label, object_type, object_id, browser_at FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s ORDER BY browser_at IS NOT NULL, browser_at ASC, id ASC LIMIT %d',
			$table,
			'',
			(int) $limit
		);
	}

	return (array) $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- prepared just above.
}

/**
 * How many scanned pages have had the browser check, and how many have not.
 *
 * @return array{checked:int,total:int}
 */
function lynbro_a11y_browser_coverage() {
	global $wpdb;
	$table = lynbro_a11y_table_pages();
	return array(
		'total'   => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s', $table, '' ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		'checked' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE scanned_at IS NOT NULL AND http_status >= 200 AND http_status < 400 AND note = %s AND browser_at IS NOT NULL', $table, '' ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	);
}

/**
 * The URL that opens one page in a frame of this screen at one width.
 *
 * @param string $url      Page URL.
 * @param string $viewport desktop | phone.
 * @param string $nonce    Live-check nonce.
 * @return string
 */
function lynbro_a11y_browser_frame_url( $url, $viewport, $nonce ) {
	return add_query_arg(
		array(
			LYNBRO_A11Y_LIVE_ARG     => $nonce,
			LYNBRO_A11Y_VIEWPORT_ARG => $viewport,
			LYNBRO_A11Y_BATCH_ARG    => '1',
		),
		$url
	);
}

/**
 * Link to this screen for one page, starting at once.
 *
 * @param int $page_id Page id.
 * @return string
 */
function lynbro_a11y_browser_check_link( $page_id ) {
	return add_query_arg(
		array(
			'page'    => 'lynbro-accessibility-browser',
			'scope'   => 'page',
			'page_id' => (int) $page_id,
			'start'   => '1',
		),
		admin_url( 'admin.php' )
	);
}

/**
 * The screen.
 *
 * @return void
 */
function lynbro_a11y_render_browser_check() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only choice of which pages to list; nothing is written by this request.
	$scope   = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'unchecked';
	$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
	$start   = ! empty( $_GET['start'] );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! in_array( $scope, array( 'all', 'unchecked', 'page' ), true ) || ( 'page' === $scope && ! $page_id ) ) {
		$scope = 'unchecked';
	}

	$coverage = lynbro_a11y_browser_coverage();
	$queue    = lynbro_a11y_browser_queue( $scope, $page_id );
	$nonce    = wp_create_nonce( 'lynbro_a11y_live' );
	$items    = array();

	foreach ( $queue as $row ) {
		$items[] = array(
			'id'      => (int) $row['id'],
			'label'   => lynbro_a11y_page_label( $row ),
			'desktop' => lynbro_a11y_browser_frame_url( (string) $row['url'], 'desktop', $nonce ),
			'phone'   => lynbro_a11y_browser_frame_url( (string) $row['url'], 'phone', $nonce ),
			'report'  => admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . (int) $row['id'] ),
		);
	}

	wp_enqueue_script(
		'lynbro-a11y-browser',
		LYNBRO_A11Y_URL . 'admin/browser-check.js',
		array(),
		lynbro_a11y_asset_ver( 'admin/browser-check.js' ),
		true
	);
	wp_localize_script(
		'lynbro-a11y-browser',
		'lynbroA11yBrowser',
		array(
			'pages'      => $items,
			'homeOrigin' => lynbro_a11y_origin( home_url() ),
			'autostart'  => ( $start && $items ) ? '1' : '0',
			'timeout'    => 60,
			'strings'    => array(
				/* translators: 1: page number, 2: pages in total, 3: page name. */
				'checking'  => __( 'Checking page %1$s of %2$s: %3$s', 'lynbro-accessibility-checker' ),
				/* translators: 1: pages checked, 2: pages in total. */
				'progress'  => __( 'Checked so far: %1$s of %2$s.', 'lynbro-accessibility-checker' ),
				/* translators: %s: number of pages checked. */
				'finished'  => __( 'Finished. Pages checked in the browser: %s.', 'lynbro-accessibility-checker' ),
				/* translators: %s: number of pages checked. */
				'stopped'   => __( 'Stopped. Pages checked in the browser: %s. Start again to carry on with the pages not checked yet.', 'lynbro-accessibility-checker' ),
				/* translators: 1: contrast, focus and target-size findings, 2: markup findings looked up. */
				'tally'     => __( '%1$s contrast, focus and target-size findings recorded; %2$s markup findings looked up at desktop and phone width.', 'lynbro-accessibility-checker' ),
				'failedHead' => __( 'These pages could not be checked: they did not load in time, or the results could not be saved.', 'lynbro-accessibility-checker' ),
				'blocked'   => __( 'The pages do not load inside this screen, so nothing could be checked. A security plugin or the server probably sends "X-Frame-Options: DENY" or a Content-Security-Policy "frame-ancestors" rule for the whole site. Allowing the site to frame itself (SAMEORIGIN / \'self\') fixes it; until then, use "Check in browser" on each page of the report.', 'lynbro-accessibility-checker' ),
				'stop'      => __( 'Stop', 'lynbro-accessibility-checker' ),
				'report'    => __( 'Open the report', 'lynbro-accessibility-checker' ),
				'pageReport' => __( 'Open this page in the report', 'lynbro-accessibility-checker' ),
			),
		)
	);
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Browser check', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-browser' ); ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'Check every page in the browser', 'lynbro-accessibility-checker' ); ?></h2>
			<p>
				<?php esc_html_e( 'Contrast, a visible focus ring and the size of tap targets come from the stylesheet, so they are measured in a browser, not read from the markup. This screen opens each page of the report here, at desktop width (1280 pixels) and at phone width (390 pixels), measures it and moves on.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p>
				<?php esc_html_e( 'It also looks every markup finding up on the painted page. A finding on an element that is not on screen at either width when the page loads (a block the theme hides, a closed menu or pop-up) stops counting, and so does the phone copy of a block a page builder prints twice, once for desktop and once for phones. Both stay listed on the page\'s report with the reason.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="lynbro-muted">
				<?php esc_html_e( 'It runs in this browser tab, a few seconds per page. Keep the tab open and in front until it says it has finished; a browser slows down tabs it is not showing.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p>
				<strong>
					<?php
					printf(
						/* translators: 1: pages checked in the browser, 2: scanned pages. */
						esc_html__( 'Checked in the browser so far: %1$s of %2$s scanned pages.', 'lynbro-accessibility-checker' ),
						esc_html( number_format_i18n( $coverage['checked'] ) ),
						esc_html( number_format_i18n( $coverage['total'] ) )
					);
					?>
				</strong>
			</p>

			<?php if ( 'page' === $scope ) : ?>
				<?php $page = lynbro_a11y_get_page( $page_id ); ?>
				<p>
					<?php
					printf(
						/* translators: %s: page name. */
						esc_html__( 'Only this page: %s', 'lynbro-accessibility-checker' ),
						esc_html( $page ? lynbro_a11y_page_label( $page ) : '#' . $page_id )
					);
					?>
					· <a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-browser' ) ); ?>"><?php esc_html_e( 'Check other pages', 'lynbro-accessibility-checker' ); ?></a>
				</p>
			<?php else : ?>
				<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="lynbro-a11y-browser-scope">
					<input type="hidden" name="page" value="lynbro-accessibility-browser">
					<fieldset>
						<legend><?php esc_html_e( 'Which pages', 'lynbro-accessibility-checker' ); ?></legend>
						<label><input type="radio" name="scope" value="unchecked" <?php checked( 'unchecked', $scope ); ?>> <?php esc_html_e( 'Pages not checked in the browser yet', 'lynbro-accessibility-checker' ); ?></label><br>
						<label><input type="radio" name="scope" value="all" <?php checked( 'all', $scope ); ?>> <?php esc_html_e( 'Every scanned page, the ones checked longest ago first', 'lynbro-accessibility-checker' ); ?></label>
					</fieldset>
					<p><button type="submit" class="button"><?php esc_html_e( 'Show these pages', 'lynbro-accessibility-checker' ); ?></button></p>
				</form>
			<?php endif; ?>

			<?php if ( ! $items ) : ?>
				<p>
					<?php
					echo esc_html(
						'unchecked' === $scope && $coverage['total']
							? __( 'Every scanned page has been checked in the browser. Choose "Every scanned page" to check them again.', 'lynbro-accessibility-checker' )
							: __( 'There are no scanned pages to check. Run a scan on the Overview tab first.', 'lynbro-accessibility-checker' )
					);
					?>
				</p>
			<?php else : ?>
				<p class="lynbro-tools-row">
					<button type="button" class="button button-primary" id="lynbro-a11y-browser-start">
						<?php
						printf(
							/* translators: %s: number of pages. */
							esc_html( _n( 'Check %s page in the browser', 'Check %s pages in the browser', count( $items ), 'lynbro-accessibility-checker' ) ),
							esc_html( number_format_i18n( count( $items ) ) )
						);
						?>
					</button>
					<button type="button" class="button" id="lynbro-a11y-browser-stop" hidden><?php esc_html_e( 'Stop', 'lynbro-accessibility-checker' ); ?></button>
				</p>

				<div id="lynbro-a11y-browser-run" hidden>
					<progress id="lynbro-a11y-browser-progress" max="<?php echo esc_attr( (string) count( $items ) ); ?>" value="0"></progress>
					<p id="lynbro-a11y-browser-now"></p>
					<p id="lynbro-a11y-browser-status" role="status" aria-live="polite"></p>
					<div id="lynbro-a11y-browser-result"></div>
					<div class="lynbro-a11y-frames" aria-hidden="true">
						<figure>
							<div class="lynbro-a11y-frame lynbro-a11y-frame--desktop" id="lynbro-a11y-frame-desktop"></div>
							<figcaption><?php esc_html_e( 'Desktop, 1280 pixels', 'lynbro-accessibility-checker' ); ?></figcaption>
						</figure>
						<figure>
							<div class="lynbro-a11y-frame lynbro-a11y-frame--phone" id="lynbro-a11y-frame-phone"></div>
							<figcaption><?php esc_html_e( 'Phone, 390 pixels', 'lynbro-accessibility-checker' ); ?></figcaption>
						</figure>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
