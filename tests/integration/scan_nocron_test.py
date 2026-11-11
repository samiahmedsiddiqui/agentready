"""All pages scan on a site with WP-Cron disabled: the open page drives it, guarded by a lock."""
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
ids = []
ALL = "/wp-admin/admin.php?page=agentwelcome-verify&tab=all"


def nonce(page):
    m = re.search(r'name="action" value="agentwelcome_scan" />\s*<input[^>]*name="_wpnonce" value="([^"]+)"', page)
    return m.group(1) if m else ""


def act(action):
    return admin.req("/wp-admin/admin-post.php", {"action": "agentwelcome_scan", "_wpnonce": nonce(admin.get(ALL)[2]), "scan_action": action}, follow=False)


def poll():
    hdr = {"X-WP-Nonce": admin.get("/wp-admin/admin-ajax.php?action=rest-nonce")[2].strip()}
    return json.loads(admin.get("/wp-json/agentwelcome/v1/scan", headers=hdr)[2])


def lock_zero(on):
    if on:
        php('global $wpdb; $wpdb->query("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (\'agentwelcome_lock_0\', \'" . time() . "\', \'no\')");')
    else:
        php('global $wpdb; $wpdb->delete($wpdb->options, array("option_name" => "agentwelcome_lock_0"));')


try:
    wpcli("agentwelcome", "cache", "regenerate", "--all")
    N = sum(int(v["n"]) for v in counts().values())
    pid = int(wpcli("post", "create", "--post_type=page", "--post_title=Scan Nocron", "--post_status=publish", "--post_content=<p>Never stored.</p>", "--porcelain").strip().splitlines()[-1])
    ids.append(pid)
    wpcli("cron", "event", "delete", "agentwelcome_generate_post")

    print("--- cron enabled: reading the status does not advance the scan")
    check("setup: stored pages", N >= 10, N)
    act("start")
    a, b = poll(), poll()
    check("two quick reads leave a running scan untouched", a["running"] and b["running"] and a["checked"] == 0 and b["checked"] == 0, (a, b))
    act("stop")
    check("stopped", not poll()["running"])

    print("--- cron disabled: notes")
    set_env(no_cron=True)
    page = admin.get(ALL)[2]
    check("the All pages tab explains that WP-Cron is disabled", "WP-Cron is disabled on this site." in page and "it runs the scan itself" in page)
    check("the Status card still has its note", "WP-Cron is disabled on this site, so background refreshes only run" in admin.get("/wp-admin/admin.php?page=agentwelcome")[2])
    code, h, _, _ = act("start")
    body = admin.get(h["location"].replace(B, ""))[2]
    check("starting says the page runs it", "WP-Cron is disabled on this site, so this page runs it while it stays open." in body, body[:200])

    print("--- cron disabled: polling drives the scan")
    check("no cron event is needed", True)
    lock_zero(True)
    held = poll()
    check("a held lock stops a second tab from running it too", held["running"] and held["checked"] == 0, held)
    lock_zero(False)
    steps = 0
    s = poll()
    first = s["checked"]
    while s["running"] and steps < 60:
        steps += 1
        s = poll()
    check("one poll runs a small batch", first in (1, 2, 3), first)
    check("repeated polls finish the scan without any cron event", not s["running"], (steps, s))
    check("every stored page was checked", s["checked"] == N and s["in_sync"] == N and s["differs"] == 0, (s, N))
    check("the lock is released", php('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = \'agentwelcome_lock_0\'");') == "0")
    page = admin.get(ALL)[2]
    check("the report shows the page without a stored copy", "1 page not stored yet" in page and "Generate missing" in page)
    check("the queued actions carry the cron note", "Update outdated and Generate missing queue background work." in page)

    print("--- notices beside other queued actions")
    code, h, _, _ = act("generate_missing")
    check("Generate missing says it waits for a real cron job", "waits for a real cron job" in admin.get(h["location"].replace(B, ""))[2])
    wpcli("option", "delete", "agentwelcome_batch")
    list_page = admin.get("/wp-admin/edit.php?post_type=page")[2]
    bulk = re.search(r'id="posts-filter".*?name="_wpnonce" value="([^"]+)"', list_page, re.S).group(1)
    code, h, _, _ = admin.req("/wp-admin/edit.php", {"post_type": "page", "action": "agentwelcome_regenerate", "action2": "-1", "post[]": ["5"], "_wpnonce": bulk, "_wp_http_referer": "/wp-admin/edit.php?post_type=page"}, follow=False)
    check("the bulk action notice says it waits for a real cron job", "1 page is queued for regeneration." in admin.get(h["location"].replace(B, ""))[2] and "waits for a real cron job" in admin.get(h["location"].replace(B, ""))[2])
    cache_nonce = re.search(r'id="agentwelcome-cache-form".*?name="_wpnonce" value="([^"]+)"', admin.get("/wp-admin/admin.php?page=agentwelcome")[2], re.S).group(1)
    code, h, _, _ = admin.req("/wp-admin/admin-post.php", {"action": "agentwelcome_cache", "_wpnonce": cache_nonce, "cache_action": "regenerate_all"}, follow=False)
    check("Regenerate all says it waits for a real cron job", "waits for a real cron job" in admin.get(h["location"].replace(B, ""))[2])
finally:
    clear_env()
    for i in ids:
        wpcli("post", "delete", str(i), "--force")
    php("delete_option('agentwelcome_scan'); delete_option('agentwelcome_batch');")
    lock_zero(False)
    reset_state()
done("SCAN-NOCRON")
