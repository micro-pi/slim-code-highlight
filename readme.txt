=== Slim Code Highlight ===
Requires at least: 5.9
Requires PHP: 7.4
Version: 1.1.0
License: GPL v2 or later

Syntax-highlights code inside `<pre>` blocks with Prism.js — including
the old `lang:xxx decode:true` markup left over from a previous,
now-uninstalled highlighter plugin, so existing posts light up with no
content edits needed. One library only. No bloat.

== Features ==

* Recognizes this site's old `lang:xxx decode:true` `<pre>` classes
  (java, c, c++, arduino, python, ruby, applescript, xhtml, and more)
  and rewrites them to Prism.js's `<pre><code class="language-xxx">`
  shape at render time — the stored post content is never touched
* Also recognizes `class="console"` blocks and, optionally, `<pre>`
  blocks with no class at all
* Optional line numbers and a copy-to-clipboard button (Prism's own
  official plugins)
* 8 built-in Prism themes to choose from
* Font size, as a percentage of the theme's own size (100% / 90% / 80% / 70%)
* Every language Prism supports works with zero configuration — its
  own autoloader plugin fetches only the language grammars (and their
  dependencies) actually found on the current page
* Independently toggleable for Posts and Pages
* Prism (core + autoloader) and its theme CSS load only on a singular
  post/page whose content has a `<pre>` block — never sitewide

== Design notes ==

* **The old markup is translated on the fly, on every request, via a
  `the_content` filter — not a one-time database rewrite.** Turning the
  plugin off (or changing a setting) instantly reverts every post's
  rendered output with no cleanup step and no risk to the stored
  content, the same non-destructive approach Slim Post Images takes for
  its lightbox markup.
* **`lang:xxx` tokens map through a small translation table**
  (`SCH_Frontend::LANG_MAP`) only where the site's old token doesn't
  already match a real Prism component id — `c++` to `cpp`, `xhtml` to
  `markup`, and so on. A token not in the table is passed straight
  through as-is, so `lang:php` or `lang:sql` (already valid Prism ids)
  work with no table entry needed. The very common `lang:default`
  token — used across this site for blocks with no specific language
  picked — maps to Prism's `none` (monospace + line numbers/copy button
  if enabled, no false-colored tokens) rather than guessing a language.
* **Loads languages via Prism's own autoloader plugin, not a hand-picked
  component list.** An earlier version of this plugin shipped one
  combined CDN request (`cdn.jsdelivr.net/combine/...`) built from a
  fixed language list; in testing, a language with an unmet dependency
  (`php`, which needs `markup-templating`) threw partway through that
  single concatenated script and silently broke highlighting for every
  language on the page, not just the one missing its dependency. The
  autoloader resolves each language's dependency chain correctly and
  loads every language as its own isolated `<script>`, so one bad or
  unusual id can't take down the rest — and any Prism-supported
  language works with no settings changes at all.
* **A `<pre>` block with no recognized class is left untouched by
  default** ("Also apply to `<pre>` blocks with no language class",
  off by default) since it could be genuine non-code preformatted text
  (ASCII art, a quoted log excerpt) rather than a missed code sample.
* **The font-size override only targets the outer `<pre>`, not also its
  `<code>` child.** The Prism theme's own CSS sets a font-size on both;
  applying the same percentage to both independently would compound
  instead of applying once (`<code>` is nested inside `<pre>`, so 80% on
  both means the effective size is 80% of 80% = 64%, not 80%) — caught
  live (a real page loaded 10.88px instead of the intended 13.6px)
  before shipping. `<code>` inherits the resolved size from `<pre>` on
  its own, so only `<pre>` needs the rule.
* **A block that already has a proper `<code class="language-xxx">`
  inside its `<pre>`** (hand-written, or from a block editor code
  block) is left completely alone — only the enclosing `<pre>` gets the
  `line-numbers` class added when that setting is on, since the Line
  Numbers plugin reads it from there.
* Registered on `the_content` at priority 20, after wpautop and
  `do_shortcode` have finished, so it sees the final `<pre>` markup.

== Installation ==

1. Upload the `slim-code-highlight` folder to `/wp-content/plugins/`.
2. Activate — it works immediately with no configuration for any post
   already using the site's old `lang:xxx decode:true` markup.
   Optionally visit Settings > Code Highlight to change the theme or
   turn off line numbers/copy button.
