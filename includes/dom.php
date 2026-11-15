<?php
/**
 * Reading a page as a document instead of as a string.
 *
 * Everything in checks.php works on a parsed tree. That is a deliberate choice
 * against the obvious alternative, which is a pile of regular expressions: an
 * expression that finds <img> without alt will happily find it inside a comment,
 * inside a <script> string, or inside an attribute of something else, and it
 * cannot answer the question that actually matters most often — "what is the
 * accessible name of this element", which depends on children, on ancestors and
 * on ids somewhere else entirely.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Is the DOM extension available at all?
 *
 * Shared hosts occasionally ship PHP without ext-dom. Rather than fatal, the
 * scanner says so on screen and stops. Silence would look like "no problems".
 *
 * @return bool
 */
function lynbro_a11y_dom_available() {
	return class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' );
}

/**
 * Parse an HTML document.
 *
 * @param string $html Full HTML of a page, or a fragment.
 * @return DOMDocument|null Null when the document could not be parsed at all.
 */
function lynbro_a11y_load_document( $html ) {
	if ( ! lynbro_a11y_dom_available() ) {
		return null;
	}
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return null;
	}

	$previous = libxml_use_internal_errors( true );

	$doc                     = new DOMDocument();
	$doc->preserveWhiteSpace = true;
	$doc->strictErrorChecking = false;

	// libxml assumes ISO-8859-1 for a document with no encoding declaration, and
	// a Danish page then arrives with its æ, ø and å already mangled — which
	// would make our own "the alt text is meaningless" checks compare mush.
	// A meta charset is the documented way to tell it otherwise, and prepending
	// one is safe even when the document already has one: the first wins.
	$prefix = '<?xml encoding="UTF-8">';
	$loaded = $doc->loadHTML( $prefix . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );

	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded ) {
		return null;
	}

	// Remove the processing instruction we just added so it never shows up in a
	// snippet or an XPath result.
	foreach ( iterator_to_array( $doc->childNodes ) as $node ) {
		if ( XML_PI_NODE === $node->nodeType ) {
			$doc->removeChild( $node );
		}
	}

	return $doc;
}

/**
 * An XPath object bound to a document.
 *
 * @param DOMDocument $doc Parsed document.
 * @return DOMXPath
 */
function lynbro_a11y_xpath( $doc ) {
	return new DOMXPath( $doc );
}

/**
 * Elements matching an XPath expression, as a plain array.
 *
 * @param DOMXPath      $xpath   Bound XPath.
 * @param string        $query   Expression.
 * @param DOMNode|null  $context Node to search inside, for a relative expression.
 * @return array<int,DOMElement>
 */
function lynbro_a11y_query( $xpath, $query, $context = null ) {
	$out  = array();
	$list = $context ? $xpath->query( $query, $context ) : $xpath->query( $query );
	if ( ! $list ) {
		return $out;
	}
	foreach ( $list as $node ) {
		if ( $node instanceof DOMElement ) {
			$out[] = $node;
		}
	}
	return $out;
}

/**
 * Lower-cased tag name.
 *
 * @param DOMNode $node Any node.
 * @return string
 */
function lynbro_a11y_tag( $node ) {
	return $node instanceof DOMElement ? strtolower( $node->tagName ) : '';
}

/**
 * An attribute, trimmed, or an empty string.
 *
 * @param DOMElement $el   Element.
 * @param string     $name Attribute name.
 * @return string
 */
function lynbro_a11y_attr( $el, $name ) {
	return $el->hasAttribute( $name ) ? trim( $el->getAttribute( $name ) ) : '';
}

/**
 * Text content with runs of whitespace collapsed.
 *
 * @param DOMNode $node Node to read.
 * @return string
 */
function lynbro_a11y_text( $node ) {
	$text = preg_replace( '/\s+/u', ' ', (string) $node->textContent );
	return trim( (string) $text );
}

/**
 * Does this class list hide the element at every screen size?
 *
 * Only classes whose meaning does not depend on the theme's stylesheet:
 * Bootstrap `d-none` and Tailwind `hidden` (unless a breakpoint class shows it
 * again), Elementor's three `elementor-hidden-*` device classes together, and a
 * few utility names. A class that hides only on one device is not here: the
 * element is real on the others.
 *
 * @param string $class_attr The class attribute.
 * @return bool
 */
function lynbro_a11y_class_hides( $class_attr ) {
	if ( '' === $class_attr ) {
		return false;
	}
	$tokens = preg_split( '/\s+/', strtolower( $class_attr ), -1, PREG_SPLIT_NO_EMPTY );
	$tokens = array_flip( $tokens );
	$shown  = false;
	foreach ( $tokens as $token => $unused ) {
		if ( preg_match( '/^(?:d-(?:sm|md|lg|xl|xxl)-(?!none)|(?:sm|md|lg|xl|2xl):(?:block|flex|grid|inline|table))/', (string) $token ) ) {
			$shown = true;
		}
	}
	if ( ! $shown && ( isset( $tokens['d-none'] ) || isset( $tokens['hidden'] ) ) ) {
		return true;
	}
	if ( isset( $tokens['elementor-hidden-desktop'], $tokens['elementor-hidden-tablet'], $tokens['elementor-hidden-mobile'] ) ) {
		return true;
	}
	$names = (array) apply_filters( 'lynbro_a11y_hidden_classes', array( 'is-hidden', 'u-hidden', 'screen-hidden', 'elementor-hidden' ) );
	foreach ( $names as $name ) {
		if ( isset( $tokens[ strtolower( (string) $name ) ] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Is this element hidden from everybody?
 *
 * The cases the markup can settle on its own: attributes, inline styles and the
 * class names in `lynbro_a11y_class_hides()`. A rule that lives only in a
 * stylesheet is invisible to us here (the browser check sees it), and
 * pretending otherwise would mean reporting things that are not on the page.
 *
 * @param DOMElement $el Element.
 * @return bool
 */
function lynbro_a11y_is_hidden( $el ) {
	for ( $node = $el; $node instanceof DOMElement; $node = $node->parentNode ) {
		if ( $node->hasAttribute( 'hidden' ) ) {
			return true;
		}
		if ( 'true' === strtolower( lynbro_a11y_attr( $node, 'aria-hidden' ) ) ) {
			return true;
		}
		$style = strtolower( lynbro_a11y_attr( $node, 'style' ) );
		if ( '' !== $style && preg_match( '/display\s*:\s*none|visibility\s*:\s*hidden/', $style ) ) {
			return true;
		}
		if ( lynbro_a11y_class_hides( lynbro_a11y_attr( $node, 'class' ) ) ) {
			return true;
		}
		if ( 'template' === lynbro_a11y_tag( $node ) || 'script' === lynbro_a11y_tag( $node ) || 'noscript' === lynbro_a11y_tag( $node ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Elements whose ids exist in the document, as a lookup.
 *
 * @param DOMXPath $xpath Bound XPath.
 * @return array<string,int> id => how many elements carry it.
 */
function lynbro_a11y_id_map( $xpath ) {
	$map = array();
	foreach ( lynbro_a11y_query( $xpath, '//*[@id]' ) as $el ) {
		$id = lynbro_a11y_attr( $el, 'id' );
		if ( '' === $id ) {
			continue;
		}
		$map[ $id ] = isset( $map[ $id ] ) ? $map[ $id ] + 1 : 1;
	}
	return $map;
}

/**
 * The accessible name of an element, as far as markup alone can tell.
 *
 * This follows the order browsers use — aria-labelledby, then aria-label, then
 * the element's own content, then title — but stops short of the full
 * accessible name computation, which needs CSS. Where it cannot be sure it
 * returns what it found and lets the calling check decide.
 *
 * @param DOMElement  $el    Element.
 * @param DOMXPath    $xpath Bound XPath, for resolving id references.
 * @return string
 */
function lynbro_a11y_accessible_name( $el, $xpath ) {
	$labelledby = lynbro_a11y_attr( $el, 'aria-labelledby' );
	if ( '' !== $labelledby ) {
		$parts = array();
		foreach ( preg_split( '/\s+/', $labelledby ) as $id ) {
			$target = lynbro_a11y_element_by_id( $xpath, $id );
			if ( $target ) {
				$parts[] = lynbro_a11y_text( $target );
			}
		}
		$joined = trim( implode( ' ', array_filter( $parts ) ) );
		if ( '' !== $joined ) {
			return $joined;
		}
	}

	$label = lynbro_a11y_attr( $el, 'aria-label' );
	if ( '' !== $label ) {
		return $label;
	}

	$tag = lynbro_a11y_tag( $el );

	if ( 'input' === $tag ) {
		$type = strtolower( lynbro_a11y_attr( $el, 'type' ) );
		if ( in_array( $type, array( 'submit', 'reset', 'button' ), true ) ) {
			$value = lynbro_a11y_attr( $el, 'value' );
			if ( '' !== $value ) {
				return $value;
			}
		}
		if ( 'image' === $type ) {
			$alt = lynbro_a11y_attr( $el, 'alt' );
			if ( '' !== $alt ) {
				return $alt;
			}
		}
		$associated = lynbro_a11y_label_for( $el, $xpath );
		if ( '' !== $associated ) {
			return $associated;
		}
	}

	if ( in_array( $tag, array( 'select', 'textarea' ), true ) ) {
		$associated = lynbro_a11y_label_for( $el, $xpath );
		if ( '' !== $associated ) {
			return $associated;
		}
	}

	// Content: the element's text, and failing that whatever an image, a
	// labelled child or an SVG title inside it contributes. Browsers do the
	// same walk; this is the part of it markup alone can answer.
	$text = lynbro_a11y_text( $el );
	if ( '' === $text ) {
		foreach ( lynbro_a11y_query( $xpath, './/img | .//*[@aria-label] | .//svg/*[local-name()="title"]', $el ) as $child ) {
			$candidate = 'title' === lynbro_a11y_tag( $child )
				? lynbro_a11y_text( $child )
				: trim( lynbro_a11y_attr( $child, 'alt' ) . ' ' . lynbro_a11y_attr( $child, 'aria-label' ) );
			$candidate = trim( $candidate );
			if ( '' !== $candidate ) {
				$text = $candidate;
				break;
			}
		}
	}
	if ( '' !== $text ) {
		return $text;
	}

	return lynbro_a11y_attr( $el, 'title' );
}

/**
 * The first element carrying an id.
 *
 * @param DOMXPath $xpath Bound XPath.
 * @param string   $id    Id to find.
 * @return DOMElement|null
 */
function lynbro_a11y_element_by_id( $xpath, $id ) {
	$id = trim( (string) $id );
	if ( '' === $id ) {
		return null;
	}
	$found = lynbro_a11y_query( $xpath, sprintf( '//*[@id=%s]', lynbro_a11y_xpath_literal( $id ) ) );
	return $found ? $found[0] : null;
}

/**
 * The text of a <label> associated with a field, by for= or by wrapping.
 *
 * @param DOMElement $el    Form control.
 * @param DOMXPath   $xpath Bound XPath.
 * @return string
 */
function lynbro_a11y_label_for( $el, $xpath ) {
	$id = lynbro_a11y_attr( $el, 'id' );
	if ( '' !== $id ) {
		$labels = lynbro_a11y_query( $xpath, sprintf( '//label[@for=%s]', lynbro_a11y_xpath_literal( $id ) ) );
		if ( $labels ) {
			$text = lynbro_a11y_text( $labels[0] );
			if ( '' !== $text ) {
				return $text;
			}
		}
	}

	for ( $node = $el->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		if ( 'label' === lynbro_a11y_tag( $node ) ) {
			// The field's own value must not count as its label.
			$clone = $node->cloneNode( true );
			foreach ( iterator_to_array( $clone->getElementsByTagName( 'input' ) ) as $inner ) {
				$inner->parentNode->removeChild( $inner );
			}
			$text = lynbro_a11y_text( $clone );
			if ( '' !== $text ) {
				return $text;
			}
		}
	}

	return '';
}

/**
 * Quote a string for use inside an XPath expression.
 *
 * XPath 1.0 has no escape character, so a value containing both quote marks has
 * to be assembled with concat(). Without this, an id containing an apostrophe
 * turns a query into a syntax error — and a silent zero-result query looks
 * exactly like "no problem here".
 *
 * @param string $value Raw value.
 * @return string Quoted expression.
 */
function lynbro_a11y_xpath_literal( $value ) {
	$value = (string) $value;
	if ( false === strpos( $value, "'" ) ) {
		return "'" . $value . "'";
	}
	if ( false === strpos( $value, '"' ) ) {
		return '"' . $value . '"';
	}
	$parts = explode( "'", $value );
	$out   = array();
	foreach ( $parts as $i => $part ) {
		if ( $i > 0 ) {
			$out[] = '"\'"';
		}
		if ( '' !== $part ) {
			$out[] = "'" . $part . "'";
		}
	}
	return 'concat(' . implode( ',', $out ) . ')';
}

/**
 * A short CSS-like path to an element, so a person can find it on the page.
 *
 * An id short-circuits the whole path, because an id is the shortest true
 * answer. Otherwise we walk up at most five ancestors: a full path from <html>
 * is accurate and unreadable, and nobody scrolls a selector.
 *
 * @param DOMElement $el Element.
 * @return string
 */
function lynbro_a11y_selector( $el ) {
	$parts = array();
	$depth = 0;

	for ( $node = $el; $node instanceof DOMElement && $depth < 5; $node = $node->parentNode, $depth++ ) {
		$tag = lynbro_a11y_tag( $node );
		if ( '' === $tag || 'html' === $tag ) {
			break;
		}

		$id = lynbro_a11y_attr( $node, 'id' );
		if ( '' !== $id && preg_match( '/^[A-Za-z][\w:.-]*$/', $id ) ) {
			array_unshift( $parts, '#' . $id );
			return implode( ' > ', $parts );
		}

		$piece = $tag;
		$class = lynbro_a11y_attr( $node, 'class' );
		if ( '' !== $class ) {
			$first = strtok( $class, " \t\n" );
			if ( $first && preg_match( '/^[A-Za-z][\w-]*$/', $first ) ) {
				$piece .= '.' . $first;
			}
		}

		$index = lynbro_a11y_sibling_index( $node );
		if ( $index > 1 ) {
			$piece .= ':nth-of-type(' . $index . ')';
		}

		array_unshift( $parts, $piece );

		if ( 'body' === $tag ) {
			break;
		}
	}

	return implode( ' > ', $parts );
}

/**
 * Position of an element among siblings of the same tag, one-based.
 *
 * @param DOMElement $el Element.
 * @return int
 */
function lynbro_a11y_sibling_index( $el ) {
	$index = 1;
	$tag   = lynbro_a11y_tag( $el );
	for ( $node = $el->previousSibling; $node; $node = $node->previousSibling ) {
		if ( $node instanceof DOMElement && lynbro_a11y_tag( $node ) === $tag ) {
			$index++;
		}
	}
	return $index;
}

/**
 * The element's own markup, shortened for display.
 *
 * The opening tag alone is usually what identifies the problem; the children
 * are noise in a report table. When the element has no children worth showing
 * we print it whole.
 *
 * @param DOMElement $el     Element.
 * @param int        $length Maximum characters.
 * @return string
 */
function lynbro_a11y_snippet( $el, $length = 220 ) {
	$html = $el->ownerDocument->saveHTML( $el );
	$html = preg_replace( '/\s+/u', ' ', (string) $html );
	$html = trim( (string) $html );

	if ( function_exists( 'mb_strlen' ) ? mb_strlen( $html ) <= $length : strlen( $html ) <= $length ) {
		return $html;
	}

	// Cut at the end of the opening tag when that fits; a truncated attribute is
	// worse than no attribute.
	$close = strpos( $html, '>' );
	if ( false !== $close && $close < $length ) {
		return substr( $html, 0, $close + 1 ) . ' …';
	}

	return ( function_exists( 'mb_substr' ) ? mb_substr( $html, 0, $length ) : substr( $html, 0, $length ) ) . ' …';
}

/* -------------------------------------------------------------------------
 * Colour, for the contrast checks that can be settled without a browser.
 * ---------------------------------------------------------------------- */

/**
 * Parse a CSS colour into RGB.
 *
 * Handles the notations that actually turn up in inline styles: #rgb, #rrggbb,
 * rgb(), rgba() and the sixteen named colours everyone still uses. Anything
 * else returns null, and the check that asked simply does not fire — a wrong
 * contrast figure is worse than no figure.
 *
 * @param string $value CSS colour.
 * @return array{0:int,1:int,2:int}|null
 */
function lynbro_a11y_parse_colour( $value ) {
	$value = strtolower( trim( (string) $value ) );
	if ( '' === $value ) {
		return null;
	}

	$named = array(
		'black'   => array( 0, 0, 0 ),
		'silver'  => array( 192, 192, 192 ),
		'gray'    => array( 128, 128, 128 ),
		'grey'    => array( 128, 128, 128 ),
		'white'   => array( 255, 255, 255 ),
		'maroon'  => array( 128, 0, 0 ),
		'red'     => array( 255, 0, 0 ),
		'purple'  => array( 128, 0, 128 ),
		'fuchsia' => array( 255, 0, 255 ),
		'green'   => array( 0, 128, 0 ),
		'lime'    => array( 0, 255, 0 ),
		'olive'   => array( 128, 128, 0 ),
		'yellow'  => array( 255, 255, 0 ),
		'navy'    => array( 0, 0, 128 ),
		'blue'    => array( 0, 0, 255 ),
		'teal'    => array( 0, 128, 128 ),
		'aqua'    => array( 0, 255, 255 ),
	);
	if ( isset( $named[ $value ] ) ) {
		return $named[ $value ];
	}

	if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
		return array(
			hexdec( str_repeat( $m[1][0], 2 ) ),
			hexdec( str_repeat( $m[1][1], 2 ) ),
			hexdec( str_repeat( $m[1][2], 2 ) ),
		);
	}
	if ( preg_match( '/^#([0-9a-f]{6})$/', $value, $m ) ) {
		return array(
			hexdec( substr( $m[1], 0, 2 ) ),
			hexdec( substr( $m[1], 2, 2 ) ),
			hexdec( substr( $m[1], 4, 2 ) ),
		);
	}
	if ( preg_match( '/^rgba?\(\s*([0-9]{1,3})\s*[, ]\s*([0-9]{1,3})\s*[, ]\s*([0-9]{1,3})/', $value, $m ) ) {
		return array(
			min( 255, (int) $m[1] ),
			min( 255, (int) $m[2] ),
			min( 255, (int) $m[3] ),
		);
	}

	return null;
}

/**
 * Relative luminance, per the WCAG definition.
 *
 * @param array{0:int,1:int,2:int} $rgb Colour channels, 0-255.
 * @return float
 */
function lynbro_a11y_luminance( $rgb ) {
	$channels = array();
	foreach ( $rgb as $value ) {
		$c            = $value / 255;
		$channels[]   = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

/**
 * Contrast ratio between two colours, 1.0 to 21.0.
 *
 * @param array{0:int,1:int,2:int} $a First colour.
 * @param array{0:int,1:int,2:int} $b Second colour.
 * @return float
 */
function lynbro_a11y_contrast_ratio( $a, $b ) {
	$la = lynbro_a11y_luminance( $a );
	$lb = lynbro_a11y_luminance( $b );
	$hi = max( $la, $lb );
	$lo = min( $la, $lb );
	return round( ( $hi + 0.05 ) / ( $lo + 0.05 ), 2 );
}

/**
 * Declarations from an inline style attribute, as property => value.
 *
 * @param string $style Value of a style attribute.
 * @return array<string,string>
 */
function lynbro_a11y_parse_style( $style ) {
	$out = array();
	foreach ( explode( ';', (string) $style ) as $declaration ) {
		if ( false === strpos( $declaration, ':' ) ) {
			continue;
		}
		list( $property, $value ) = explode( ':', $declaration, 2 );
		$property                 = strtolower( trim( $property ) );
		if ( '' !== $property ) {
			$out[ $property ] = trim( $value );
		}
	}
	return $out;
}
