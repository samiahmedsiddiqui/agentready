# Stored Markdown

How the generated Markdown is stored, refreshed and removed.

Generated Markdown is stored in a `{prefix}agentready_pages` table. There is no expiry: a copy stays until the page changes or you clear it.

- Saving, trashing, unpublishing or password-protecting a page updates or removes its copy. A refresh runs in the background (WP-Cron) a few seconds after the save, so fields saved by ACF or a page builder are included.
- A page with no copy is generated while the request waits. If another request is already generating it, the request waits up to 5 seconds and then answers `503` with `Retry-After: 10`.
- An out-of-date copy is still served while a new one is built. Copies built by an older plugin version are rebuilt on the next request.
- Changing the content selector, the excluded elements or the HTTP Basic credentials marks every copy out of date and rebuilds them in the background.
- Saving an ACF options page marks every copy out of date and rebuilds them in the background. Saving a page in Elementor refreshes that page, and saving an Elementor saved template refreshes every page.
- Changing the SEO meta description of a page (Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework) refreshes that page, because it feeds `llms.txt` and the metadata block.
- Switching the theme, updating the active theme or saving the Customizer marks every copy out of date and rebuilds them in the background, and a notice on the AgentReady pages says so. Activating, deactivating or updating other plugins only shows a notice that points to Check all pages, because plugin updates are frequent. Dismiss a notice with its link. Nothing is recorded while the plugin is inactive.
- Responses carry `X-AgentReady-Cache: HIT`, `STALE` or `MISS`.
- Hosts, CDNs and browsers can keep `.md` and `llms.txt` for a few minutes (the plugin allows 5, with `Cache-Control: max-age=300`). If a change does not show yet, add a query string such as `/about.md?t=123`. Query strings are ignored when the page is found.
- If the page could not be fetched and the post content was used, the copy is retried about 5 minutes later.

Refresh by hand from the **Status** column (**Regenerate all**, **Clear cache**), the AgentReady box on the edit screen (**Regenerate now**, **Clear**), the **Regenerate Markdown** row action and bulk action in the post lists, or WP-CLI:

```bash
wp agentready cache status
wp agentready cache clear
wp agentready cache regenerate 42 57
wp agentready cache regenerate --all
```

Uninstalling always drops the table and clears any saved HTTP Basic credentials. Your other settings, the per-page choices and the llms.txt capability are removed too only if "Delete all data on uninstall" is ticked.
