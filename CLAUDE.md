# Admin Studio — project rules

These rules apply to every change in this repository, and to all of Láyò's projects.

## Design

- **Never use a thick border on one side only.** No `border-left`, `border-right`, `border-top` or `border-bottom` stripes on notices, messages, cards, quotes, menu items or anything else, and no `border-inline-start` equivalents. Use an even border on all sides, a background tint, or both. This includes overriding WordPress core styles that add a side stripe (for example `.notice` and `.message`) wherever this plugin renders them.
- Colours come from the brand kit (`--adminstudio-*` custom properties). Do not hardcode colours in front-end styles.
- Animate only `transform` and `opacity`. Respect `prefers-reduced-motion`.
- Every interactive element needs visible `:focus-visible` styling.

## Code

- WordPress coding standards (WPCS 3) and PHP 7.4+ compatibility. Run PHPCS before committing.
- Sanitize every input, escape every output, check a nonce and a capability on every form and AJAX handler.
- No inline `<style>` blocks echoed into admin screens. Use enqueued files or `wp_add_inline_style()`.
- Only commit files the plugin needs. No build output, editor settings, test scripts or scratch notes.

## WordPress.org compliance

The plugin must always pass the WordPress.org review rules (https://wordpress.org/plugins/developers/, the Detailed Plugin Guidelines and the review team's Common Issues page):

- Run the official Plugin Check tool and PHPCS before every commit; both must report no errors.
- Never use "WP", "WordPress" or another project's trademark in the plugin name or slug.
- No HEREDOC or NOWDOC syntax.
- Escape at the point of output. Never echo a pre-built HTML string; print templates directly, or pass HTML through `wp_kses()` / `wp_kses_post()`.
- Scripts and styles only through the enqueue API, `wp_add_inline_script()`, `wp_add_inline_style()` or `wp_print_inline_script_tag()`. No hand-written `<script>` tags.
- No remote assets, tracking or calls home. Any external request must be user-initiated and documented in the readme's External services section, with terms and privacy links.
- Do not bundle libraries WordPress already ships (jQuery, PHPMailer and so on). List every third-party library with its licence and source in the readme.
- Front-end credits or links only when the site owner opts in. Site-wide admin notices must be dismissible or disappear once resolved.
- `readme.txt` must pass the readme validator: Stable tag equals the plugin Version, "Tested up to" never exceeds the current WordPress release, five tags at most, short description under 150 characters.
- `CLAUDE.md`, `docs/` and other development files are listed in `.distignore` and `.gitattributes` so they never ship in the plugin zip.

## Commits

- Write commit messages in plain, human language describing the change.
- Never mention AI, assistants, code generators or similar tools in commit messages, code comments or documentation. No co-author trailers.

## Credit

- Author: Láyò — https://www.layothedeveloper.com

## Documentation

- `docs/admin-studio-documentation.pdf` is the user documentation, built from `docs/documentation.html`.
- Update the documentation in the same change as any feature or behaviour change, and rebuild the PDF. It must always match the code.
