<?php
/**
 * The checks.
 *
 * Each function takes the parsed document and returns a list of findings. A
 * finding is deliberately small — rule id, the element, and one sentence of
 * context — because the explanation lives once in rules.php rather than being
 * repeated at every call site.
 *
 * Two habits run through all of them:
 *
 *   • Nothing hidden is reported. A theme that ships a hidden fallback menu is
 *     not a theme with fifty broken links, and a report full of things that are
 *     not on the page is a report nobody reads twice.
 *   • Where markup cannot settle a question, the finding is a 'review', not a
 *     failure. Guessing produces the same false confidence that overlays sell.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Make one finding.
 *
 * @param string          $rule    Rule id.
 * @param DOMElement|null $el      Element it was found on, when there is one.
 * @param string          $context One short sentence naming the specific value.
 * @return array<string,mixed>
 */
function lynbro_a11y_finding( $rule, $el = null, $context = '' ) {
	return array(
		'rule'     => $rule,
		'selector' => $el instanceof DOMElement ? lynbro_a11y_selector( $el ) : '',
		'snippet'  => $el instanceof DOMElement ? lynbro_a11y_snippet( $el ) : '',
		'context'  => (string) $context,
	);
}

/**
 * Every check, in the order the report groups them.
 *
 * @return array<int,callable>
 */
function lynbro_a11y_checks() {
	$checks = array(
		'lynbro_a11y_check_document',
		'lynbro_a11y_check_headings',
		'lynbro_a11y_check_images',
		'lynbro_a11y_check_links',
		'lynbro_a11y_check_forms',
		'lynbro_a11y_check_tables',
		'lynbro_a11y_check_media',
		'lynbro_a11y_check_aria',
		'lynbro_a11y_check_presentation',
	);

	/**
	 * Filter the list of checks.
	 *
	 * A check is any callable taking ( DOMDocument $doc, DOMXPath $xpath ) and
	 * returning an array of findings from lynbro_a11y_finding().
	 *
	 * @param array<int,callable> $checks Check callables.
	 */
	return (array) apply_filters( 'lynbro_a11y_checks', $checks );
}

/**
 * Run every check over a document.
 *
 * @param DOMDocument $doc Parsed page.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_analyse( $doc ) {
	$xpath    = lynbro_a11y_xpath( $doc );
	$findings = array();

	foreach ( lynbro_a11y_checks() as $check ) {
		if ( ! is_callable( $check ) ) {
			continue;
		}
		$result = call_user_func( $check, $doc, $xpath );
		if ( is_array( $result ) && $result ) {
			$findings = array_merge( $findings, $result );
		}
	}

	/**
	 * Filter the findings for one page before they are stored.
	 *
	 * @param array<int,array<string,mixed>> $findings Findings.
	 * @param DOMDocument                    $doc      The page.
	 */
	return (array) apply_filters( 'lynbro_a11y_findings', $findings, $doc );
}

/* -------------------------------------------------------------------------
 * Document
 * ---------------------------------------------------------------------- */

/**
 * Title, language, viewport, landmarks, skip link.
 *
 * These only make sense on a whole page. A fragment — the content of one post,
 * scanned without its theme — has no <html> element, and reporting that it has
 * no language would be reporting the scanner's own limitation as the site's
 * problem.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_document( $doc, $xpath ) {
	$out  = array();
	$html = lynbro_a11y_query( $xpath, '/html' );
	if ( ! $html ) {
		return $out;
	}
	$html = $html[0];
	if ( ! lynbro_a11y_query( $xpath, '/html/head' ) ) {
		// Loaded from a fragment rather than a served page.
		return $out;
	}

	$titles = lynbro_a11y_query( $xpath, '/html/head/title' );
	if ( ! $titles || '' === lynbro_a11y_text( $titles[0] ) ) {
		$out[] = lynbro_a11y_finding( 'page_title_missing' );
	}

	$lang = lynbro_a11y_attr( $html, 'lang' );
	if ( '' === $lang || ! preg_match( '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $lang ) ) {
		$out[] = lynbro_a11y_finding(
			'html_lang_missing',
			$html,
			'' === $lang
				? __( 'No lang attribute on the html element.', 'lynbro-accessibility-checker' )
				: sprintf( /* translators: %s: the value found in the lang attribute. */ __( 'lang="%s" is not a language tag.', 'lynbro-accessibility-checker' ), $lang )
		);
	}

	foreach ( lynbro_a11y_query( $xpath, '//meta[translate(@name,"VIEWPORT","viewport")="viewport"]' ) as $meta ) {
		$content = strtolower( lynbro_a11y_attr( $meta, 'content' ) );
		if ( false !== strpos( str_replace( ' ', '', $content ), 'user-scalable=no' ) ) {
			$out[] = lynbro_a11y_finding( 'viewport_zoom_blocked', $meta, __( 'The viewport says user-scalable=no.', 'lynbro-accessibility-checker' ) );
			continue;
		}
		if ( preg_match( '/maximum-scale\s*=\s*([0-9.]+)/', $content, $m ) && (float) $m[1] < 2 ) {
			$out[] = lynbro_a11y_finding(
				'viewport_zoom_blocked',
				$meta,
				sprintf( /* translators: %s: the maximum-scale value found. */ __( 'The viewport caps zoom at %s.', 'lynbro-accessibility-checker' ), $m[1] )
			);
		}
	}

	if ( ! lynbro_a11y_query( $xpath, '//main | //*[translate(@role,"MAIN","main")="main"]' ) ) {
		$out[] = lynbro_a11y_finding( 'main_landmark_missing' );
	}

	if ( ! lynbro_a11y_has_skip_link( $xpath ) ) {
		$out[] = lynbro_a11y_finding( 'skip_link_missing' );
	}

	return $out;
}

/**
 * Does the page start with a link into itself?
 *
 * A skip link is a same-page link that appears early in the source. "Early" is
 * taken as within the first fifteen links, which covers a header that puts a
 * logo and a couple of utility links before it.
 *
 * @param DOMXPath $xpath Bound XPath.
 * @return bool
 */
function lynbro_a11y_has_skip_link( $xpath ) {
	$links = lynbro_a11y_query( $xpath, '//body//a[@href]' );
	$seen  = 0;
	foreach ( $links as $link ) {
		if ( ++$seen > 15 ) {
			break;
		}
		$href = lynbro_a11y_attr( $link, 'href' );
		if ( '' !== $href && '#' === $href[0] && strlen( $href ) > 1 ) {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Headings
 * ---------------------------------------------------------------------- */

/**
 * One h1, in order, none empty.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_headings( $doc, $xpath ) {
	$out      = array();
	$headings = lynbro_a11y_query( $xpath, '//body//h1 | //body//h2 | //body//h3 | //body//h4 | //body//h5 | //body//h6' );

	$visible  = array();
	foreach ( $headings as $heading ) {
		if ( ! lynbro_a11y_is_hidden( $heading ) ) {
			$visible[] = $heading;
		}
	}

	$h1 = 0;
	$previous_level = 0;

	foreach ( $visible as $heading ) {
		$level = (int) substr( lynbro_a11y_tag( $heading ), 1 );

		if ( '' === lynbro_a11y_text( $heading ) && '' === lynbro_a11y_accessible_name( $heading, $xpath ) ) {
			$out[] = lynbro_a11y_finding(
				'heading_empty',
				$heading,
				sprintf( /* translators: %d: heading level, 1 to 6. */ __( 'An h%d with no text.', 'lynbro-accessibility-checker' ), $level )
			);
		}

		if ( 1 === $level ) {
			$h1++;
		}

		if ( $previous_level > 0 && $level > $previous_level + 1 ) {
			$out[] = lynbro_a11y_finding(
				'heading_order_skipped',
				$heading,
				sprintf(
					/* translators: 1: the previous heading level, 2: this heading level. */
					__( 'h%1$d is followed by h%2$d.', 'lynbro-accessibility-checker' ),
					$previous_level,
					$level
				)
			);
		}
		$previous_level = $level;
	}

	if ( $visible && 0 === $h1 ) {
		$out[] = lynbro_a11y_finding( 'heading_missing_h1' );
	}
	if ( $h1 > 1 ) {
		$out[] = lynbro_a11y_finding(
			'heading_multiple_h1',
			null,
			sprintf(
				/* translators: %d: how many h1 elements were found. */
				_n( '%d first-level heading on this page.', '%d first-level headings on this page.', $h1, 'lynbro-accessibility-checker' ),
				$h1
			)
		);
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * Images
 * ---------------------------------------------------------------------- */

/**
 * Alt text: present, meaningful, and the right length.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_images( $doc, $xpath ) {
	$out = array();

	foreach ( lynbro_a11y_query( $xpath, '//body//img' ) as $img ) {
		if ( lynbro_a11y_is_hidden( $img ) ) {
			continue;
		}

		$in_link = lynbro_a11y_ancestor( $img, 'a' );

		if ( ! $img->hasAttribute( 'alt' ) ) {
			// An image that is the whole of a link is a worse case with a better
			// explanation, so it gets its own rule rather than the generic one.
			if ( $in_link && '' === lynbro_a11y_link_text_without( $in_link, $img ) ) {
				$out[] = lynbro_a11y_finding( 'img_linked_alt_empty', $img, __( 'The image is the only content of the link, and has no alt attribute.', 'lynbro-accessibility-checker' ) );
			} else {
				$out[] = lynbro_a11y_finding( 'img_alt_missing', $img, lynbro_a11y_image_hint( $img ) );
			}
			continue;
		}

		$alt = lynbro_a11y_attr( $img, 'alt' );

		if ( '' === $alt ) {
			if ( $in_link && '' === lynbro_a11y_link_text_without( $in_link, $img ) && '' === lynbro_a11y_accessible_name( $in_link, $xpath ) ) {
				$out[] = lynbro_a11y_finding( 'img_linked_alt_empty', $img, __( 'The image is the only content of the link, and its alt text is empty.', 'lynbro-accessibility-checker' ) );
			}
			continue;
		}

		if ( lynbro_a11y_alt_is_meaningless( $alt, lynbro_a11y_attr( $img, 'src' ) ) ) {
			$out[] = lynbro_a11y_finding(
				'img_alt_redundant',
				$img,
				sprintf( /* translators: %s: the alt text found. */ __( 'The alt text is "%s".', 'lynbro-accessibility-checker' ), $alt )
			);
			continue;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $alt ) : strlen( $alt );
		if ( $length > 150 ) {
			$out[] = lynbro_a11y_finding(
				'img_alt_long',
				$img,
				sprintf( /* translators: %d: number of characters. */ __( 'The alt text is %d characters long.', 'lynbro-accessibility-checker' ), $length )
			);
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//input[translate(@type,"IMAGE","image")="image"]' ) as $input ) {
		if ( lynbro_a11y_is_hidden( $input ) ) {
			continue;
		}
		if ( '' === lynbro_a11y_accessible_name( $input, $xpath ) ) {
			$out[] = lynbro_a11y_finding( 'input_image_alt_missing', $input );
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//area' ) as $area ) {
		if ( '' === lynbro_a11y_attr( $area, 'alt' ) && '' === lynbro_a11y_attr( $area, 'aria-label' ) ) {
			$out[] = lynbro_a11y_finding( 'area_alt_missing', $area );
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//*[local-name()="svg"]' ) as $svg ) {
		if ( lynbro_a11y_is_hidden( $svg ) ) {
			continue;
		}
		// An icon inside a link or button that already has text is decorative in
		// practice; flagging it would bury the real cases.
		$owner = lynbro_a11y_ancestor( $svg, 'a' );
		if ( ! $owner ) {
			$owner = lynbro_a11y_ancestor( $svg, 'button' );
		}
		if ( $owner && '' !== lynbro_a11y_accessible_name( $owner, $xpath ) ) {
			continue;
		}
		if ( '' !== lynbro_a11y_attr( $svg, 'aria-label' ) || '' !== lynbro_a11y_attr( $svg, 'aria-labelledby' ) ) {
			continue;
		}
		$has_title = false;
		foreach ( $svg->childNodes as $child ) {
			if ( $child instanceof DOMElement && 'title' === strtolower( $child->localName ) && '' !== lynbro_a11y_text( $child ) ) {
				$has_title = true;
				break;
			}
		}
		if ( ! $has_title ) {
			$out[] = lynbro_a11y_finding( 'svg_no_name', $svg );
		}
	}

	return $out;
}

/**
 * The nearest ancestor with a given tag name.
 *
 * @param DOMElement $el  Starting element.
 * @param string     $tag Tag to look for.
 * @return DOMElement|null
 */
function lynbro_a11y_ancestor( $el, $tag ) {
	for ( $node = $el->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		if ( lynbro_a11y_tag( $node ) === $tag ) {
			return $node;
		}
	}
	return null;
}

/**
 * The text of a link, ignoring one element inside it.
 *
 * @param DOMElement $link   The link.
 * @param DOMElement $ignore Element to leave out.
 * @return string
 */
function lynbro_a11y_link_text_without( $link, $ignore ) {
	$clone = $link->cloneNode( true );
	foreach ( iterator_to_array( $clone->getElementsByTagName( lynbro_a11y_tag( $ignore ) ) ) as $node ) {
		$node->parentNode->removeChild( $node );
	}
	return lynbro_a11y_text( $clone );
}

/**
 * Does this alt text say anything?
 *
 * The word lists are in English, Danish and the two other languages our own
 * sites run in, because "billede" is exactly as useless as "image" and an
 * English-only word list is why a Danish site passes a scan it should fail.
 *
 * @param string $alt Alt text.
 * @param string $src Image source, to catch alt text that is just the file name.
 * @return bool
 */
function lynbro_a11y_alt_is_meaningless( $alt, $src ) {
	$normalised = strtolower( trim( $alt ) );
	$normalised = trim( $normalised, ".,:;!?-_ \t" );

	if ( '' === $normalised ) {
		return false; // Empty alt is a decision, not a mistake; handled elsewhere.
	}

	$useless = array(
		'image', 'images', 'photo', 'photos', 'picture', 'pic', 'img', 'graphic', 'icon',
		'logo', 'banner', 'spacer', 'placeholder', 'thumbnail', 'thumb', 'screenshot',
		'billede', 'billeder', 'foto', 'ikon', 'grafik',
		'bild', 'bilde', 'zdjęcie',
		'зображення', 'фото', 'картинка', 'изображение', 'иконка',
	);
	if ( in_array( $normalised, $useless, true ) ) {
		return true;
	}

	// "image of a hand" is fine; "image123" and "DSC_0042" are not.
	if ( preg_match( '/^(image|img|photo|pic|dsc|screenshot|billede|foto)[\s_-]*[0-9]+$/', $normalised ) ) {
		return true;
	}

	if ( '' !== $src ) {
		$path = wp_parse_url( $src, PHP_URL_PATH );
		$file = strtolower( rawurldecode( basename( $path ? $path : $src ) ) );
		if ( '' !== $file && ( $normalised === $file || $normalised === pathinfo( $file, PATHINFO_FILENAME ) ) ) {
			return true;
		}
	}

	// A bare file name used as alt text, whatever the image is called.
	if ( preg_match( '/^[\w\-. ]+\.(jpe?g|png|gif|webp|avif|svg)$/', $normalised ) ) {
		return true;
	}

	return false;
}

/**
 * A hint naming the image, so the report row is findable.
 *
 * @param DOMElement $img Image element.
 * @return string
 */
function lynbro_a11y_image_hint( $img ) {
	$src = lynbro_a11y_attr( $img, 'src' );
	if ( '' === $src ) {
		$src = lynbro_a11y_attr( $img, 'data-src' );
	}
	if ( '' === $src ) {
		return '';
	}
	$path = wp_parse_url( $src, PHP_URL_PATH );
	$file = $path ? basename( $path ) : $src;
	return sprintf( /* translators: %s: image file name. */ __( 'Image: %s', 'lynbro-accessibility-checker' ), $file );
}

/* -------------------------------------------------------------------------
 * Links and buttons
 * ---------------------------------------------------------------------- */

/**
 * Links that say where they go, buttons that say what they do.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_links( $doc, $xpath ) {
	$out = array();

	foreach ( lynbro_a11y_query( $xpath, '//body//a' ) as $link ) {
		if ( lynbro_a11y_is_hidden( $link ) ) {
			continue;
		}

		if ( ! $link->hasAttribute( 'href' ) ) {
			// Only a problem when it behaves like a control. A named anchor is a
			// perfectly ordinary thing and reporting it would be noise.
			if ( $link->hasAttribute( 'onclick' ) || '' !== lynbro_a11y_attr( $link, 'role' ) ) {
				$out[] = lynbro_a11y_finding( 'anchor_without_href', $link );
			}
			continue;
		}

		$name = lynbro_a11y_accessible_name( $link, $xpath );

		if ( '' === $name ) {
			$out[] = lynbro_a11y_finding(
				'link_empty',
				$link,
				sprintf( /* translators: %s: the link target. */ __( 'The link points at %s.', 'lynbro-accessibility-checker' ), lynbro_a11y_attr( $link, 'href' ) )
			);
			continue;
		}

		if ( lynbro_a11y_text_is_ambiguous( $name ) ) {
			$out[] = lynbro_a11y_finding(
				'link_text_ambiguous',
				$link,
				sprintf( /* translators: %s: the link text. */ __( 'The link reads "%s".', 'lynbro-accessibility-checker' ), $name )
			);
		} elseif ( preg_match( '#^(https?://|www\.)#i', trim( $name ) ) ) {
			$out[] = lynbro_a11y_finding(
				'link_text_is_url',
				$link,
				sprintf( /* translators: %s: the link text. */ __( 'The link reads "%s".', 'lynbro-accessibility-checker' ), $name )
			);
		}

		$document = lynbro_a11y_linked_document( lynbro_a11y_attr( $link, 'href' ) );
		if ( '' !== $document ) {
			$out[] = lynbro_a11y_finding(
				'link_to_document',
				$link,
				sprintf( /* translators: %s: the file name the link points at. */ __( 'The link points at %s.', 'lynbro-accessibility-checker' ), $document )
			);
		}

		if ( '_blank' === strtolower( lynbro_a11y_attr( $link, 'target' ) ) && ! lynbro_a11y_announces_new_window( $link, $name ) ) {
			$out[] = lynbro_a11y_finding( 'link_new_window', $link, sprintf( /* translators: %s: the link text. */ __( 'The link reads "%s".', 'lynbro-accessibility-checker' ), $name ) );
		}
	}

	$buttons = lynbro_a11y_query(
		$xpath,
		'//body//button | //body//input[translate(@type,"SUBMITREBTON","submitrebton")="submit" or translate(@type,"SUBMITREBTON","submitrebton")="button" or translate(@type,"SUBMITREBTON","submitrebton")="reset"]'
	);
	foreach ( $buttons as $button ) {
		if ( lynbro_a11y_is_hidden( $button ) ) {
			continue;
		}
		if ( '' === lynbro_a11y_accessible_name( $button, $xpath ) ) {
			$out[] = lynbro_a11y_finding( 'button_empty', $button );
		}
	}

	return $out;
}

/**
 * Is this link text one of the phrases that says nothing?
 *
 * @param string $text Link text.
 * @return bool
 */
function lynbro_a11y_text_is_ambiguous( $text ) {
	$normalised = strtolower( trim( (string) $text ) );
	$normalised = trim( $normalised, " \t\n.,:;!?→>«»\"'" );
	$normalised = preg_replace( '/\s+/u', ' ', $normalised );

	$phrases = array(
		'click here', 'click', 'here', 'read more', 'more', 'learn more', 'find out more',
		'continue', 'continue reading', 'details', 'this', 'this page', 'link', 'go', 'more info',
		'more information', 'download', 'view', 'see more', 'start',
		'læs mere', 'læs', 'mere', 'klik her', 'klik', 'her', 'se mere', 'detaljer', 'hent', 'fortsæt',
		'läs mer', 'les mer', 'mehr', 'weiterlesen', 'hier klicken',
		'читати далі', 'детальніше', 'докладніше', 'тут', 'клікніть тут',
		'читать далее', 'подробнее', 'здесь',
	);

	/**
	 * Filter the phrases counted as uninformative link text.
	 *
	 * A site with its own house style — or a language we do not ship — can add
	 * to this rather than switching the rule off.
	 *
	 * @param array<int,string> $phrases Lower-case phrases.
	 */
	$phrases = (array) apply_filters( 'lynbro_a11y_ambiguous_link_text', $phrases );

	return in_array( $normalised, $phrases, true );
}

/**
 * Does a link already warn that it opens a new window?
 *
 * @param DOMElement $link Link element.
 * @param string     $name Its accessible name.
 * @return bool
 */
function lynbro_a11y_announces_new_window( $link, $name ) {
	$haystack = strtolower( $name . ' ' . lynbro_a11y_attr( $link, 'title' ) . ' ' . lynbro_a11y_attr( $link, 'aria-label' ) );
	foreach ( array( 'new window', 'new tab', 'nyt vindue', 'ny fane', 'нове вікно', 'новая вкладка' ) as $phrase ) {
		if ( false !== strpos( $haystack, $phrase ) ) {
			return true;
		}
	}
	return false;
}

/* -------------------------------------------------------------------------
 * Forms
 * ---------------------------------------------------------------------- */

/**
 * Fields with labels, labels with fields, ids that are unique.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_forms( $doc, $xpath ) {
	$out = array();

	$fields = lynbro_a11y_query( $xpath, '//body//input | //body//select | //body//textarea' );

	foreach ( $fields as $field ) {
		$type = strtolower( lynbro_a11y_attr( $field, 'type' ) );
		if ( in_array( $type, array( 'hidden', 'submit', 'reset', 'button', 'image' ), true ) ) {
			continue;
		}
		if ( lynbro_a11y_is_hidden( $field ) ) {
			continue;
		}

		$name = lynbro_a11y_accessible_name( $field, $xpath );

		if ( '' === $name ) {
			$placeholder = lynbro_a11y_attr( $field, 'placeholder' );
			if ( '' !== $placeholder ) {
				$out[] = lynbro_a11y_finding(
					'form_placeholder_as_label',
					$field,
					sprintf( /* translators: %s: the placeholder text. */ __( 'The only hint is the placeholder "%s".', 'lynbro-accessibility-checker' ), $placeholder )
				);
			} else {
				$out[] = lynbro_a11y_finding(
					'form_label_missing',
					$field,
					lynbro_a11y_field_hint( $field )
				);
			}
		}

		$purpose = lynbro_a11y_field_purpose( $field );
		if ( '' !== $purpose && '' === lynbro_a11y_attr( $field, 'autocomplete' ) ) {
			$out[] = lynbro_a11y_finding(
				'form_autocomplete_missing',
				$field,
				sprintf( /* translators: %s: the autocomplete value that belongs on the field. */ __( 'This field asks for something autocomplete="%s" would fill in.', 'lynbro-accessibility-checker' ), $purpose )
			);
		}
	}

	$ids = lynbro_a11y_id_map( $xpath );

	foreach ( lynbro_a11y_query( $xpath, '//body//label[@for]' ) as $label ) {
		$for = lynbro_a11y_attr( $label, 'for' );
		if ( '' !== $for && empty( $ids[ $for ] ) ) {
			$out[] = lynbro_a11y_finding(
				'form_label_orphaned',
				$label,
				sprintf( /* translators: %s: the value of the for attribute. */ __( 'The label points at id "%s", which is not on the page.', 'lynbro-accessibility-checker' ), $for )
			);
		}
	}

	foreach ( $ids as $id => $count ) {
		if ( $count > 1 ) {
			$out[] = lynbro_a11y_finding(
				'duplicate_id',
				null,
				sprintf(
					/* translators: 1: the duplicated id, 2: how many elements carry it. */
					__( 'id="%1$s" is on %2$d elements.', 'lynbro-accessibility-checker' ),
					$id,
					$count
				)
			);
		}
	}

	return $out;
}

/**
 * A hint naming the field, so a report row can be matched to the form.
 *
 * @param DOMElement $field Form control.
 * @return string
 */
function lynbro_a11y_field_hint( $field ) {
	$name = lynbro_a11y_attr( $field, 'name' );
	$type = lynbro_a11y_attr( $field, 'type' );
	if ( '' !== $name ) {
		return sprintf( /* translators: %s: the field's name attribute. */ __( 'Field name="%s".', 'lynbro-accessibility-checker' ), $name );
	}
	if ( '' !== $type ) {
		return sprintf( /* translators: %s: the field's type attribute. */ __( 'An input of type "%s".', 'lynbro-accessibility-checker' ), $type );
	}
	return '';
}

/**
 * The autocomplete token a field is asking for, if any.
 *
 * Deliberately conservative: only the fields where the purpose is unambiguous
 * from the type or a very plain name. Guessing here would put an autocomplete
 * warning on every search box on the internet.
 *
 * @param DOMElement $field Form control.
 * @return string Autocomplete token, or an empty string.
 */
function lynbro_a11y_field_purpose( $field ) {
	if ( 'input' !== lynbro_a11y_tag( $field ) ) {
		return '';
	}

	$type = strtolower( lynbro_a11y_attr( $field, 'type' ) );
	if ( 'email' === $type ) {
		return 'email';
	}
	if ( 'tel' === $type ) {
		return 'tel';
	}

	if ( ! in_array( $type, array( '', 'text' ), true ) ) {
		return '';
	}

	$name = strtolower( lynbro_a11y_attr( $field, 'name' ) . ' ' . lynbro_a11y_attr( $field, 'id' ) );
	$map  = array(
		'email'      => array( 'email', 'e-mail', 'epost', 'mail' ),
		'tel'        => array( 'phone', 'telephone', 'telefon', 'tlf', 'mobil' ),
		'postal-code' => array( 'postnr', 'postcode', 'postal', 'zip' ),
		'name'       => array( 'your-name', 'fullname', 'full-name', 'navn' ),
	);
	foreach ( $map as $token => $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $name, $needle ) ) {
				return $token;
			}
		}
	}

	return '';
}

/* -------------------------------------------------------------------------
 * Tables
 * ---------------------------------------------------------------------- */

/**
 * Data tables need header cells; layout tables are left alone.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_tables( $doc, $xpath ) {
	$out = array();

	foreach ( lynbro_a11y_query( $xpath, '//body//table' ) as $table ) {
		if ( lynbro_a11y_is_hidden( $table ) ) {
			continue;
		}
		if ( 'presentation' === strtolower( lynbro_a11y_attr( $table, 'role' ) ) || 'none' === strtolower( lynbro_a11y_attr( $table, 'role' ) ) ) {
			continue;
		}

		$rows = $table->getElementsByTagName( 'tr' );
		if ( $rows->length < 2 ) {
			// One row is a layout table or a stray fragment, not a data table.
			continue;
		}

		$headers = $table->getElementsByTagName( 'th' );
		if ( 0 === $headers->length ) {
			$out[] = lynbro_a11y_finding(
				'table_header_missing',
				$table,
				sprintf( /* translators: %d: number of rows in the table. */ __( 'A table with %d rows and no th cells.', 'lynbro-accessibility-checker' ), $rows->length )
			);
			continue;
		}

		foreach ( $headers as $header ) {
			if ( '' === lynbro_a11y_text( $header ) && '' === lynbro_a11y_accessible_name( $header, $xpath ) ) {
				$out[] = lynbro_a11y_finding( 'table_header_empty', $header );
			}
		}
	}

	return $out;
}

/* -------------------------------------------------------------------------
 * Media and frames
 * ---------------------------------------------------------------------- */

/**
 * Frames need titles; video and audio need a person to confirm the rest.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_media( $doc, $xpath ) {
	$out = array();

	foreach ( lynbro_a11y_query( $xpath, '//body//iframe' ) as $frame ) {
		if ( lynbro_a11y_is_hidden( $frame ) ) {
			continue;
		}
		if ( '' === lynbro_a11y_attr( $frame, 'title' ) && '' === lynbro_a11y_attr( $frame, 'aria-label' ) ) {
			$out[] = lynbro_a11y_finding( 'iframe_title_missing', $frame, lynbro_a11y_frame_hint( $frame ) );
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//video' ) as $video ) {
		$has_captions = false;
		foreach ( $video->getElementsByTagName( 'track' ) as $track ) {
			$kind = strtolower( lynbro_a11y_attr( $track, 'kind' ) );
			if ( 'captions' === $kind || 'subtitles' === $kind ) {
				$has_captions = true;
				break;
			}
		}
		if ( ! $has_captions ) {
			$out[] = lynbro_a11y_finding( 'video_no_captions', $video );
		}
	}

	$audio = lynbro_a11y_query( $xpath, '//body//audio' );
	if ( $audio ) {
		$out[] = lynbro_a11y_finding(
			'audio_needs_transcript',
			$audio[0],
			sprintf(
				/* translators: %d: number of audio players on the page. */
				_n( '%d audio player on this page.', '%d audio players on this page.', count( $audio ), 'lynbro-accessibility-checker' ),
				count( $audio )
			)
		);
	}

	return $out;
}

/**
 * A hint naming what a frame holds, from its source.
 *
 * @param DOMElement $frame The iframe.
 * @return string
 */
function lynbro_a11y_frame_hint( $frame ) {
	$src = lynbro_a11y_attr( $frame, 'src' );
	if ( '' === $src ) {
		$src = lynbro_a11y_attr( $frame, 'data-src' );
	}
	if ( '' === $src ) {
		return '';
	}
	$host = wp_parse_url( $src, PHP_URL_HOST );
	if ( ! $host ) {
		return '';
	}
	return sprintf( /* translators: %s: host name the frame loads from. */ __( 'The frame loads from %s.', 'lynbro-accessibility-checker' ), $host );
}

/* -------------------------------------------------------------------------
 * ARIA and keyboard
 * ---------------------------------------------------------------------- */

/**
 * References that resolve, roles that exist, focus that is reachable.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_aria( $doc, $xpath ) {
	$out = array();
	$ids = lynbro_a11y_id_map( $xpath );

	foreach ( array( 'aria-labelledby', 'aria-describedby', 'aria-controls', 'aria-owns' ) as $attribute ) {
		foreach ( lynbro_a11y_query( $xpath, sprintf( '//body//*[@%s]', $attribute ) ) as $el ) {
			$value = lynbro_a11y_attr( $el, $attribute );
			if ( '' === $value ) {
				continue;
			}
			$missing = array();
			foreach ( preg_split( '/\s+/', $value ) as $id ) {
				if ( '' !== $id && empty( $ids[ $id ] ) ) {
					$missing[] = $id;
				}
			}
			if ( $missing ) {
				$out[] = lynbro_a11y_finding(
					'aria_reference_broken',
					$el,
					sprintf(
						/* translators: 1: the ARIA attribute, 2: the ids that are missing. */
						__( '%1$s points at "%2$s", which is not on the page.', 'lynbro-accessibility-checker' ),
						$attribute,
						implode( ', ', $missing )
					)
				);
			}
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//*[translate(@aria-hidden,"TRUE","true")="true"]' ) as $hidden ) {
		$focusable = lynbro_a11y_query(
			$xpath,
			'.//a[@href] | .//button | .//input | .//select | .//textarea | .//*[@tabindex]',
			$hidden
		);
		foreach ( $focusable as $el ) {
			if ( '-1' === lynbro_a11y_attr( $el, 'tabindex' ) ) {
				continue;
			}
			if ( $el->hasAttribute( 'disabled' ) ) {
				continue;
			}
			$out[] = lynbro_a11y_finding(
				'aria_hidden_focusable',
				$el,
				sprintf( /* translators: %s: the tag name of the focusable element. */ __( 'A <%s> inside aria-hidden="true" can still be tabbed to.', 'lynbro-accessibility-checker' ), lynbro_a11y_tag( $el ) )
			);
			break; // One finding per hidden container is enough to act on.
		}
	}

	$roles = lynbro_a11y_aria_roles();
	foreach ( lynbro_a11y_query( $xpath, '//body//*[@role]' ) as $el ) {
		foreach ( preg_split( '/\s+/', strtolower( lynbro_a11y_attr( $el, 'role' ) ) ) as $role ) {
			if ( '' !== $role && ! in_array( $role, $roles, true ) ) {
				$out[] = lynbro_a11y_finding(
					'aria_role_invalid',
					$el,
					sprintf( /* translators: %s: the role value found. */ __( 'role="%s" is not an ARIA role.', 'lynbro-accessibility-checker' ), $role )
				);
				break;
			}
		}
	}

	foreach ( lynbro_a11y_query( $xpath, '//body//*[@tabindex]' ) as $el ) {
		$tabindex = lynbro_a11y_attr( $el, 'tabindex' );
		if ( is_numeric( $tabindex ) && (int) $tabindex > 0 ) {
			$out[] = lynbro_a11y_finding(
				'tabindex_positive',
				$el,
				sprintf( /* translators: %s: the tabindex value found. */ __( 'tabindex="%s".', 'lynbro-accessibility-checker' ), $tabindex )
			);
		}
	}

	return $out;
}

/**
 * The ARIA roles a browser recognises.
 *
 * @return array<int,string>
 */
function lynbro_a11y_aria_roles() {
	return array(
		'alert', 'alertdialog', 'application', 'article', 'associationlist', 'associationlistitemkey',
		'associationlistitemvalue', 'banner', 'blockquote', 'button', 'caption', 'cell', 'checkbox',
		'code', 'columnheader', 'combobox', 'command', 'comment', 'complementary', 'composite',
		'contentinfo', 'definition', 'deletion', 'dialog', 'directory', 'document', 'emphasis',
		'feed', 'figure', 'form', 'generic', 'grid', 'gridcell', 'group', 'heading', 'image', 'img',
		'input', 'insertion', 'landmark', 'link', 'list', 'listbox', 'listitem', 'log', 'main',
		'mark', 'marquee', 'math', 'menu', 'menubar', 'menuitem', 'menuitemcheckbox', 'menuitemradio',
		'meter', 'navigation', 'none', 'note', 'option', 'paragraph', 'presentation', 'progressbar',
		'radio', 'radiogroup', 'range', 'region', 'roletype', 'row', 'rowgroup', 'rowheader',
		'scrollbar', 'search', 'searchbox', 'section', 'sectionhead', 'select', 'separator', 'slider',
		'spinbutton', 'status', 'strong', 'structure', 'subscript', 'suggestion', 'superscript',
		'switch', 'tab', 'table', 'tablist', 'tabpanel', 'term', 'textbox', 'time', 'timer',
		'toolbar', 'tooltip', 'tree', 'treegrid', 'treeitem', 'widget', 'window',
	);
}

/* -------------------------------------------------------------------------
 * Presentation
 * ---------------------------------------------------------------------- */

/**
 * Movement nobody can stop, and contrast the markup itself gets wrong.
 *
 * @param DOMDocument $doc   Parsed page.
 * @param DOMXPath    $xpath Bound XPath.
 * @return array<int,array<string,mixed>>
 */
function lynbro_a11y_check_presentation( $doc, $xpath ) {
	$out = array();

	foreach ( lynbro_a11y_query( $xpath, '//body//blink | //body//marquee' ) as $el ) {
		$out[] = lynbro_a11y_finding(
			'blink_or_marquee',
			$el,
			sprintf( /* translators: %s: the tag name found. */ __( 'A <%s> element.', 'lynbro-accessibility-checker' ), lynbro_a11y_tag( $el ) )
		);
	}

	// Inline colour pairs. Only where BOTH colours are set on the same element:
	// anything else needs the stylesheet, and that is what the browser check is
	// for. Reporting a guess here would be the overlay's mistake in miniature.
	foreach ( lynbro_a11y_query( $xpath, '//body//*[@style]' ) as $el ) {
		if ( lynbro_a11y_is_hidden( $el ) ) {
			continue;
		}
		$style = lynbro_a11y_parse_style( lynbro_a11y_attr( $el, 'style' ) );
		if ( ! isset( $style['color'] ) ) {
			continue;
		}
		$background = isset( $style['background-color'] ) ? $style['background-color'] : ( isset( $style['background'] ) ? $style['background'] : '' );
		if ( '' === $background ) {
			continue;
		}

		$foreground_rgb = lynbro_a11y_parse_colour( $style['color'] );
		$background_rgb = lynbro_a11y_parse_colour( $background );
		if ( ! $foreground_rgb || ! $background_rgb ) {
			continue;
		}
		if ( '' === lynbro_a11y_text( $el ) ) {
			continue;
		}

		$ratio    = lynbro_a11y_contrast_ratio( $foreground_rgb, $background_rgb );
		$required = lynbro_a11y_required_contrast( $style );

		if ( $ratio < $required ) {
			$out[] = lynbro_a11y_finding(
				'inline_contrast_low',
				$el,
				sprintf(
					/* translators: 1: measured contrast ratio, 2: required ratio, 3: text colour, 4: background colour. */
					__( 'Contrast is %1$s:1 where %2$s:1 is needed (%3$s on %4$s).', 'lynbro-accessibility-checker' ),
					number_format_i18n( $ratio, 2 ),
					number_format_i18n( $required, 1 ),
					$style['color'],
					$background
				)
			);
		}
	}

	return $out;
}

/**
 * The contrast ratio this text needs, given what the inline style says about
 * its size.
 *
 * WCAG lowers the bar to 3:1 for large text — 24px, or 18.66px when bold.
 * Applying 4.5 to a 40px headline would report a failure that is not one.
 *
 * @param array<string,string> $style Parsed inline declarations.
 * @return float
 */
function lynbro_a11y_required_contrast( $style ) {
	$size_px = 0.0;
	if ( isset( $style['font-size'] ) && preg_match( '/^([0-9.]+)\s*(px|pt|rem|em)?$/', trim( $style['font-size'] ), $m ) ) {
		$value = (float) $m[1];
		$unit  = isset( $m[2] ) ? $m[2] : 'px';
		switch ( $unit ) {
			case 'pt':
				$size_px = $value * ( 96 / 72 );
				break;
			case 'rem':
			case 'em':
				$size_px = $value * 16;
				break;
			default:
				$size_px = $value;
		}
	}

	$weight = isset( $style['font-weight'] ) ? strtolower( trim( $style['font-weight'] ) ) : '';
	$bold   = ( 'bold' === $weight || 'bolder' === $weight || ( is_numeric( $weight ) && (int) $weight >= 700 ) );

	if ( $size_px >= 24 || ( $bold && $size_px >= 18.66 ) ) {
		return 3.0;
	}
	return 4.5;
}

/**
 * The file name when a link points at a PDF or office document, else ''.
 *
 * Judged on the path only, so `prisliste.pdf?v=2` counts and a page called
 * /pdf-guide/ does not.
 *
 * @param string $href Link target.
 * @return string
 */
function lynbro_a11y_linked_document( $href ) {
	$path = (string) wp_parse_url( (string) $href, PHP_URL_PATH );
	if ( '' === $path || ! preg_match( '/\.(pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf)$/i', $path ) ) {
		return '';
	}
	return rawurldecode( basename( $path ) );
}
