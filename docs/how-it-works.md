# How AgentReady works

The addresses it serves, how a page becomes Markdown, and which pages are left out.

## URLs

| Page | Markdown URL |
| --- | --- |
| `/about/` | `/about.md` |
| `/services/web/` | `/services/web.md` |
| Homepage (static front page) | `/index.md` |
| Site index | `/llms.txt` |
| All pages in one file (opt-in) | `/llms-full.txt` |

`/about/index.html.md`, `/about/index.md` and `/index.html.md` are accepted as aliases. The homepage URL defaults to `/index.md`; choose `/index.html.md` instead under AgentReady > Settings > Content > Homepage. Both keep working, and the chosen one is the one listed in `llms.txt` and the page head. If a real page has the slug `index`, `/index.html.md` is used. Markdown responses send `Content-Type: text/markdown`, a canonical `Link` header to the HTML page, `X-Robots-Tag: noindex`, and support `ETag` and `If-Modified-Since`. Each page also gets `<link rel="alternate" type="text/markdown">` in its head.

## Custom Permalinks

Works with default WordPress permalinks and with the [Custom Permalinks](https://wordpress.org/plugins/custom-permalinks/) plugin. A page with a custom URL gets its Markdown at that URL plus `.md` (`/company/about-us.md`), `llms.txt` and the discovery tag use it, and `.md` requests for the original WordPress URL (`/about.md`) are redirected to it. URL patterns in the exclusion list match the custom path.

## How it works

1. A request for `/about.md` is caught early (on `parse_request`), so no rewrite rules are added and nothing needs flushing.
2. The post is resolved from the path and checked for eligibility: published, not password protected, post type enabled, not excluded.
3. The page is fetched from its own public URL as a logged-out visitor, so theme templates, ACF output and page builders are included. If that fails, the post content is used instead.
4. The main content is selected (`main`, `[role="main"]`, `#content`, `#main`, `.site-content`, `article`, then `body`). Navigation, sidebars, forms, scripts, hidden elements and site-level headers and footers are removed. Headers and footers inside an `article`, `main` or `section` are kept. The result is converted to Markdown with a metadata header.

## noindex pages

By default, pages that Yoast SEO, Rank Math, All in One SEO, SEOPress or The SEO Framework mark as noindex are left out of `llms.txt`, `llms-full.txt` and the `.md` files (the `.md` URL then gives the normal 404 page), and their stored copy is removed. Only the page's own setting counts, not the rules of a whole post type, and only the plugins that are active are read, so a value left behind by a removed plugin is ignored. Untick **Leave out pages set to noindex** under AgentReady > Settings > Content > Exclusions to include them again. The editor box and Verify & Preview say when this is the reason a page is not served.
