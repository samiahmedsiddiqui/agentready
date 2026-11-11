# AgentWelcome – llms.txt & Markdown for AI Agents

A WordPress plugin that serves an `llms.txt` index and a clean Markdown version of every page, so AI agents can read your content.

The Markdown is built from the rendered page, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever the theme.

## Features

- `/llms.txt`, a Markdown index of your pages. Write your own text instead, or put it above the generated list.
- Optional `/llms-full.txt`, the Markdown of every page in one file.
- Optional comment lines in `robots.txt` that point to these files.
- A `.md` version of every page, for example `/about.md`.
- All public post types enabled by default, with a setting to exclude any of them.
- Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term. Pages set to noindex by an SEO plugin are left out by default.
- Per-page description for `llms.txt`. When empty it uses the meta description from Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework, then the excerpt. The page content is never used, so a page without any of these is listed without a description.
- Verify & Preview: checks that the stored Markdown matches the page, shows a rendered preview and the changes, and updates it in one click.
- Check all pages: a background scan that lists every stored page that has drifted from its live page.
- Works behind HTTP Basic authentication.
- Generated Markdown is stored and refreshed when a page is saved, so agents get a fast answer.
- Runs on production by default. Staging, development and local are opt-in, and a URL allow-list and a `wp-config.php` kill switch keep copied sites quiet.

## Requirements

- PHP 8.0+ with the DOM extension, WordPress 6.0+.
- **Pretty permalinks.** Plain permalinks (`?p=123`) are not supported. Nothing is served for them and no Markdown is generated for URLs that contain a query string.

## Installation

Composer:

```bash
composer require wpackagist-plugin/agentwelcome
```

Or copy the plugin folder to `wp-content/plugins/agentwelcome` and activate it.

## Documentation

- [How it works](docs/how-it-works.md): the addresses it serves, how a page becomes Markdown, Custom Permalinks and noindex pages.
- [Settings and pages](docs/settings.md): the Settings, llms.txt and Verify & Preview pages, and who can manage the llms.txt files.
- [Environments](docs/environments.md): where the plugin runs, allowed URLs, and what happens when a site is copied or moved.
- [Stored Markdown](docs/stored-markdown.md): how the Markdown is stored and refreshed, WP-CLI commands, and uninstall.
- [Hooks](docs/hooks.md): filters and code examples.
- [Development](docs/development.md): checks, tests and how the plugin is developed.

## Development

Developed with AI coding tools and maintained by Sami Ahmed Siddiqui. Every change is reviewed by the author and checked with automated tests, PHPCS, PHPStan and Plugin Check before release. Setup and commands are in [docs/development.md](docs/development.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
