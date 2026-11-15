<?php
/**
 * The overview.
 *
 * What an owner needs on opening this screen, in order: is anything broken, how
 * much, where do I start, and what has this thing not looked at. The last one
 * is the part every other tool leaves out.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the overview.
 *
 * @return void
 */
function lynbro_a11y_render_dashboard() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	$totals   = lynbro_a11y_totals();
	$progress = lynbro_a11y_progress();
	$manual   = lynbro_a11y_manual_progress();
	$by_rule  = lynbro_a11y_issues_by_rule();
	$running  = lynbro_a11y_scan_is_running();
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Accessibility', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-checker' ); ?>

		<?php if ( ! lynbro_a11y_dom_available() ) : ?>
			<div class="notice notice-error inline">
				<p><?php esc_html_e( 'PHP on this server does not have the DOM extension, so pages cannot be parsed. Ask your host to enable ext-dom — it is part of a standard PHP build.', 'lynbro-accessibility-checker' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'Scan', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'Every published page, checked against WCAG 2.2 AA. There is no page limit and never will be — a scan that stops at twenty pages is a scan that hides the other four hundred.', 'lynbro-accessibility-checker' ); ?>
			</p>

			<div class="lynbro-a11y-scan"
				data-state="<?php echo esc_attr( $progress['state'] ); ?>">
				<p>
					<button type="button" class="button button-primary" id="lynbro-a11y-start" <?php disabled( $running ); ?>>
						<?php esc_html_e( 'Scan the site', 'lynbro-accessibility-checker' ); ?>
					</button>
					<button type="button" class="button" id="lynbro-a11y-stop" <?php disabled( ! $running ); ?>>
						<?php esc_html_e( 'Stop', 'lynbro-accessibility-checker' ); ?>
					</button>
				</p>

				<div class="lynbro-a11y-progress"
					role="progressbar"
					aria-valuemin="0"
					aria-valuemax="100"
					aria-valuenow="<?php echo esc_attr( (string) $progress['percent'] ); ?>"
					aria-label="<?php esc_attr_e( 'Scan progress', 'lynbro-accessibility-checker' ); ?>">
					<span class="bar" style="width:<?php echo esc_attr( (string) $progress['percent'] ); ?>%"></span>
				</div>

				<p id="lynbro-a11y-status" role="status" aria-live="polite">
					<?php
					if ( $progress['total'] > 0 ) {
						printf(
							/* translators: 1: pages scanned, 2: pages in total. */
							esc_html__( 'Scanned %1$s of %2$s pages.', 'lynbro-accessibility-checker' ),
							esc_html( number_format_i18n( $progress['done'] ) ),
							esc_html( number_format_i18n( $progress['total'] ) )
						);
					} elseif ( $totals['pages_scanned'] > 0 ) {
						printf(
							/* translators: %s: number of pages. */
							esc_html( _n( '%s page has been scanned.', '%s pages have been scanned.', $totals['pages_scanned'], 'lynbro-accessibility-checker' ) ),
							esc_html( number_format_i18n( $totals['pages_scanned'] ) )
						);
					} else {
						esc_html_e( 'Nothing has been scanned yet.', 'lynbro-accessibility-checker' );
					}
					?>
				</p>

				<?php if ( '' !== $progress['note'] ) : ?>
					<p class="lynbro-muted"><?php echo esc_html( $progress['note'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'What the scan found', 'lynbro-accessibility-checker' ); ?></h2>
			<ul class="lynbro-a11y-figures">
				<li>
					<span class="figure"><?php echo esc_html( number_format_i18n( $totals['error'] ) ); ?></span>
					<span class="label"><?php esc_html_e( 'Failures', 'lynbro-accessibility-checker' ); ?></span>
					<span class="note"><?php esc_html_e( 'Provable from the markup', 'lynbro-accessibility-checker' ); ?></span>
				</li>
				<li>
					<span class="figure"><?php echo esc_html( number_format_i18n( $totals['warning'] ) ); ?></span>
					<span class="label"><?php esc_html_e( 'Likely failures', 'lynbro-accessibility-checker' ); ?></span>
					<span class="note"><?php esc_html_e( 'Worth your eyes on', 'lynbro-accessibility-checker' ); ?></span>
				</li>
				<li>
					<span class="figure"><?php echo esc_html( number_format_i18n( $totals['review'] ) ); ?></span>
					<span class="label"><?php esc_html_e( 'Need a person', 'lynbro-accessibility-checker' ); ?></span>
					<span class="note"><?php esc_html_e( 'A machine cannot settle these', 'lynbro-accessibility-checker' ); ?></span>
				</li>
				<li>
					<span class="figure"><?php echo esc_html( number_format_i18n( $totals['pages_clean'] ) ); ?></span>
					<span class="label"><?php esc_html_e( 'Pages with no findings', 'lynbro-accessibility-checker' ); ?></span>
					<span class="note">
						<?php
						printf(
							/* translators: %s: number of pages scanned. */
							esc_html__( 'of %s scanned', 'lynbro-accessibility-checker' ),
							esc_html( number_format_i18n( $totals['pages_scanned'] ) )
						);
						?>
					</span>
				</li>
			</ul>

			<?php if ( $totals['pages_failed'] > 0 ) : ?>
				<p class="lynbro-a11y-unchecked">
					<?php
					printf(
						/* translators: %s: number of pages. */
						esc_html( _n( '%s page could not be checked, so it is not counted above.', '%s pages could not be checked, so they are not counted above.', $totals['pages_failed'], 'lynbro-accessibility-checker' ) ),
						esc_html( number_format_i18n( $totals['pages_failed'] ) )
					);
					?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report' ) ); ?>"><?php esc_html_e( 'See why in the report', 'lynbro-accessibility-checker' ); ?></a>
				</p>
			<?php endif; ?>

			<p class="lynbro-muted">
				<?php esc_html_e( 'There is no percentage here on purpose. No automated scan can tell you a site is accessible — automation reaches about a third of WCAG, and anyone selling you a compliance score is selling you the thing American regulators have started fining people for. The counts are the counts; the rest is on the Manual checks tab.', 'lynbro-accessibility-checker' ); ?>
			</p>
		</div>

		<?php if ( $by_rule ) : ?>
			<div class="lynbro-section">
				<h2><?php esc_html_e( 'Start here', 'lynbro-accessibility-checker' ); ?></h2>
				<p class="desc"><?php esc_html_e( 'The same fault repeated across many pages is usually one line in a theme template. These are ordered by how much they cost you.', 'lynbro-accessibility-checker' ); ?></p>

				<table class="widefat striped lynbro-a11y-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'Issues grouped by rule, worst first', 'lynbro-accessibility-checker' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Problem', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Severity', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col"><?php esc_html_e( 'WCAG', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Times', 'lynbro-accessibility-checker' ); ?></th>
							<th scope="col" class="num"><?php esc_html_e( 'Pages', 'lynbro-accessibility-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $by_rule, 0, 12 ) as $row ) : ?>
							<?php $rule = lynbro_a11y_rule( $row['rule'] ); ?>
							<tr>
								<th scope="row">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&rule=' . rawurlencode( $row['rule'] ) ) ); ?>">
										<?php echo esc_html( isset( $rule['title'] ) ? $rule['title'] : $row['rule'] ); ?>
									</a>
								</th>
								<td><?php echo wp_kses_post( lynbro_a11y_badge( $row['severity'] ) ); ?></td>
								<td>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: WCAG criterion number, 2: conformance level. */
											__( '%1$s (%2$s)', 'lynbro-accessibility-checker' ),
											isset( $rule['wcag'] ) ? $rule['wcag'] : '—',
											isset( $rule['level'] ) ? $rule['level'] : '—'
										)
									);
									?>
								</td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['total'] ) ); ?></td>
								<td class="num"><?php echo esc_html( number_format_i18n( $row['pages'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'What the scan cannot see', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php
				printf(
					/* translators: 1: number of checks answered, 2: number of checks in total. */
					esc_html__( 'Eighteen things need a person to look at them. You have answered %1$s of %2$s.', 'lynbro-accessibility-checker' ),
					esc_html( number_format_i18n( $manual['total'] - $manual['unknown'] ) ),
					esc_html( number_format_i18n( $manual['total'] ) )
				);
				?>
			</p>
			<p>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-manual' ) ); ?>">
					<?php esc_html_e( 'Go through the manual checks', 'lynbro-accessibility-checker' ); ?>
				</a>
			</p>
		</div>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'The European Accessibility Act, honestly', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'The Act has applied since 28 June 2025. In Denmark, Erhvervsstyrelsen supervises it. But microenterprises — under 10 people and turnover at or below 2 million euro — are exempt for services, so a great many small sites are not obliged to do any of this.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="desc">
				<?php esc_html_e( 'We would rather tell you that than sell you a scan you did not need. If you are over that line, the report and the manual checks are what an assessment looks like; if you are under it, everything here still makes your site work for more people, which was always the better reason.', 'lynbro-accessibility-checker' ); ?>
			</p>
		</div>
	</div>
	<?php
}
