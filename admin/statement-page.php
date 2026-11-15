<?php
/**
 * The accessibility statement screen.
 *
 * A short form for what only the owner knows, the facts the checks supply, a
 * preview of the statement exactly as it will be written, and one button that
 * writes it to a draft page. See includes/statement.php for why it is built
 * this way.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle the form, if it was sent.
 *
 * @return array{saved:bool,written:int,errors:array<int,string>}
 */
function lynbro_a11y_statement_handle_post() {
	$result = array(
		'saved'   => false,
		'written'   => 0,
		'suggested' => false,
		'errors'    => array(),
	);

	if ( ! isset( $_POST['lynbro_a11y_statement_nonce'] ) ) {
		return $result;
	}
	check_admin_referer( 'lynbro_a11y_statement', 'lynbro_a11y_statement_nonce' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked on the line above.
	$raw = isset( $_POST['lynbro_a11y_statement'] ) && is_array( $_POST['lynbro_a11y_statement'] )
		? map_deep( wp_unslash( $_POST['lynbro_a11y_statement'] ), 'sanitize_textarea_field' )
		: array();
	$write   = ! empty( $_POST['lynbro_a11y_write'] );
	$suggest = isset( $_POST['lynbro_a11y_suggest'] ) ? sanitize_key( wp_unslash( $_POST['lynbro_a11y_suggest'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	// A suggestion button puts the ready wording in the field; the owner sees it before anything is written.
	if ( '' !== $suggest ) {
		$text = lynbro_a11y_enforcement_suggestion( $suggest );
		if ( '' !== $text ) {
			$raw['enforcement']  = $text;
			$result['suggested'] = true;
			$write               = false;
		}
	}

	$previous = lynbro_a11y_statement_settings();
	$clean    = lynbro_a11y_sanitize_statement( $raw, $previous );

	if ( isset( $raw['email'] ) && '' !== trim( (string) $raw['email'] ) && '' === $clean['email'] ) {
		$result['errors'][] = __( 'That email address does not look right, so it was not saved.', 'lynbro-accessibility-checker' );
	}

	update_option( LYNBRO_A11Y_STATEMENT_OPTION, $clean, false );
	$result['saved'] = true;

	if ( $write ) {
		if ( '' === $clean['email'] && '' === $clean['phone'] ) {
			$result['errors'][] = __( 'Add an email address or a phone number first. A statement has to tell people how to report a barrier.', 'lynbro-accessibility-checker' );
			return $result;
		}
		$page_id = lynbro_a11y_write_statement_page();
		if ( is_wp_error( $page_id ) ) {
			$result['errors'][] = $page_id->get_error_message();
		} else {
			$result['written'] = (int) $page_id;
		}
	}

	return $result;
}

/**
 * Render the statement screen.
 *
 * @return void
 */
function lynbro_a11y_render_statement() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	$posted   = lynbro_a11y_statement_handle_post();
	$s        = lynbro_a11y_statement_settings();
	$facts    = lynbro_a11y_statement_facts();
	$page     = $s['page_id'] ? get_post( (int) $s['page_id'] ) : null;
	$has_page = $page && 'page' === $page->post_type && 'trash' !== $page->post_status;
	$preview  = lynbro_a11y_build_statement(
		array_merge(
			$s,
			array(
				'prepared' => '' !== (string) $s['prepared'] ? $s['prepared'] : current_time( 'Y-m-d' ),
				'reviewed' => current_time( 'Y-m-d' ),
			)
		),
		3
	);
	$site_lang = str_replace( '_', '-', get_locale() );
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Accessibility statement', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-statement' ); ?>

		<?php foreach ( $posted['errors'] as $error ) : ?>
			<div class="notice notice-error inline" role="alert"><p><?php echo esc_html( $error ); ?></p></div>
		<?php endforeach; ?>

		<?php if ( $posted['written'] ) : ?>
			<div class="notice notice-success inline" role="status">
				<p>
					<?php
					if ( 'publish' === get_post_status( $posted['written'] ) ) {
						esc_html_e( 'The statement page has been updated. The previous text is kept in the page’s revisions.', 'lynbro-accessibility-checker' );
					} else {
						esc_html_e( 'The statement has been written to a draft page. Read it, change what you want, publish it and link to it from your footer.', 'lynbro-accessibility-checker' );
					}
					?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $posted['written'] ) ); ?>"><?php esc_html_e( 'Edit the statement page', 'lynbro-accessibility-checker' ); ?></a>
				</p>
			</div>
		<?php elseif ( $posted['suggested'] ) : ?>
			<div class="notice notice-info inline" role="status">
				<p><?php esc_html_e( 'The suggested wording is now in the Enforcement procedure field and saved. Check that it fits your business before you write the page.', 'lynbro-accessibility-checker' ); ?></p>
			</div>
		<?php elseif ( $posted['saved'] && ! $posted['errors'] ) : ?>
			<?php lynbro_a11y_saved_notice( true ); ?>
		<?php endif; ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'A statement people can find', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'The European Accessibility Act expects a website that sells to consumers to publish an accessibility statement: how accessible the site is, what is not accessible yet, and how to report a barrier. This screen writes a first version from your scan results and manual checks. You read it, change what you want and publish it.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="desc">
				<?php esc_html_e( 'This is a writing aid, not legal advice. It does not decide whether the rules apply to you or what your statement must contain where you are, and it does not certify the site. The statement is published under your name, so read it and correct it before you publish it.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="desc">
				<?php
				if ( $has_page ) {
					printf(
						/* translators: 1: link to the page, 2: page status, 3: date last written. */
						esc_html__( 'Statement page: %1$s (%2$s), last written %3$s.', 'lynbro-accessibility-checker' ),
						'<a href="' . esc_url( (string) get_edit_post_link( $page->ID ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a>',
						esc_html( (string) get_post_status_object( $page->post_status )->label ),
						esc_html( '' !== (string) $s['reviewed'] ? mysql2date( get_option( 'date_format' ), $s['reviewed'] . ' 00:00:00' ) : '—' )
					);
				} else {
					esc_html_e( 'There is no statement page yet.', 'lynbro-accessibility-checker' );
				}
				?>
			</p>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'lynbro_a11y_statement', 'lynbro_a11y_statement_nonce' ); ?>
			<?php // Enter in a text field presses the first submit button in the form: make that a plain save, not a suggestion button. ?>
			<button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true"><?php esc_html_e( 'Save', 'lynbro-accessibility-checker' ); ?></button>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'Who people contact', 'lynbro-accessibility-checker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="statement_org"><?php esc_html_e( 'Organisation name', 'lynbro-accessibility-checker' ); ?></label></th>
							<td><input type="text" id="statement_org" name="lynbro_a11y_statement[org]" class="regular-text" value="<?php echo esc_attr( (string) $s['org'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_email"><?php esc_html_e( 'Email address', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="email" id="statement_email" name="lynbro_a11y_statement[email]" class="regular-text" autocomplete="email" value="<?php echo esc_attr( (string) $s['email'] ); ?>" aria-describedby="statement_contact_help">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_phone"><?php esc_html_e( 'Phone number', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="tel" id="statement_phone" name="lynbro_a11y_statement[phone]" class="regular-text" autocomplete="tel" value="<?php echo esc_attr( (string) $s['phone'] ); ?>" aria-describedby="statement_contact_help">
								<p class="description" id="statement_contact_help"><?php esc_html_e( 'At least one of the two. Both are printed on the public page, so use an address and a number meant for customers.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_reply"><?php esc_html_e( 'How soon you reply', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="text" id="statement_reply" name="lynbro_a11y_statement[reply]" class="regular-text" value="<?php echo esc_attr( (string) $s['reply'] ); ?>" aria-describedby="statement_reply_help">
								<p class="description" id="statement_reply_help"><?php esc_html_e( 'Optional, in your site’s language, for example “within five working days”. Printed as “We aim to reply …”.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'How accessible the site is', 'lynbro-accessibility-checker' ); ?></h2>
				<p class="desc">
					<?php
					if ( $facts['pages_scanned'] > 0 ) {
						printf(
							/* translators: 1: pages checked, 2: date, 3: failures, 4: likely failures, 5: manual checks answered, 6: manual checks in total. */
							esc_html__( 'From your checks: %1$s pages scanned (last on %2$s), %3$s failures and %4$s likely failures, %5$s of %6$s manual checks answered.', 'lynbro-accessibility-checker' ),
							esc_html( number_format_i18n( $facts['pages_scanned'] ) ),
							esc_html( (string) $facts['scan_date'] ),
							esc_html( number_format_i18n( $facts['errors'] ) ),
							esc_html( number_format_i18n( $facts['warnings'] ) ),
							esc_html( number_format_i18n( $facts['manual_done'] ) ),
							esc_html( number_format_i18n( $facts['manual_total'] ) )
						);
					} else {
						printf(
							/* translators: %s: link to the overview screen. */
							esc_html__( 'The site has not been scanned yet, so the statement has no findings to list. %s first for a statement that says something.', 'lynbro-accessibility-checker' ),
							'<a href="' . esc_url( admin_url( 'admin.php?page=lynbro-accessibility' ) ) . '">' . esc_html__( 'Scan the site', 'lynbro-accessibility-checker' ) . '</a>'
						);
					}
					?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Compliance status', 'lynbro-accessibility-checker' ); ?></th>
							<td>
								<fieldset aria-describedby="statement_status_help">
									<legend class="screen-reader-text"><?php esc_html_e( 'Compliance status', 'lynbro-accessibility-checker' ); ?></legend>
									<?php foreach ( lynbro_a11y_statement_statuses() as $value => $label ) : ?>
										<label for="<?php echo esc_attr( 'statement_status_' . $value ); ?>">
											<input type="radio" id="<?php echo esc_attr( 'statement_status_' . $value ); ?>" name="lynbro_a11y_statement[status]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $s['status'], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</label><br>
									<?php endforeach; ?>
								</fieldset>
								<p class="description" id="statement_status_help">
									<?php esc_html_e( 'Your choice, not the plugin’s. Automated testing reaches about a third of WCAG, so “fully meets” needs the manual checks too.', 'lynbro-accessibility-checker' ); ?>
								</p>
								<?php if ( 'full' === $s['status'] && ( $facts['errors'] > 0 || $facts['warnings'] > 0 || $facts['manual_fails'] ) ) : ?>
									<p class="lynbro-a11y-unchecked">
										<?php esc_html_e( 'Your checks still show problems, so a statement that says the site fully meets WCAG 2.2 AA would not be accurate.', 'lynbro-accessibility-checker' ); ?>
									</p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Known problems', 'lynbro-accessibility-checker' ); ?></th>
							<td>
								<label for="statement_include_scan">
									<input type="checkbox" id="statement_include_scan" name="lynbro_a11y_statement[include_scan]" value="1" <?php checked( ! empty( $s['include_scan'] ) ); ?>>
									<?php esc_html_e( 'List the failures and likely failures from the scan, the manual checks answered as a problem, and linked documents', 'lynbro-accessibility-checker' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_other"><?php esc_html_e( 'Other known problems', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<textarea id="statement_other" name="lynbro_a11y_statement[other]" rows="3" class="large-text" aria-describedby="statement_other_help"><?php echo esc_textarea( (string) $s['other'] ); ?></textarea>
								<p class="description" id="statement_other_help"><?php esc_html_e( 'One per line, in plain words, with when you expect to fix it if you know.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_burden"><?php esc_html_e( 'Disproportionate burden', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<textarea id="statement_burden" name="lynbro_a11y_statement[burden]" rows="3" class="large-text" aria-describedby="statement_burden_help"><?php echo esc_textarea( (string) $s['burden'] ); ?></textarea>
								<p class="description" id="statement_burden_help"><?php esc_html_e( 'Optional. Anything you will not make accessible because the cost would be out of proportion, and why. Leave empty if there is nothing.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_scope"><?php esc_html_e( 'Outside the scope', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<textarea id="statement_scope" name="lynbro_a11y_statement[scope]" rows="3" class="large-text" aria-describedby="statement_scope_help"><?php echo esc_textarea( (string) $s['scope'] ); ?></textarea>
								<p class="description" id="statement_scope_help"><?php esc_html_e( 'Optional. For example content from other companies that you do not control, such as an embedded map or a booking widget.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="statement_enforcement"><?php esc_html_e( 'Enforcement procedure', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<textarea id="statement_enforcement" name="lynbro_a11y_statement[enforcement]" rows="3" class="large-text" aria-describedby="statement_enforcement_help"><?php echo esc_textarea( (string) $s['enforcement'] ); ?></textarea>
								<p class="description" id="statement_enforcement_help"><?php esc_html_e( 'Optional. Where people can turn if they are not satisfied with your answer: the authority that supervises accessibility in your country, with its contact details. One paragraph per line; web addresses and email addresses become links.', 'lynbro-accessibility-checker' ); ?></p>
								<p class="lynbro-tools-row">
									<?php foreach ( lynbro_a11y_enforcement_suggestions() as $key => $suggestion ) : ?>
										<button type="submit" class="button" name="lynbro_a11y_suggest" value="<?php echo esc_attr( $key ); ?>">
											<?php
											/* translators: %s: where the wording applies, e.g. "Denmark: a business that sells to consumers". */
											echo esc_html( sprintf( __( 'Use the wording for %s', 'lynbro-accessibility-checker' ), $suggestion['label'] ) );
											?>
										</button>
									<?php endforeach; ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Checked against the Danish authorities’ own pages in November 2026. It replaces what is in the field. Danish public bodies are under a different law and publish their statement through Digitaliseringsstyrelsen’s own solution (was.digst.dk), not with this page.', 'lynbro-accessibility-checker' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<p class="submit lynbro-tools-row">
				<button type="submit" class="button button-primary" name="lynbro_a11y_write" value="1">
					<?php $has_page ? esc_html_e( 'Save and update the statement page', 'lynbro-accessibility-checker' ) : esc_html_e( 'Save and write the statement page', 'lynbro-accessibility-checker' ); ?>
				</button>
				<button type="submit" class="button"><?php esc_html_e( 'Save', 'lynbro-accessibility-checker' ); ?></button>
			</p>
		</form>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'Preview', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc"><?php esc_html_e( 'This is the text the button writes, in your site’s language, from what is saved above and the checks as they are now.', 'lynbro-accessibility-checker' ); ?></p>
			<div class="lynbro-a11y-statement-preview" lang="<?php echo esc_attr( $site_lang ); ?>">
				<?php
				// Our own markup, assembled from escaped parts in includes/statement.php.
				echo wp_kses_post( $preview['markup'] );
				?>
			</div>
		</div>
	</div>
	<?php
}
