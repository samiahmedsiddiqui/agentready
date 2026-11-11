"""Uninstall: the table is always dropped, settings and per-page meta only when asked."""
from kit import *

wpcli("plugin", "activate", "agentwelcome")
reset_state()
site = anon()
TABLE = "global $wpdb; echo (int) ( $wpdb->get_var( \"SHOW TABLES LIKE '{$wpdb->prefix}agentwelcome_pages'\" ) !== null );"


def populate():
    site.get("/about.md")
    site.get("/hello-post.md")
    wpcli("post", "meta", "update", "12", "_agentwelcome_exclude", "1")
    wpcli("post", "meta", "update", "13", "_agentwelcome_description", "Kept description")
    php('global $wpdb; $wpdb->query("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (\'agentwelcome_lock_77\', \'1\', \'no\')"); update_option("agentwelcome_batch", array("last_id" => 3), false); update_option("agentwelcome_scan", array("running" => true), false); wp_schedule_single_event(time() + 900, "agentwelcome_scan_batch"); wp_schedule_single_event(time() + 600, "agentwelcome_generate_post", array(5)); wp_schedule_single_event(time() + 600, "agentwelcome_prune");')


def state():
    return {
        "table": php(TABLE),
        "version": php("echo get_option('agentwelcome_db_version') === false ? 'gone' : get_option('agentwelcome_db_version');"),
        "batch": php("echo get_option('agentwelcome_batch') === false ? 'gone' : 'there';"),
        "fingerprint": php("echo get_option('agentwelcome_site_fingerprint') === false ? 'gone' : 'there';"),
        "scan": php("echo get_option('agentwelcome_scan') === false ? 'gone' : 'there';"),
        "scan_events": len(events("agentwelcome_scan_batch")),
        "lock": php('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE \'agentwelcome\\_lock\\_%\'");'),
        "settings": php("echo get_option('agentwelcome_settings') === false ? 'gone' : 'there';"),
        "changes": php("echo get_option('agentwelcome_changes') === false ? 'gone' : 'there';"),
        "llms": php("echo get_option('agentwelcome_llms') === false ? 'gone' : 'there';"),
        "exclude_meta": php("echo count( get_posts( array( 'post_type' => 'any', 'meta_key' => '_agentwelcome_exclude', 'numberposts' => -1 ) ) );"),
        "description_meta": php("echo count( get_posts( array( 'post_type' => 'any', 'meta_key' => '_agentwelcome_description', 'numberposts' => -1 ) ) );"),
        "events": len(events("agentwelcome_generate_post")) + len(events("agentwelcome_prune")),
    }


def uninstall():
    wpcli("plugin", "deactivate", "agentwelcome")
    out = wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
    wpcli("plugin", "activate", "agentwelcome")
    return out


print("--- default: only the cache goes")
wpcli("option", "update", "agentwelcome_settings", '{"excluded_posts":"checkout","delete_data":false}', "--format=json")
populate()
before = state()
check("setup: table, version, batch, lock and events exist", before["table"] == "1" and before["batch"] == "there" and before["scan"] == "there" and before["scan_events"] == 1 and before["lock"] == "1" and before["events"] >= 1 and before["settings"] == "there", before)
php('global $wpdb; $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}agentwelcome_pages");')
php('delete_option("agentwelcome_db_version");')
wpcli("plugin", "deactivate", "agentwelcome")
wpcli("plugin", "activate", "agentwelcome")
check("the table is recreated by the version check, not by activation", php(TABLE) == "1" and php("echo get_option('agentwelcome_db_version');") == "1")
populate()
out = wpcli("plugin", "deactivate", "agentwelcome") + wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
after = state()
check("table dropped", after["table"] == "0", after)
check("db version, batch, locks and events removed", after["version"] == "gone" and after["batch"] == "gone" and after["fingerprint"] == "gone" and after["scan"] == "gone" and after["scan_events"] == 0 and after["lock"] == "0" and after["events"] == 0, after)
check("settings kept", after["settings"] == "there")
check("per-page choices kept", after["exclude_meta"] == "1" and after["description_meta"] == "1", after)

print("--- the plugin works again after reinstalling")
wpcli("plugin", "activate", "agentwelcome")
c, hh, b, _ = site.get("/about.md")
check("the table is created on the first load and the page is served", c == 200 and hh.get("x-agentwelcome-cache") == "MISS", (c, hh.get("x-agentwelcome-cache")))
check("and stored", row(5) is not None)

print("--- Delete all data on uninstall")
wpcli("option", "update", "agentwelcome_settings", '{"excluded_posts":"checkout","delete_data":true}', "--format=json")
populate()
wpcli("plugin", "deactivate", "agentwelcome")
wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
after = state()
check("table dropped", after["table"] == "0", after)
check("settings removed", after["settings"] == "gone", after)
check("per-page choices removed", after["exclude_meta"] == "0" and after["description_meta"] == "0", after)
check("everything else removed too", after["version"] == "gone" and after["batch"] == "gone" and after["fingerprint"] == "gone" and after["scan"] == "gone" and after["scan_events"] == 0 and after["lock"] == "0" and after["events"] == 0, after)

print("--- audit: every option populated through the settings form, box checked")
wpcli("plugin", "activate", "agentwelcome")
admin = Client()
check("admin logged in", admin.login())


def save_everything(delete):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {
        "option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome",
        "agentwelcome_settings[excluded_post_types][]": ["book"], "agentwelcome_settings[excluded_posts]": "checkout", "agentwelcome_settings[excluded_terms][]": ["1"],
        "agentwelcome_settings[home_markdown_url]": "index.html.md", "agentwelcome_settings[show_meta_box]": "1",
        "agentwelcome_settings[content_selector]": "main", "agentwelcome_settings[strip_selectors]": ".x-test",
        "agentwelcome_settings[http_auth_enabled]": "1", "agentwelcome_settings[http_auth_user]": "site", "agentwelcome_settings[http_auth_password]": "s3cr3t!",
        "agentwelcome_settings[allowed_environments][]": ["staging"], "agentwelcome_settings[allowed_urls]": "127.0.0.1",
    }
    if delete:
        form["agentwelcome_settings[delete_data]"] = "1"
    admin.req("/wp-admin/options.php", form)
    return jphp("echo json_encode(get_option('agentwelcome_settings'));")


for delete in (False, True):
    print("--- audit: delete_data " + ("ticked" if delete else "unticked"))
    saved = save_everything(delete)
    check("setup: all settings saved, box checked", saved["show_meta_box"] is True and saved["delete_data"] is delete and saved["home_markdown_url"] == "index.html.md" and saved["excluded_terms"] == [1] and saved["http_auth_user"] == "site" and saved["http_auth_password"].startswith("v1:") and saved["allowed_environments"] == ["staging"], saved)
    populate()
    wpcli("option", "update", "agentwelcome_changes", '{"plugins":{"count":2,"at":1}}', "--format=json")
    wpcli("option", "update", "agentwelcome_llms", '{"mode":"hybrid","custom":"# Mine","full":true,"robots_line":true}', "--format=json")
    wpcli("plugin", "deactivate", "agentwelcome")
    wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
    after = state()
    wpcli("plugin", "activate", "agentwelcome")
    check("cache table, db version, batch, locks and events always removed", after["table"] == "0" and after["version"] == "gone" and after["batch"] == "gone" and after["fingerprint"] == "gone" and after["scan"] == "gone" and after["changes"] == "gone" and after["scan_events"] == 0 and after["lock"] == "0" and after["events"] == 0, after)
    if delete:
        check("settings removed (homepage URL, terms, box, credentials, environments)", after["settings"] == "gone", after)
        check("per-page choices removed", after["exclude_meta"] == "0" and after["description_meta"] == "0", after)
        check("the llms.txt page settings are removed", after["llms"] == "gone", after)
    else:
        kept = jphp("echo json_encode(get_option('agentwelcome_settings'));")
        expected = dict(saved, http_auth_enabled=False, http_auth_user="", http_auth_password="")
        check("settings kept, with every value intact except the credentials", kept == expected, kept)
        check("saved HTTP Basic credentials are cleared even though the delete option is off", kept["http_auth_user"] == "" and kept["http_auth_password"] == "" and kept["http_auth_enabled"] is False, kept)
        check("the raw option holds no trace of the password or its user name", "v1:" not in php("echo serialize(get_option('agentwelcome_settings'));") and '"site"' not in php("echo wp_json_encode(get_option('agentwelcome_settings'));"))
        check("per-page choices kept", after["exclude_meta"] == "1" and after["description_meta"] == "1", after)
        check("the llms.txt page settings are kept", after["llms"] == "there" and jphp("echo json_encode(get_option('agentwelcome_llms'));") == {"mode": "hybrid", "custom": "# Mine", "full": True, "robots_line": True}, after)

wpcli("option", "delete", "agentwelcome_llms")
wpcli("plugin", "activate", "agentwelcome")
wpcli("post", "meta", "update", "12", "_agentwelcome_exclude", "1")
wpcli("post", "meta", "update", "13", "_agentwelcome_description", "Custom llms.txt description.")
reset_state()
done("UNINSTALL")
