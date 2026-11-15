<?php
/**
 * Plugin Name:       Lynbro Accessibility Checker
 * Plugin URI:        https://plugins.lynbro.dk/lynbro-accessibility-checker
 * Description:       Scan your whole site against WCAG 2.2 AA, see every problem with the element that causes it and how to fix it, and switch on the fixes that can be made safely. No overlay, no account, no page limit.
 * Version:           0.2.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Lynbro
 * Author URI:        https://lynbro.dk
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lynbro-accessibility-checker
 * Domain Path:       /languages
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_VERSION', '0.2.2' );
define( 'LYNBRO_A11Y_FILE', __FILE__ );
define( 'LYNBRO_A11Y_DIR', plugin_dir_path( __FILE__ ) );
define( 'LYNBRO_A11Y_URL', plugin_dir_url( __FILE__ ) );
define( 'LYNBRO_A11Y_SLUG', 'lynbro-accessibility-checker' );

/**
 * Cache-busting version for an asset: plugin version plus the file's mtime, so
 * an updated file is never served stale from a browser cache.
 *
 * @param string $relative_path Path relative to the plugin root.
 * @return string
 */
function lynbro_a11y_asset_ver( $relative_path ) {
	$file  = LYNBRO_A11Y_DIR . ltrim( $relative_path, '/' );
	$mtime = is_readable( $file ) ? (int) filemtime( $file ) : 0;
	return $mtime ? LYNBRO_A11Y_VERSION . '.' . $mtime : LYNBRO_A11Y_VERSION;
}

require_once LYNBRO_A11Y_DIR . 'includes/options.php';
require_once LYNBRO_A11Y_DIR . 'includes/db.php';
require_once LYNBRO_A11Y_DIR . 'includes/rules.php';
require_once LYNBRO_A11Y_DIR . 'includes/dom.php';
require_once LYNBRO_A11Y_DIR . 'includes/checks.php';
require_once LYNBRO_A11Y_DIR . 'includes/store.php';
require_once LYNBRO_A11Y_DIR . 'includes/sources.php';
require_once LYNBRO_A11Y_DIR . 'includes/scanner.php';
require_once LYNBRO_A11Y_DIR . 'includes/queue.php';
require_once LYNBRO_A11Y_DIR . 'includes/live.php';
require_once LYNBRO_A11Y_DIR . 'includes/fixes.php';
require_once LYNBRO_A11Y_DIR . 'includes/manual.php';
require_once LYNBRO_A11Y_DIR . 'includes/onsave.php';
require_once LYNBRO_A11Y_DIR . 'includes/statement.php';
require_once LYNBRO_A11Y_DIR . 'includes/lynbro-core/lynbro-shell.php';

if ( is_admin() ) {
	require_once LYNBRO_A11Y_DIR . 'admin/menu.php';
	require_once LYNBRO_A11Y_DIR . 'admin/dashboard.php';
	require_once LYNBRO_A11Y_DIR . 'admin/report-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/browser-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/fixes-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/manual-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/statement-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/settings-page.php';
	require_once LYNBRO_A11Y_DIR . 'admin/ajax.php';
	require_once LYNBRO_A11Y_DIR . 'admin/export.php';
	require_once LYNBRO_A11Y_DIR . 'admin/columns.php';
}

/**
 * Activation: seed defaults and create the tables.
 *
 * @param bool $network_wide Whether activation is network-wide.
 * @return void
 */
function lynbro_a11y_activate( $network_wide = false ) {
	if ( $network_wide && is_multisite() ) {
		foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
			switch_to_blog( $blog_id );
			lynbro_a11y_activate_site();
			restore_current_blog();
		}
		return;
	}
	lynbro_a11y_activate_site();
}
register_activation_hook( __FILE__, 'lynbro_a11y_activate' );

/**
 * Per-site activation work.
 *
 * Nothing is switched on that changes the front end. A freshly activated copy
 * reports; it does not start editing the site's markup behind the owner's back.
 *
 * @return void
 */
function lynbro_a11y_activate_site() {
	add_option( 'lynbro_a11y_version', LYNBRO_A11Y_VERSION );

	if ( false === get_option( LYNBRO_A11Y_OPTION, false ) ) {
		add_option( LYNBRO_A11Y_OPTION, lynbro_a11y_default_options() );
	}
	lynbro_a11y_create_tables();
	update_option( 'lynbro_a11y_db', LYNBRO_A11Y_DB_VERSION );
}

/**
 * A new site on a multisite network gets the same setup.
 *
 * @param int|WP_Site $site Site ID or object.
 * @return void
 */
function lynbro_a11y_on_new_site( $site ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( plugin_basename( __FILE__ ) ) ) {
		return;
	}
	switch_to_blog( is_object( $site ) ? (int) $site->blog_id : (int) $site );
	lynbro_a11y_activate_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'lynbro_a11y_on_new_site', 20 );

/**
 * Deactivation: drop scheduled events. Scan results are kept until uninstall.
 *
 * @return void
 */
function lynbro_a11y_deactivate() {
	wp_clear_scheduled_hook( 'lynbro_a11y_cron_tick' );
	wp_clear_scheduled_hook( 'lynbro_a11y_cron_scan' );
	wp_unschedule_hook( 'lynbro_a11y_scan_post' );
}
register_deactivation_hook( __FILE__, 'lynbro_a11y_deactivate' );

/**
 * Keep the schema and the cron in step after an update.
 *
 * @return void
 */
function lynbro_a11y_maybe_upgrade() {
	if ( get_option( 'lynbro_a11y_version' ) !== LYNBRO_A11Y_VERSION || get_option( 'lynbro_a11y_db' ) !== LYNBRO_A11Y_DB_VERSION ) {
		lynbro_a11y_create_tables();
		update_option( 'lynbro_a11y_version', LYNBRO_A11Y_VERSION );
		update_option( 'lynbro_a11y_db', LYNBRO_A11Y_DB_VERSION );
	}
	lynbro_a11y_schedule_events();
}
add_action( 'admin_init', 'lynbro_a11y_maybe_upgrade' );

/**
 * A "Settings" link in the plugins list, so the plugin is findable without a
 * notice, a redirect or a welcome screen.
 *
 * @param array<int,string> $links Existing action links.
 * @param string            $file  Plugin file the links belong to.
 * @return array<int,string>
 */
function lynbro_a11y_action_links( $links, $file ) {
	if ( plugin_basename( LYNBRO_A11Y_FILE ) !== $file ) {
		return $links;
	}
	array_unshift(
		$links,
		'<a href="' . esc_url( admin_url( 'admin.php?page=lynbro-accessibility' ) ) . '">' . esc_html__( 'Report', 'lynbro-accessibility-checker' ) . '</a>'
	);
	return $links;
}
add_filter( 'plugin_action_links', 'lynbro_a11y_action_links', 10, 2 );
