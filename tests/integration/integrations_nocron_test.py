"""PR 5 behaviour with WP-Cron disabled: what still works at once, what waits, and what the notices say."""
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
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


def state(pid):
    r = row(pid)
    return r["state"] if r else None


def changes():
    return jphp("echo json_encode(get_option('agentwelcome_changes'));") or {}


def batch_running():
    return php("echo get_option('agentwelcome_batch') === false ? 'no' : 'yes';") == "yes"


def save_main(**fields):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome"}
    form.update({"agentwelcome_settings[%s]" % k: v for k, v in fields.items()})
    return admin.req("/wp-admin/options.php", form, follow=False)


try:
    ids = {s: create_page(s, "<p>Body of %s.</p>" % s, post_name=s) for s in ("nc-a", "nc-b")}
    for s in ids:
        md(s)
        php("do_action('agentwelcome_generate_post', %d);" % ids[s])
    set_env(no_cron=True, noindex_all=True)

    print("--- the site is in the no-cron state")
    check("the Status card says WP-Cron is disabled", "WP-Cron is disabled" in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])

    print("--- noindex works at once, without cron")
    wpcli("post", "meta", "update", str(ids["nc-a"]), "_yoast_wpseo_meta-robots-noindex", "1")
    check("a noindex page is left out of the .md and llms.txt straight away", md("nc-a")[0] != 200 and not listed("nc-a"), (md("nc-a")[0], listed("nc-a")))
    wpcli("post", "meta", "update", str(ids["nc-a"]), "_yoast_wpseo_meta-robots-noindex", "2")
    check("and returns straight away when set back", md("nc-a")[0] == 200 and listed("nc-a"))

    print("--- the setting toggle")
    save_main(show_meta_box="1")
    wpcli("post", "meta", "update", str(ids["nc-b"]), "_yoast_wpseo_meta-robots-noindex", "1")
    check("with the box off, a noindex page is served", md("nc-b")[0] == 200 and listed("nc-b"))
    save_main(show_meta_box="1", exclude_noindex="1")
    check("with the box on, it leaves again", md("nc-b")[0] != 200 and not listed("nc-b"), (md("nc-b")[0], listed("nc-b"), jphp("echo json_encode(get_option('agentwelcome_settings'));")))
    wpcli("post", "meta", "delete", str(ids["nc-b"]), "_yoast_wpseo_meta-robots-noindex")

    print("--- an SEO description change waits for cron")
    php("do_action('agentwelcome_generate_post', %d);" % ids["nc-a"])
    wpcli("post", "meta", "update", str(ids["nc-a"]), "_yoast_wpseo_metadesc", "A new description")
    time.sleep(12)
    md("nc-a")
    check("the copy is out of date and stays so without cron", state(ids["nc-a"]) == "stale", state(ids["nc-a"]))
    php("do_action('agentwelcome_generate_post', %d);" % ids["nc-a"])
    check("a manual refresh fixes it", state(ids["nc-a"]) == "fresh")

    print("--- change notices with cron disabled")
    wpcli("option", "delete", "agentwelcome_changes")
    php("do_action('activated_plugin', 'hello-dolly/hello.php');")
    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("the plugin notice still appears", "1 plugin was activated, deactivated or updated" in p, re.findall(r"\d plugins? w[^<]*", p)[:1])
    php("do_action('after_switch_theme');")
    p = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    check("a theme switch marks pages out of date", state(ids["nc-a"]) == "stale")
    time.sleep(12)
    check("and nothing refreshes them without cron", state(ids["nc-a"]) == "stale" and state(ids["nc-b"]) == "stale")
    claim = "is refreshing the stored Markdown of every page in the background" in p
    says_cron = "WP-Cron" in re.sub(r"\s+", " ", re.search(r'class="[^"]*notice[^"]*"[^>]*>.*?agentwelcome_dismiss', p, re.S).group(0)) if "agentwelcome_dismiss" in p else False
    check("the theme notice does not promise a background refresh that cannot run", (not claim) or says_cron, "claims refresh: %s, mentions cron: %s" % (claim, says_cron))
    scan = admin.get("/wp-admin/admin.php?page=agentwelcome-verify&tab=all")[2]
    check("Check all pages is offered and the open page drives it", "WP-Cron is disabled on this site." in scan)
finally:
    clear_env()
    reset_state()
done("INTEGRATIONS NO CRON")
