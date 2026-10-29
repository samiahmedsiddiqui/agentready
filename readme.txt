=== AgentReady – llms.txt & Markdown for AI Agents ===
Contributors: sasiddiqui
Tags: llms-txt, markdown, ai, llm, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serves an llms.txt index and a clean Markdown version of every page, so AI agents can read your content.

== Description ==

AgentReady builds a Markdown version of each page from its rendered output, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever theme you use.

* `/llms.txt`, a Markdown index of your pages.
* A `.md` version of every page, for example `/about.md`. The homepage is `/index.md`.
* All public post types are served by default, with a setting to exclude any of them.
* Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term.
* Per-page description for `llms.txt`. When empty it uses the SEO plugin's meta description, then the excerpt. The page content is never used.
* Verify & Preview: checks that the stored Markdown matches the page, shows a rendered preview and the changes, and updates it in one click. A scan checks every stored page in the background.
* Works on sites protected with HTTP Basic authentication.
* Generated Markdown is stored and refreshed when a page is saved.
* Runs on production by default. Staging, development and local sites are opt-in.
* Drafts, private and password-protected content are never exposed.

**Pretty permalinks are required.** Plain permalinks such as `?p=123` are not supported: no Markdown pages or `llms.txt` are served, and the settings page shows a notice.

= How it works =

When an agent requests `/about.md`, AgentReady reads the public page `/about/` the same way a visitor would, keeps the main content, removes navigation, scripts and forms, and converts the result to Markdown with a short metadata header. Each Markdown response carries a canonical link to the HTML page and a `noindex` header, so search engines do not treat it as duplicate content.

= Stored Markdown =

The generated Markdown is stored in its own database table and refreshed in the background when you save a page. You can regenerate or clear it from the settings page, the edit screen, the post lists and WP-CLI.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/agentready`, or install it from the Plugins screen.
2. Activate the plugin.
3. Make sure Settings > Permalinks is not set to "Plain".
4. Open `/llms.txt` and any page URL with `.md` added to check the output, or use AgentReady > Verify & Preview.

== Frequently Asked Questions ==

= What are the Markdown URLs? =

Add `.md` to the page URL without the trailing slash: `/about/` becomes `/about.md` and `/services/web/` becomes `/services/web.md`. The homepage is `/index.md`. `/about/index.html.md`, `/about/index.md` and `/index.html.md` also work. You can choose `/index.html.md` as the homepage URL under AgentReady > Settings > Content > Homepage; the chosen one is listed in llms.txt.

= Why do I get a 404 for a page? =

The page is not published, is password protected, belongs to an excluded post type, is hidden in the editor, matches an exclusion rule, or has no pretty permalink. Verify & Preview tells you which.

= Does it work with Custom Permalinks? =

Yes. Pages, posts and custom post types that have a custom URL set by the Custom Permalinks plugin get their `.md` version at that URL plus `.md`, for example `/company/about-us.md`. A request for the original WordPress URL plus `.md`, for example `/about.md`, is redirected to it. Pages with default WordPress permalinks work as usual.

= Does it work with plain permalinks? =

No. Pages that use `?p=123` style URLs are never converted. Choose another structure in Settings > Permalinks.

= Why does AgentReady do nothing on my staging site? =

It only runs on production by default. Open AgentReady > Settings > Environment and tick your environment, or define `WP_ENVIRONMENT_TYPE` as `production`. To limit it to certain domains, set "Allowed URLs", or define `AGENTREADY_ALLOWED_URLS` in `wp-config.php`. `define( 'AGENTREADY_ENABLED', false );` switches it off completely.

= How do I know the stored Markdown matches my page? =

Open AgentReady > Verify & Preview, enter the page address and press Check page. It builds fresh Markdown from the live page and tells you whether the stored copy is in sync, shows a preview and the changes, and checks that the text on the page is in the Markdown. If the copy is out of date, press Update stored copy. The All pages tab does the same check for every stored page in the background and lists the ones that differ.

= I changed a page but the .md or llms.txt still shows the old text. =

Your host, a CDN or the browser may be keeping the file for a few minutes. AgentReady allows 5 minutes. Add a query string to the address, for example `/about.md?t=123` or `/llms.txt?t=123`, to skip that cache. Also check that the page was saved: it is refreshed in the background a few seconds after saving.

= How do I refresh the stored Markdown? =

Pages refresh in the background when you save them. To refresh by hand use "Regenerate now" in the AgentReady box on the edit screen, "Regenerate all" or "Clear cache" on the settings page, the "Regenerate Markdown" action in the post lists, or `wp agentready cache regenerate --all`.

= What is removed when I delete the plugin? =

The stored Markdown table is always removed, and so are any saved HTTP Basic credentials. Your other settings and the choices saved on pages are removed only if you tick "Delete all data on uninstall" on the Environment tab.

= My site asks for a username and password. =

Tick "This site is protected with HTTP Basic authentication" in AgentReady > Settings and enter the credentials. They are stored encrypted and only sent to your own site. You can also define `AGENTREADY_HTTP_AUTH_USER` and `AGENTREADY_HTTP_AUTH_PASSWORD` in `wp-config.php`.

= What happens if the page cannot be fetched? =

AgentReady falls back to the post content. That output is thinner, because fields printed by your theme are missing. Verify & Preview shows when this happens and why.

= Can I exclude pages with code? =

Yes. Use the `agentready_is_eligible` filter to leave out a single post, `agentready_excluded_post_types` to leave out post types, and `agentready_show_meta_box` to hide the box on the edit screen. Examples are in the plugin's GitHub README.

= Can I hide the AgentReady box on the edit screen? =

Yes. Untick "Show the AgentReady box on the edit screen" in AgentReady > Settings. Anything already saved on a page, such as hiding it or its description, keeps working.

= Can I change what counts as the main content? =

Yes. Set the content selector in AgentReady > Settings. The default tries `main`, `[role="main"]`, `#content`, `#main`, `.site-content` and `article`, then the whole body.

= Can I remove parts of a page from the Markdown? =

Yes. Add CSS selectors, one per line, under "Elements to exclude". They are removed in addition to the built-in list (navigation, sidebars, forms, scripts, hidden elements, and any site-level header or footer). Headers and footers inside an article are kept. Verify & Preview lets you try selectors without saving them.

== Changelog ==

= Unreleased =
* New AgentReady menu with Settings and Verify & Preview pages. The settings moved from the Settings menu to AgentReady > Settings.
* Verify & Preview checks a page against its stored Markdown, previews it, and updates it. The All pages tab scans every stored page in the background and reports which differ.
* The Markdown front matter description follows the same order as llms.txt and is left out when none is set, instead of using a summary of the page body.
* Post types, pages and taxonomy terms share one Exclusions card.
* Saved HTTP Basic credentials are always removed when the plugin is deleted.
* `llms.txt` descriptions come from the per-page description, then the SEO meta description, then the excerpt. They no longer fall back to text taken from the page content.
* Stored Markdown with background refresh, on-demand generation and manual regenerate and clear controls.
* Environment control: production by default, optional staging, development and local, allowed URLs, and the `AGENTREADY_ENABLED` and `AGENTREADY_ALLOWED_URLS` constants.
* Settings split into tabs, with system info.
* The homepage Markdown URL is now `/index.md`, with a setting to use `/index.html.md` instead. Both keep working.
* "Delete all data on uninstall" option.
* WP-CLI commands `wp agentready cache clear|regenerate|status`.

= 0.1.0 =
* Initial release.
