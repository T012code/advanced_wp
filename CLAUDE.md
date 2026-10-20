# Advanced WP — project rules

These rules apply to every change in this repository, and to all of Láyò's projects.

## Design

- **Never use a thick border on one side only.** No `border-left`, `border-right`, `border-top` or `border-bottom` stripes on notices, messages, cards, quotes, menu items or anything else, and no `border-inline-start` equivalents. Use an even border on all sides, a background tint, or both. This includes overriding WordPress core styles that add a side stripe (for example `.notice` and `.message`) wherever this plugin renders them.
- Colours come from the brand kit (`--advwp-*` custom properties). Do not hardcode colours in front-end styles.
- Animate only `transform` and `opacity`. Respect `prefers-reduced-motion`.
- Every interactive element needs visible `:focus-visible` styling.

## Code

- WordPress coding standards (WPCS 3) and PHP 7.4+ compatibility. Run PHPCS before committing.
- Sanitize every input, escape every output, check a nonce and a capability on every form and AJAX handler.
- No inline `<style>` blocks echoed into admin screens. Use enqueued files or `wp_add_inline_style()`.
- Only commit files the plugin needs. No build output, editor settings, test scripts or scratch notes.

## Commits

- Write commit messages in plain, human language describing the change.
- Never mention AI, assistants, code generators or similar tools in commit messages, code comments or documentation. No co-author trailers.

## Credit

- Author: Láyò — https://www.layothedeveloper.com

## Documentation

- `docs/advanced-wp-documentation.pdf` is the user documentation, built from `docs/documentation.html`.
- Update the documentation in the same change as any feature or behaviour change, and rebuild the PDF. It must always match the code.
