# Settings and pages

The AgentWelcome admin pages: Settings, llms.txt, who can manage the llms.txt files, and Verify & Preview.

## Settings

The **AgentWelcome** menu has three pages. **Settings** has three tabs. **Content**: exclusions (post types, pages by ID or URL pattern, taxonomy terms), the homepage Markdown URL and the editor box. **Rendering**: the content selector, extra elements to exclude (CSS selectors, added to the built-in list) and HTTP Basic authentication. **Environment**: where the plugin runs, and whether uninstalling removes your settings. The Status column shows system info and how many pages are stored. **llms.txt** and **Verify & Preview** are described below.

When HTTP Basic credentials are saved, AgentWelcome also sends them with WordPress's own background request to `wp-cron.php` (and only to this site), so scheduled refreshes keep running on a protected site. Without credentials, WP-Cron cannot reach a site that asks for them, and background work waits until something else calls `wp-cron.php`.

HTTP Basic credentials are stored encrypted with a key derived from the site's auth salt. To keep them out of the database, define them in `wp-config.php`:

```php
define( 'AGENTWELCOME_HTTP_AUTH_USER', 'user' );
define( 'AGENTWELCOME_HTTP_AUTH_PASSWORD', 'password' );
```

## llms.txt

AgentWelcome > llms.txt has three cards. These settings are saved apart from the main settings and are removed on uninstall when "Delete all data on uninstall" is ticked.

- **The llms.txt file.** Choose a mode. *Automatic* lists your pages, as before. *Custom* serves exactly the text you write. *Hybrid* serves your text first, then the generated list of pages (leave the list out of your text). You can type the text or load it from a `.txt` or `.md` file on your computer. The file is read in the browser and nothing is uploaded. Notes appear when the text has no title line, links to other sites or is very large, but they never block saving. A custom text is capped at 200,000 bytes. An empty text serves the generated file. A collapsed preview shows what the file contains now.
- **llms-full.txt.** Off by default. It has the same three modes as llms.txt, with its own text: *Automatic* joins the stored pages, *Custom* serves exactly your text, and *Hybrid* puts your text first and then the stored pages, within the size limit. When on, `/llms-full.txt` joins the stored Markdown of every eligible page: a title, a `Source:` line with the page URL, then the body, without the metadata block. It reads only stored copies, so pages without one are left out until you generate them (Verify & Preview > All pages > Generate missing). The file stops at 5 MB (change it with `agentwelcome_llms_full_max_bytes`), includes only whole pages, and ends with a note saying how many were left out. When the option is off, the address returns the normal 404 page.
- **robots.txt.** Off by default. When on, WordPress's generated robots.txt gets comment lines such as `# llms.txt: https://example.com/llms.txt`. They add no allow or disallow rules, so crawler access does not change. They are skipped when the site asks search engines not to index it, and when a real `robots.txt` file exists on the server, because WordPress then does not generate one.

A real `llms.txt` or `llms-full.txt` file in the site root is served by the web server before WordPress runs, so AgentWelcome cannot replace it, and the page shows a notice. Delete or rename the file to use the generated one.

## Who can manage the llms.txt files

The llms.txt page (your own text, llms-full.txt and the robots.txt line) needs the `agentwelcome_manage_files` capability. Administrators get it the first time they open the admin after the plugin is active. If you take it away from the administrator role it is not added back. Give it to any role or user with a role editor plugin, or in code:

```php
get_role( 'editor' )->add_cap( 'agentwelcome_manage_files' );
```

A user who has this capability but not `manage_options` sees a menu with the llms.txt page only. Settings, Verify & Preview, the scan and the cache buttons stay with administrators. To use an existing capability instead, map it with the `agentwelcome_files_capability` filter, for example `add_filter( 'agentwelcome_files_capability', fn() => 'edit_pages' );`. The capability is removed from all roles and users when you delete the plugin with "Delete all data on uninstall" ticked.

## Change the llms.txt files with the REST API

A script or an AI tool can read and change the same settings as the llms.txt page through the REST route `/wp-json/agentwelcome/v1/llms`. It signs in with an [application password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/), and the user needs the `agentwelcome_manage_files` capability. Application passwords need HTTPS.

### 1. Give the capability

Pick one of these. All of them can be undone later.

**To one user only (recommended).** Create a user, for example `ai-editor`. A role that can sign in is enough, such as Editor. Then add the capability to that user:

- With a role editor plugin: open the user's capabilities and add the custom capability `agentwelcome_manage_files`.
- With WP-CLI: `wp user add-cap ai-editor agentwelcome_manage_files`
- With code, run once: `get_user_by( 'login', 'ai-editor' )->add_cap( 'agentwelcome_manage_files' );`

**To a whole role.** Every user with that role gets it, so use this only when that is what you want.

- With a role editor plugin: open the role and add the custom capability.
- With WP-CLI: `wp cap add editor agentwelcome_manage_files`
- With code, run once: `get_role( 'editor' )->add_cap( 'agentwelcome_manage_files' );`

**With an existing capability.** To allow everyone who has a capability WordPress already knows, map it with a filter, for example `add_filter( 'agentwelcome_files_capability', fn() => 'edit_pages' );`. This also changes who can open the llms.txt page.

### 2. Create an application password

Sign in as the user, open Users > Profile, and under Application Passwords enter a name and click Add. Copy the password, because WordPress shows it only once. Create one for each person or tool, so you can revoke one without touching the others.

### 3. Read the settings

```bash
curl -s -u "ai-editor:application-password" https://example.com/wp-json/agentwelcome/v1/llms
```

The response is JSON:

```json
{
  "mode": "custom",
  "custom": "# My site\n\nWelcome.",
  "full": true,
  "full_mode": "custom",
  "full_custom": "# My site (full)\n\nWelcome.",
  "robots_line": false,
  "notes": [],
  "full_notes": [],
  "max_bytes": 200000
}
```

- `mode` and `custom` are for llms.txt. `full`, `full_mode` and `full_custom` are for llms-full.txt, and `robots_line` is the robots.txt option.
- `mode` and `full_mode` are `automatic`, `custom` or `hybrid`. The text is used only in `custom` and `hybrid` mode.
- `notes` and `full_notes` are the warnings the llms.txt page shows: `no_title`, `external` and `large`. They never stop a save.

### 4. Change the settings

Send a POST (or PUT) with JSON that holds only the fields to change. A field that is not sent keeps its value.

```bash
# Only llms.txt
curl -u "ai-editor:application-password" -X POST https://example.com/wp-json/agentwelcome/v1/llms \
  -H "Content-Type: application/json" \
  -d '{"mode":"custom","custom":"# My site\n\nWelcome."}'

# Only llms-full.txt
curl -u "ai-editor:application-password" -X POST https://example.com/wp-json/agentwelcome/v1/llms \
  -H "Content-Type: application/json" \
  -d '{"full":true,"full_mode":"custom","full_custom":"# My site (full)\n\nWelcome."}'
```

To send the text of files without escaping it by hand, build the JSON from the files:

```bash
python3 -c 'import json; print(json.dumps({"mode":"custom","custom":open("llms.txt",encoding="utf-8").read(),"full":True,"full_mode":"custom","full_custom":open("llms-full.txt",encoding="utf-8").read()}))' \
  | curl -u "ai-editor:application-password" -X POST https://example.com/wp-json/agentwelcome/v1/llms \
      -H "Content-Type: application/json" -d @-
```

- Each text is replaced completely, not merged, so always send the whole file. Read the current text first and keep a copy.
- The text is cleaned the same way as on the page. A text larger than 200,000 bytes is refused with a 413 error, and an unknown mode with a 400 error.
- The response holds the saved settings. Open `/llms.txt` and `/llms-full.txt` to check them. If you still see the old text, add `?t=123` to the address or wait a few minutes, because the site may cache it.
- A real `llms.txt` or `llms-full.txt` file in the site root is served by the web server before WordPress runs. Do not upload one for this.

### 5. Test the access

Read the settings as in step 3 and look at the HTTP status.

| Result | Meaning |
| --- | --- |
| 200 and the settings | The user has the capability. |
| 403 | The user is signed in but does not have the capability. A different user without it must get this. |
| 401 | The username or application password is wrong, or application passwords are not available. |

To see what WordPress has for the user, open `/wp-json/wp/v2/users/me?context=edit` with the same login and look for `agentwelcome_manage_files` in the capabilities.

If you get a 403 although the capability is set, the host or a firewall may be blocking the request because of an unusual User-Agent. Send a normal one, such as the one `curl` sends.

### 6. Take the access away

Remove the capability (`wp user remove-cap ai-editor agentwelcome_manage_files`, or `wp cap remove editor agentwelcome_manage_files` for a role) and revoke the application password under Users > Profile.

## Verify & Preview

AgentWelcome > Verify & Preview has two tabs.

**One page.** Enter a page URL (the field starts with your homepage address). AgentWelcome reads the page now, builds fresh Markdown with your saved settings, and shows:

- a verdict: **In sync**, **Out of date** (with the number of lines that differ), **Not stored yet**, or **Not served** with the reason;
- a rendered **Preview** (an approximation, made without any library and without HTML from the page), the **Raw Markdown** with a copy button, the **Changes** between the stored copy and the fresh Markdown, and **Page text**, which checks that the text blocks of the content area are in the Markdown and lists any that were lost, and the text your exclusions removed on purpose;
- an **Update stored copy** (or **Create stored copy**) button when the copy is out of date or missing.

You can also try a different content selector or extra exclusions without saving them; the comparison then shows what would change if you saved them. The edit screen's AgentWelcome box has a **Check against the page** link for administrators.

Pages with content that changes on every load, such as the time, can look out of date each time. Use the `agentwelcome_compare_ignore` filter, which returns regular expressions for text to leave out of the comparison.

**All pages.** Start a scan and AgentWelcome checks every stored page in the background, a few at a time, and reports how many are in sync and which differ, with a Compare link for each. The scan changes nothing. On a site that disables WP-Cron, the open All pages tab runs the scan itself, a few pages at a time, and it pauses when you close the tab. **Update outdated** then queues a refresh for the pages that differ, and **Generate missing** builds the pages that have no stored copy yet.
