<?php
/**
 * The Fixes screen.
 *
 * Each fix gets three sentences: what it does, what to be careful of, and how
 * many of the things it addresses are on the site right now. The last one comes
 * from the scan, so this screen never recommends something the site does not
 * need — and never hides a caveat to make a switch look more attractive.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the Fixes screen.
 *
 * @return void
 */
function lynbro_a11y_render_fixes() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	$saved = false;
	if ( isset( $_POST['lynbro_a11y_fixes_nonce'] ) ) {
		check_admin_referer( 'lynbro_a11y_fixes', 'lynbro_a11y_fixes_nonce' );
		lynbro_a11y_save_fixes();
		$saved = true;
	}

	$options  = lynbro_a11y_options();
	$counts   = lynbro_a11y_counts_by_rule_id();
	$defs     = lynbro_a11y_fix_definitions();
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Fixes', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-fixes' ); ?>
		<?php lynbro_a11y_saved_notice( $saved ); ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'What these do', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'Every fix here is off until you switch it on, and switching it off puts things back exactly as they were. Nothing edits your posts, your theme or your database — the changes happen as the page is built and stop the moment you say so.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="desc">
				<?php esc_html_e( 'This is not an accessibility overlay. There is no widget, no toolbar and no script sent to your visitors that claims to make the site compliant. These are ordinary corrections to your own markup, and each one only addresses the specific fault named next to it.', 'lynbro-accessibility-checker' ); ?>
			</p>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'lynbro_a11y_fixes', 'lynbro_a11y_fixes_nonce' ); ?>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'Available fixes', 'lynbro-accessibility-checker' ); ?></h2>

				<?php foreach ( $defs as $key => $fix ) : ?>
					<?php
					$rule_id = $fix['rules'];
					$found   = ( '' !== $rule_id && isset( $counts[ $rule_id ] ) ) ? (int) $counts[ $rule_id ] : 0;
					?>
					<fieldset class="lynbro-a11y-fix">
						<legend class="screen-reader-text"><?php echo esc_html( $fix['title'] ); ?></legend>

						<p class="lynbro-a11y-fix-toggle">
							<label for="<?php echo esc_attr( $key ); ?>">
								<input type="checkbox"
									id="<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( $key ); ?>"
									value="1"
									<?php checked( ! empty( $options[ $key ] ) ); ?>>
								<strong><?php echo esc_html( $fix['title'] ); ?></strong>
							</label>
						</p>

						<p class="lynbro-a11y-fix-does"><?php echo esc_html( $fix['does'] ); ?></p>
						<p class="lynbro-a11y-fix-careful"><?php echo esc_html( $fix['careful'] ); ?></p>

						<?php if ( '' !== $rule_id ) : ?>
							<p class="lynbro-muted">
								<?php if ( $found > 0 ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=lynbro-accessibility-report&rule=' . rawurlencode( $rule_id ) ) ); ?>">
										<?php
										printf(
											/* translators: %s: number of occurrences found. */
											esc_html( _n( 'Your last scan found %s of these.', 'Your last scan found %s of these.', $found, 'lynbro-accessibility-checker' ) ),
											esc_html( number_format_i18n( $found ) )
										);
										?>
									</a>
								<?php else : ?>
									<?php esc_html_e( 'Your last scan found none of these, so this fix has nothing to do right now.', 'lynbro-accessibility-checker' ); ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>

						<?php if ( 'fix_skip_link' === $key ) : ?>
							<p class="lynbro-a11y-fix-setting">
								<label for="fix_skip_target"><?php esc_html_e( 'Id of your content area', 'lynbro-accessibility-checker' ); ?></label>
								<input type="text"
									id="fix_skip_target"
									name="fix_skip_target"
									class="regular-text"
									value="<?php echo esc_attr( (string) $options['fix_skip_target'] ); ?>"
									aria-describedby="fix_skip_target_help">
								<span id="fix_skip_target_help" class="lynbro-muted">
									<?php esc_html_e( 'Without the # — most themes use "main" or "content". The skip link needs this to point at something; if it points at nothing, it is worse than not having one.', 'lynbro-accessibility-checker' ); ?>
								</span>
							</p>
						<?php endif; ?>

						<?php if ( 'fix_focus_outline' === $key ) : ?>
							<p class="lynbro-a11y-fix-setting">
								<label for="fix_focus_colour"><?php esc_html_e( 'Outline colour', 'lynbro-accessibility-checker' ); ?></label>
								<input type="color"
									id="fix_focus_colour"
									name="fix_focus_colour"
									value="<?php echo esc_attr( (string) $options['fix_focus_colour'] ); ?>"
									aria-describedby="fix_focus_colour_help">
								<span id="fix_focus_colour_help" class="lynbro-muted">
									<?php esc_html_e( 'Pick something that stands out against your background. An outline nobody can see is the problem, not the fix.', 'lynbro-accessibility-checker' ); ?>
								</span>
							</p>
						<?php endif; ?>
					</fieldset>
				<?php endforeach; ?>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save fixes', 'lynbro-accessibility-checker' ); ?></button>
				</p>
			</div>
		</form>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'What we will not do for you', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'Heading levels are not on this list, and that is deliberate. Changing an h4 to an h3 means deciding what the author meant by it, and a plugin that quietly renumbers your headings has made a decision it cannot explain to you afterwards. The same goes for writing alt text nobody wrote, and for guessing at link text. The scan tells you where those are; you make the call.', 'lynbro-accessibility-checker' ); ?>
			</p>
		</div>
	</div>
	<?php
}

/**
 * Save the fixes form.
 *
 * @return void
 */
function lynbro_a11y_save_fixes() {
	$current = lynbro_a11y_options();
	$input   = $current;

	foreach ( array_keys( lynbro_a11y_fix_definitions() ) as $key ) {
		$input[ $key ] = isset( $_POST[ $key ] ) ? 1 : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the caller.
	}

	if ( isset( $_POST['fix_skip_target'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the caller.
		$input['fix_skip_target'] = sanitize_text_field( wp_unslash( $_POST['fix_skip_target'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	if ( isset( $_POST['fix_focus_colour'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the caller.
		$input['fix_focus_colour'] = sanitize_text_field( wp_unslash( $_POST['fix_focus_colour'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	update_option( LYNBRO_A11Y_OPTION, lynbro_a11y_sanitize_options( $input ) );
	lynbro_a11y_flush_options_cache();
}

/**
 * How many times each rule fires across the site, keyed by rule id.
 *
 * @return array<string,int>
 */
function lynbro_a11y_counts_by_rule_id() {
	$out = array();
	foreach ( lynbro_a11y_issues_by_rule() as $row ) {
		$out[ $row['rule'] ] = $row['total'];
	}
	return $out;
}
