# AgentReady – llms.txt & Markdown for AI Agents

A WordPress plugin that serves an `llms.txt` index and a clean Markdown version of every page, so AI agents can read your content.

The Markdown is built from the rendered page, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included.

## Features

- `/llms.txt` and `/llms-full.txt`, generated automatically or customised.
- A `.md` version of every page, for example `/about.md`.
- All public post types enabled by default, with a setting to exclude any of them.
- Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term.
- Per-page description override for `llms.txt`.
- Preview tool to test the generated Markdown for any URL.
- Optional robots.txt rules that allow AI crawlers.

## Status

Early development. The project structure and exclusion logic are in place; the Markdown pipeline is not built yet.

## Installation

Composer:

```bash
composer require wpackagist-plugin/agentready
```

Or copy the plugin folder to `wp-content/plugins/agentready` and activate it.

## Development

```bash
composer install
composer lint
```

Requires PHP 8.0+ and WordPress 6.0+.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
