<?php
/**
 * Two tables, and the reason there are two.
 *
 * A page is scanned over and over; an issue on it comes and goes. Keeping them
 * apart means a rescan replaces one page's findings without touching anything
 * else, and the report can count by page or by rule without a single join over
 * post content.
 *
 * Each issue carries a fingerprint — page, rule, selector, context — so that a
 * rescan can recognise the same problem it found last week and keep two things
 * about it: when it first appeared, and whether the owner has told us to stop
 * mentioning it. Without that, "ignore this" would last until the next scan,
 * which is the same as not having it.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/*
 * Schema version, separate from the plugin version so a column added between
 * releases still reaches every install. Bump it with every change to the
 * CREATE TABLE statements below.
 */
define( 'LYNBRO_A11Y_DB_VERSION', '4' );

/**
 * The pages table.
 *
 * @return string
 */
function lynbro_a11y_table_pages() {
	global $wpdb;
	return $wpdb->prefix . 'lynbro_a11y_pages';
}

/**
 * The issues table.
 *
 * @return string
 */
function lynbro_a11y_table_issues() {
	global $wpdb;
	return $wpdb->prefix . 'lynbro_a11y_issues';
}

/**
 * Every table this plugin owns.
 *
 * @return array<int,string>
 */
function lynbro_a11y_tables() {
	return array( lynbro_a11y_table_pages(), lynbro_a11y_table_issues() );
}

/**
 * Create or update the schema.
 *
 * @return void
 */
function lynbro_a11y_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$pages   = lynbro_a11y_table_pages();
	$issues  = lynbro_a11y_table_issues();

	// url is indexed through a hash: a URL can be longer than the 191 characters
	// a utf8mb4 index allows, and truncating the index would silently merge two
	// different pages that share a long prefix.
	dbDelta(
		"CREATE TABLE {$pages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url_hash char(32) NOT NULL,
			url text NOT NULL,
			object_type varchar(20) NOT NULL DEFAULT 'url',
			object_id bigint(20) unsigned NOT NULL DEFAULT 0,
			label varchar(255) NOT NULL DEFAULT '',
			scanned_at datetime DEFAULT NULL,
			browser_at datetime DEFAULT NULL,
			http_status smallint(5) unsigned NOT NULL DEFAULT 0,
			errors smallint(5) unsigned NOT NULL DEFAULT 0,
			warnings smallint(5) unsigned NOT NULL DEFAULT 0,
			reviews smallint(5) unsigned NOT NULL DEFAULT 0,
			note varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			UNIQUE KEY url_hash (url_hash),
			KEY scanned_at (scanned_at),
			KEY errors (errors),
			KEY object (object_type,object_id)
		) {$charset};"
	);

	dbDelta(
		"CREATE TABLE {$issues} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_id bigint(20) unsigned NOT NULL,
			fingerprint char(32) NOT NULL,
			rule varchar(64) NOT NULL,
			severity varchar(10) NOT NULL DEFAULT 'error',
			source varchar(10) NOT NULL DEFAULT 'markup',
			selector varchar(255) NOT NULL DEFAULT '',
			snippet text NOT NULL,
			context varchar(255) NOT NULL DEFAULT '',
			first_seen datetime DEFAULT NULL,
			last_seen datetime DEFAULT NULL,
			ignored tinyint(1) NOT NULL DEFAULT 0,
			ignored_note varchar(255) NOT NULL DEFAULT '',
			ignored_by bigint(20) unsigned NOT NULL DEFAULT 0,
			ignored_at datetime DEFAULT NULL,
			render tinyint(3) unsigned NOT NULL DEFAULT 0,
			offscreen tinyint(1) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY fingerprint (fingerprint),
			KEY page_id (page_id),
			KEY rule (rule),
			KEY severity (severity)
		) {$charset};"
	);
}

/**
 * Drop the tables. Used by uninstall.php only.
 *
 * @return void
 */
function lynbro_a11y_drop_tables() {
	global $wpdb;
	foreach ( lynbro_a11y_tables() as $table ) {
		// %i is the identifier placeholder, WordPress 6.2 and up — which is why
		// this plugin requires 6.2. Below that it silently does nothing, and the
		// tables outlive the uninstall.
		$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
