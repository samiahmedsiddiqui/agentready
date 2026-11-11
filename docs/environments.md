# Environments

Where the plugin runs, and how copies and moves of a site are handled.

AgentReady is active on production, and when the environment type is not set (WordPress treats that as production). Tick staging, development or local on the Environment tab to run there too. Set the type with `WP_ENVIRONMENT_TYPE`; many hosts do it for you.

When the plugin is inactive, `.md` and `/llms.txt` return the normal 404 page, no discovery tag is added, and nothing is scheduled. The settings page says why, and Verify & Preview still works.

Two constants in `wp-config.php` take priority over the settings:

```php
// Switch the plugin off completely.
define( 'AGENTREADY_ENABLED', false );

// Only run when the site's host matches one of these. * matches anything.
define( 'AGENTREADY_ALLOWED_URLS', 'www.example.com, *.example.org' );
```

Use the constant for the allowed URLs on hosts that copy a site to staging and replace the domain inside the database, because that can rewrite the setting too.

**Copies and moves pause AgentReady by themselves.** The first time it runs, AgentReady saves a hash of the site address (the host and path, ignoring `http` or `https`, `www.` and a trailing slash). Because it is a hash, a host's search-and-replace cannot find and rewrite it. When the address no longer matches, as on a staging copy or after a move, AgentReady pauses itself and shows a banner with a **Resume on this address** button for administrators. A real domain change shows the same banner and costs one click. An `AGENTREADY_ALLOWED_URLS` constant overrides this check, and `add_filter( 'agentready_check_site_address', '__return_false' );` turns it off. To resume from the command line, run `wp eval 'AgentReady\Environment\SiteAddress::remember();'`.
