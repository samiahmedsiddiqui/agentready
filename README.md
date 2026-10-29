# AgentReady – llms.txt & Markdown for AI Agents

A WordPress plugin that serves an `llms.txt` index and a clean Markdown version of every page, so AI agents can read your content.

The Markdown is built from the rendered page, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever the theme.

## Features

- `/llms.txt`, a Markdown index of your pages.
- A `.md` version of every page, for example `/about.md`.
- All public post types enabled by default, with a setting to exclude any of them.
- Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term.
- Per-page description override for `llms.txt`.
- Preview tool to test the generated Markdown for any URL.
- Works behind HTTP Basic authentication.

## URLs

| Page | Markdown URL |
| --- | --- |
| `/about/` | `/about.md` |
| `/services/web/` | `/services/web.md` |
| Homepage (static front page) | `/index.html.md` |
| Site index | `/llms.txt` |

`/about/index.html.md`, `/about/index.md` and `/index.md` are accepted as aliases. Markdown responses send `Content-Type: text/markdown`, a canonical `Link` header to the HTML page, `X-Robots-Tag: noindex`, and support `ETag` and `If-Modified-Since`. Each page also gets `<link rel="alternate" type="text/markdown">` in its head.

## Requirements

- PHP 8.0+ with the DOM extension, WordPress 6.0+.
- **Pretty permalinks.** Plain permalinks (`?p=123`) are not supported. Nothing is served for them and no Markdown is generated for URLs that contain a query string.

## How it works

1. A request for `/about.md` is caught early (on `parse_request`), so no rewrite rules are added and nothing needs flushing.
2. The post is resolved from the path and checked for eligibility: published, not password protected, post type enabled, not excluded.
3. The page is fetched from its own public URL as a logged-out visitor, so theme templates, ACF output and page builders are included. If that fails, the post content is used instead.
4. The main content is selected (`main`, `[role="main"]`, `#content`, `#main`, `.site-content`, `article`, then `body`), navigation, scripts and forms are removed, and the result is converted to Markdown with a metadata header.

## Settings

Settings > AgentReady has post types, exclusions, the content selector, HTTP Basic authentication, and a Preview tab.

HTTP Basic credentials are stored encrypted with a key derived from the site's auth salt. To keep them out of the database, define them in `wp-config.php`:

```php
define( 'AGENTREADY_HTTP_AUTH_USER', 'user' );
define( 'AGENTREADY_HTTP_AUTH_PASSWORD', 'password' );
```

## Hooks

| Filter | Purpose |
| --- | --- |
| `agentready_is_eligible` | Exclude a post programmatically. |
| `agentready_resolve_post` | Map a request path to a post. |
| `agentready_use_loopback` | Return `false` to skip fetching the page and use the post content. |
| `agentready_loopback_timeout` | Seconds to wait for the page request (default 15). |
| `agentready_strip_selectors` | CSS selectors for elements to remove. |
| `agentready_markdown` | Final Markdown for a page. |
| `agentready_description` | The description used in the metadata and `llms.txt`. |
| `agentready_llms_txt` | Final `llms.txt` content. |
| `agentready_llms_max_items` | Entries per post type in `llms.txt` (default 500). |
| `agentready_content_type` | Content type of the Markdown response. |
| `agentready_response_headers` | Response headers. |

## Installation

Composer:

```bash
composer require wpackagist-plugin/agentready
```

Or copy the plugin folder to `wp-content/plugins/agentready` and activate it.

## Development

```bash
composer install
```

| Command | What it does |
| --- | --- |
| `composer lint` | Runs PHP_CodeSniffer with the WordPress Coding Standards and PHP 8.0+ compatibility checks. |
| `composer format` | Fixes the violations PHP_CodeSniffer can fix automatically. |
| `composer analyse` | Runs PHPStan. |
| `composer test` | Runs the PHPUnit unit tests (no WordPress needed). |

Every pull request runs these checks plus [Plugin Check](https://github.com/WordPress/plugin-check).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
