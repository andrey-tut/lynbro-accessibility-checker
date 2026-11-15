<?php
/**
 * The manual checklist screen.
 *
 * Eighteen questions, each with an answer, a note and a date. This is the part
 * an inspector actually asks about, and the part no scanner can produce.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the manual checks.
 *
 * @return void
 */
function lynbro_a11y_render_manual() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	$saved = false;
	if ( isset( $_POST['lynbro_a11y_manual_nonce'] ) ) {
		check_admin_referer( 'lynbro_a11y_manual', 'lynbro_a11y_manual_nonce' );
		lynbro_a11y_save_manual();
		$saved = true;
	}

	$items    = lynbro_a11y_manual_items();
	$progress = lynbro_a11y_manual_progress();
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Manual checks', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-manual' ); ?>
		<?php lynbro_a11y_saved_notice( $saved ); ?>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'The half a scanner cannot do', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'Automated testing reaches roughly a third of WCAG. Everything below needs somebody to open the site and look — which is exactly what a tool selling you a compliance badge is not telling you.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="desc">
				<?php
				printf(
					/* translators: 1: answered, 2: total, 3: number of problems found. */
					esc_html__( 'Answered %1$s of %2$s. Problems recorded: %3$s.', 'lynbro-accessibility-checker' ),
					esc_html( number_format_i18n( $progress['total'] - $progress['unknown'] ) ),
					esc_html( number_format_i18n( $progress['total'] ) ),
					esc_html( number_format_i18n( $progress['fail'] ) )
				);
				?>
			</p>
		</div>

		<form method="post" action="">
			<?php wp_nonce_field( 'lynbro_a11y_manual', 'lynbro_a11y_manual_nonce' ); ?>

			<?php foreach ( $items as $id => $item ) : ?>
				<?php $answer = lynbro_a11y_manual_answer( $id ); ?>
				<div class="lynbro-section lynbro-a11y-manual-item">
					<fieldset>
						<legend>
							<span class="lynbro-a11y-manual-title"><?php echo esc_html( $item['title'] ); ?></span>
							<span class="lynbro-muted">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: WCAG criterion, 2: conformance level. */
										__( 'WCAG %1$s (%2$s)', 'lynbro-accessibility-checker' ),
										$item['wcag'],
										$item['level']
									)
								);
								?>
							</span>
						</legend>

						<p class="lynbro-a11y-manual-ask"><?php echo esc_html( $item['ask'] ); ?></p>

						<p class="lynbro-a11y-manual-answers">
							<?php foreach ( array( 'unknown', 'pass', 'fail', 'na' ) as $value ) : ?>
								<label for="<?php echo esc_attr( $id . '_' . $value ); ?>">
									<input type="radio"
										id="<?php echo esc_attr( $id . '_' . $value ); ?>"
										name="answer[<?php echo esc_attr( $id ); ?>]"
										value="<?php echo esc_attr( $value ); ?>"
										<?php checked( $answer['state'], $value ); ?>>
									<?php echo esc_html( lynbro_a11y_manual_label( $value ) ); ?>
								</label>
							<?php endforeach; ?>
						</p>

						<p>
							<label for="<?php echo esc_attr( 'note_' . $id ); ?>"><?php esc_html_e( 'What you found', 'lynbro-accessibility-checker' ); ?></label>
							<textarea id="<?php echo esc_attr( 'note_' . $id ); ?>"
								name="note[<?php echo esc_attr( $id ); ?>]"
								rows="2"
								class="large-text"><?php echo esc_textarea( (string) $answer['note'] ); ?></textarea>
						</p>

						<?php if ( '' !== (string) $answer['updated'] ) : ?>
							<p class="lynbro-muted">
								<?php
								$user = $answer['user'] ? get_userdata( (int) $answer['user'] ) : null;
								printf(
									/* translators: 1: date and time, 2: who answered. */
									esc_html__( 'Answered %1$s by %2$s.', 'lynbro-accessibility-checker' ),
									esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $answer['updated'] ) ),
									esc_html( $user ? $user->display_name : __( 'someone since removed', 'lynbro-accessibility-checker' ) )
								);
								?>
							</p>
						<?php endif; ?>
					</fieldset>
				</div>
			<?php endforeach; ?>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save answers', 'lynbro-accessibility-checker' ); ?></button>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Save the checklist.
 *
 * @return void
 */
function lynbro_a11y_save_manual() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked by the caller.
	$answers = isset( $_POST['answer'] ) && is_array( $_POST['answer'] )
		? map_deep( wp_unslash( $_POST['answer'] ), 'sanitize_key' )
		: array();
	// Notes keep their line breaks, so sanitize_textarea_field rather than
	// sanitize_text_field: this is where someone writes down what they found.
	$notes = isset( $_POST['note'] ) && is_array( $_POST['note'] )
		? map_deep( wp_unslash( $_POST['note'] ), 'sanitize_textarea_field' )
		: array();
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	foreach ( array_keys( lynbro_a11y_manual_items() ) as $id ) {
		$answer = isset( $answers[ $id ] ) ? sanitize_key( $answers[ $id ] ) : 'unknown';
		$note   = isset( $notes[ $id ] ) ? (string) $notes[ $id ] : '';
		$stored = lynbro_a11y_manual_answer( $id );

		// Only write when something changed, so the date on screen keeps meaning
		// "when this was last actually looked at" rather than "last save".
		if ( $answer === $stored['state'] && trim( $note ) === trim( (string) $stored['note'] ) ) {
			continue;
		}
		lynbro_a11y_manual_save( $id, $answer, $note );
	}
}
