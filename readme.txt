=== AgentWelcome – llms.txt & Markdown for AI Agents ===
Contributors: sasiddiqui
Tags: llms-txt, markdown, ai, llm, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serves an llms.txt index and a clean Markdown version of every page, so AI agents can read your content.

== Description ==

AgentWelcome builds a Markdown version of each page from its rendered output, so ACF fields, Gutenberg blocks, Elementor layouts and classic editor content are all included, whatever theme you use.

* `/llms.txt`, a Markdown index of your pages. Write your own text instead, or put it above the generated list.
* Optional `/llms-full.txt`, the Markdown of every page in one file.
* Optional comment lines in `robots.txt` that point to these files.
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

When an agent requests `/about.md`, AgentWelcome reads the public page `/about/` the same way a visitor would, keeps the main content, removes navigation, scripts and forms, and converts the result to Markdown with a short metadata header. Each Markdown response carries a canonical link to the HTML page and a `noindex` header, so search engines do not treat it as duplicate content.

= Stored Markdown =

The generated Markdown is stored in its own database table and refreshed in the background when you save a page. You can regenerate or clear it from the settings page, the edit screen, the post lists and WP-CLI.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/agentwelcome`, or install it from the Plugins screen.
2. Activate the plugin.
3. Make sure Settings > Permalinks is not set to "Plain".
4. Open `/llms.txt` and any page URL with `.md` added to check the output, or use AgentWelcome > Verify & Preview.

== Frequently Asked Questions ==

= What are the Markdown URLs? =

Add `.md` to the page URL without the trailing slash: `/about/` becomes `/about.md` and `/services/web/` becomes `/services/web.md`. The homepage is `/index.md`. `/about/index.html.md`, `/about/index.md` and `/index.html.md` also work. You can choose `/index.html.md` as the homepage URL under AgentWelcome > Settings > Content > Homepage; the chosen one is listed in llms.txt.

= Why do I get a 404 for a page? =

The page is not published, is password protected, belongs to an excluded post type, is hidden in the editor, matches an exclusion rule, or has no pretty permalink. Verify & Preview tells you which.

= Does it work with Custom Permalinks? =

Yes. Pages, posts and custom post types that have a custom URL set by the Custom Permalinks plugin get their `.md` version at that URL plus `.md`, for example `/company/about-us.md`. A request for the original WordPress URL plus `.md`, for example `/about.md`, is redirected to it. Pages with default WordPress permalinks work as usual.

= Does it work with plain permalinks? =

No. Pages that use `?p=123` style URLs are never converted. Choose another structure in Settings > Permalinks.

= Why does AgentWelcome do nothing on my staging site? =

It only runs on production by default. Open AgentWelcome > Settings > Environment and tick your environment, or define `WP_ENVIRONMENT_TYPE` as `production`. To limit it to certain domains, set "Allowed URLs", or define `AGENTWELCOME_ALLOWED_URLS` in `wp-config.php`. `define( 'AGENTWELCOME_ENABLED', false );` switches it off completely.

= How do I know the stored Markdown matches my page? =

Open AgentWelcome > Verify & Preview, enter the page address and press Check page. It builds fresh Markdown from the live page and tells you whether the stored copy is in sync, shows a preview and the changes, and checks that the text on the page is in the Markdown. If the copy is out of date, press Update stored copy. The All pages tab does the same check for every stored page in the background and lists the ones that differ. If your site disables WP-Cron, that tab runs the scan itself while it stays open.

= I changed a page but the .md or llms.txt still shows the old text. =

Your host, a CDN or the browser may be keeping the file for a few minutes. AgentWelcome allows 5 minutes. Add a query string to the address, for example `/about.md?t=123` or `/llms.txt?t=123`, to skip that cache. Also check that the page was saved: it is refreshed in the background a few seconds after saving.

= Why did AgentWelcome pause itself after I copied or moved my site? =

AgentWelcome remembers a hash of your site address. When the address changes, as on a staging copy or after a move to a new domain, it pauses so a copy does not serve Markdown and llms.txt to AI agents. A banner on its pages has a "Resume on this address" button for administrators. If you set `AGENTWELCOME_ALLOWED_URLS` in `wp-config.php`, that overrides this check. Developers can turn it off with the `agentwelcome_check_site_address` filter.

= Can I write my own llms.txt? =

Yes. Open AgentWelcome > llms.txt and choose Custom to serve exactly your text, or Hybrid to serve your text followed by the list of pages AgentWelcome builds. You can type the text or load it from a .txt or .md file. Automatic keeps the generated file.

= What is llms-full.txt? =

An optional file with the Markdown of every page in one place, for agents that prefer a single download. Turn it on under AgentWelcome > llms.txt. It has the same modes as llms.txt (Automatic, Custom and Hybrid) with its own text. It uses the stored copies, so generate any missing pages first. It stops at 5 MB and says how many pages were left out.

= I already have an llms.txt file on my server. =

A real file in the site root is served by the web server before WordPress runs, so AgentWelcome cannot replace it. The llms.txt page shows a notice when it finds llms.txt or llms-full.txt there. Delete or rename the file to use the one AgentWelcome generates.

= What does the robots.txt option do? =

It adds comment lines that point to llms.txt (and llms-full.txt when it is on). They add no allow or disallow rules. It does nothing if a real robots.txt file exists on the server or if the site asks search engines not to index it.

= How do I refresh the stored Markdown? =

Pages refresh in the background when you save them. To refresh by hand use "Regenerate now" in the AgentWelcome box on the edit screen, "Regenerate all" or "Clear cache" on the settings page, the "Regenerate Markdown" action in the post lists, or `wp agentwelcome cache regenerate --all`.

= Who can edit llms.txt? =

Administrators, through the `agentwelcome_manage_files` capability that they get automatically. You can give that capability to any role or user with a role editor plugin, and those users see a menu with the llms.txt page only. Settings, Verify & Preview and the cache buttons stay with administrators. Editors can still hide a page or set its description from the AgentWelcome box on pages they can edit.

= What is removed when I delete the plugin? =

The stored Markdown table is always removed, and so are any saved HTTP Basic credentials. Your other settings and the choices saved on pages are removed only if you tick "Delete all data on uninstall" on the Environment tab. The llms.txt capability is removed from all roles and users in the same case.

= My site asks for a username and password. =

Tick "This site is protected with HTTP Basic authentication" in AgentWelcome > Settings and enter the credentials. They are stored encrypted and only sent to your own site. You can also define `AGENTWELCOME_HTTP_AUTH_USER` and `AGENTWELCOME_HTTP_AUTH_PASSWORD` in `wp-config.php`.

= My staging site asks for a username and password and background refreshes never run. =

Save the credentials in AgentWelcome > Settings > Rendering > Site access. AgentWelcome then sends them with the page request and with WordPress's own background request, so scheduled refreshes run. Without them, WP-Cron cannot reach the site.

= What happens if the page cannot be fetched? =

AgentWelcome falls back to the post content. That output is thinner, because fields printed by your theme are missing. Verify & Preview shows when this happens and why.

= Why is a page I can see in the browser missing from llms.txt? =

If an SEO plugin (Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework) marks the page as noindex, AgentWelcome leaves it out by default. The AgentWelcome box on the edit screen says so. To include such pages, untick "Leave out pages set to noindex" under AgentWelcome > Settings > Content > Exclusions.

= Do the stored copies update when I change my theme, ACF options or Elementor templates? =

Yes. Switching the theme, updating the active theme, saving the Customizer, saving an ACF options page or saving an Elementor saved template marks the stored Markdown out of date and rebuilds it in the background. Saving a page in Elementor refreshes that page. Activating, deactivating or updating other plugins only shows a notice, because those are frequent; use Verify & Preview > All pages to see whether anything drifted.

= Can I exclude pages with code? =

Yes. Use the `agentwelcome_is_eligible` filter to leave out a single post, `agentwelcome_excluded_post_types` to leave out post types, and `agentwelcome_show_meta_box` to hide the box on the edit screen. Examples are in the plugin's GitHub README.

= Can I hide the AgentWelcome box on the edit screen? =

Yes. Untick "Show the AgentWelcome box on the edit screen" in AgentWelcome > Settings. Anything already saved on a page, such as hiding it or its description, keeps working.

= Can I change what counts as the main content? =

Yes. Set the content selector in AgentWelcome > Settings. The default tries `main`, `[role="main"]`, `#content`, `#main`, `.site-content` and `article`, then the whole body.

= Can I remove parts of a page from the Markdown? =

Yes. Add CSS selectors, one per line, under "Elements to exclude". They are removed in addition to the built-in list (navigation, sidebars, forms, scripts, hidden elements, and any site-level header or footer). Headers and footers inside an article are kept. Verify & Preview lets you try selectors without saving them.

= How is this plugin developed? =

Developed with AI coding tools and maintained by Sami Ahmed Siddiqui. Every change is reviewed by the author and checked with automated tests, PHPCS, PHPStan and Plugin Check before release.

== Changelog ==

= 1.0.0 =
* Initial release.
