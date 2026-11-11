# Settings and pages

The AgentReady admin pages: Settings, llms.txt, who can manage the llms.txt files, and Verify & Preview.

## Settings

The **AgentReady** menu has three pages. **Settings** has three tabs. **Content**: exclusions (post types, pages by ID or URL pattern, taxonomy terms), the homepage Markdown URL and the editor box. **Rendering**: the content selector, extra elements to exclude (CSS selectors, added to the built-in list) and HTTP Basic authentication. **Environment**: where the plugin runs, and whether uninstalling removes your settings. The Status column shows system info and how many pages are stored. **llms.txt** and **Verify & Preview** are described below.

When HTTP Basic credentials are saved, AgentReady also sends them with WordPress's own background request to `wp-cron.php` (and only to this site), so scheduled refreshes keep running on a protected site. Without credentials, WP-Cron cannot reach a site that asks for them, and background work waits until something else calls `wp-cron.php`.

HTTP Basic credentials are stored encrypted with a key derived from the site's auth salt. To keep them out of the database, define them in `wp-config.php`:

```php
define( 'AGENTREADY_HTTP_AUTH_USER', 'user' );
define( 'AGENTREADY_HTTP_AUTH_PASSWORD', 'password' );
```

## llms.txt

AgentReady > llms.txt has three cards. These settings are saved apart from the main settings and are removed on uninstall when "Delete all data on uninstall" is ticked.

- **The llms.txt file.** Choose a mode. *Automatic* lists your pages, as before. *Custom* serves exactly the text you write. *Hybrid* serves your text first, then the generated list of pages (leave the list out of your text). You can type the text or load it from a `.txt` or `.md` file on your computer. The file is read in the browser and nothing is uploaded. Notes appear when the text has no title line, links to other sites or is very large, but they never block saving. A custom text is capped at 200,000 bytes. An empty text serves the generated file. A collapsed preview shows what the file contains now.
- **llms-full.txt.** Off by default. When on, `/llms-full.txt` joins the stored Markdown of every eligible page: a title, a `Source:` line with the page URL, then the body, without the metadata block. It reads only stored copies, so pages without one are left out until you generate them (Verify & Preview > All pages > Generate missing). The file stops at 5 MB (change it with `agentready_llms_full_max_bytes`), includes only whole pages, and ends with a note saying how many were left out. When the option is off, the address returns the normal 404 page.
- **robots.txt.** Off by default. When on, WordPress's generated robots.txt gets comment lines such as `# llms.txt: https://example.com/llms.txt`. They add no allow or disallow rules, so crawler access does not change. They are skipped when the site asks search engines not to index it, and when a real `robots.txt` file exists on the server, because WordPress then does not generate one.

## Who can manage the llms.txt files

The llms.txt page (your own text, llms-full.txt and the robots.txt line) needs the `agentready_manage_files` capability. Administrators get it the first time they open the admin after the plugin is active. If you take it away from the administrator role it is not added back. Give it to any role or user with a role editor plugin, or in code:

```php
get_role( 'editor' )->add_cap( 'agentready_manage_files' );
```

A user who has this capability but not `manage_options` sees a menu with the llms.txt page only. Settings, Verify & Preview, the scan and the cache buttons stay with administrators. To use an existing capability instead, map it with the `agentready_files_capability` filter, for example `add_filter( 'agentready_files_capability', fn() => 'edit_pages' );`. The capability is removed from all roles and users when you delete the plugin with "Delete all data on uninstall" ticked.

## Verify & Preview

AgentReady > Verify & Preview has two tabs.

**One page.** Enter a page URL (the field starts with your homepage address). AgentReady reads the page now, builds fresh Markdown with your saved settings, and shows:

- a verdict: **In sync**, **Out of date** (with the number of lines that differ), **Not stored yet**, or **Not served** with the reason;
- a rendered **Preview** (an approximation, made without any library and without HTML from the page), the **Raw Markdown** with a copy button, the **Changes** between the stored copy and the fresh Markdown, and **Page text**, which checks that the text blocks of the content area are in the Markdown and lists any that were lost, and the text your exclusions removed on purpose;
- an **Update stored copy** (or **Create stored copy**) button when the copy is out of date or missing.

You can also try a different content selector or extra exclusions without saving them; the comparison then shows what would change if you saved them. The edit screen's AgentReady box has a **Check against the page** link for administrators.

Pages with content that changes on every load, such as the time, can look out of date each time. Use the `agentready_compare_ignore` filter, which returns regular expressions for text to leave out of the comparison.

**All pages.** Start a scan and AgentReady checks every stored page in the background, a few at a time, and reports how many are in sync and which differ, with a Compare link for each. The scan changes nothing. On a site that disables WP-Cron, the open All pages tab runs the scan itself, a few pages at a time, and it pauses when you close the tab. **Update outdated** then queues a refresh for the pages that differ, and **Generate missing** builds the pages that have no stored copy yet.
