<?php
/**
 * Settings.
 *
 * Nothing on this screen is a limit on what you may do. The numbers are about
 * how hard the scan leans on your server per tick, not about how much of your
 * site it is allowed to look at.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the settings screen.
 *
 * @return void
 */
function lynbro_a11y_render_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'lynbro-accessibility-checker' ) );
	}

	$saved = false;
	if ( isset( $_POST['lynbro_a11y_settings_nonce'] ) ) {
		check_admin_referer( 'lynbro_a11y_settings', 'lynbro_a11y_settings_nonce' );
		// Sanitised on the way in as well as on the way out: lynbro_a11y_sanitize_options()
		// validates every field against its own schema, but a raw superglobal
		// should never travel that far in the first place.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked on the line above.
		$raw = isset( $_POST['lynbro_a11y'] ) && is_array( $_POST['lynbro_a11y'] )
			? map_deep( wp_unslash( $_POST['lynbro_a11y'] ), 'sanitize_textarea_field' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			: array();
		// The fix settings live on their own screen; keep them as they are.
		$current = lynbro_a11y_options();
		foreach ( array_keys( lynbro_a11y_fix_definitions() ) as $key ) {
			$raw[ $key ] = $current[ $key ];
		}
		$raw['fix_skip_target']  = $current['fix_skip_target'];
		$raw['fix_focus_colour'] = $current['fix_focus_colour'];

		update_option( LYNBRO_A11Y_OPTION, lynbro_a11y_sanitize_options( $raw ) );

		// Basic auth for a staging site. An empty password field keeps the saved one.
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked above.
		$access_in = isset( $_POST['lynbro_a11y_access'] ) && is_array( $_POST['lynbro_a11y_access'] ) ? wp_unslash( $_POST['lynbro_a11y_access'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised below; a password must not be altered.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$access_now  = lynbro_a11y_site_access();
		$access_user = isset( $access_in['user'] ) ? sanitize_text_field( (string) $access_in['user'] ) : '';
		$access_pass = isset( $access_in['pass'] ) ? (string) $access_in['pass'] : '';
		$access_pass = '' === $access_pass ? $access_now['pass'] : str_replace( array( "\r", "\n", "\0" ), '', $access_pass );
		if ( '' === $access_user || ! empty( $access_in['forget'] ) ) {
			delete_option( LYNBRO_A11Y_ACCESS_OPTION );
		} else {
			update_option( LYNBRO_A11Y_ACCESS_OPTION, array( 'user' => $access_user, 'pass' => $access_pass ), false );
		}
		lynbro_a11y_flush_options_cache();
		lynbro_a11y_schedule_events();
		$saved = true;
	}

	$options = lynbro_a11y_options();
	$access  = lynbro_a11y_site_access();
	?>
	<div class="wrap lynbro-wrap lynbro-a11y">
		<?php lynbro_a11y_header( __( 'Settings', 'lynbro-accessibility-checker' ), 'lynbro-accessibility-settings' ); ?>
		<?php lynbro_a11y_saved_notice( $saved ); ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'lynbro_a11y_settings', 'lynbro_a11y_settings_nonce' ); ?>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'What gets scanned', 'lynbro-accessibility-checker' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="post_types"><?php esc_html_e( 'Post types', 'lynbro-accessibility-checker' ); ?></label>
							</th>
							<td>
								<textarea id="post_types" name="lynbro_a11y[post_types]" rows="3" class="large-text code" aria-describedby="post_types_help"><?php echo esc_textarea( (string) $options['post_types'] ); ?></textarea>
								<p class="description" id="post_types_help">
									<?php esc_html_e( 'One per line. Leave empty to scan every public post type, which is what most sites want.', 'lynbro-accessibility-checker' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Also include', 'lynbro-accessibility-checker' ); ?></th>
							<td>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Other pages to include in the scan', 'lynbro-accessibility-checker' ); ?></legend>
									<label for="include_front_page">
										<input type="checkbox" id="include_front_page" name="lynbro_a11y[include_front_page]" value="1" <?php checked( ! empty( $options['include_front_page'] ) ); ?>>
										<?php esc_html_e( 'The front page', 'lynbro-accessibility-checker' ); ?>
									</label><br>
									<label for="include_terms">
										<input type="checkbox" id="include_terms" name="lynbro_a11y[include_terms]" value="1" <?php checked( ! empty( $options['include_terms'] ) ); ?>>
										<?php esc_html_e( 'Category and tag pages', 'lynbro-accessibility-checker' ); ?>
									</label><br>
									<label for="include_archives">
										<input type="checkbox" id="include_archives" name="lynbro_a11y[include_archives]" value="1" <?php checked( ! empty( $options['include_archives'] ) ); ?>>
										<?php esc_html_e( 'Post type archives', 'lynbro-accessibility-checker' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="extra_urls"><?php esc_html_e( 'Extra addresses', 'lynbro-accessibility-checker' ); ?></label>
							</th>
							<td>
								<textarea id="extra_urls" name="lynbro_a11y[extra_urls]" rows="3" class="large-text code" aria-describedby="extra_urls_help"><?php echo esc_textarea( (string) $options['extra_urls'] ); ?></textarea>
								<p class="description" id="extra_urls_help"><?php esc_html_e( 'One full address per line, for pages WordPress does not list — a shop checkout, say.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="exclude_urls"><?php esc_html_e( 'Leave out', 'lynbro-accessibility-checker' ); ?></label>
							</th>
							<td>
								<textarea id="exclude_urls" name="lynbro_a11y[exclude_urls]" rows="3" class="large-text code" aria-describedby="exclude_urls_help"><?php echo esc_textarea( (string) $options['exclude_urls'] ); ?></textarea>
								<p class="description" id="exclude_urls_help"><?php esc_html_e( 'One per line. Plain text matched anywhere in the address; * is allowed. No regular expressions — a mistyped one would silently empty the whole scan.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="ignored_rules"><?php esc_html_e( 'Rules to stop reporting', 'lynbro-accessibility-checker' ); ?></label>
							</th>
							<td>
								<textarea id="ignored_rules" name="lynbro_a11y[ignored_rules]" rows="3" class="large-text code" aria-describedby="ignored_rules_help"><?php echo esc_textarea( (string) $options['ignored_rules'] ); ?></textarea>
								<p class="description" id="ignored_rules_help">
									<?php esc_html_e( 'One rule id per line. The scan still looks for them — this only hides them from the report, so putting one back shows the issues that were there all along without a rescan.', 'lynbro-accessibility-checker' ); ?>
								</p>
								<details>
									<summary><?php esc_html_e( 'The rule ids', 'lynbro-accessibility-checker' ); ?></summary>
									<ul class="lynbro-a11y-rule-ids">
										<?php foreach ( lynbro_a11y_rules() as $id => $rule ) : ?>
											<li><code><?php echo esc_html( $id ); ?></code> — <?php echo esc_html( $rule['title'] ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'How hard the scan works', 'lynbro-accessibility-checker' ); ?></h2>
				<p class="desc"><?php esc_html_e( 'These control how much your server does at a time, never how much of the site is scanned. A scan always covers everything; these only decide how long it takes.', 'lynbro-accessibility-checker' ); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="batch_pages"><?php esc_html_e( 'Pages per tick', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="number" id="batch_pages" name="lynbro_a11y[batch_pages]" value="<?php echo esc_attr( (string) $options['batch_pages'] ); ?>" min="1" max="50" class="small-text">
								<p class="description"><?php esc_html_e( 'Lower on a small shared host, higher on a server that can take it.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tick_seconds"><?php esc_html_e( 'Seconds per tick', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="number" id="tick_seconds" name="lynbro_a11y[tick_seconds]" value="<?php echo esc_attr( (string) $options['tick_seconds'] ); ?>" min="3" max="60" class="small-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="request_timeout"><?php esc_html_e( 'Seconds to wait for a page', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="number" id="request_timeout" name="lynbro_a11y[request_timeout]" value="<?php echo esc_attr( (string) $options['request_timeout'] ); ?>" min="3" max="60" class="small-text">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="memory_ceiling_pct"><?php esc_html_e( 'Pause at this much memory', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="number" id="memory_ceiling_pct" name="lynbro_a11y[memory_ceiling_pct]" value="<?php echo esc_attr( (string) $options['memory_ceiling_pct'] ); ?>" min="50" max="95" class="small-text">
								<span aria-hidden="true">%</span>
								<p class="description"><?php esc_html_e( 'Of the PHP memory limit. The scan pauses and carries on next tick rather than dying halfway.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="scan_schedule"><?php esc_html_e( 'Scan on a schedule', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<select id="scan_schedule" name="lynbro_a11y[scan_schedule]">
									<option value="off" <?php selected( $options['scan_schedule'], 'off' ); ?>><?php esc_html_e( 'Only when I ask', 'lynbro-accessibility-checker' ); ?></option>
									<option value="daily" <?php selected( $options['scan_schedule'], 'daily' ); ?>><?php esc_html_e( 'Every day', 'lynbro-accessibility-checker' ); ?></option>
									<option value="weekly" <?php selected( $options['scan_schedule'], 'weekly' ); ?>><?php esc_html_e( 'Every week', 'lynbro-accessibility-checker' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Check pages when saved', 'lynbro-accessibility-checker' ); ?></th>
							<td>
								<label for="scan_on_save">
									<input type="checkbox" id="scan_on_save" name="lynbro_a11y[scan_on_save]" value="1" <?php checked( ! empty( $options['scan_on_save'] ) ); ?>>
									<?php esc_html_e( 'Check a published page again a few seconds after it is saved', 'lynbro-accessibility-checker' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Runs in the background, so saving is not slower. The result shows in the Accessibility column of the page list and in the report.', 'lynbro-accessibility-checker' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="lynbro-section">
				<h2><?php esc_html_e( 'A site behind a password', 'lynbro-accessibility-checker' ); ?></h2>
				<p class="desc">
					<?php esc_html_e( 'For a staging site behind basic auth (the browser’s own username and password box). The scanner sends them only to this site’s own address, never to another host. Leave empty on a public site.', 'lynbro-accessibility-checker' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="access_user"><?php esc_html_e( 'Username', 'lynbro-accessibility-checker' ); ?></label></th>
							<td><input type="text" id="access_user" name="lynbro_a11y_access[user]" class="regular-text" autocomplete="off" value="<?php echo esc_attr( $access['user'] ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="access_pass"><?php esc_html_e( 'Password', 'lynbro-accessibility-checker' ); ?></label></th>
							<td>
								<input type="password" id="access_pass" name="lynbro_a11y_access[pass]" class="regular-text" autocomplete="new-password" value="" aria-describedby="access_pass_help">
								<p class="description" id="access_pass_help">
									<?php
									if ( '' !== $access['pass'] ) {
										esc_html_e( 'A password is saved. Leave the field empty to keep it.', 'lynbro-accessibility-checker' );
									} else {
										esc_html_e( 'Stored in your database and sent with each scan request to this site.', 'lynbro-accessibility-checker' );
									}
									?>
								</p>
								<?php if ( '' !== $access['user'] ) : ?>
									<label for="access_forget">
										<input type="checkbox" id="access_forget" name="lynbro_a11y_access[forget]" value="1">
										<?php esc_html_e( 'Forget the username and password', 'lynbro-accessibility-checker' ); ?>
									</label>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="description">
					<?php esc_html_e( 'Maintenance mode is different: it answers every visitor with HTTP 503, and the scan cannot get past it. Switch it off while you scan, or let requests with the X-Lynbro-Accessibility header through it if your maintenance plugin allows that.', 'lynbro-accessibility-checker' ); ?>
				</p>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'lynbro-accessibility-checker' ); ?></button>
			</p>
		</form>

		<div class="lynbro-section">
			<h2><?php esc_html_e( 'Your data', 'lynbro-accessibility-checker' ); ?></h2>
			<p class="desc">
				<?php esc_html_e( 'Everything this plugin knows is in your own database, and nothing is sent anywhere. The export is the whole report as a file you can keep, hand to a supplier, or send to an assessor.', 'lynbro-accessibility-checker' ); ?>
			</p>
			<p class="lynbro-tools-row">
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lynbro_a11y_export&format=csv' ), 'lynbro_a11y_export' ) ); ?>">
					<?php esc_html_e( 'Export the report (CSV)', 'lynbro-accessibility-checker' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=lynbro_a11y_export&format=json' ), 'lynbro_a11y_export' ) ); ?>">
					<?php esc_html_e( 'Export the report (JSON)', 'lynbro-accessibility-checker' ); ?>
				</a>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-lynbro-confirm="<?php echo esc_attr__( 'Delete every scan result? The settings are kept.', 'lynbro-accessibility-checker' ); ?>">
				<?php lynbro_a11y_nonce_field( 'lynbro_a11y_clear' ); ?>
				<input type="hidden" name="action" value="lynbro_a11y_clear">
				<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete all scan results', 'lynbro-accessibility-checker' ); ?></button>
			</form>
		</div>

		<?php
		if ( function_exists( 'lynbro_render_tools_section' ) ) {
			lynbro_render_tools_section(
				array( LYNBRO_A11Y_OPTION, LYNBRO_A11Y_MANUAL_OPTION ),
				'lynbro-accessibility-settings'
			);
		}
		?>
	</div>
	<?php
}

/**
 * Declare which options this plugin's Tools panel owns, at load time as well as
 * at render time — otherwise the handler that runs on admin-post has no
 * registry to consult and would refuse to export anything.
 *
 * @return void
 */
function lynbro_a11y_register_tools_keys() {
	if ( function_exists( 'lynbro_tools_register_keys' ) ) {
		lynbro_tools_register_keys(
			'lynbro-accessibility-settings',
			array( LYNBRO_A11Y_OPTION, LYNBRO_A11Y_MANUAL_OPTION )
		);
	}
}
add_action( 'admin_init', 'lynbro_a11y_register_tools_keys' );
