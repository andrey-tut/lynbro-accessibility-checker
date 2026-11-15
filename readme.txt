=== Lynbro Accessibility Checker ===
Contributors: lynbro
Tags: accessibility, wcag, a11y, eaa, accessibility checker
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Scan your whole site against WCAG 2.2 AA, see the element causing each problem and how to fix it, and switch on the fixes that are safe. No overlay.

== Description ==

Lynbro Accessibility Checker checks every published page on your site against WCAG 2.2
level AA, shows you each problem with the exact element that causes it, explains
why it matters to a real person and tells you how to put it right. Then it lets
you switch on the handful of corrections that can be made safely without
guessing at what you meant.

Everything runs on your own server. There is no account, no API key, nothing is
sent anywhere, and there is no page limit — the whole-site scan and the
site-wide report are free, for every page you have, for as long as you have the
plugin.

Source code and issue tracker on GitHub:
https://github.com/andrey-tut/lynbro-accessibility-checker

= This is not an overlay =

Accessibility overlays — the widget with the little person icon that promises to
make a site compliant — do not work, and saying they do has become a legal
problem in its own right. In April 2025 the United States Federal Trade
Commission ordered accessiBe to pay one million dollars over exactly those
claims.

This plugin does the opposite thing. It finds the problems in your markup and
tells you where they are. Nothing is added to your visitors' pages except the
specific corrections you choose, and no visitor is ever sent a script that
claims your site is compliant.

= What it checks =

Around forty rules, each tied to a WCAG 2.2 success criterion:

* **Images** — missing alt attributes, alt text that says nothing ("image",
  "billede", the file name), alt text too long to listen to, image links with no
  text, image maps and unnamed inline SVGs.
* **Forms** — fields with no label, fields using a placeholder as the label,
  labels pointing at a field that is not there, name and e-mail fields missing
  autocomplete, duplicate ids.
* **Links and buttons** — empty links, "read more" and "læs mere" with nothing
  around them, raw addresses used as link text, links that open a new window
  without saying so, buttons with no accessible name.
* **Headings** — a skipped level, an empty heading, a missing or duplicated h1.
* **Page structure** — no page title, no language attribute, no main region, no
  skip link, and a viewport that stops people zooming on a phone.
* **Tables** — data tables with no header cells, empty header cells.
* **Video and embeds** — untitled frames, video with no caption track, audio
  that needs a transcript.
* **ARIA and keyboard** — references pointing at nothing, focusable elements
  hidden from screen readers, roles that do not exist, forced tab order.
* **In the browser** — contrast measured with the colours your stylesheet
  actually paints, whether keyboard focus is visible at all, and controls
  smaller than 24 by 24 pixels (target size, added at AA in WCAG 2.2).

The last three are measured by opening the page, because contrast and focus are
the result of your stylesheet and cannot be worked out from HTML. That check
runs only when you, signed in as an administrator, ask for it: on one page, or
on every page at once from the **Browser check** tab, which opens each page at
desktop width and at phone width in your own browser tab and moves on.

= Keeps up as you edit =

When you save a published page or post, that page is checked again a few
seconds later in the background, so saving is not slower. Every post and page
list gets an **Accessibility** column with the result of the last check —
failures and likely failures, linked to that page's report — so editors see
which pages need work without opening the report. A full re-scan can also run
daily or weekly on its own.

= What it fixes, if you ask =

Every fix is off until you switch it on, and switching it off puts things back.
None of them edits a post, a template or your database.

* Fill in missing alt text from what is already saved in your Media Library.
* Add a skip link at the very start of the page.
* Show a visible outline where the keyboard is, in a colour you pick.
* Underline links inside your content, so colour is not the only signal.
* Say "(opens in a new window)" on links that do.
* Give untitled YouTube, Vimeo, Maps and similar embeds a name.
* Label the WordPress search and comment fields when the theme ships them bare.
* Set the page language when the theme forgot it.
* Let people zoom on a phone again.
* Undo a forced tab order.

= What it deliberately will not do =

It will not renumber your headings, write alt text you did not write, or invent
link text. Each of those means deciding what you meant, and a plugin that
quietly makes that decision cannot explain it to you afterwards. Those show up
in the report, and you make the call.

= The half no scanner can do =

Automated testing reaches roughly a third of WCAG. A tool that reports "no
issues found" without telling you that is telling you something misleading, so
this one says it plainly and gives you the rest as a checklist: eighteen
questions covering the WCAG 2.2 A and AA criteria that need a person, each with
an answer, a note and a date. Including the ones added in WCAG 2.2 that most
sites have never been assessed against — focus not obscured, dragging movements,
consistent help, redundant entry, accessible authentication.

There is no compliance percentage anywhere in this plugin. No automated scan can
produce one honestly.

= An accessibility statement, written from your results =

The Act expects a site that sells to consumers to publish an accessibility
statement: how accessible the site is, what is not accessible yet and how to
report a barrier. The Statement tab writes one for you from what the plugin
already knows — the problems the scan found, the pages it read and when, the
manual checks you answered — plus the few things only you know: who people
contact, what is out of scope.

It is written in your site's language as an ordinary draft page made of blocks.
You read it, change what you want and publish it. The compliance status is your
choice, and the screen tells you when that choice does not match your own
checks. The statement describes your own assessment as exactly that, and it
carries no plugin name or link.

On a Danish-language site the "where to complain" part starts filled in: people
who are not satisfied with your answer can send a tip to Erhvervsstyrelsen, the
authority that supervises the accessibility requirements for products and
services in Denmark. A button puts the same wording back if you change it. In
other countries you write in your own authority, because it depends on the
country and the sector.

The Statement tab is a writing aid, not legal advice: it does not decide whether
the rules apply to you, and it does not certify your site.

= The European Accessibility Act, honestly =

The Act has applied since 28 June 2025, and in Denmark Erhvervsstyrelsen
supervises it. But microenterprises — under 10 people and turnover at or below
2 million euro — are exempt for services, so a great many small sites are not
obliged to do any of this.

We would rather say so than sell you something you do not need. If you are over
that line, the report plus the manual checks are what an assessment looks like,
and both export as a file you can hand to an assessor. If you are under it,
everything here still makes your site work for more people, which was always the
better reason.

= No limits =

No page cap, no monthly quota, no "contact us for larger sites". Full-site
scanning, the site-wide report, the export and every fix are in the free plugin
and always will be. If we ever charge for something, it will be something that
costs us money to run — not a switch we put in front of code that is already on
your server.

== Installation ==

1. Install from **Plugins → Add New** (search for "Lynbro Accessibility Checker") or upload the ZIP, then activate.
2. Open **Lynbro → Accessibility** and press **Scan the site**. The scan runs in short bursts in the background; you can leave the screen.
3. Start with the overview: it lists the problems that affect the most pages first, usually one line in a theme template.
4. Open the **Browser check** tab and check every page in the browser, to measure contrast, keyboard focus and target size (or press **Check in browser** on one page of the report).
5. Switch on the fixes you want under **Fixes**, then work through the manual checks.

The plugin needs the PHP DOM extension (present on almost every host) and a site that can request its own pages over HTTP. If either is missing, the report says so on the page instead of showing a clean result.

== Frequently Asked Questions ==

= Does this make my site compliant? =

No, and neither does anything else you can install. It finds the problems that
can be found automatically, tells you how to fix them, fixes a few of them for
you if you ask, and is honest about the rest. Compliance is what is left after
the work.

= Why does the scan open my pages over HTTP? =

Because almost every problem that matters lives in the theme — the menu with no
landmark, the header image with no alt text, the search field with a placeholder
instead of a label. Checking only the post content would miss all of it and
report a clean site that fails its first real audit.

If your host blocks a site from calling itself, the report says so on the page's
row rather than pretending the page was checked.

= Why do contrast and focus need a separate button? =

Because they are not in your HTML. They are the result of your stylesheet, your
fonts and the screen size, and the only way to know them is to look at the page
as a browser has drawn it. Pressing "Check in browser" opens the page with a
measuring script that runs once, reports back, and is gone on the next load.

= Can it check contrast on every page, not one at a time? =

Yes. The **Browser check** tab opens each page of the report inside the screen,
once 1280 pixels wide and once 390 pixels wide, waits for the fonts, measures
and moves on, a few seconds per page. It runs in your browser tab, so keep the
tab open and in front until it says it has finished. Stopped halfway, it carries
on with the pages not checked yet. If a security plugin forbids the site from
showing its own pages in a frame (X-Frame-Options DENY), the screen says so at
once and the per-page button still works.

= Why does a finding say "not counted"? =

The browser check also looks up every markup finding on the painted page. A
finding on an element that is not on screen when the page loads, at desktop
width or at phone width, stops counting: browsers leave such elements out of
what a screen reader gets, and axe skips them too. When a page builder prints a
block twice, once for desktop and once for phones, the phone copy of the same
problem stops counting and the desktop copy counts. Both stay in the page's list
with the reason, so a closed menu or pop-up can still be checked while it is
open. Elements hidden at every size by their class (Bootstrap d-none, Tailwind
hidden, Elementor blocks switched off on desktop, tablet and phone) are skipped
by the scan itself.

= Will a scan slow my site down? =

It runs in short bursts with a time budget and a memory ceiling, and it stops
by itself if it gets close to either. Nothing about it ever runs on a visitor's
request. On a small shared host, lower "Pages per tick" in the settings.

= Does it see what my page builder or theme outputs? =

Yes. The scanner reads each page the way a visitor's browser receives it, so it
checks whatever Elementor, Divi, Beaver Builder, the block editor or your theme
actually put on the page — headers, footers and menus included.

= Can I hide a finding that is not a problem? =

Yes. Every finding has "Not a problem? Hide it": hide it on that page, or hide
the same element on every page — a theme footer or an embedded widget you
cannot change — including pages it appears on later. You can add a reason, and
the report shows who hid it and when, so a colleague or an assessor sees a
decision rather than a gap. A whole rule can be hidden from its own report page.
Hidden findings drop out of the counts, so the numbers on screen always match
the list under them.

= Can it scan a staging site behind a password? =

Yes. Enter the basic auth username and password under Settings, "A site behind
a password". They are sent only to your site's own address, never to another
host, not even after a redirect. A page that asks for a password, a site in
maintenance mode and a password-protected post each get a plain explanation in
the report instead of a bare error code.

= Is a page checked again when I update it? =

Yes, a few seconds after you save it, in the background. The result appears in
the Accessibility column of the page list. You can switch this off in the
settings.

= Does it write an accessibility statement? =

Yes. Fill in the Statement tab and press "Save and write the statement page".
You get a draft page in your site's language listing what your checks found and
how to contact you. Press it again after the next scan and the page is updated;
the previous text stays in the page's revisions.

= Is there a Pro version? =

Not for anything in this plugin. Everything here works completely, for every
page, for ever.

== Screenshots ==

1. The overview: what the scan found, what to start with, and what it could not see.
2. The report for one page, with the element behind each problem and how to fix it.
3. One rule across the whole site — usually one line in a theme template.
4. The fixes, each with what it does and what to be careful of.
5. The manual checks: the WCAG 2.2 criteria a scanner cannot settle.
6. The accessibility statement: what only you know, and a preview of the page it writes.

== External Services ==

This plugin's own features contact no external service. Nothing about your site, your content
or your visitors is sent anywhere.

To read a page, the scanner asks your own site for that page over HTTP, the same
way a visitor's browser would. That request goes to your own domain and nowhere
else. It carries a header, `X-Lynbro-Accessibility: scan`, so you can recognise
it in your access log.

**WordPress.org plugin directory (the shared Lynbro "Our plugins" screen).**
Apart from the above, one request goes to WordPress.org, from every Lynbro plugin
alike: when an administrator who may install plugins opens Lynbro → Our plugins,
the screen asks the WordPress.org Plugin Directory API
(https://api.wordpress.org/plugins/info/1.2/) which Lynbro plugins are listed,
through WordPress core's own `plugins_api()` — the same call the Plugins → Add New
screen makes.

* **What is sent:** the query (plugins by the author "lynbro"), plus what WordPress
  core adds to every request to WordPress.org: your WordPress version, the admin
  language and, in the User-Agent, your site address. Nothing about your visitors,
  users or content.
* **When:** only on that screen, at most once a day (the answer is cached for a day;
  after a failed request, an hour). No other screen and no visitor request triggers it.
* Installing a suggested plugin uses WordPress core's own installer, which downloads
  the plugin from downloads.wordpress.org — only when you click "Install now".
* WordPress.org [Terms of Service](https://wordpress.org/about/terms-of-service/) ·
  [Privacy Policy](https://wordpress.org/about/privacy/)

== Changelog ==

= 0.2.2 =
* Readme: External Services now names the one WordPress.org request the shared "Our plugins" screen makes — what is sent, when, and the WordPress.org terms and privacy policy.
* The plugin names and descriptions on "Our plugins" and in the suggestions are translatable.
* Links on Lynbro screens use the darker brand green, which meets WCAG AA contrast on the admin background.

= 0.2.1 =
* Shared Lynbro admin 1.1.0: after two weeks of use and a first success (for example settings saved), this plugin's own screen asks once, politely and without stars, whether it is doing its job. Answers are per user: "Maybe later" or "Don't ask again", and only one Lynbro plugin asks in any 30 days. A finished scan now counts as that first success.
* A small "Suggested for this site" block under the plugin's main screen lists Lynbro plugins that fit what the site already uses (WooCommerce, forms, a Danish setup …), with the reason. It can be hidden; nothing installs on its own and nothing is sent anywhere.
* Tab labels on Lynbro screens meet WCAG AA contrast.
* Lynbro admin screens now read the plugin's bundled translations (languages/) when WordPress has no language pack for it, so a Danish admin sees Danish instead of English.

= 0.2.0 =

* Elements a theme hides with a class that works at every screen size (`d-none`, `hidden`, Elementor hidden on all devices) are no longer reported; an element hidden on one device only still is, because it is real on the others.
* New name: Lynbro Accessibility Checker. Your settings and results are kept.
* A published page is checked again a few seconds after it is saved.
* An Accessibility column in the post and page lists shows each page's result and links to its report.
* A page that could not be fetched or read is no longer counted as checked or clean; the overview says how many were not checked.
* The report link after a browser check now opens the page's report.
* The editor shows the page's last result in a box at the side, and the page report has a «Check this page again» button.
* Unpublished, trashed and deleted pages leave the report.
* Links to PDFs and office documents are flagged so the files get their own check.
* New Statement tab: writes an accessibility statement in your site's language, from your scan results and manual checks, to a draft page you review and publish.
* On Danish sites the statement's complaint section starts with Erhvervsstyrelsen and its tip page; web and email addresses in your own text become links.
* Hide a finding on one page or the same element on every page, with a reason; the report shows who hid it and when. A whole rule can be hidden from its report page.
* Staging sites behind basic auth can be scanned (username and password in Settings, sent only to your own site). Clear messages for pages behind a password, maintenance mode and password-protected posts.
* Pages without a title are named by their path in the report, never by a raw URL.
* New Browser check tab: measures contrast, focus and target size on every page in one run, at desktop and phone width, in your browser tab.
* Findings on elements that are off screen at both widths, and on the phone copy of a block that also has a desktop copy, are listed with the reason but no longer counted.
* The browser check waits for web fonts before measuring, so target sizes are not read from the fallback font.
* Wide report tables scroll inside their own frame on a phone.

= 0.1.0 =

* First release.

== Upgrade Notice ==

= 0.2.0 =

Pages are re-checked when saved, post lists get an Accessibility column, and a new Statement tab writes your accessibility statement. Pages the scanner could not read are no longer counted as clean.

== Privacy ==

Everything the plugin knows is stored in your own database: the list of pages it
has scanned, the issues found on each, and your answers to the manual checks
along with the date and the user who answered, and the contact details you
entered for the accessibility statement. Hidden findings keep the reason given,
the user who hid them and the date. If you enter a username and password for a
staging site, they are stored in your database and sent only to your own site
with each scan request.

No personal data about your visitors is collected, because the plugin never runs
on a visitor's request. The in-page measurement script is only ever loaded for a
signed-in administrator who asked for it.

Removing the plugin removes all of it. A scan report is a list of your site's
weak points, and it should not outlive the plugin that made it. The statement
page is yours, not the plugin's: it is an ordinary page and stays.
