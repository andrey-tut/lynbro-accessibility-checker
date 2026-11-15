<?php
/**
 * Uninstall: take everything with us.
 *
 * A scan report is a list of your site's weak points. Leaving it behind in a
 * database after the plugin is gone is not tidy and it is not harmless.
 *
 * @package LynbroAccessibility
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/db.php';

/**
 * Remove this plugin's data from the current site.
 *
 * @return void
 */
function lynbro_a11y_uninstall_site() {
	lynbro_a11y_drop_tables();

	foreach ( array( 'lynbro_a11y_options', 'lynbro_a11y_manual', 'lynbro_a11y_version', 'lynbro_a11y_scan_state', 'lynbro_a11y_scan_list', 'lynbro_a11y_statement', 'lynbro_a11y_site_access', 'lynbro_a11y_db', 'lynbro_a11y_ignore_everywhere' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'lynbro_a11y_scan_lock' );

	wp_clear_scheduled_hook( 'lynbro_a11y_cron_tick' );
	wp_clear_scheduled_hook( 'lynbro_a11y_cron_scan' );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $blog_id ) {
		switch_to_blog( $blog_id );
		lynbro_a11y_uninstall_site();
		restore_current_blog();
	}
} else {
	lynbro_a11y_uninstall_site();
}
