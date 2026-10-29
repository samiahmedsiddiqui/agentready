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
* A `.md` version of every page, for example `/about.md`. The homepage is `/index.html.md`.
* All public post types are served by default, with a setting to exclude any of them.
* Hide individual pages from the editor, or exclude by ID, URL pattern or taxonomy term.
* Per-page description override for `llms.txt`.
* A preview tool to test the generated Markdown for any URL.
* Works on sites protected with HTTP Basic authentication.
* Drafts, private and password-protected content are never exposed.

**Pretty permalinks are required.** Plain permalinks such as `?p=123` are not supported: no Markdown pages or `llms.txt` are served, and the settings page shows a notice.

= How it works =

When an agent requests `/about.md`, AgentReady reads the public page `/about/` the same way a visitor would, keeps the main content, removes navigation, scripts and forms, and converts the result to Markdown with a short metadata header. Each Markdown response carries a canonical link to the HTML page and a `noindex` header, so search engines do not treat it as duplicate content.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/agentready`, or install it from the Plugins screen.
2. Activate the plugin.
3. Make sure Settings > Permalinks is not set to "Plain".
4. Open `/llms.txt` and any page URL with `.md` added to check the output, or use Settings > AgentReady > Preview.

== Frequently Asked Questions ==

= What are the Markdown URLs? =

Add `.md` to the page URL without the trailing slash: `/about/` becomes `/about.md` and `/services/web/` becomes `/services/web.md`. The homepage is `/index.html.md`. `/about/index.html.md`, `/about/index.md` and `/index.md` also work.

= Why do I get a 404 for a page? =

The page is not published, is password protected, belongs to an excluded post type, is hidden in the editor, matches an exclusion rule, or has no pretty permalink. The Preview tab tells you which.

= Does it work with Custom Permalinks? =

Yes. Pages, posts and custom post types that have a custom URL set by the Custom Permalinks plugin get their `.md` version at that URL plus `.md`, for example `/company/about-us.md`. A request for the original WordPress URL plus `.md`, for example `/about.md`, is redirected to it. Pages with default WordPress permalinks work as usual.

= Does it work with plain permalinks? =

No. Pages that use `?p=123` style URLs are never converted. Choose another structure in Settings > Permalinks.

= My site asks for a username and password. =

Tick "This site is protected with HTTP Basic authentication" in Settings > AgentReady and enter the credentials. They are stored encrypted and only sent to your own site. You can also define `AGENTREADY_HTTP_AUTH_USER` and `AGENTREADY_HTTP_AUTH_PASSWORD` in `wp-config.php`.

= What happens if the page cannot be fetched? =

AgentReady falls back to the post content. That output is thinner, because fields printed by your theme are missing. The Preview tab shows when this happens and why.

= Can I change what counts as the main content? =

Yes. Set the content selector in Settings > AgentReady. The default tries `main`, `[role="main"]`, `#content`, `#main`, `.site-content` and `article`, then the whole body.

= Can I remove parts of a page from the Markdown? =

Yes. Add CSS selectors, one per line, under "Elements to exclude". They are removed in addition to the built-in list (navigation, sidebars, forms, scripts, hidden elements, and any site-level header or footer). Headers and footers inside an article are kept. The Preview tab lets you try selectors without saving them.

== Changelog ==

= 0.1.0 =
* Initial release.
