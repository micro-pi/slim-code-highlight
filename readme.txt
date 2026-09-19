=== Slim Code Highlight ===
Requires at least: 5.9
Requires PHP: 7.4
Version: 1.3.1
License: GPL v2 or later

Syntax-highlights code inside `<pre>` blocks with Prism.js — including
the old `lang:xxx decode:true` markup left over from a previous,
now-uninstalled highlighter plugin, so existing posts light up with no
content edits needed. One library only. No bloat.

== Features ==

* A "Code (Slim Highlight)" block for the block editor: pick a language
  from a dropdown (or type any Prism-supported id), paste or type code,
  and see it syntax-highlighted right there in the editor — no more
  switching to the Code/Text tab to write a code block
* The block's preview matches the theme chosen on Settings > Code
  Highlight, and its "Code"/"Preview" sections fold open and closed by
  clicking their label — handy once a post has several code blocks
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
* Settings screen includes a supported-languages reference table and
  copy-pasteable usage examples for both the old `lang:xxx` markup and
  modern Prism `<code class="language-xxx">` markup
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
* **The settings-screen reference table shows both the old-style
  `lang:xxx` token and the modern Prism id side by side per language**,
  since the two differ for a handful of entries (`c++` vs `cpp`,
  `html`/`xhtml` vs `markup`) and are identical for the rest — rather
  than picking one convention and leaving the reader to guess whether
  it also applies to the other markup style.

* **The block saves the exact same markup this plugin already knows how
  to leave alone.** `SCH_Frontend::transform_block()`'s "already has a
  proper `<code class="language-xxx">`" branch existed before this block
  did (for hand-written Prism markup) — the block's `save()` just
  produces that same shape, so nothing on the front-end rendering side
  needed to change. The block is purely an editor-side authoring
  convenience over markup this plugin already supported.
* **No contenteditable-based "highlight while you type, in place" editor.**
  That approach (what libraries like CodeJar do) needs careful caret-
  position save/restore across every re-highlight, and CodeJar
  specifically turned out to have no CDN-loadable global build to pull
  in safely (ESM-only, even in old versions — checked directly against
  jsDelivr before deciding against it). Instead: WordPress's own
  `PlainText` component (the same one core's Code block uses, so
  paste/undo/keyboard behavior already matches what authors expect) for
  typing, plus a separate read-only `<pre><code>` preview underneath
  that Prism re-highlights after every change. Simpler, has no caret
  edge cases to get wrong, and still shows highlighted code right in the
  editor.
* **The editor's preview uses the same Prism theme configured on
  Settings > Code Highlight**, not a fixed one — so what an author sees
  while writing already looks like the real front-end output.
* **A curated language dropdown, with a free-text escape hatch.**
  `SCH_Settings::POPULAR_LANGUAGES` (already used for the settings
  screen's reference table) drives the block's dropdown too, so the two
  never list languages differently. Picking "Other…", or opening a post
  whose language isn't in that list, reveals a plain text field instead
  — any of Prism's 300+ supported ids works, not just the ~19 curated
  ones, matching what the autoloader could already load on the front end.
* **The preview's outer `&lt;pre&gt;` needs the `language-xxx` class too, not
  just the inner `&lt;code&gt;`.** A Prism theme's dark background is set by a
  rule keyed to `pre[class*="language-"]` specifically (checked directly
  against the Okaidia theme's own CSS: `pre[class*="language-"]{background:
  #272822}`) — separate from the `code[class*="language-"]{color:#f8f8f2}`
  rule that supplies the light token text color. The block's first version
  only added the language class to the `&lt;code&gt;` element, so the dark
  background rule never matched: light-colored text meant to sit on a dark
  background was rendering on the plain editor background instead —
  technically "highlighted" but washed out and illegible. The saved
  markup itself was never affected (`SCH_Frontend`'s transform already
  added the class to the front-end `&lt;pre&gt;` at render time), only the
  in-editor live preview.
* **The "Code"/"Preview" sections are native `&lt;details&gt;`/`&lt;summary&gt;`
  elements, not a custom collapse component.** The browser owns the
  open/closed state entirely — no click handler, no state variable, and
  because the `open` prop passed to them never changes across re-renders,
  React never fights a manual toggle by resetting it back.
* **Block registration happens on `init`, unconditionally** (not gated
  behind `is_admin()`) — the block type itself must be registered on
  every request, front end included, for WordPress to correctly parse
  and render the saved block comment delimiters back into HTML; only
  its editor script/style are wp-admin-only; enqueued automatically by
  WordPress core off the block type's declared `editor_script` /
  `editor_style`, not a separate manual enqueue call.

== Installation ==

1. Upload the `slim-code-highlight` folder to `/wp-content/plugins/`.
2. Activate — it works immediately with no configuration for any post
   already using the site's old `lang:xxx decode:true` markup.
   Optionally visit Settings > Code Highlight to change the theme or
   turn off line numbers/copy button.
