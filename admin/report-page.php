<?php
/**
 * The report.
 *
 * Three views of the same data, because there are three ways people arrive:
 * "what is wrong with this page", "where else does this fault appear", and
 * "which pages are worst". All three are free and unlimited — the competitor
 * that charges for the site-wide view is the reason this plugin exists.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Router for the report screen.
 *
 * @return void
 */
function lynbro_a11y_render_report() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation between views.
	$rule    = isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '';
	$page_id = isset( $_GET['page_id'] ) ? absint( $_GET['page_id'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( $page_id ) {
		lynbro_a11y_render_report_page_view( $page_id );
		return;
	}
	if ( '' !== $rule && lynbro_a11y_rule( $rule ) ) {
		lynbro_a11y_render_report_rule_view( $rule );
		return;
	}

	lynbro_a11y_render_report_overview();
}

/**
 * Pages, worst first.
 *
 * @return void
 */
function lynbro_a11y_render_report_overview() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging.
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per    = 50;
	$total  = lynbro_a11y_count_pages( false );
	$rows   = lynbro_a11y_list_pages(
		array(
			'offset' => ( $paged - 1 ) * $per,
			'number' => $per,
		)
	);
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Report', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-report' ); ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'Pages', 'lynbro-accessibility-checker' ); ?></h2>

			<?php if ( ! $rows ) : ?>
				<p><?php esc_html_e( 'Nothing has been scanned yet. Start a scan on the Overview tab.', 'lynbro-accessibility-checker' ); ?></p>
			<?php else : ?>
				<p class="desc">
					<?php esc_html_e( 'Open a page to see every problem on it, with the element that causes it. "Check in browser" opens the page with the contrast, focus and target-size measurements running.', 'lynbro-accessibility-checker' ); ?>
				</p>
				<?php $coverage = lynbro_a11y_browser_coverage(); ?>
				<?php if ( $coverage['checked'] < $coverage['total'] ) : ?>
					<p class="lynbro-a11y-coverage">
						<?php
						printf(
							/* translators: 1: pages checked in the browser, 2: scanned pages. */
							esc_html__( 'Contrast, focus and target size have been measured on %1$s of %2$s pages.', 'lynbro-accessibility-checker' ),
							esc_html( number_format_i18n( $coverage['checked'] ) ),
							esc_html( number_format_i18n( $coverage['total'] ) )
						);
						?>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-browser' ) ); ?>"><?php esc_html_e( 'Check every page in the browser', 'lynbro-accessibility-checker' ); ?></a>
					</p>
				<?php endif; ?>

				<div class="lynbro-a11y-scroll" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Scanned pages, worst first', 'lynbro-accessibility-checker' ); ?>">
				<table class="widefat striped lynbro-a11y-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Scanned pages, worst first', 'lynbro-accessibility-checker' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Page', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Failures', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Likely', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'To review', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Last scanned', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'lynbro-accessibility-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<th scope="row">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . (int) $row['id'] ) ); ?>">
										<?php echo esc_html( lynbro_a11y_page_label( $row ) ); ?>
									</a>
									<span class="lynbro-muted lynbro-a11y-url"><?php echo esc_html( (string) $row['url'] ); ?></span>
									<?php if ( '' !== (string) $row['note'] ) : ?>
										<span class="lynbro-muted"><?php echo esc_html( (string) $row['note'] ); ?></span>
									<?php endif; ?>
								</th>
								<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['errors'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['warnings'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( (int) $row['reviews'] ) ); ?></td>
								<td>
									<?php
									echo esc_html(
										$row['scanned_at']
											? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['scanned_at'] )
											: __( 'never', 'lynbro-accessibility-checker' )
									);
									?>
								</td>
								<td>
									<a href="<?php echo esc_url( lynbro_a11y_live_check_url( (string) $row['url'] ) ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Check in browser', 'lynbro-accessibility-checker' ); ?>
										<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'lynbro-accessibility-checker' ); ?></span>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>

				<?php lynbro_a11y_pagination( $paged, (int) ceil( $total / $per ), admin_url( 'admin.php?page=lynbro-accessibility-report' ) ); ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Everything found on one page.
 *
 * @param int $page_id Page id.
 * @return void
 */
function lynbro_a11y_render_report_page_view( $page_id ) {
	$page = lynbro_a11y_get_page( $page_id );
	if ( ! $page ) {
		wp_die( esc_html__( 'That page is not in the report.', 'lynbro-accessibility-checker' ) );
	}

	$issues = lynbro_a11y_page_issues( $page_id, true );
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Report', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-report' ); ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report' ) ); ?>">
				<?php esc_html_e( '← All pages', 'lynbro-accessibility-checker' ); ?>
			</a>
		</p>

		<div class="lynbro-section">
			<h2><?php echo esc_html( lynbro_a11y_page_label( $page ) ); ?></h2>
			<p class="desc">
				<?php /* The address is shown, but it is not the link text: a screen reader
				   reads a bare URL out character by character. Our own scanner reported
				   this screen for it. */ ?>
				<a href="<?php echo esc_url( (string) $page['url'] ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open this page', 'lynbro-accessibility-checker' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'lynbro-accessibility-checker' ); ?></span>
				</a>
				<span class="lynbro-a11y-url"><?php echo esc_html( (string) $page['url'] ); ?></span>
			</p>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display flag after our own redirect.
			if ( ! empty( $_GET['lynbro_a11y_rechecked'] ) ) :
				?>
				<div class="notice notice-success inline"><p><?php esc_html_e( 'The page has been checked again. What you see below is what is on it now.', 'lynbro-accessibility-checker' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $page['scanned_at'] ) ) : ?>
				<p class="lynbro-muted">
					<?php
					printf(
						/* translators: %s: date and time of the last check. */
						esc_html__( 'Last checked %s.', 'lynbro-accessibility-checker' ),
						esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $page['scanned_at'] ) )
					);
					?>
				</p>
			<?php endif; ?>

			<div class="lynbro-tools-row">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php lynbro_a11y_nonce_field( 'lynbro_a11y_rescan_' . (int) $page['id'] ); ?>
					<input type="hidden" name="action" value="lynbro_a11y_rescan_page">
					<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) (int) $page['id'] ); ?>">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Check this page again', 'lynbro-accessibility-checker' ); ?></button>
				</form>
				<a class="button" href="<?php echo esc_url( lynbro_a11y_browser_check_link( (int) $page['id'] ) ); ?>">
					<?php esc_html_e( 'Check in the browser, at desktop and phone width', 'lynbro-accessibility-checker' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( lynbro_a11y_live_check_url( (string) $page['url'] ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Open the page with the check running', 'lynbro-accessibility-checker' ); ?>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'lynbro-accessibility-checker' ); ?></span>
				</a>
			</div>

			<?php if ( empty( $page['browser_at'] ) ) : ?>
				<p class="lynbro-muted">
					<?php esc_html_e( 'Contrast, focus visibility and target size are not in this page\'s markup — they are the result of its stylesheet. Nothing below covers them until you run the browser check.', 'lynbro-accessibility-checker' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( ! $issues ) : ?>
				<p><?php esc_html_e( 'Nothing was found in the markup of this page. That is not the same as "this page is accessible" — see the Manual checks tab.', 'lynbro-accessibility-checker' ); ?></p>
			<?php else : ?>
				<?php lynbro_a11y_render_issue_list( $issues ); ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Everywhere one rule fires.
 *
 * @param string $rule Rule id.
 * @return void
 */
function lynbro_a11y_render_report_rule_view( $rule ) {
	$definition = lynbro_a11y_rule( $rule );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only paging.
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$per    = 100;
	$issues = lynbro_a11y_issues_for_rule( $rule, $per + 1, ( $paged - 1 ) * $per );
	$more   = count( $issues ) > $per;
	$issues = array_slice( $issues, 0, $per );
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Report', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-report' ); ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report' ) ); ?>">
				<?php esc_html_e( '← All pages', 'lynbro-accessibility-checker' ); ?>
			</a>
		</p>

		<div class="lynbro-section">
			<h2><?php echo esc_html( $definition['title'] ); ?></h2>
			<p>
				<?php echo wp_kses_post( lynbro_a11y_badge( $definition['severity'] ) ); ?>
				<span class="lynbro-muted">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: WCAG criterion, 2: conformance level. */
							__( 'WCAG 2.2 success criterion %1$s, level %2$s', 'lynbro-accessibility-checker' ),
							$definition['wcag'],
							$definition['level']
						)
					);
					?>
				</span>
			</p>

			<h3><?php esc_html_e( 'Why this matters', 'lynbro-accessibility-checker' ); ?></h3>
			<p><?php echo esc_html( $definition['why'] ); ?></p>

			<h3><?php esc_html_e( 'How to fix it', 'lynbro-accessibility-checker' ); ?></h3>
			<p><?php echo esc_html( $definition['how'] ); ?></p>

			<?php $rule_hidden = in_array( $rule, lynbro_a11y_ignored_rules(), true ); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lynbro-a11y-rule-hide">
				<?php lynbro_a11y_nonce_field( 'lynbro_a11y_ignore_rule_' . $rule ); ?>
				<input type="hidden" name="action" value="lynbro_a11y_ignore_rule">
				<input type="hidden" name="rule" value="<?php echo esc_attr( $rule ); ?>">
				<input type="hidden" name="hidden" value="<?php echo esc_attr( $rule_hidden ? '0' : '1' ); ?>">
				<?php if ( $rule_hidden ) : ?>
					<p class="lynbro-a11y-dismissal"><?php esc_html_e( 'This rule is hidden on every page: it is still checked, but not reported or counted.', 'lynbro-accessibility-checker' ); ?></p>
					<button type="submit" class="button"><?php esc_html_e( 'Report this rule again', 'lynbro-accessibility-checker' ); ?></button>
				<?php else : ?>
					<button type="submit" class="button"><?php esc_html_e( 'Hide this rule on every page', 'lynbro-accessibility-checker' ); ?></button>
				<?php endif; ?>
			</form>

			<?php if ( ! $issues ) : ?>
				<p><?php esc_html_e( 'This one is not on your site right now.', 'lynbro-accessibility-checker' ); ?></p>
			<?php else : ?>
				<h3><?php esc_html_e( 'Where it is', 'lynbro-accessibility-checker' ); ?></h3>
				<div class="lynbro-a11y-scroll" role="region" tabindex="0" aria-label="<?php esc_attr_e( 'Every place this rule fires', 'lynbro-accessibility-checker' ); ?>">
				<table class="widefat striped lynbro-a11y-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Every place this rule fires', 'lynbro-accessibility-checker' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Page', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Element', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Detail', 'lynbro-accessibility-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $issues as $issue ) : ?>
							<tr>
								<th scope="row">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&page_id=' . (int) $issue['page_id'] ) ); ?>">
										<?php echo esc_html( lynbro_a11y_page_label( $issue ) ); ?>
									</a>
								</th>
								<td><code><?php echo esc_html( (string) $issue['selector'] ); ?></code></td>
								<td><?php echo esc_html( (string) $issue['context'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				</div>

				<?php if ( $more || $paged > 1 ) : ?>
					<p class="lynbro-a11y-pager">
						<?php if ( $paged > 1 ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'lynbro-accessibility-report', 'rule' => $rule, 'paged' => $paged - 1 ), admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Previous', 'lynbro-accessibility-checker' ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $more ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'lynbro-accessibility-report', 'rule' => $rule, 'paged' => $paged + 1 ), admin_url( 'admin.php' ) ) ); ?>">
								<?php esc_html_e( 'Next', 'lynbro-accessibility-checker' ); ?>
							</a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * A list of issues, grouped by severity, each with its explanation.
 *
 * @param array<int,array<string,mixed>> $issues Issue rows.
 * @return void
 */
function lynbro_a11y_render_issue_list( $issues ) {
	$groups = array( 'error' => array(), 'warning' => array(), 'review' => array() );
	foreach ( $issues as $issue ) {
		$key = isset( $groups[ $issue['severity'] ] ) ? $issue['severity'] : 'error';
		$groups[ $key ][] = $issue;
	}

	foreach ( $groups as $severity => $rows ) {
		if ( ! $rows ) {
			continue;
		}

		// What counts first; what is hidden, off screen or a phone copy after it,
		// so the number in the heading is the number of entries at the top.
		$counted = array();
		$aside   = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['ignored'] ) || ! empty( $row['offscreen'] ) ) {
				$aside[] = $row;
			} else {
				$counted[] = $row;
			}
		}
		$rows = array_merge( $counted, $aside );

		$heading = sprintf(
			/* translators: 1: severity name, 2: how many issues. */
			__( '%1$s (%2$s)', 'lynbro-accessibility-checker' ),
			lynbro_a11y_severity_label( $severity ),
			number_format_i18n( count( $counted ) )
		);
		if ( $aside ) {
			$heading .= ' · ' . sprintf(
				/* translators: %s: how many findings are listed but not counted. */
				__( '%s more listed, not counted', 'lynbro-accessibility-checker' ),
				number_format_i18n( count( $aside ) )
			);
		}
		printf( '<h3>%s</h3>', esc_html( $heading ) );

		echo '<ul class="lynbro-a11y-issues">';
		foreach ( $rows as $issue ) {
			$rule = lynbro_a11y_rule( $issue['rule'] );
			?>
			<?php $offscreen = isset( $issue['offscreen'] ) ? (int) $issue['offscreen'] : 0; ?>
			<li class="<?php echo esc_attr( $issue['ignored'] || $offscreen ? 'is-ignored' : '' ); ?>">
				<h4>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&rule=' . rawurlencode( $issue['rule'] ) ) ); ?>">
						<?php echo esc_html( isset( $rule['title'] ) ? $rule['title'] : $issue['rule'] ); ?>
					</a>
					<span class="lynbro-muted">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: WCAG criterion, 2: level. */
								__( 'WCAG %1$s (%2$s)', 'lynbro-accessibility-checker' ),
								isset( $rule['wcag'] ) ? $rule['wcag'] : '—',
								isset( $rule['level'] ) ? $rule['level'] : '—'
							)
						);
						?>
					</span>
				</h4>

				<?php if ( '' !== (string) $issue['context'] ) : ?>
					<p class="lynbro-a11y-context"><?php echo esc_html( (string) $issue['context'] ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== (string) $issue['selector'] ) : ?>
					<p class="lynbro-a11y-where">
						<span class="screen-reader-text"><?php esc_html_e( 'Where:', 'lynbro-accessibility-checker' ); ?></span>
						<code><?php echo esc_html( (string) $issue['selector'] ); ?></code>
					</p>
				<?php endif; ?>

				<?php if ( '' !== (string) $issue['snippet'] ) : ?>
					<pre class="lynbro-a11y-snippet"><code><?php echo esc_html( (string) $issue['snippet'] ); ?></code></pre>
				<?php endif; ?>

				<p class="lynbro-a11y-how"><?php echo esc_html( isset( $rule['how'] ) ? $rule['how'] : '' ); ?></p>

				<?php if ( LYNBRO_A11Y_OFFSCREEN_HIDDEN === $offscreen ) : ?>
					<p class="lynbro-a11y-offscreen"><?php esc_html_e( 'Not counted: the browser check found this element off screen when the page loads, at desktop width and at phone width, so browsers leave it out of what a screen reader gets. If it is a menu, tab or pop-up that opens, check it while it is open.', 'lynbro-accessibility-checker' ); ?></p>
				<?php elseif ( LYNBRO_A11Y_OFFSCREEN_TWIN === $offscreen ) : ?>
					<p class="lynbro-a11y-offscreen"><?php esc_html_e( 'Not counted: this is the phone copy of a block the page also prints for desktop, with the same problem. The desktop copy is counted; fixing the block fixes both.', 'lynbro-accessibility-checker' ); ?></p>
				<?php endif; ?>

				<?php if ( $issue['ignored'] ) : ?>
					<?php $everywhere = lynbro_a11y_issue_hidden_everywhere( $issue ); ?>
					<p class="lynbro-a11y-dismissal">
						<?php echo esc_html( lynbro_a11y_dismissal_line( $issue, $everywhere ) ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lynbro-a11y-ignore">
						<?php lynbro_a11y_nonce_field( 'lynbro_a11y_ignore_' . (int) $issue['id'] ); ?>
						<input type="hidden" name="action" value="lynbro_a11y_ignore">
						<input type="hidden" name="issue" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
						<input type="hidden" name="ignored" value="0">
						<input type="hidden" name="scope" value="<?php echo esc_attr( $everywhere ? 'everywhere' : 'page' ); ?>">
						<button type="submit" class="button-link">
							<?php echo esc_html( $everywhere ? __( 'Show this again on every page', 'lynbro-accessibility-checker' ) : __( 'Show this again', 'lynbro-accessibility-checker' ) ); ?>
						</button>
					</form>
				<?php else : ?>
					<details class="lynbro-a11y-ignore">
						<summary><?php esc_html_e( 'Not a problem? Hide it', 'lynbro-accessibility-checker' ); ?></summary>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php lynbro_a11y_nonce_field( 'lynbro_a11y_ignore_' . (int) $issue['id'] ); ?>
							<input type="hidden" name="action" value="lynbro_a11y_ignore">
							<input type="hidden" name="issue" value="<?php echo esc_attr( (string) $issue['id'] ); ?>">
							<input type="hidden" name="ignored" value="1">
							<p>
								<label for="<?php echo esc_attr( 'lynbro-a11y-note-' . (int) $issue['id'] ); ?>"><?php esc_html_e( 'Why (optional, kept with the decision)', 'lynbro-accessibility-checker' ); ?></label><br>
								<input type="text" class="regular-text" maxlength="255" id="<?php echo esc_attr( 'lynbro-a11y-note-' . (int) $issue['id'] ); ?>" name="note">
							</p>
							<p class="lynbro-tools-row">
								<button type="submit" class="button" name="scope" value="page"><?php esc_html_e( 'Hide on this page', 'lynbro-accessibility-checker' ); ?></button>
								<?php if ( '' !== (string) $issue['selector'] ) : ?>
									<button type="submit" class="button" name="scope" value="everywhere"><?php esc_html_e( 'Hide this element on every page', 'lynbro-accessibility-checker' ); ?></button>
								<?php endif; ?>
							</p>
						</form>
					</details>
				<?php endif; ?>
			</li>
			<?php
		}
		echo '</ul>';
	}
}

/**
 * "Hidden on every page by Anna on 8 November 2026: part of the theme."
 *
 * @param array<string,mixed> $issue      Issue row.
 * @param bool                $everywhere Whether it is hidden on every page.
 * @return string
 */
function lynbro_a11y_dismissal_line( $issue, $everywhere ) {
	$user = ! empty( $issue['ignored_by'] ) ? get_userdata( (int) $issue['ignored_by'] ) : false;
	$who  = $user ? $user->display_name : '';
	$when = ! empty( $issue['ignored_at'] ) ? mysql2date( get_option( 'date_format' ), (string) $issue['ignored_at'] ) : '';

	if ( '' !== $who && '' !== $when ) {
		$line = $everywhere
			/* translators: 1: user name, 2: date. */
			? sprintf( __( 'Hidden on every page by %1$s on %2$s.', 'lynbro-accessibility-checker' ), $who, $when )
			/* translators: 1: user name, 2: date. */
			: sprintf( __( 'Hidden by %1$s on %2$s.', 'lynbro-accessibility-checker' ), $who, $when );
	} else {
		$line = $everywhere ? __( 'Hidden on every page.', 'lynbro-accessibility-checker' ) : __( 'Hidden.', 'lynbro-accessibility-checker' );
	}

	if ( '' !== (string) $issue['ignored_note'] ) {
		/* translators: 1: who hid it and when, 2: the reason they gave. */
		$line = sprintf( __( '%1$s Reason: %2$s', 'lynbro-accessibility-checker' ), $line, (string) $issue['ignored_note'] );
	}
	return $line;
}

/**
 * A plain previous/next pager.
 *
 * @param int    $paged Current page, one-based.
 * @param int    $total Total pages.
 * @param string $base  Base URL.
 * @return void
 */
function lynbro_a11y_pagination( $paged, $total, $base ) {
	if ( $total < 2 ) {
		return;
	}
	echo '<p class="lynbro-a11y-pager">';
	if ( $paged > 1 ) {
		printf(
			'<a class="button" href="%s">%s</a> ',
			esc_url( add_query_arg( 'paged', $paged - 1, $base ) ),
			esc_html__( 'Previous', 'lynbro-accessibility-checker' )
		);
	}
	printf(
		'<span class="lynbro-muted">%s</span> ',
		esc_html(
			sprintf(
				/* translators: 1: current page number, 2: total pages. */
				__( 'Page %1$s of %2$s', 'lynbro-accessibility-checker' ),
				number_format_i18n( $paged ),
				number_format_i18n( $total )
			)
		)
	);
	if ( $paged < $total ) {
		printf(
			'<a class="button" href="%s">%s</a>',
			esc_url( add_query_arg( 'paged', $paged + 1, $base ) ),
			esc_html__( 'Next', 'lynbro-accessibility-checker' )
		);
	}
	echo '</p>';
}
