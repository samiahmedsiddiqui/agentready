"""Noindex handling, SEO meta refresh, ACF options, Elementor saves, theme and plugin change notices."""
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
wpcli("user", "create", "kiteditor", "kiteditor@example.com", "--role=editor", "--user_pass=kit-pass-2")
editor = Client()
check("editor logged in", editor.login("kiteditor", "kit-pass-2"))
site = anon()
n = [0]
UPG = "require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php'; "


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def md(slug):
    return site.get("/%s.md%s" % (slug, bust()))


def listed(slug):
    return "/%s.md" % slug in site.get("/llms.txt" + bust())[2]


def in_full(slug):
    return "/%s/" % slug in site.get("/llms-full.txt" + bust())[2]


def state(pid):
    r = row(pid)
    return r["state"] if r else None


def batch_running():
    return php("echo get_option('agentwelcome_batch') === false ? 'no' : 'yes';") == "yes"


def clear_batch():
    php("delete_option('agentwelcome_batch'); wp_unschedule_hook('agentwelcome_regenerate_batch');")


def fire(code, pid):
    """Runs PHP and, in the same process, reads the state of one stored page and whether a refresh is running. A cron run cannot slip in between."""
    out = php(code + " global $wpdb; $s = $wpdb->get_var( $wpdb->prepare( \"SELECT state FROM {$wpdb->prefix}agentwelcome_pages WHERE post_id = %d\", " + str(pid) + " ) ); echo json_encode( array( 'state' => $s, 'running' => get_option( 'agentwelcome_batch' ) !== false ) );")
    return json.loads(out.splitlines()[-1])


def changes():
    return jphp("echo json_encode(get_option('agentwelcome_changes'));") or {}


def save_main(**fields):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome"}
    form.update({"agentwelcome_settings[%s]" % k: v for k, v in fields.items()})
    return admin.req("/wp-admin/options.php", form, follow=False)


try:
    ids = {}
    for slug in ("integ-a", "integ-b", "integ-c", "integ-d", "integ-e"):
        ids[slug] = create_page(slug, "<p>Body of %s.</p>" % slug, post_name=slug)
        check("page %s is served" % slug, md(slug)[0] == 200)

    print("--- noindex: the setting")
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("the box is ticked by default", re.search(r'name="agentwelcome_settings\[exclude_noindex\]"[^>]*checked', page) is not None)
    check("with an explanation", "Leave out pages set to noindex" in page and "Only the setting on the page itself is read" in page)

    print("--- noindex: nothing happens when no SEO plugin is active")
    wpcli("post", "meta", "update", str(ids["integ-a"]), "_yoast_wpseo_meta-robots-noindex", "1")
    check("a leftover value of a plugin that is not active is ignored", md("integ-a")[0] == 200 and listed("integ-a"))

    print("--- noindex: each plugin's own value")
    set_env(noindex_all=True)
    cases = [
        ("Yoast noindex", "_yoast_wpseo_meta-robots-noindex", "1", True),
        ("Yoast index", "_yoast_wpseo_meta-robots-noindex", "2", False),
        ("SEOPress noindex", "_seopress_robots_index", "yes", True),
        ("The SEO Framework noindex", "_genesis_noindex", "1", True),
        ("The SEO Framework index", "_genesis_noindex", "-1", False),
    ]
    slug = "integ-b"
    for label, key, value, excluded in cases:
        wpcli("post", "meta", "delete", str(ids[slug]), "_yoast_wpseo_meta-robots-noindex")
        wpcli("post", "meta", "delete", str(ids[slug]), "_seopress_robots_index")
        wpcli("post", "meta", "delete", str(ids[slug]), "_genesis_noindex")
        wpcli("post", "meta", "update", str(ids[slug]), key, value)
        check(label + ": " + ("left out" if excluded else "kept"), (md(slug)[0] != 200 and not listed(slug) and not in_full(slug)) if excluded else (md(slug)[0] == 200 and listed(slug)), md(slug)[0])
    wpcli("post", "meta", "delete", str(ids[slug]), "_genesis_noindex")
    php("update_post_meta(%d, 'rank_math_robots', array('index'));" % ids[slug])
    check("Rank Math index: kept", md(slug)[0] == 200)
    php("update_post_meta(%d, 'rank_math_robots', array('noindex','nofollow'));" % ids[slug])
    check("Rank Math noindex: left out", md(slug)[0] != 200 and not listed(slug))
    wpcli("post", "meta", "delete", str(ids[slug]), "rank_math_robots")
    check("meta removed: back in the list", md(slug)[0] == 200 and listed(slug))

    print("--- noindex: effects")
    slug = "integ-c"
    wpcli("post", "meta", "update", str(ids[slug]), "_yoast_wpseo_meta-robots-noindex", "1")
    php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    check("the stored copy of a noindex page is deleted when it is refreshed", row(ids[slug]) is None, row(ids[slug]))
    reason = admin.get("/wp-admin/post.php?post=%d&action=edit" % ids[slug])[2]
    check("the editor box explains why", "marks the page as noindex" in reason, re.findall(r"[^.]*noindex[^.]*\.", reason)[:2])
    r = save_main(show_meta_box="1")
    check("unticking is the absence of the box: saving without it turns the setting off", r[0] == 302 and jphp("echo json_encode(get_option('agentwelcome_settings')['exclude_noindex']);") is False)
    check("noindex pages are served again", md(slug)[0] == 200 and listed(slug))
    check("and the refresh to generate them was started", batch_running())
    clear_batch()
    r = save_main(show_meta_box="1", exclude_noindex="1")
    check("ticking it again leaves them out", md(slug)[0] != 200 and not listed(slug))
    check("and schedules the cleanup of stored copies", len(events("agentwelcome_prune")) >= 1)
    wpcli("post", "meta", "delete", str(ids[slug]), "_yoast_wpseo_meta-robots-noindex")
    clear_env()

    set_env(no_cron=True)
    print("--- SEO meta changes refresh the page")
    slug = "integ-d"
    md(slug)
    check("setup: stored and fresh", state(ids[slug]) == "fresh", state(ids[slug]))
    wpcli("post", "meta", "update", str(ids[slug]), "_yoast_wpseo_metadesc", "A new description")
    check("changing a Yoast description marks the page out of date", state(ids[slug]) == "stale", state(ids[slug]))
    check("and queues a refresh", any(str(ids[slug]) in str(e.get("args")) for e in events("agentwelcome_generate_post")))
    php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    check("the refresh makes it fresh again", state(ids[slug]) == "fresh")
    for key in ("rank_math_description", "_aioseo_description", "_seopress_titles_desc", "_genesis_description"):
        php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
        wpcli("post", "meta", "update", str(ids[slug]), key, "Text for " + key)
        check(key + " change marks it out of date", state(ids[slug]) == "stale", state(ids[slug]))
    php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    wpcli("post", "meta", "update", str(ids[slug]), "some_other_meta", "x")
    check("an unrelated meta change does not", state(ids[slug]) == "fresh", state(ids[slug]))

    print("--- ACF options pages")
    for slug in ("integ-a", "integ-d", "integ-e"):
        md(slug)
        php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    clear_batch()
    for target in ("user_5", "category_7", 123, "new_post"):
        php("do_action('acf/save_post', %s);" % (repr(target) if isinstance(target, str) else target))
    check("saving a post, user or term does not refresh everything", not batch_running() and state(ids["integ-a"]) == "fresh")
    php("do_action('acf/save_post', 'options');")
    check("saving an options page marks every page out of date", state(ids["integ-a"]) == "stale" and state(ids["integ-e"]) == "stale", (state(ids["integ-a"]), state(ids["integ-e"])))
    check("and starts a background refresh", batch_running())
    clear_batch()
    for slug in ("integ-a", "integ-e"):
        md(slug)
    php("do_action('agentwelcome_generate_post', %d);" % ids["integ-a"])
    set_env(enabled=False, no_cron=True)
    php("do_action('acf/save_post', 'options');")
    check("nothing happens while AgentWelcome is switched off", not batch_running())
    set_env(no_cron=True)

    print("--- Elementor")
    for slug in ("integ-a", "integ-e"):
        php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    clear_batch()
    doc = "new class { public function get_main_id() { return %d; } }"
    php("do_action('elementor/document/after_save', %s, array());" % (doc % ids["integ-e"]))
    check("saving a page in Elementor refreshes just that page", state(ids["integ-e"]) == "stale" and state(ids["integ-a"]) == "fresh" and not batch_running(), (state(ids["integ-e"]), state(ids["integ-a"])))
    php("do_action('agentwelcome_generate_post', %d);" % ids["integ-e"])
    php("register_post_type('elementor_library', array('public' => false)); $t = wp_insert_post(array('post_type' => 'elementor_library', 'post_title' => 'integ-template', 'post_status' => 'publish')); do_action('elementor/document/after_save', new class($t) { public function __construct(private int $id) {} public function get_main_id() { return $this->id; } }, array()); wp_delete_post($t, true);")
    check("saving a saved template refreshes every page", state(ids["integ-a"]) == "stale" and batch_running(), (state(ids["integ-a"]), batch_running()))
    clear_batch()
    php("do_action('elementor/document/after_save', 'not a document', array()); do_action('elementor/document/after_save', new class { public function get_main_id() { return 99999999; } }, array());")
    check("a bad or unknown document is ignored", not batch_running())

    clear_env()
    print("--- theme and plugin changes")
    wpcli("option", "delete", "agentwelcome_changes")
    for slug in ("integ-a", "integ-e"):
        md(slug)
        php("do_action('agentwelcome_generate_post', %d);" % ids[slug])
    clear_batch()
    php("do_action('activated_plugin', 'agentwelcome/agentwelcome.php'); do_action('deactivated_plugin', 'agentwelcome/agentwelcome.php');")
    check("activating AgentWelcome itself is not a change", "plugins" not in changes(), changes())
    php("do_action('activated_plugin', 'hello-dolly/hello.php');")
    php("do_action('deactivated_plugin', 'akismet/akismet.php');")
    c = changes()
    check("plugin activations are counted, not acted on", c.get("plugins", {}).get("count") == 2 and "refresh" not in c and not batch_running(), c)
    php(UPG + "do_action('upgrader_process_complete', null, array('action' => 'update', 'type' => 'plugin', 'plugins' => array('a/a.php', 'agentwelcome/agentwelcome.php', 'b/b.php')));")
    check("plugin updates add to the count, not counting AgentWelcome", changes()["plugins"]["count"] == 4, changes())
    php(UPG + "do_action('upgrader_process_complete', null, array('action' => 'install', 'type' => 'plugin', 'plugins' => array('c/c.php')));")
    check("installs are not counted", changes()["plugins"]["count"] == 4)
    check("no page was marked out of date by plugin changes", state(ids["integ-a"]) == "fresh")

    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("a notice on the settings page says 4 plugins changed", "4 plugins were activated, deactivated or updated" in p, re.findall(r"\d plugins? w[^<]*", p)[:1])
    check("it links to Check all pages", "page=agentwelcome-verify&#038;tab=all" in p or "page=agentwelcome-verify&amp;tab=all" in p)
    check("the notice also shows on the llms.txt and Verify pages", all("plugins were activated" in admin.get(u)[2] for u in ("/wp-admin/admin.php?page=agentwelcome-llms", "/wp-admin/admin.php?page=agentwelcome-verify")))

    r = fire("do_action('after_switch_theme');", ids["integ-a"])
    c = changes()
    check("a theme switch is recorded", c["refresh"]["reason"] == "theme", c)
    check("and marks every page out of date with a refresh started", r["state"] == "stale" and r["running"], r)
    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("the notice says the theme changed and pages are refreshing", "The active theme changed. AgentWelcome is refreshing the stored Markdown of every page in the background." in p)
    clear_batch()
    php("do_action('customize_save_after');")
    check("saving the Customizer is recorded", changes()["refresh"]["reason"] == "customizer")
    clear_batch()
    theme = php("echo get_stylesheet();")
    r = fire(UPG + "do_action('upgrader_process_complete', null, array('action' => 'update', 'type' => 'theme', 'themes' => array('some-other-theme')));", ids["integ-a"])
    check("updating a different theme is not a change", changes()["refresh"]["reason"] == "customizer" and not r["running"], r)
    r = fire(UPG + "do_action('upgrader_process_complete', null, array('action' => 'update', 'type' => 'theme', 'themes' => array('%s')));" % theme, ids["integ-a"])
    check("updating the active theme is", changes()["refresh"]["reason"] == "theme_update" and r["running"], r)
    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("with its own wording", "The active theme was updated. AgentWelcome is refreshing" in p)
    clear_batch()

    print("--- dismissing")
    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    links = re.findall(r'href="([^"]*agentwelcome_dismiss[^"]*)"', p)
    links = [l.replace("&#038;", "&").replace("&amp;", "&") for l in links]
    check("each notice has a Dismiss link", len(links) == 2, links)
    refresh_link = [l for l in links if "key=refresh" in l][0]
    bad = admin.req(refresh_link.replace(B, "").replace("_wpnonce=", "_wpnonce=x"), follow=False)
    check("a bad token is refused", bad[0] == 403 and "refresh" in changes(), bad[0])
    eds = editor.req(refresh_link.replace(B, ""), follow=False)
    check("an editor cannot dismiss", eds[0] in (302, 403) and "refresh" in changes(), eds[0])
    ok = admin.req(refresh_link.replace(B, ""), follow=False)
    check("an administrator dismisses it and returns to the page", ok[0] == 302 and "refresh" not in changes() and "plugins" in changes(), (ok[0], changes()))
    check("the other notice stays", "plugins were activated" in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])
    plugin_link = [l for l in links if "key=plugins" in l][0]
    admin.req(plugin_link.replace(B, ""), follow=False)
    check("and can be dismissed too", not changes())
    check("no notices are left", "agentwelcome_dismiss" not in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])

    print("--- nothing recorded while AgentWelcome is switched off")
    set_env(enabled=False)
    php("do_action('after_switch_theme'); do_action('activated_plugin', 'x/x.php');")
    check("no theme or plugin notice, no refresh", not changes() and not batch_running(), changes())
    clear_env()
finally:
    clear_env()
    wpcli("user", "delete", "kiteditor", "--yes")
    reset_state()
done("INTEGRATIONS")
