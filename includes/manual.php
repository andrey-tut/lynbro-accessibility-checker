<?php
/**
 * The checks no scanner can make.
 *
 * This file exists because of a number the accessibility industry prefers not
 * to print: automated testing reaches roughly a third of WCAG. Everything else
 * needs somebody to look. An overlay that claims otherwise is what the FTC
 * fined accessiBe a million dollars over, and a scanner that reports "no issues
 * found" without saying what it never looked at is telling the same lie more
 * quietly.
 *
 * So the plugin says it out loud, and turns the remainder into a short list of
 * questions with an honest answer stored against each. For a Danish company
 * under the European Accessibility Act that list is the useful half: an
 * inspector asks what you checked and how, and "the plugin said it was fine" is
 * not an answer.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

define( 'LYNBRO_A11Y_MANUAL_OPTION', 'lynbro_a11y_manual' );

/**
 * The manual checklist.
 *
 * Every item is a WCAG 2.2 A or AA criterion that no amount of parsing can
 * settle. The ones marked new in 2.2 are called out, because they are the ones
 * that turned up after most sites were last audited.
 *
 * @return array<string,array<string,string>>
 */
function lynbro_a11y_manual_items() {
	$items = array(
		'captions' => array(
			'wcag'  => '1.2.2',
			'level' => 'A',
			'title' => __( 'Videos have captions, and the captions are right', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Open each video and read the captions along with the audio. Automatic captions are usually wrong about names, prices and Danish place names — which is exactly the part that matters.', 'lynbro-accessibility-checker' ),
		),
		'audio_description' => array(
			'wcag'  => '1.2.5',
			'level' => 'AA',
			'title' => __( 'Anything shown but not said in a video is described', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Listen to the video without looking at it. If something important only appears on screen — a phone number, a form being filled in — it needs describing.', 'lynbro-accessibility-checker' ),
		),
		'reading_order' => array(
			'wcag'  => '1.3.2',
			'level' => 'A',
			'title' => __( 'The reading order matches the visual order', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Turn off the site stylesheet, or read the page in reader mode. If the sidebar arrives in the middle of an article, the source order is wrong.', 'lynbro-accessibility-checker' ),
		),
		'images_of_text' => array(
			'wcag'  => '1.4.5',
			'level' => 'AA',
			'title' => __( 'Text is text, not a picture of text', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Look for banners, price lists and opening hours saved as images. They do not enlarge, do not translate and cannot be read aloud.', 'lynbro-accessibility-checker' ),
		),
		'reflow' => array(
			'wcag'  => '1.4.10',
			'level' => 'AA',
			'title' => __( 'The page works at 320 pixels wide and at 400% zoom', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Zoom the browser to 400% on a desktop. Nothing should need to be scrolled sideways to be read.', 'lynbro-accessibility-checker' ),
		),
		'hover_content' => array(
			'wcag'  => '1.4.13',
			'level' => 'AA',
			'title' => __( 'Tooltips and dropdowns can be dismissed and stay open long enough to use', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Hover over a menu that opens a submenu. Escape should close it, and moving the mouse onto the submenu should not make it vanish.', 'lynbro-accessibility-checker' ),
		),
		'keyboard_trap' => array(
			'wcag'  => '2.1.2',
			'level' => 'A',
			'title' => __( 'The keyboard can always get out again', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Tab through the whole page, including any pop-up, cookie banner or chat widget. If focus goes into something it cannot leave, that page is unusable without a mouse.', 'lynbro-accessibility-checker' ),
		),
		'flashing' => array(
			'wcag'  => '2.3.1',
			'level' => 'A',
			'title' => __( 'Nothing flashes more than three times a second', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Check animated banners and video intros. This one is a seizure risk, not an inconvenience.', 'lynbro-accessibility-checker' ),
		),
		'timing' => array(
			'wcag'  => '2.2.1',
			'level' => 'A',
			'title' => __( 'Any time limit can be turned off or extended', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Booking steps, checkout sessions and carousels that advance on their own all count.', 'lynbro-accessibility-checker' ),
		),
		'headings_describe' => array(
			'wcag'  => '2.4.6',
			'level' => 'AA',
			'title' => __( 'Headings and labels actually describe what follows', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Read only the headings. They should work as a summary of the page on their own.', 'lynbro-accessibility-checker' ),
		),
		'focus_not_obscured' => array(
			'wcag'  => '2.4.11',
			'level' => 'AA',
			'title' => __( 'The focused element is never hidden behind something else', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'New in WCAG 2.2. Tab down a long page with a sticky header: the focused link must not disappear underneath it.', 'lynbro-accessibility-checker' ),
		),
		'dragging' => array(
			'wcag'  => '2.5.7',
			'level' => 'AA',
			'title' => __( 'Nothing can only be done by dragging', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'New in WCAG 2.2. Sliders, sortable lists and map controls need a way that is a single tap or click instead.', 'lynbro-accessibility-checker' ),
		),
		'consistent_navigation' => array(
			'wcag'  => '3.2.3',
			'level' => 'AA',
			'title' => __( 'Navigation is in the same place on every page', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Compare the front page, a product page and the contact page. The menu should not move or change order.', 'lynbro-accessibility-checker' ),
		),
		'consistent_help' => array(
			'wcag'  => '3.2.6',
			'level' => 'A',
			'title' => __( 'Help is in the same place on every page that has it', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'New in WCAG 2.2. If the phone number, chat or contact link is in the footer on one page, it belongs in the footer on all of them.', 'lynbro-accessibility-checker' ),
		),
		'error_identification' => array(
			'wcag'  => '3.3.1',
			'level' => 'A',
			'title' => __( 'Form errors say, in words, what is wrong', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Submit your contact form empty. A red border is not an error message: the text has to name the field and the problem.', 'lynbro-accessibility-checker' ),
		),
		'error_suggestion' => array(
			'wcag'  => '3.3.3',
			'level' => 'AA',
			'title' => __( 'Form errors say how to put it right', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'Type an invalid postcode. "Invalid" is not enough — "A Danish postcode is four digits" is.', 'lynbro-accessibility-checker' ),
		),
		'redundant_entry' => array(
			'wcag'  => '3.3.7',
			'level' => 'A',
			'title' => __( 'Nothing has to be typed in twice', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'New in WCAG 2.2. If a checkout asks for the delivery address and then the billing address, there must be a way to copy it rather than retype it.', 'lynbro-accessibility-checker' ),
		),
		'accessible_auth' => array(
			'wcag'  => '3.3.8',
			'level' => 'AA',
			'title' => __( 'Logging in does not require solving a puzzle from memory', 'lynbro-accessibility-checker' ),
			'ask'   => __( 'New in WCAG 2.2. A password field must allow pasting, and a CAPTCHA that asks people to recognise objects or transcribe text fails this outright.', 'lynbro-accessibility-checker' ),
		),
	);

	/**
	 * Filter the manual checklist.
	 *
	 * @param array<string,array<string,string>> $items Checklist items.
	 */
	return (array) apply_filters( 'lynbro_a11y_manual_items', $items );
}

/**
 * The stored answers.
 *
 * @return array<string,array<string,mixed>>
 */
function lynbro_a11y_manual_state() {
	$state = get_option( LYNBRO_A11Y_MANUAL_OPTION, array() );
	return is_array( $state ) ? $state : array();
}

/**
 * The answer for one item.
 *
 * @param string $id Item id.
 * @return array{state:string,note:string,updated:string,user:int}
 */
function lynbro_a11y_manual_answer( $id ) {
	$state = lynbro_a11y_manual_state();
	$row   = isset( $state[ $id ] ) && is_array( $state[ $id ] ) ? $state[ $id ] : array();

	return wp_parse_args(
		$row,
		array(
			'state'   => 'unknown',
			'note'    => '',
			'updated' => '',
			'user'    => 0,
		)
	);
}

/**
 * Record an answer.
 *
 * Who answered and when is stored alongside it. That is not surveillance — it
 * is the difference between a checklist and evidence. An inspector asking how a
 * site was assessed wants a date.
 *
 * @param string $id     Item id.
 * @param string $answer pass | fail | na | unknown.
 * @param string $note   Free text.
 * @return void
 */
function lynbro_a11y_manual_save( $id, $answer, $note = '' ) {
	$items = lynbro_a11y_manual_items();
	if ( ! isset( $items[ $id ] ) ) {
		return;
	}
	if ( ! in_array( $answer, array( 'pass', 'fail', 'na', 'unknown' ), true ) ) {
		$answer = 'unknown';
	}

	$state        = lynbro_a11y_manual_state();
	$state[ $id ] = array(
		'state'   => $answer,
		'note'    => substr( sanitize_textarea_field( $note ), 0, 1000 ),
		'updated' => current_time( 'mysql' ),
		'user'    => get_current_user_id(),
	);

	update_option( LYNBRO_A11Y_MANUAL_OPTION, $state, false );
}

/**
 * How far through the checklist the site is.
 *
 * @return array<string,int>
 */
function lynbro_a11y_manual_progress() {
	$counts = array( 'pass' => 0, 'fail' => 0, 'na' => 0, 'unknown' => 0, 'total' => 0 );

	foreach ( array_keys( lynbro_a11y_manual_items() ) as $id ) {
		$answer = lynbro_a11y_manual_answer( $id );
		$key    = isset( $counts[ $answer['state'] ] ) ? $answer['state'] : 'unknown';
		$counts[ $key ]++;
		$counts['total']++;
	}

	return $counts;
}

/**
 * Human label for an answer.
 *
 * @param string $answer Answer key.
 * @return string
 */
function lynbro_a11y_manual_label( $answer ) {
	switch ( $answer ) {
		case 'pass':
			return __( 'Checked, fine', 'lynbro-accessibility-checker' );
		case 'fail':
			return __( 'Checked, a problem', 'lynbro-accessibility-checker' );
		case 'na':
			return __( 'Does not apply here', 'lynbro-accessibility-checker' );
		default:
			return __( 'Not looked at yet', 'lynbro-accessibility-checker' );
	}
}
