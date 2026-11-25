# NG Schema & Internal Linker

WordPress plugin that outputs JSON-LD structured data and adds internal links from keyword rules.

Product direction, scope and delivery: Nada Gamal.

## Schema
- Organization (name, logo), BlogPosting / Article / NewsArticle (headline, ISO dates, author Person, publisher, image, language), BreadcrumbList (post: home > category > post; page: parent chain), HowTo (steps typed in a box on the post editor, needs 2+ steps).
- Printed as one `@graph` script in the head. Output is encoded so a post title cannot close the script tag.
- Steps aside when Yoast, Rank Math, AIOSEO or SEOPress is active (tick "force" to override), so you never get duplicate schema.
- Filter `ngsl_graph` lets you edit the output in code.

## Internal linker
- Rules: one per line, `keyword | https://url`. Whole-word, case-insensitive, Arabic and other Unicode letters supported. Optional: also link to titles of recent posts.
- Safe by design: uses an HTML parser, never touches headings, existing links, code, pre, scripts, styles, buttons, captions, never links a page to itself, limits links per post (default 5) and per keyword (default 1). Only http(s) URLs are accepted. Runs when a post is displayed; your stored content is not changed, so turning it off removes the links.
- Labels in English, Arabic (RTL), French and German (FR/DE machine translated, need review).

## Tests
`tests/run-tests.php`: 38 checks, 0 failed (WordPress + PHP 8.2, Playground): graph content and dates, type whitelist, HowTo rules, breadcrumbs for posts and pages, script-breakout title, other-SEO detection, 15 linker cases (case, headings, existing links/code, limits, whole words, Arabic, self-link, entities, script/style, lists/captions), rule parsing, the_content integration, nonce/capability on the editor box, 4-language page, escaping. Testing found and fixed a real bug (only one link was added per paragraph).

## Honest limits
- Schema was not validated in Google's Rich Results Test or schema.org validator (no live site). Fields follow the schema.org definitions.
- Other-SEO detection covers four well-known plugins; with others, tick or untick "force" yourself.
- The linker does not judge relevance: bad rules produce bad links. Review a few pages after adding rules.
- FAQPage schema is not here; the FAQ widget in NG Studio Pro is the place for it.
- Smoke-tested with real Elementor 4.4.2: schema is printed on an Elementor-built post. Not tested in the Elementor editor, with Bricks, or on a live host.
