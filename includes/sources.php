<?php
/**
 * What counts as "the whole site".
 *
 * There is no cap here, and that is the point. The competition puts full-site
 * scanning behind a paid tier; the sentence in our readme that says we do not
 * is only true if this file never stops early. Batching happens in the queue,
 * where it belongs — a list of URLs is cheap; fetching them is not.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Post types the scan covers.
 *
 * @return array<int,string>
 */
function lynbro_a11y_scan_post_types() {
	$configured = lynbro_a11y_lines( lynbro_a11y_option( 'post_types' ) );

	if ( $configured ) {
		$types = array_values( array_intersect( $configured, get_post_types( array( 'public' => true ) ) ) );
	} else {
		$types = array_values( get_post_types( array( 'public' => true ) ) );
		$types = array_values( array_diff( $types, array( 'attachment' ) ) );
	}

	/**
	 * Filter the post types the scan covers.
	 *
	 * @param array<int,string> $types Post type names.
	 */
	return (array) apply_filters( 'lynbro_a11y_post_types', $types );
}

/**
 * Build the list of URLs to scan, in the order they will be visited.
 *
 * The front page comes first because it is the page an inspector opens first.
 *
 * @return array<int,array<string,mixed>> Each with url, object_type, object_id, label.
 */
function lynbro_a11y_collect_urls() {
	$targets = array();

	if ( lynbro_a11y_option( 'include_front_page' ) ) {
		$targets[] = array(
			'url'         => home_url( '/' ),
			'object_type' => 'home',
			'object_id'   => 0,
			'label'       => __( 'Front page', 'lynbro-accessibility-checker' ),
		);
	}

	$post_ids = get_posts(
		array(
			'post_type'              => lynbro_a11y_scan_post_types(),
			'post_status'            => 'publish',
			'numberposts'            => -1,
			'fields'                 => 'ids',
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'suppress_filters'       => false,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	foreach ( (array) $post_ids as $post_id ) {
		$permalink = get_permalink( $post_id );
		if ( ! $permalink ) {
			continue;
		}
		if ( 'private' === get_post_status( $post_id ) ) {
			continue;
		}
		$targets[] = array(
			'url'         => $permalink,
			'object_type' => 'post',
			'object_id'   => (int) $post_id,
			'label'       => get_the_title( $post_id ),
		);
	}

	if ( lynbro_a11y_option( 'include_terms' ) ) {
		$taxonomies = get_taxonomies( array( 'public' => true ) );
		$terms      = get_terms(
			array(
				'taxonomy'   => array_values( $taxonomies ),
				'hide_empty' => true,
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$link = get_term_link( $term );
				if ( is_wp_error( $link ) ) {
					continue;
				}
				$targets[] = array(
					'url'         => $link,
					'object_type' => 'term',
					'object_id'   => (int) $term->term_id,
					'label'       => $term->name,
				);
			}
		}
	}

	if ( lynbro_a11y_option( 'include_archives' ) ) {
		foreach ( lynbro_a11y_scan_post_types() as $type ) {
			$link = get_post_type_archive_link( $type );
			if ( $link ) {
				$targets[] = array(
					'url'         => $link,
					'object_type' => 'archive',
					'object_id'   => 0,
					'label'       => sprintf( /* translators: %s: post type name. */ __( 'Archive: %s', 'lynbro-accessibility-checker' ), $type ),
				);
			}
		}
	}

	foreach ( lynbro_a11y_lines( lynbro_a11y_option( 'extra_urls' ) ) as $url ) {
		$targets[] = array(
			'url'         => $url,
			'object_type' => 'url',
			'object_id'   => 0,
			'label'       => '',
		);
	}

	$targets = lynbro_a11y_apply_exclusions( $targets );

	/**
	 * Filter the pages a scan will visit.
	 *
	 * @param array<int,array<string,mixed>> $targets Scan targets.
	 */
	$targets = (array) apply_filters( 'lynbro_a11y_scan_targets', $targets );

	// One row per URL, whatever produced it.
	$unique = array();
	foreach ( $targets as $target ) {
		if ( empty( $target['url'] ) ) {
			continue;
		}
		$unique[ esc_url_raw( $target['url'] ) ] = $target;
	}

	return array_values( $unique );
}

/**
 * Drop anything matching the exclusion list.
 *
 * A line is matched as a plain substring of the URL, with a * allowed anywhere.
 * No regular expressions: a mistyped expression that matches everything would
 * silently produce an empty scan, and an empty scan looks like a clean site.
 *
 * @param array<int,array<string,mixed>> $targets Scan targets.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_apply_exclusions( $targets ) {
	$patterns = lynbro_a11y_lines( lynbro_a11y_option( 'exclude_urls' ) );
	if ( ! $patterns ) {
		return $targets;
	}

	return array_values(
		array_filter(
			$targets,
			static function ( $target ) use ( $patterns ) {
				$url = isset( $target['url'] ) ? (string) $target['url'] : '';
				foreach ( $patterns as $pattern ) {
					if ( false !== strpos( $pattern, '*' ) ) {
						$regex = '#' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '#i';
						if ( preg_match( $regex, $url ) ) {
							return false;
						}
					} elseif ( false !== stripos( $url, $pattern ) ) {
						return false;
					}
				}
				return true;
			}
		)
	);
}
