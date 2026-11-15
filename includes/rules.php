<?php
/**
 * The rule catalogue: what we look for, and what we tell people about it.
 *
 * A finding that says "1.1.1 failure" is useless to the person who has to fix
 * it. Every rule here therefore carries four things: what it is, why it matters
 * to a real person, how to fix it, and the WCAG success criterion an auditor
 * will quote. That is the whole difference between a linter and a report a
 * Danish company can hand to Erhvervsstyrelsen.
 *
 * Severity is deliberately three-valued and honest:
 *   error   — a WCAG 2.2 A/AA failure we can prove from the markup.
 *   warning — very likely a failure, but the markup alone cannot settle it.
 *   review  — nothing is wrong yet; a human has to look, and we say at what.
 *
 * We never turn these into a "your site is 87% accessible" number. That number
 * is what the FTC fined accessiBe a million dollars for implying, and it is not
 * a number anybody can produce from an automated scan: automation reaches
 * roughly a third of WCAG. The counts are the counts.
 *
 * @package LynbroAccessibility
 */

defined( 'ABSPATH' ) || exit;

/**
 * Every rule, keyed by id.
 *
 * Built once per request. Pass $refresh after switching locale (the statement
 * is written in the site's language, which may not be the admin's), so the
 * titles are translated again rather than served from the first build.
 *
 * @param bool $refresh Rebuild instead of returning the cached list.
 * @return array<string,array<string,mixed>>
 */
function lynbro_a11y_rules( $refresh = false ) {
	static $rules = null;
	if ( null !== $rules && ! $refresh ) {
		return $rules;
	}

	$rules = array(

		/* ---- Document ----------------------------------------------------- */
		'page_title_missing' => array(
			'severity' => 'error',
			'wcag'     => '2.4.2',
			'level'    => 'A',
			'group'    => 'document',
			'title'    => __( 'The page has no title', 'lynbro-accessibility-checker' ),
			'why'      => __( 'The title is the first thing a screen reader announces and the only thing that distinguishes one open tab from another. Without it people navigate by guesswork.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the page a title. In WordPress that normally means the post or page needs a name, or the theme is not printing wp_head().', 'lynbro-accessibility-checker' ),
		),
		'html_lang_missing' => array(
			'severity' => 'error',
			'wcag'     => '3.1.1',
			'level'    => 'A',
			'group'    => 'document',
			'title'    => __( 'The page does not say what language it is in', 'lynbro-accessibility-checker' ),
			'why'      => __( 'A screen reader picks its pronunciation from the language attribute. Danish text read aloud with English pronunciation is close to unusable.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'The <html> element needs a lang attribute. Themes get this from language_attributes(); if yours hard-codes the tag, the fix on the Fixes screen can set it for you.', 'lynbro-accessibility-checker' ),
		),
		'viewport_zoom_blocked' => array(
			'severity' => 'error',
			'wcag'     => '1.4.4',
			'level'    => 'AA',
			'group'    => 'document',
			'title'    => __( 'Zoom is switched off on phones', 'lynbro-accessibility-checker' ),
			'why'      => __( 'user-scalable=no and a maximum-scale below 2 stop someone with low vision from pinching to enlarge the text. On a phone that is the difference between reading the page and leaving it.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Remove user-scalable=no and any maximum-scale below 2 from the viewport meta tag, or switch on the fix that strips them.', 'lynbro-accessibility-checker' ),
		),
		'main_landmark_missing' => array(
			'severity' => 'warning',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'document',
			'title'    => __( 'The page has no main region', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Screen reader users jump straight to the main content instead of listening to the menu on every page. With no <main> there is nothing to jump to.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Wrap the content area of the theme template in <main> (or add role="main" to the element that already holds it).', 'lynbro-accessibility-checker' ),
		),
		'skip_link_missing' => array(
			'severity' => 'warning',
			'wcag'     => '2.4.1',
			'level'    => 'A',
			'group'    => 'document',
			'title'    => __( 'There is no skip link', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Someone using only a keyboard has to tab through the whole menu on every single page before reaching the content. A skip link makes that one keystroke.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add a link to the main content as the first focusable element on the page, or switch on the skip link fix.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Headings ------------------------------------------------------ */
		'heading_missing_h1' => array(
			'severity' => 'warning',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'headings',
			'title'    => __( 'The page has no first-level heading', 'lynbro-accessibility-checker' ),
			'why'      => __( 'The h1 is what tells a screen reader user what this page is, in one line, before anything else.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the page one h1 — usually the page title in the theme template.', 'lynbro-accessibility-checker' ),
		),
		'heading_multiple_h1' => array(
			'severity' => 'warning',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'headings',
			'title'    => __( 'The page has more than one first-level heading', 'lynbro-accessibility-checker' ),
			'why'      => __( 'More than one h1 means the page claims to be about several things at once, and the outline people navigate by stops making sense.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Keep one h1 and demote the others to h2. Page builders often set a section title to h1 by accident.', 'lynbro-accessibility-checker' ),
		),
		'heading_order_skipped' => array(
			'severity' => 'error',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'headings',
			'title'    => __( 'A heading level was skipped', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Heading levels are the table of contents a screen reader reads out. Jumping from h2 to h4 suggests a missing section, and people lose their place.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Change the heading to the next level down from the one above it. Headings mark structure, not size — use CSS for size.', 'lynbro-accessibility-checker' ),
		),
		'heading_empty' => array(
			'severity' => 'error',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'headings',
			'title'    => __( 'A heading is empty', 'lynbro-accessibility-checker' ),
			'why'      => __( 'An empty heading is announced as a heading with nothing in it — a signpost pointing at nothing.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the heading text, or remove it. Empty headings are usually left behind by a page builder used for spacing.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Images -------------------------------------------------------- */
		'img_alt_missing' => array(
			'severity' => 'error',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'An image has no alt attribute', 'lynbro-accessibility-checker' ),
			'why'      => __( 'With no alt attribute a screen reader falls back to reading the file name aloud. "IMG underscore four seven two dot jpeg" is what your customer hears.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add alt text describing what the image does on this page. If it is purely decorative, alt="" is correct — but the attribute must still be there.', 'lynbro-accessibility-checker' ),
		),
		'img_alt_redundant' => array(
			'severity' => 'warning',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'The alt text says nothing useful', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Alt text like "image", "photo", "billede" or the file name carries no information. It passes an automated check and tells a blind visitor nothing.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Describe what the image shows or what it is for. If it adds nothing, mark it decorative with alt="".', 'lynbro-accessibility-checker' ),
		),
		'img_alt_long' => array(
			'severity' => 'review',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'The alt text is very long', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Alt text is read out in one uninterrupted breath. Past roughly 150 characters people stop being able to hold it.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Shorten the alt text to the essentials and put the detail in the page text or a caption, where everyone can read it.', 'lynbro-accessibility-checker' ),
		),
		'img_linked_alt_empty' => array(
			'severity' => 'error',
			'wcag'     => '2.4.4',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'A link contains only an image with no alt text', 'lynbro-accessibility-checker' ),
			'why'      => __( 'The image is the only thing in the link, so its alt text is the link text. Empty, and the link is announced as "link" with no destination.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the image alt text that says where the link goes — not what the picture looks like.', 'lynbro-accessibility-checker' ),
		),
		'input_image_alt_missing' => array(
			'severity' => 'error',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'An image button has no alt text', 'lynbro-accessibility-checker' ),
			'why'      => __( 'An <input type="image"> is a button. Without alt text nobody using a screen reader knows what pressing it does.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add an alt attribute describing the action, for example alt="Search".', 'lynbro-accessibility-checker' ),
		),
		'svg_no_name' => array(
			'severity' => 'warning',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'An inline SVG has no accessible name', 'lynbro-accessibility-checker' ),
			'why'      => __( 'An SVG with no title and no label is either announced as nothing or, in some browsers, as a meaningless "graphic".', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add aria-hidden="true" if the icon is decorative and sits next to text; otherwise give it role="img" and an aria-label.', 'lynbro-accessibility-checker' ),
		),
		'area_alt_missing' => array(
			'severity' => 'error',
			'wcag'     => '1.1.1',
			'level'    => 'A',
			'group'    => 'images',
			'title'    => __( 'An image map region has no alt text', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Each clickable region of an image map is a link. Without alt text the whole map is unusable without sight.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give every <area> an alt attribute describing where it leads.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Links and buttons ---------------------------------------------- */
		'link_empty' => array(
			'severity' => 'error',
			'wcag'     => '2.4.4',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'A link has no text', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Screen reader users list the links on a page to navigate it. A link with no text appears in that list as a blank line.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Put text in the link, or give it an aria-label. Icon-only links need a label even when the icon is obvious to sighted people.', 'lynbro-accessibility-checker' ),
		),
		'link_text_ambiguous' => array(
			'severity' => 'warning',
			'wcag'     => '2.4.4',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'The link text does not say where it goes', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Out of context — and a link list is exactly that — "read more", "click here" and "læs mere" all lead nowhere in particular.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Name the destination in the link: "Read more about opening hours". Same length, actually useful.', 'lynbro-accessibility-checker' ),
		),
		'link_text_is_url' => array(
			'severity' => 'warning',
			'wcag'     => '2.4.4',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'The link text is a raw address', 'lynbro-accessibility-checker' ),
			'why'      => __( 'A screen reader reads a bare URL character by character. Nobody listens to that to the end.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Replace the address with what it leads to: "Our price list" instead of https://…/prices-2026-final.pdf.', 'lynbro-accessibility-checker' ),
		),
		'link_new_window' => array(
			// Level AAA, so it is not a failure of the AA target the Act works to.
			// It is still here, and still worth acting on, because it is one of the
			// most common complaints screen reader users make — but reporting it as
			// a failure would inflate the count with something no auditor will
			// raise, and an inflated count is how a report loses its authority.
			'severity' => 'review',
			'wcag'     => '3.2.5',
			'level'    => 'AAA',
			'group'    => 'links',
			'title'    => __( 'A link opens a new window without warning', 'lynbro-accessibility-checker' ),
			'why'      => __( 'A new tab moves the keyboard focus somewhere else and breaks the back button. Sighted people notice; screen reader users often do not. This is a level AAA criterion, so it is not required for the AA conformance the European Accessibility Act works to — it is here because it is cheap to fix and people notice.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Say so in the link text, or switch on the fix that appends "(opens in a new window)" for screen readers.', 'lynbro-accessibility-checker' ),
		),
		'link_to_document' => array(
			// A review, not a failure: the scanner cannot open the file, and plenty
			// of PDFs are properly tagged. But a price list or terms PDF is part of
			// the page's content, it is where most small Danish sites quietly fail,
			// and nobody remembers to test it unless something says so.
			'severity' => 'review',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'A link leads to a document that needs its own check', 'lynbro-accessibility-checker' ),
			'why'      => __( 'A PDF or Office file linked from the page is part of its content, and the same rules apply to it. Scanned or untagged PDFs have no headings, no reading order and no text a screen reader can find.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Open the document and check that it has real text, headings and a title (in Acrobat: Accessibility check; in Word: Check Accessibility before saving as PDF). Where the information fits on a web page, put it there instead.', 'lynbro-accessibility-checker' ),
		),
		'button_empty' => array(
			'severity' => 'error',
			'wcag'     => '4.1.2',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'A button has no accessible name', 'lynbro-accessibility-checker' ),
			'why'      => __( 'A button announced only as "button" gives no reason to press it — and it is usually the one that submits the form.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Put text in the button, or give it an aria-label. For <input type="submit"> use the value attribute.', 'lynbro-accessibility-checker' ),
		),
		'anchor_without_href' => array(
			'severity' => 'warning',
			'wcag'     => '2.1.1',
			'level'    => 'A',
			'group'    => 'links',
			'title'    => __( 'An <a> element is used as a control but cannot be reached by keyboard', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Without href an anchor is not focusable. Anyone who cannot use a mouse cannot reach it at all.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Use a <button> for something that acts on the page, and keep <a href> for going somewhere.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Forms ---------------------------------------------------------- */
		'form_label_missing' => array(
			'severity' => 'error',
			'wcag'     => '3.3.2',
			'level'    => 'A',
			'group'    => 'forms',
			'title'    => __( 'A form field has no label', 'lynbro-accessibility-checker' ),
			'why'      => __( 'An unlabelled field is announced as "edit text, blank". This is the single most common reason a contact form never gets filled in.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add a <label for="…"> pointing at the field id, or give the field an aria-label.', 'lynbro-accessibility-checker' ),
		),
		'form_placeholder_as_label' => array(
			'severity' => 'warning',
			'wcag'     => '3.3.2',
			'level'    => 'A',
			'group'    => 'forms',
			'title'    => __( 'A field uses its placeholder instead of a label', 'lynbro-accessibility-checker' ),
			'why'      => __( 'The placeholder disappears the moment someone starts typing, so anyone who is interrupted has lost the question. It is also usually too faint to read.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add a real label. Keep the placeholder for an example of the format, not for the question itself.', 'lynbro-accessibility-checker' ),
		),
		'form_label_orphaned' => array(
			'severity' => 'error',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'forms',
			'title'    => __( 'A label points at a field that does not exist', 'lynbro-accessibility-checker' ),
			'why'      => __( 'The label looks right on screen and is connected to nothing. Clicking it does not focus the field, and screen readers do not pair them.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Make the label\'s for attribute match the id of its field exactly.', 'lynbro-accessibility-checker' ),
		),
		'form_autocomplete_missing' => array(
			'severity' => 'warning',
			'wcag'     => '1.3.5',
			'level'    => 'AA',
			'group'    => 'forms',
			'title'    => __( 'A field asking for personal details has no autocomplete', 'lynbro-accessibility-checker' ),
			'why'      => __( 'autocomplete lets the browser fill in a name, e-mail or phone number. For someone with a motor impairment that is the difference between a form they can complete and one they cannot.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add the matching autocomplete value, for example autocomplete="email", "name" or "tel".', 'lynbro-accessibility-checker' ),
		),
		'duplicate_id' => array(
			'severity' => 'warning',
			'wcag'     => '4.1.1',
			'level'    => 'A',
			'group'    => 'forms',
			'title'    => __( 'The same id is used more than once', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Labels, aria-labelledby and skip links all find their target by id. With duplicates only the first one is ever found.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Make every id on the page unique. Duplicated ids usually come from the same block or widget being placed twice.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Tables ---------------------------------------------------------- */
		'table_header_missing' => array(
			'severity' => 'error',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'tables',
			'title'    => __( 'A data table has no header cells', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Header cells are what let a screen reader say "Price: 249 kroner" instead of reading a wall of loose numbers.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Mark the header row with <th> cells and give each one scope="col" or scope="row".', 'lynbro-accessibility-checker' ),
		),
		'table_header_empty' => array(
			'severity' => 'warning',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'tables',
			'title'    => __( 'A table header cell is empty', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Every cell in that column will be announced with nothing in front of it, which is the same as having no header at all.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the header cell text. If the column genuinely has no name, say what it holds — "Action", for example.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Media and frames -------------------------------------------------- */
		'iframe_title_missing' => array(
			'severity' => 'error',
			'wcag'     => '4.1.2',
			'level'    => 'A',
			'group'    => 'media',
			'title'    => __( 'An embedded frame has no title', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Frames appear in a screen reader\'s list of page regions. With no title the entry reads "frame", and there is no way to tell the map from the video.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add a title attribute saying what is inside, for example title="Map of our office". The Fixes screen can do this for common embeds.', 'lynbro-accessibility-checker' ),
		),
		'video_no_captions' => array(
			'severity' => 'review',
			'wcag'     => '1.2.2',
			'level'    => 'A',
			'group'    => 'media',
			'title'    => __( 'A video may have no captions', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Without captions the video is silent for a deaf visitor. This is also the criterion the Danish authority is most likely to ask about first.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Add a <track kind="captions"> to the video, or captions in the platform you host it on. Only you can confirm the captions are correct — automatic ones usually are not.', 'lynbro-accessibility-checker' ),
		),
		'audio_needs_transcript' => array(
			'severity' => 'review',
			'wcag'     => '1.2.1',
			'level'    => 'A',
			'group'    => 'media',
			'title'    => __( 'Audio on this page needs a transcript', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Spoken audio with no written version excludes anyone who cannot hear it — and anyone in an open-plan office.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Publish a transcript on the same page and link to it next to the player.', 'lynbro-accessibility-checker' ),
		),

		/* ---- ARIA and keyboard ------------------------------------------------- */
		'aria_reference_broken' => array(
			'severity' => 'error',
			'wcag'     => '1.3.1',
			'level'    => 'A',
			'group'    => 'aria',
			'title'    => __( 'An ARIA reference points at nothing', 'lynbro-accessibility-checker' ),
			'why'      => __( 'aria-labelledby and aria-describedby name an element by id. When that id is not on the page the name is silently dropped, and the element goes back to having no name.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Correct the id, or remove the attribute and label the element directly.', 'lynbro-accessibility-checker' ),
		),
		'aria_hidden_focusable' => array(
			'severity' => 'error',
			'wcag'     => '4.1.2',
			'level'    => 'A',
			'group'    => 'aria',
			'title'    => __( 'Something hidden from screen readers can still be reached by keyboard', 'lynbro-accessibility-checker' ),
			'why'      => __( 'aria-hidden="true" around a link or button creates a focus stop that is announced as nothing. The keyboard user simply disappears into silence.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Either remove aria-hidden, or take the element out of the tab order as well.', 'lynbro-accessibility-checker' ),
		),
		'aria_role_invalid' => array(
			'severity' => 'warning',
			'wcag'     => '4.1.2',
			'level'    => 'A',
			'group'    => 'aria',
			'title'    => __( 'An element has a role that does not exist', 'lynbro-accessibility-checker' ),
			'why'      => __( 'An unrecognised role is ignored, so the element is announced as whatever it was before — usually a plain box.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Use a role from the ARIA specification, or drop the attribute and use the matching HTML element instead.', 'lynbro-accessibility-checker' ),
		),
		'tabindex_positive' => array(
			'severity' => 'warning',
			'wcag'     => '2.4.3',
			'level'    => 'A',
			'group'    => 'aria',
			'title'    => __( 'A positive tabindex changes the keyboard order', 'lynbro-accessibility-checker' ),
			'why'      => __( 'tabindex="1" and above pull an element to the front of the tab order for the whole page. The result is a keyboard journey that jumps around the screen.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Use tabindex="0" for something that should be focusable in its normal place, and put the markup in the order people should meet it.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Presentation ------------------------------------------------------- */
		'blink_or_marquee' => array(
			'severity' => 'error',
			'wcag'     => '2.2.2',
			'level'    => 'A',
			'group'    => 'presentation',
			'title'    => __( 'Content moves or blinks with no way to stop it', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Anything that moves for more than five seconds without a pause control is a barrier for people with attention or vestibular disorders — and a seizure risk when it blinks.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Remove <blink> and <marquee>. Both were removed from HTML long ago and no longer belong on a live site.', 'lynbro-accessibility-checker' ),
		),
		'inline_contrast_low' => array(
			'severity' => 'error',
			'wcag'     => '1.4.3',
			'level'    => 'AA',
			'group'    => 'presentation',
			'title'    => __( 'Text set in the markup has too little contrast', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Below 4.5:1 the text is hard to read for anyone with reduced vision, and impossible in sunlight on a phone.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Darken the text or lighten the background until the ratio is at least 4.5:1 — or 3:1 for text at 24px, or 19px bold.', 'lynbro-accessibility-checker' ),
		),

		/* ---- Found in the browser, not in the markup --------------------------- */
		'contrast_text_low' => array(
			'severity' => 'error',
			'wcag'     => '1.4.3',
			'level'    => 'AA',
			'group'    => 'browser',
			'title'    => __( 'Text has too little contrast against its background', 'lynbro-accessibility-checker' ),
			'why'      => __( 'This one is measured with the colours the browser actually painted, stylesheet and all. Below 4.5:1 a large share of your visitors over fifty are straining to read it.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Adjust the colours in the theme or the block until the ratio reaches 4.5:1 — 3:1 for large text.', 'lynbro-accessibility-checker' ),
		),
		'focus_not_visible' => array(
			'severity' => 'error',
			'wcag'     => '2.4.7',
			'level'    => 'AA',
			'group'    => 'browser',
			'title'    => __( 'Keyboard focus cannot be seen', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Without a visible focus ring a keyboard user cannot tell where they are on the page. Themes very often remove it on purpose, for looks.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Stop removing the browser outline, or switch on the focus outline fix and pick a colour that suits the design.', 'lynbro-accessibility-checker' ),
		),
		'target_too_small' => array(
			'severity' => 'warning',
			'wcag'     => '2.5.8',
			'level'    => 'AA',
			'group'    => 'browser',
			'title'    => __( 'A control is smaller than 24 by 24 pixels', 'lynbro-accessibility-checker' ),
			'why'      => __( 'Added at AA in WCAG 2.2. A target below 24 by 24 is hard to hit with a tremor, a large finger or a stylus — and this is one of the newest criteria, so it is one the older tools miss.', 'lynbro-accessibility-checker' ),
			'how'      => __( 'Give the control more padding, or leave at least 24 pixels of clear space around it.', 'lynbro-accessibility-checker' ),
		),
	);

	/**
	 * Filter the rule catalogue.
	 *
	 * @param array<string,array<string,mixed>> $rules Rules, keyed by id.
	 */
	$rules = (array) apply_filters( 'lynbro_a11y_rules', $rules );

	return $rules;
}

/**
 * One rule, or an empty array when the id is unknown.
 *
 * @param string $id Rule id.
 * @return array<string,mixed>
 */
function lynbro_a11y_rule( $id ) {
	$rules = lynbro_a11y_rules();
	return isset( $rules[ $id ] ) ? $rules[ $id ] : array();
}

/**
 * Human name for a severity.
 *
 * @param string $severity error | warning | review.
 * @return string
 */
function lynbro_a11y_severity_label( $severity ) {
	switch ( $severity ) {
		case 'error':
			return __( 'Failure', 'lynbro-accessibility-checker' );
		case 'warning':
			return __( 'Likely failure', 'lynbro-accessibility-checker' );
		default:
			return __( 'Needs a person', 'lynbro-accessibility-checker' );
	}
}

/**
 * Human name for a rule group.
 *
 * @param string $group Group key.
 * @return string
 */
function lynbro_a11y_group_label( $group ) {
	$labels = array(
		'document'     => __( 'Page structure', 'lynbro-accessibility-checker' ),
		'headings'     => __( 'Headings', 'lynbro-accessibility-checker' ),
		'images'       => __( 'Images', 'lynbro-accessibility-checker' ),
		'links'        => __( 'Links and buttons', 'lynbro-accessibility-checker' ),
		'forms'        => __( 'Forms', 'lynbro-accessibility-checker' ),
		'tables'       => __( 'Tables', 'lynbro-accessibility-checker' ),
		'media'        => __( 'Video and embeds', 'lynbro-accessibility-checker' ),
		'aria'         => __( 'ARIA and keyboard', 'lynbro-accessibility-checker' ),
		'presentation' => __( 'Presentation', 'lynbro-accessibility-checker' ),
		'browser'      => __( 'Measured in the browser', 'lynbro-accessibility-checker' ),
	);
	return isset( $labels[ $group ] ) ? $labels[ $group ] : $group;
}

/**
 * The WCAG 2.2 reference for a success criterion.
 *
 * @param string $sc Criterion number, for example 1.4.3.
 * @return string URL, or empty when we have no number.
 */
function lynbro_a11y_wcag_url( $sc ) {
	$sc = preg_replace( '/[^0-9.]/', '', (string) $sc );
	if ( '' === $sc ) {
		return '';
	}
	return 'https://www.w3.org/WAI/WCAG22/Understanding/';
}
