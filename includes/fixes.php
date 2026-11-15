<?php
/**
 * The fixes.
 *
 * Every one of these is off until somebody switches it on, and every one says
 * on screen exactly what it changes. That is not caution for its own sake: a
 * plugin that starts rewriting a theme's markup the moment it is activated is
 * the plugin that gets blamed for the next thing that breaks, and it deserves
 * to be. Switching a fix off puts the markup back — nothing here edits a post,
 * a template or the database.
 *
 * What is deliberately NOT here: anything that would rewrite the whole page
 * through an output buffer. Buffering the entire response to run a regular
 * expression over it is how several plugins in this category work, and it
 * breaks every other plugin that also buffers. Each fix below hooks the one
 * filter that owns the markup it changes.
 *
 * Also deliberately not here: heading order. A heading level cannot be
 * corrected without deciding what the author meant, and a plugin that silently
 * renumbers someone's headings has made a decision it cannot explain. The
 * scanner reports those; a person fixes them.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every fix: what it is called, what it does, and what it touches.
 *
 * @return array<string,array<string,string>>
 */
function lynbro_a11y_fix_definitions() {
	return array(
		'fix_alt_from_library'  => array(
			'title'   => __( 'Fill in missing alt text from the Media Library', 'lynbro-accessibility-checker' ),
			'does'    => __( 'When an image in your content has no alt attribute but the same file has alt text saved in the Media Library, that text is used.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'It never invents alt text. An image with nothing saved in the library is left alone and stays in the report.', 'lynbro-accessibility-checker' ),
			'rules'   => 'img_alt_missing',
		),
		'fix_skip_link'         => array(
			'title'   => __( 'Add a skip link', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Puts a "Skip to content" link at the very start of the page. It is invisible until it is focused, exactly as WordPress does it in the admin.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'It needs something to skip to. Set the id of your content area below — most themes use "main" or "content".', 'lynbro-accessibility-checker' ),
			'rules'   => 'skip_link_missing',
		),
		'fix_focus_outline'     => array(
			'title'   => __( 'Show where the keyboard is', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Draws a visible outline around whatever has keyboard focus, for themes that remove the browser default.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'Uses :focus-visible, so the outline appears for keyboard users and not on every mouse click.', 'lynbro-accessibility-checker' ),
			'rules'   => 'focus_not_visible',
		),
		'fix_underline_links'   => array(
			'title'   => __( 'Underline links inside your text', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Underlines links in post and page content, so colour is not the only thing marking them.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'Only links inside the content area. Menus, buttons and image links are left alone.', 'lynbro-accessibility-checker' ),
			'rules'   => '',
		),
		'fix_new_window_notice' => array(
			'title'   => __( 'Say when a link opens a new window', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Adds "(opens in a new window)" to links in your content that open a new tab. Screen readers announce it; nothing changes on screen.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'A link that already says so in its text is left alone.', 'lynbro-accessibility-checker' ),
			'rules'   => 'link_new_window',
		),
		'fix_iframe_titles'     => array(
			'title'   => __( 'Name embedded frames', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Gives an untitled embed in your content a title naming what it is — "YouTube video", "Google Maps map" and so on.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'A generic name is better than none, and worse than one you write yourself. Frames it does not recognise keep no title and stay in the report.', 'lynbro-accessibility-checker' ),
			'rules'   => 'iframe_title_missing',
		),
		'fix_core_form_labels'  => array(
			'title'   => __( 'Label the WordPress search and comment fields', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Adds proper labels to the search field and the comment form fields when your theme ships them without.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'Only touches the forms WordPress itself renders. Fields from a form plugin are that plugin\'s to fix.', 'lynbro-accessibility-checker' ),
			'rules'   => 'form_label_missing',
		),
		'fix_lang_attribute'    => array(
			'title'   => __( 'Set the page language when the theme forgets', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Adds the site language and text direction to the html element if they are missing.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'Runs in the browser, because by the time WordPress knows the theme skipped it the tag has already been sent.', 'lynbro-accessibility-checker' ),
			'rules'   => 'html_lang_missing',
		),
		'fix_viewport_zoom'     => array(
			'title'   => __( 'Let people zoom on a phone', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Removes user-scalable=no and any maximum-scale below 2 from the viewport tag.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'Some themes set this on purpose to stop a layout shifting. If yours does, check the layout after switching this on.', 'lynbro-accessibility-checker' ),
			'rules'   => 'viewport_zoom_blocked',
		),
		'fix_tabindex_cleanup'  => array(
			'title'   => __( 'Undo forced tab order', 'lynbro-accessibility-checker' ),
			'does'    => __( 'Turns tabindex="1" and higher into tabindex="0", so the keyboard follows the page instead of jumping.', 'lynbro-accessibility-checker' ),
			'careful' => __( 'The element stays focusable — it just stops queue-jumping the whole page.', 'lynbro-accessibility-checker' ),
			'rules'   => 'tabindex_positive',
		),
	);
}

/**
 * Is a fix switched on?
 *
 * @param string $fix Fix key.
 * @return bool
 */
function lynbro_a11y_fix_on( $fix ) {
	return (bool) lynbro_a11y_option( $fix, 0 );
}

/**
 * Are any of the fixes on at all?
 *
 * @return bool
 */
function lynbro_a11y_any_fix_on() {
	foreach ( array_keys( lynbro_a11y_fix_definitions() ) as $fix ) {
		if ( lynbro_a11y_fix_on( $fix ) ) {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Stylesheet and script — one of each, and only when there is work for them.
 * ---------------------------------------------------------------------- */

/**
 * Enqueue the front-end pieces the switched-on fixes need.
 *
 * @return void
 */
function lynbro_a11y_enqueue_front_end() {
	if ( is_admin() ) {
		return;
	}

	$needs_css = lynbro_a11y_fix_on( 'fix_skip_link' ) || lynbro_a11y_fix_on( 'fix_focus_outline' ) || lynbro_a11y_fix_on( 'fix_underline_links' ) || lynbro_a11y_fix_on( 'fix_new_window_notice' );
	$needs_js  = lynbro_a11y_fix_on( 'fix_lang_attribute' ) || lynbro_a11y_fix_on( 'fix_viewport_zoom' ) || lynbro_a11y_fix_on( 'fix_tabindex_cleanup' );

	if ( $needs_css ) {
		wp_enqueue_style(
			'lynbro-a11y-fixes',
			LYNBRO_A11Y_URL . 'public/fixes.css',
			array(),
			lynbro_a11y_asset_ver( 'public/fixes.css' )
		);

		$inline = '';
		if ( lynbro_a11y_fix_on( 'fix_focus_outline' ) ) {
			$colour  = sanitize_hex_color( (string) lynbro_a11y_option( 'fix_focus_colour' ) );
			$colour  = $colour ? $colour : '#1a7f5a';
			$inline .= sprintf(
				':focus-visible{outline:3px solid %s;outline-offset:2px;}',
				$colour
			);
		}
		if ( $inline ) {
			wp_add_inline_style( 'lynbro-a11y-fixes', $inline );
		}
	}

	if ( $needs_js ) {
		wp_enqueue_script(
			'lynbro-a11y-fixes',
			LYNBRO_A11Y_URL . 'public/fixes.js',
			array(),
			lynbro_a11y_asset_ver( 'public/fixes.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'lynbro-a11y-fixes',
			'lynbroA11yFixes',
			array(
				'lang'     => lynbro_a11y_fix_on( 'fix_lang_attribute' ) ? get_bloginfo( 'language' ) : '',
				'dir'      => lynbro_a11y_fix_on( 'fix_lang_attribute' ) ? ( is_rtl() ? 'rtl' : 'ltr' ) : '',
				'viewport' => lynbro_a11y_fix_on( 'fix_viewport_zoom' ) ? 1 : 0,
				'tabindex' => lynbro_a11y_fix_on( 'fix_tabindex_cleanup' ) ? 1 : 0,
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'lynbro_a11y_enqueue_front_end', 20 );

/**
 * Mark the body when the underline fix is on.
 *
 * A class rather than a blanket rule, so the stylesheet can scope the change to
 * content areas and leave menus and buttons alone.
 *
 * @param array<int,string> $classes Body classes.
 * @return array<int,string>
 */
function lynbro_a11y_body_class( $classes ) {
	if ( lynbro_a11y_fix_on( 'fix_underline_links' ) ) {
		$classes[] = 'lynbro-a11y-underline-links';
	}
	return $classes;
}
add_filter( 'body_class', 'lynbro_a11y_body_class' );

/* -------------------------------------------------------------------------
 * Skip link
 * ---------------------------------------------------------------------- */

/**
 * Print the skip link as the first thing inside <body>.
 *
 * wp_body_open is the only correct place for this: the link has to be the first
 * focusable element on the page, and no filter on the content can put it there.
 * Themes older than WordPress 5.2 do not fire it, which is why the Fixes screen
 * says so rather than leaving the owner to wonder.
 *
 * @return void
 */
function lynbro_a11y_print_skip_link() {
	if ( ! lynbro_a11y_fix_on( 'fix_skip_link' ) || is_admin() ) {
		return;
	}

	$target = (string) lynbro_a11y_option( 'fix_skip_target' );
	$target = preg_replace( '/[^A-Za-z0-9_\-]/', '', $target );
	if ( '' === $target ) {
		return;
	}

	printf(
		'<a class="lynbro-a11y-skip-link" href="%s">%s</a>',
		esc_url( '#' . $target ),
		esc_html__( 'Skip to content', 'lynbro-accessibility-checker' )
	);
}
add_action( 'wp_body_open', 'lynbro_a11y_print_skip_link', 1 );

/* -------------------------------------------------------------------------
 * Content fixes — one pass over the post body, on the filter that owns it.
 * ---------------------------------------------------------------------- */

/**
 * Apply the content-level fixes to a post body.
 *
 * All three run in one parse. Parsing the same content three times to apply
 * three fixes is three times the cost for the same answer.
 *
 * @param string $content Post content.
 * @return string
 */
function lynbro_a11y_filter_content( $content ) {
	if ( is_admin() || ! is_string( $content ) || '' === trim( $content ) ) {
		return $content;
	}

	$alt      = lynbro_a11y_fix_on( 'fix_alt_from_library' );
	$window   = lynbro_a11y_fix_on( 'fix_new_window_notice' );
	$frames   = lynbro_a11y_fix_on( 'fix_iframe_titles' );

	if ( ! $alt && ! $window && ! $frames ) {
		return $content;
	}
	if ( ! lynbro_a11y_dom_available() ) {
		return $content;
	}

	$doc = lynbro_a11y_load_document( '<div id="lynbro-a11y-root">' . $content . '</div>' );
	if ( ! $doc ) {
		return $content;
	}

	$xpath = lynbro_a11y_xpath( $doc );
	$root  = lynbro_a11y_element_by_id( $xpath, 'lynbro-a11y-root' );
	if ( ! $root ) {
		return $content;
	}

	$changed = false;

	if ( $alt ) {
		$changed = lynbro_a11y_apply_alt_from_library( $root ) || $changed;
	}
	if ( $window ) {
		$changed = lynbro_a11y_apply_new_window_notice( $doc, $xpath, $root ) || $changed;
	}
	if ( $frames ) {
		$changed = lynbro_a11y_apply_iframe_titles( $root ) || $changed;
	}

	if ( ! $changed ) {
		return $content;
	}

	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $doc->saveHTML( $child );
	}

	return $out;
}
add_filter( 'the_content', 'lynbro_a11y_filter_content', 20 );

/**
 * Give images their Media Library alt text when the markup has none.
 *
 * @param DOMElement $root Content wrapper.
 * @return bool Whether anything changed.
 */
function lynbro_a11y_apply_alt_from_library( $root ) {
	$changed = false;

	foreach ( $root->getElementsByTagName( 'img' ) as $img ) {
		if ( $img->hasAttribute( 'alt' ) ) {
			continue;
		}

		$attachment_id = lynbro_a11y_attachment_id_for( $img );
		if ( ! $attachment_id ) {
			continue;
		}

		$alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		$alt = is_string( $alt ) ? trim( $alt ) : '';
		if ( '' === $alt ) {
			// Nothing saved for this image. Leaving it alone is the honest
			// answer: an invented description is worse than a missing one,
			// because it stops looking like a problem.
			continue;
		}

		$img->setAttribute( 'alt', $alt );
		$changed = true;
	}

	return $changed;
}

/**
 * The attachment behind an image in content, from its class or its URL.
 *
 * @param DOMElement $img Image element.
 * @return int Attachment id, or 0.
 */
function lynbro_a11y_attachment_id_for( $img ) {
	$class = lynbro_a11y_attr( $img, 'class' );
	if ( preg_match( '/wp-image-(\d+)/', $class, $m ) ) {
		return (int) $m[1];
	}

	$src = lynbro_a11y_attr( $img, 'src' );
	if ( '' === $src ) {
		return 0;
	}

	// attachment_url_to_postid hits the database, so only ask for images that
	// are plausibly ours to begin with.
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) || 0 !== strpos( $src, $uploads['baseurl'] ) ) {
		return 0;
	}

	return (int) attachment_url_to_postid( $src );
}

/**
 * Append a screen-reader note to links that open a new window.
 *
 * @param DOMDocument $doc   Document.
 * @param DOMXPath    $xpath Bound XPath.
 * @param DOMElement  $root  Content wrapper.
 * @return bool Whether anything changed.
 */
function lynbro_a11y_apply_new_window_notice( $doc, $xpath, $root ) {
	$changed = false;

	foreach ( lynbro_a11y_query( $xpath, './/a[@target]', $root ) as $link ) {
		if ( '_blank' !== strtolower( lynbro_a11y_attr( $link, 'target' ) ) ) {
			continue;
		}
		$name = lynbro_a11y_accessible_name( $link, $xpath );
		if ( '' === $name || lynbro_a11y_announces_new_window( $link, $name ) ) {
			continue;
		}

		$span = $doc->createElement( 'span' );
		$span->setAttribute( 'class', 'lynbro-a11y-sr-only' );
		$span->appendChild( $doc->createTextNode( ' ' . __( '(opens in a new window)', 'lynbro-accessibility-checker' ) ) );
		$link->appendChild( $span );
		$changed = true;
	}

	return $changed;
}

/**
 * Title untitled embeds by the service they come from.
 *
 * @param DOMElement $root Content wrapper.
 * @return bool Whether anything changed.
 */
function lynbro_a11y_apply_iframe_titles( $root ) {
	$changed = false;

	foreach ( $root->getElementsByTagName( 'iframe' ) as $frame ) {
		if ( '' !== lynbro_a11y_attr( $frame, 'title' ) ) {
			continue;
		}
		$src = lynbro_a11y_attr( $frame, 'src' );
		if ( '' === $src ) {
			$src = lynbro_a11y_attr( $frame, 'data-src' );
		}
		$title = lynbro_a11y_frame_title_for( $src );
		if ( '' === $title ) {
			continue;
		}
		$frame->setAttribute( 'title', $title );
		$changed = true;
	}

	return $changed;
}

/**
 * A name for an embed, from the host it loads from.
 *
 * Only services we can name honestly. "Embedded frame" would technically fill
 * the attribute and tell a screen reader user nothing, which is the failure the
 * rule is about.
 *
 * @param string $src Frame source.
 * @return string Title, or an empty string when we cannot name it.
 */
function lynbro_a11y_frame_title_for( $src ) {
	$host = $src ? wp_parse_url( $src, PHP_URL_HOST ) : '';
	if ( ! $host ) {
		return '';
	}
	$host = strtolower( $host );

	$known = array(
		'youtube.com'     => __( 'YouTube video', 'lynbro-accessibility-checker' ),
		'youtu.be'        => __( 'YouTube video', 'lynbro-accessibility-checker' ),
		'youtube-nocookie.com' => __( 'YouTube video', 'lynbro-accessibility-checker' ),
		'vimeo.com'       => __( 'Vimeo video', 'lynbro-accessibility-checker' ),
		'google.com/maps' => __( 'Google Maps map', 'lynbro-accessibility-checker' ),
		'google.com'      => __( 'Google Maps map', 'lynbro-accessibility-checker' ),
		'openstreetmap.org' => __( 'OpenStreetMap map', 'lynbro-accessibility-checker' ),
		'spotify.com'     => __( 'Spotify player', 'lynbro-accessibility-checker' ),
		'soundcloud.com'  => __( 'SoundCloud player', 'lynbro-accessibility-checker' ),
		'facebook.com'    => __( 'Facebook post', 'lynbro-accessibility-checker' ),
		'instagram.com'   => __( 'Instagram post', 'lynbro-accessibility-checker' ),
		'dailymotion.com' => __( 'Dailymotion video', 'lynbro-accessibility-checker' ),
	);

	foreach ( $known as $needle => $title ) {
		if ( false !== strpos( $host, $needle ) || false !== strpos( strtolower( (string) $src ), $needle ) ) {
			return $title;
		}
	}

	return '';
}

/* -------------------------------------------------------------------------
 * Core forms
 * ---------------------------------------------------------------------- */

/**
 * Give the WordPress search form a real label when the theme's has none.
 *
 * @param string $form Search form markup.
 * @return string
 */
function lynbro_a11y_filter_search_form( $form ) {
	if ( ! lynbro_a11y_fix_on( 'fix_core_form_labels' ) || is_admin() ) {
		return $form;
	}
	if ( ! is_string( $form ) || '' === $form ) {
		return $form;
	}
	// A theme that already labels its field is left alone.
	if ( false !== stripos( $form, '<label' ) || false !== stripos( $form, 'aria-label' ) ) {
		return $form;
	}
	if ( ! lynbro_a11y_dom_available() ) {
		return $form;
	}

	$doc = lynbro_a11y_load_document( '<div id="lynbro-a11y-root">' . $form . '</div>' );
	if ( ! $doc ) {
		return $form;
	}
	$xpath = lynbro_a11y_xpath( $doc );
	$root  = lynbro_a11y_element_by_id( $xpath, 'lynbro-a11y-root' );
	if ( ! $root ) {
		return $form;
	}

	$changed = false;
	foreach ( lynbro_a11y_query( $xpath, './/input[translate(@type,"SEARCH","search")="search" or @name="s"]', $root ) as $field ) {
		if ( '' !== lynbro_a11y_accessible_name( $field, $xpath ) ) {
			continue;
		}
		$field->setAttribute( 'aria-label', __( 'Search', 'lynbro-accessibility-checker' ) );
		$changed = true;
	}

	if ( ! $changed ) {
		return $form;
	}

	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $doc->saveHTML( $child );
	}
	return $out;
}
add_filter( 'get_search_form', 'lynbro_a11y_filter_search_form', 20 );

/**
 * Make sure the comment form's fields carry labels.
 *
 * comment_form_defaults is the documented way in: WordPress builds the fields
 * from this array, so setting them here changes the markup without touching the
 * theme and without a single line of output buffering.
 *
 * @param array<string,mixed> $defaults Comment form arguments.
 * @return array<string,mixed>
 */
function lynbro_a11y_filter_comment_form( $defaults ) {
	if ( ! lynbro_a11y_fix_on( 'fix_core_form_labels' ) || is_admin() ) {
		return $defaults;
	}

	if ( empty( $defaults['comment_field'] ) || false === stripos( (string) $defaults['comment_field'], '<label' ) ) {
		$defaults['comment_field'] = sprintf(
			'<p class="comment-form-comment"><label for="comment">%1$s</label> <textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>',
			esc_html__( 'Comment', 'lynbro-accessibility-checker' )
		);
	}

	if ( ! empty( $defaults['fields'] ) && is_array( $defaults['fields'] ) ) {
		foreach ( $defaults['fields'] as $key => $markup ) {
			if ( is_string( $markup ) && false === stripos( $markup, '<label' ) && false === stripos( $markup, 'aria-label' ) ) {
				// The theme replaced this field with something unlabelled. Ask
				// WordPress for its own version, which is labelled.
				unset( $defaults['fields'][ $key ] );
			}
		}
	}

	return $defaults;
}
add_filter( 'comment_form_defaults', 'lynbro_a11y_filter_comment_form', 20 );

/* -------------------------------------------------------------------------
 * Editor-side help: an image saved with no alt text is the cheapest fix there
 * is, and the best moment to make it is while the image is being inserted.
 * ---------------------------------------------------------------------- */

/**
 * Fill in the alt attribute WordPress prints for an attachment.
 *
 * @param array<string,string> $attr       Attributes for the image markup.
 * @param WP_Post              $attachment Attachment post.
 * @return array<string,string>
 */
function lynbro_a11y_attachment_attributes( $attr, $attachment ) {
	if ( ! lynbro_a11y_fix_on( 'fix_alt_from_library' ) ) {
		return $attr;
	}
	if ( isset( $attr['alt'] ) && '' !== trim( (string) $attr['alt'] ) ) {
		return $attr;
	}
	$alt = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
	if ( is_string( $alt ) && '' !== trim( $alt ) ) {
		$attr['alt'] = trim( $alt );
	}
	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'lynbro_a11y_attachment_attributes', 20, 2 );
