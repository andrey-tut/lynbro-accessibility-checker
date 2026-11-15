<?php
/**
 * Admin menu and the furniture the screens share.
 *
 * One visible entry in the shared Lynbro menu; the rest are tabs. And the tab
 * strip below is a real <nav> with a real aria-current, not a row of styled
 * links — this plugin is not allowed to fail its own scanner.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Our screens, in tab order.
 *
 * @return array<string,array{title:string,callback:string}>
 */
function lynbro_a11y_pages() {
	return array(
		'lynbro-accessibility'         => array( 'title' => __( 'Overview', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_dashboard' ),
		'lynbro-accessibility-report'  => array( 'title' => __( 'Report', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_report' ),
		'lynbro-accessibility-browser' => array( 'title' => __( 'Browser check', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_browser_check' ),
		'lynbro-accessibility-fixes'   => array( 'title' => __( 'Fixes', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_fixes' ),
		'lynbro-accessibility-manual'  => array( 'title' => __( 'Manual checks', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_manual' ),
		'lynbro-accessibility-statement' => array( 'title' => __( 'Statement', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_statement' ),
		'lynbro-accessibility-settings' => array( 'title' => __( 'Settings', 'lynbro-accessibility-checker' ), 'callback' => 'lynbro_a11y_render_settings' ),
	);
}

/**
 * Register our pages under the shared Lynbro menu.
 *
 * The shared shell decides where the parent menu lives and in what order the
 * suite appears; all we do is queue our pages. Registering with
 * Lynbro_Admin_Menu rather than add_submenu_page matters, because the shell
 * builds the menu late on purpose — a plugin that attaches directly at an
 * earlier priority attaches to a parent that does not exist yet.
 *
 * @return void
 */
function lynbro_a11y_admin_menu() {
	$pages = lynbro_a11y_pages();

	if ( class_exists( 'Lynbro_Admin_Menu' ) ) {
		foreach ( $pages as $slug => $page ) {
			Lynbro_Admin_Menu::add(
				array(
					'slug'       => $slug,
					'page_title' => $page['title'],
					'menu_title' => 'lynbro-accessibility' === $slug ? __( 'Accessibility', 'lynbro-accessibility-checker' ) : $page['title'],
					'capability' => 'manage_options',
					'callback'   => $page['callback'],
					'hidden'     => 'lynbro-accessibility' !== $slug,
				)
			);
		}
		return;
	}

	// An older vendored copy of the shell on the same site: it builds the parent
	// at admin_menu priority 9, so attaching straight to it still works.
	foreach ( $pages as $slug => $page ) {
		$hook = add_submenu_page(
			'lynbro',
			$page['title'],
			'lynbro-accessibility' === $slug ? __( 'Accessibility', 'lynbro-accessibility-checker' ) : $page['title'],
			'manage_options',
			$slug,
			$page['callback']
		);
		if ( 'lynbro-accessibility' !== $slug && $hook ) {
			remove_submenu_page( 'lynbro', $slug );
		}
	}
}
add_action( 'admin_menu', 'lynbro_a11y_admin_menu', 20 );

/**
 * The tab strip.
 *
 * @param string $current Slug of the active page.
 * @return void
 */
function lynbro_a11y_tabs( $current ) {
	echo '<nav class="nav-tab-wrapper lynbro-tabs" aria-label="' . esc_attr__( 'Accessibility sections', 'lynbro-accessibility-checker' ) . '">';
	foreach ( lynbro_a11y_pages() as $slug => $page ) {
		printf(
			'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
			esc_url( admin_url( 'admin.php?page=' . $slug ) ),
			$slug === $current ? ' nav-tab-active' : '',
			$slug === $current ? ' aria-current="page"' : '',
			esc_html( $page['title'] )
		);
	}
	echo '</nav>';
}

/**
 * The page heading every screen opens with.
 *
 * @param string $title   Screen title.
 * @param string $current Active tab slug.
 * @return void
 */
function lynbro_a11y_header( $title, $current ) {
	echo '<h1>' . esc_html( $title ) . '</h1>';
	lynbro_a11y_tabs( $current );
}

/**
 * Is the current request one of our screens?
 *
 * @param string $hook Current admin page hook.
 * @return bool
 */
function lynbro_a11y_is_our_screen( $hook ) {
	return false !== strpos( (string) $hook, 'lynbro-accessibility' );
}

/**
 * Our stylesheet and the scan runner, on our screens only.
 *
 * The stylesheet depends on 'lynbro-core-admin' — the handle the shared shell
 * really registers. Naming a handle that does not exist raises nothing at all:
 * WordPress simply declines to print the stylesheet and the screen renders
 * unstyled.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function lynbro_a11y_admin_assets( $hook ) {
	if ( ! lynbro_a11y_is_our_screen( $hook ) ) {
		return;
	}

	wp_enqueue_style(
		'lynbro-a11y-admin',
		LYNBRO_A11Y_URL . 'admin/a11y-admin.css',
		array( 'lynbro-core-admin' ),
		lynbro_a11y_asset_ver( 'admin/a11y-admin.css' )
	);

	wp_enqueue_script(
		'lynbro-a11y-admin',
		LYNBRO_A11Y_URL . 'admin/a11y-admin.js',
		array(),
		lynbro_a11y_asset_ver( 'admin/a11y-admin.js' ),
		true
	);

	wp_localize_script(
		'lynbro-a11y-admin',
		'lynbroA11y',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'lynbro_a11y_scan' ),
			'strings' => array(
				'starting' => __( 'Starting…', 'lynbro-accessibility-checker' ),
				'stopped'  => __( 'Stopped.', 'lynbro-accessibility-checker' ),
				'finished' => __( 'Finished. Reload to see the report.', 'lynbro-accessibility-checker' ),
				'failed'   => __( 'The scan could not continue. Reload the page to see where it got to.', 'lynbro-accessibility-checker' ),
				/* translators: 1: pages done, 2: pages in total. */
				'progress' => __( 'Scanned %1$s of %2$s pages.', 'lynbro-accessibility-checker' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'lynbro_a11y_admin_assets', 20 );

/**
 * The "Saved." confirmation, inside our own panel.
 *
 * Printed after the heading rather than before the wrap, for two reasons: a
 * notice outside .wrap is furniture WordPress may move about, and a confirmation
 * that a screen reader never announces is a confirmation only sighted people
 * get. role="status" is the announcement.
 *
 * @param bool $saved Whether something was just saved.
 * @return void
 */
function lynbro_a11y_saved_notice( $saved ) {
	if ( ! $saved ) {
		return;
	}
	printf(
		'<div class="notice notice-success inline" role="status"><p>%s</p></div>',
		esc_html__( 'Saved.', 'lynbro-accessibility-checker' )
	);
}

/**
 * A nonce field that does not carry an id.
 *
 * wp_nonce_field() gives its hidden input id="_wpnonce" — the same id every
 * time. One form per screen and nobody notices; our report screen has one
 * "hide this" form per issue, so a page with twelve findings shipped twelve
 * elements sharing an id. Labels, aria-labelledby and skip links all resolve by
 * id and only ever find the first.
 *
 * Found by running this plugin's own scanner over this plugin's own screens.
 *
 * @param string $action Nonce action.
 * @return void
 */
function lynbro_a11y_nonce_field( $action ) {
	printf(
		'<input type="hidden" name="_wpnonce" value="%s"><input type="hidden" name="_wp_http_referer" value="%s">',
		esc_attr( wp_create_nonce( $action ) ),
		esc_attr( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' ) )
	);
}

/**
 * A severity badge: colour, and a word, because colour alone is not a signal.
 *
 * @param string $severity error | warning | review.
 * @param int    $count    Optional number to show.
 * @return string HTML.
 */
function lynbro_a11y_badge( $severity, $count = null ) {
	$label = lynbro_a11y_severity_label( $severity );
	$text  = null === $count ? $label : sprintf( '%s: %s', $label, number_format_i18n( $count ) );

	return sprintf(
		'<span class="lynbro-a11y-badge is-%1$s">%2$s</span>',
		esc_attr( $severity ),
		esc_html( $text )
	);
}

/**
 * A short label for a page row.
 *
 * @param array<string,mixed> $page Page row.
 * @return string
 */
function lynbro_a11y_page_label( $page ) {
	if ( ! empty( $page['label'] ) ) {
		return (string) $page['label'];
	}
	// The address without scheme and host: still tells pages apart, and does not
	// read as a raw URL (which this plugin's own scan reports as link text).
	$parts = wp_parse_url( (string) $page['url'] );
	$path  = ( isset( $parts['path'] ) ? $parts['path'] : '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
	return '' !== $path ? $path : (string) $page['url'];
}
