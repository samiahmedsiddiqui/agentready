# Hooks

Filters and examples for changing what the plugin does from code.

`llms.txt` and `llms-full.txt` are kept for up to five minutes, so a filter that changes which pages they list or how they read can take that long to show.

| Filter | Purpose |
| --- | --- |
| `agentwelcome_is_eligible` | Exclude a post programmatically. Receives `$eligible` and the `WP_Post`. |
| `agentwelcome_is_noindex` | Whether a page counts as noindex. Receives the result and the `WP_Post`. |
| `agentwelcome_noindex_sources` | Which SEO plugins' noindex settings are read, as meta key => active. |
| `agentwelcome_excluded_post_types` | Add or remove excluded post types in code. |
| `agentwelcome_show_meta_box` | Show or hide the editor box. Receives the setting and the post type. |
| `agentwelcome_resolve_post` | Map a request path to a post. |
| `agentwelcome_use_loopback` | Return `false` to skip fetching the page and use the post content. |
| `agentwelcome_loopback_timeout` | Seconds to wait for the page request (default 15). |
| `agentwelcome_files_capability` | Capability that is checked for the llms.txt page (default `agentwelcome_manage_files`). |
| `agentwelcome_max_concurrent_renders` | Pages that may be rendered at the same time for visitors (default 3). |
| `agentwelcome_strip_selectors` | CSS selectors for elements to remove. |
| `agentwelcome_markdown` | Final Markdown for a page. |
| `agentwelcome_description` | The description used in the metadata and `llms.txt`. |
| `agentwelcome_meta_description_keys` | Post meta keys read as the SEO meta description, in order. |
| `agentwelcome_check_site_address` | Return `false` to turn off the automatic pause when the site address changes. |
| `agentwelcome_compare_ignore` | Regular expressions for text that Verify & Preview and the scan leave out when comparing. |
| `agentwelcome_llms_txt` | Final `llms.txt` content. |
| `agentwelcome_llms_full_txt` | Final `llms-full.txt` content. |
| `agentwelcome_llms_full_max_bytes` | Size limit of `llms-full.txt` (default 5,000,000 bytes). |
| `agentwelcome_llms_max_items` | Entries per post type in `llms.txt` (default 500). |
| `agentwelcome_content_type` | Content type of the Markdown response. |
| `agentwelcome_response_headers` | Response headers. |

### Excluding from code

```php
// Leave out one page.
add_filter( 'agentwelcome_is_eligible', function ( $eligible, $post ) {
	return 'private-docs' === $post->post_name ? false : $eligible;
}, 10, 2 );

// Leave out a whole post type.
add_filter( 'agentwelcome_excluded_post_types', function ( $types ) {
	return array_merge( $types, array( 'product' ) );
} );

// Hide the editor box for everyone.
add_filter( 'agentwelcome_show_meta_box', '__return_false' );
```
