=== PublishPress Permissions Content Visibility ===
Contributors: publishpress
Tags: permissions, content visibility, shortcode, access control
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.2.5
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Conditionally show or hide sections of post content for the current visitor.

== Description ==

Content Visibility is an optional module bundled with PublishPress Permissions.
It provides the `[pp_restrict]` shortcode for conditionally displaying sections
of post content based on login status, roles, capabilities, usernames, or
PublishPress Permission Groups.

Use the native shortcode with one or more conditions:

`[pp_restrict logged="in"]Members only.[/pp_restrict]`

`[pp_restrict roles="editor,author"]Editorial content.[/pp_restrict]`

`[pp_restrict capabilities="manage_options"]Site managers only.[/pp_restrict]`

`[pp_restrict usernames="sam,taylor"]Named users only.[/pp_restrict]`

`[pp_restrict groups="12,18"]Permission Group members only.[/pp_restrict]`

Separate condition attributes use `relation="all"` by default. Set
`relation="any"` when any populated condition may grant access. Values within
one list always use OR matching.

Set `hide="yes"` to invert a rule:

`[pp_restrict logged="out" hide="yes"]Hidden from logged-out visitors.[/pp_restrict]`

For migration, the module also handles `[eyesonly]`, `[eyesonlier]`, and
`[eyesonliest]` when another active plugin has not already registered those
shortcodes. Legacy conditions retain their original OR behavior.

Nested shortcodes are processed only after the visitor is authorized to see
the enclosed content.

== Frequently Asked Questions ==

= Does Content Visibility encrypt or remove restricted content? =

No. It controls the rendered output of the shortcode. Users who can edit the
post or access its raw database content can still read the enclosed content.

= Can I use Content Visibility with a page cache or CDN? =

The module sends WordPress no-cache headers and sets the standard
`DONOTCACHEPAGE` constant whenever it finds or renders a visibility shortcode.
Because caching products differ, verify restricted pages with your site's
specific cache and CDN configuration.

== Installation ==

1. Install and activate PublishPress Permissions.
2. Go to Permissions > Settings > Features.
3. Activate the Content Visibility module.
4. Add a Content Visibility shortcode to a post, page, or other supported
   content area.

== Changelog ==

= 0.1.0 =
* Add the Content Visibility module and legacy Eyes Only shortcode migration.
