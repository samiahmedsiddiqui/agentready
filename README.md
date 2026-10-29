# AgentReady – llms.txt & Markdown for AI Agents

A WordPress plugin that serves an `llms.txt` index and a clean Markdown version of every page, so AI agents can read your content.

The Markdown is built from the rendered page, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever the theme.

## Features

- `/llms.txt`, a Markdown index of your pages.
- A `.md` version of every page, for example `/about.md`.
- All public post types enabled by default, with a setting to exclude any of them.
- Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term.
- Per-page description for `llms.txt`. When empty it uses the meta description from Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework, then the excerpt. The page content is never used, so a page without any of these is listed without a description.
- Preview tool to test the generated Markdown for any URL.
- Works behind HTTP Basic authentication.
- Generated Markdown is stored and refreshed when a page is saved, so agents get a fast answer.
- Runs on production by default. Staging, development and local are opt-in, and a URL allow-list and a `wp-config.php` kill switch keep copied sites quiet.

## URLs

| Page | Markdown URL |
| --- | --- |
| `/about/` | `/about.md` |
| `/services/web/` | `/services/web.md` |
| Homepage (static front page) | `/index.md` |
| Site index | `/llms.txt` |

`/about/index.html.md`, `/about/index.md` and `/index.html.md` are accepted as aliases. The homepage URL defaults to `/index.md`; choose `/index.html.md` instead under Settings > AgentReady > Content > Homepage. Both keep working, and the chosen one is the one listed in `llms.txt` and the page head. If a real page has the slug `index`, `/index.html.md` is used. Markdown responses send `Content-Type: text/markdown`, a canonical `Link` header to the HTML page, `X-Robots-Tag: noindex`, and support `ETag` and `If-Modified-Since`. Each page also gets `<link rel="alternate" type="text/markdown">` in its head.

## Custom Permalinks

Works with default WordPress permalinks and with the [Custom Permalinks](https://wordpress.org/plugins/custom-permalinks/) plugin. A page with a custom URL gets its Markdown at that URL plus `.md` (`/company/about-us.md`), `llms.txt` and the discovery tag use it, and `.md` requests for the original WordPress URL (`/about.md`) are redirected to it. URL patterns in the exclusion list match the custom path.

## Requirements

- PHP 8.0+ with the DOM extension, WordPress 6.0+.
- **Pretty permalinks.** Plain permalinks (`?p=123`) are not supported. Nothing is served for them and no Markdown is generated for URLs that contain a query string.

## How it works

1. A request for `/about.md` is caught early (on `parse_request`), so no rewrite rules are added and nothing needs flushing.
2. The post is resolved from the path and checked for eligibility: published, not password protected, post type enabled, not excluded.
3. The page is fetched from its own public URL as a logged-out visitor, so theme templates, ACF output and page builders are included. If that fails, the post content is used instead.
4. The main content is selected (`main`, `[role="main"]`, `#content`, `#main`, `.site-content`, `article`, then `body`). Navigation, sidebars, forms, scripts, hidden elements and site-level headers and footers are removed. Headers and footers inside an `article`, `main` or `section` are kept. The result is converted to Markdown with a metadata header.

## Settings

Settings > AgentReady has four tabs. **Content**: post types, exclusions, the homepage Markdown URL and the editor box. **Rendering**: the content selector, extra elements to exclude (CSS selectors, added to the built-in list) and HTTP Basic authentication. **Environment**: where the plugin runs, and whether uninstalling removes your settings. **Preview**: shows the Markdown for any URL, and can try a different content selector or extra excludes without saving them. The Status column shows system info and how many pages are stored.

HTTP Basic credentials are stored encrypted with a key derived from the site's auth salt. To keep them out of the database, define them in `wp-config.php`:

```php
define( 'AGENTREADY_HTTP_AUTH_USER', 'user' );
define( 'AGENTREADY_HTTP_AUTH_PASSWORD', 'password' );
```

## Environments

AgentReady is active on production, and when the environment type is not set (WordPress treats that as production). Tick staging, development or local on the Environment tab to run there too. Set the type with `WP_ENVIRONMENT_TYPE`; many hosts do it for you.

When the plugin is inactive, `.md` and `/llms.txt` return the normal 404 page, no discovery tag is added, and nothing is scheduled. The settings page says why, and the Preview tab still works.

Two constants in `wp-config.php` take priority over the settings:

```php
// Switch the plugin off completely.
define( 'AGENTREADY_ENABLED', false );

// Only run when the site's host matches one of these. * matches anything.
define( 'AGENTREADY_ALLOWED_URLS', 'www.example.com, *.example.org' );
```

Use the constant for the allowed URLs on hosts that copy a site to staging and replace the domain inside the database, because that can rewrite the setting too.

## Stored Markdown

Generated Markdown is stored in a `{prefix}agentready_pages` table. There is no expiry: a copy stays until the page changes or you clear it.

- Saving, trashing, unpublishing or password-protecting a page updates or removes its copy. A refresh runs in the background (WP-Cron) a few seconds after the save, so fields saved by ACF or a page builder are included.
- A page with no copy is generated while the request waits. If another request is already generating it, the request waits up to 5 seconds and then answers `503` with `Retry-After: 10`.
- An out-of-date copy is still served while a new one is built. Copies built by an older plugin version are rebuilt on the next request.
- Changing the content selector, the excluded elements or the HTTP Basic credentials marks every copy out of date and rebuilds them in the background.
- Responses carry `X-AgentReady-Cache: HIT`, `STALE` or `MISS`.
- If the page could not be fetched and the post content was used, the copy is retried about 5 minutes later.

Refresh by hand from the **Status** column (**Regenerate all**, **Clear cache**), the AgentReady box on the edit screen (**Regenerate now**, **Clear**), the **Regenerate Markdown** row action and bulk action in the post lists, or WP-CLI:

```bash
wp agentready cache status
wp agentready cache clear
wp agentready cache regenerate 42 57
wp agentready cache regenerate --all
```

Uninstalling always drops the table. Your settings and the per-page choices are removed too only if "Delete all data on uninstall" is ticked.

## Hooks

| Filter | Purpose |
| --- | --- |
| `agentready_is_eligible` | Exclude a post programmatically. Receives `$eligible` and the `WP_Post`. |
| `agentready_excluded_post_types` | Add or remove excluded post types in code. |
| `agentready_show_meta_box` | Show or hide the editor box. Receives the setting and the post type. |
| `agentready_resolve_post` | Map a request path to a post. |
| `agentready_use_loopback` | Return `false` to skip fetching the page and use the post content. |
| `agentready_loopback_timeout` | Seconds to wait for the page request (default 15). |
| `agentready_strip_selectors` | CSS selectors for elements to remove. |
| `agentready_markdown` | Final Markdown for a page. |
| `agentready_description` | The description used in the metadata and `llms.txt`. |
| `agentready_meta_description_keys` | Post meta keys read as the SEO meta description, in order. |
| `agentready_llms_txt` | Final `llms.txt` content. |
| `agentready_llms_max_items` | Entries per post type in `llms.txt` (default 500). |
| `agentready_content_type` | Content type of the Markdown response. |
| `agentready_response_headers` | Response headers. |

### Excluding from code

```php
// Leave out one page.
add_filter( 'agentready_is_eligible', function ( $eligible, $post ) {
	return 'private-docs' === $post->post_name ? false : $eligible;
}, 10, 2 );

// Leave out a whole post type.
add_filter( 'agentready_excluded_post_types', function ( $types ) {
	return array_merge( $types, array( 'product' ) );
} );

// Hide the editor box for everyone.
add_filter( 'agentready_show_meta_box', '__return_false' );
```

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
