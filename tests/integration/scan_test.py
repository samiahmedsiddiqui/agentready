"""All pages scan: background run, report only, update outdated, stop, inactive, missing and REST."""
import html as htmllib
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
wpcli("user", "create", "kiteditor", "kiteditor@example.com", "--role=editor", "--user_pass=kit-pass-2")
editor = Client()
check("editor logged in", editor.login("kiteditor", "kit-pass-2"))
ALL = "/wp-admin/admin.php?page=agentwelcome-verify&tab=all"
ids = []


def tab():
    return admin.get(ALL)[2]


def nonce(page):
    m = re.search(r'name="action" value="agentwelcome_scan" />\s*<input[^>]*name="_wpnonce" value="([^"]+)"', page)
    return m.group(1) if m else ""


def act(action):
    page = tab()
    return admin.req("/wp-admin/admin-post.php", {"action": "agentwelcome_scan", "_wpnonce": nonce(page), "scan_action": action}, follow=False)


def state():
    return jphp("echo json_encode(get_option('agentwelcome_scan'));")


def finish():
    for _ in range(40):
        s = state()
        if not s or not s["running"]:
            return s
        wpcli("cron", "event", "run", "agentwelcome_scan_batch")
    return state()


def rows():
    return sum(int(v["n"]) for v in counts().values())


try:
    set_env(no_cron=True)
    wpcli("agentwelcome", "cache", "regenerate", "--all")
    N = rows()
    check("setup: a set of stored pages", N >= 10, N)

    print("--- before any scan")
    page = tab()
    check("the all pages tab explains the scan", "Check all pages" in page and "changes nothing until you press Update outdated" in page)
    check("there is a start button and no report", "Start scan" in page and "pages checked" not in page)
    check("the tab links are on the page", "tab=all" in page and "page=agentwelcome-verify" in page)

    print("--- a scan with nothing changed")
    code, h, _, _ = act("start")
    check("start redirects back with a notice", code == 302 and "tab=all" in h["location"] and "agentwelcome_notice=scan_started" in h["location"], h.get("location"))
    check("the scan is marked running and an event is scheduled", state()["running"] is True and len(events("agentwelcome_scan_batch")) == 1, state())
    page = admin.get(h["location"].replace(B, ""))[2]
    check("the page shows it is running, with a stop button and the notice", "Stop scan" in page and "Running since" in page and "The scan started" in page and 'data-running="1"' in page)
    s = finish()
    check("the scan finished by itself", s and s["running"] is False and s["finished"] >= s["started"] > 0, s)
    check("every stored page was checked", s["checked"] == N, (s["checked"], N))
    check("all in sync, nothing differs", s["in_sync"] == N and s["differs"] == [], s)
    page = tab()
    check("the report says so", "Every checked page matches its stored copy." in page and f"{N} pages checked" in page and f"{N} pages in sync" in page and "0 pages out of date" in page)
    check("a rescan button replaces start", "Scan again" in page and "Update outdated (" not in page)

    print("--- a changed page is reported, not changed")
    pid = int(wpcli("post", "create", "--post_type=page", "--post_title=Scan Probe", "--post_status=publish", "--post_content=<p>Old scan text.</p>", "--porcelain").strip().splitlines()[-1])
    ids.append(pid)
    wpcli("agentwelcome", "cache", "regenerate", str(pid))
    wpcli("post", "update", str(pid), "--post_content=<p>New scan text.</p><p>Another line.</p>")
    stored_before = row(pid)["markdown"]
    act("start")
    s = finish()
    check("the changed page is listed", [d["id"] for d in s["differs"]] == [pid], s["differs"])
    check("with its changed line count", s["differs"][0]["changed"] >= 2 and s["differs"][0]["title"] == "Scan Probe", s["differs"])
    check("nothing was changed by the scan", row(pid)["markdown"] == stored_before and row(pid)["state"] == "stale")
    page = tab()
    check("the report lists it with a compare link", "Pages that differ" in page and "Scan Probe" in page and "page=agentwelcome-verify" in page and ">Compare<" in page)
    check("the buttons offer to update it", "Update outdated (1)" in page)
    check("the volatile content hint is shown", "Pages with changing content" in page)

    print("--- update outdated")
    for hook in ("agentwelcome_generate_post",):
        wpcli("cron", "event", "delete", hook)
    code, h, _, _ = act("update_outdated")
    check("redirects with a queued notice and a count", code == 302 and "agentwelcome_notice=queued" in h["location"] and "agentwelcome_count=1" in h["location"], h.get("location"))
    check("the page notice reads right", "1 page is queued for regeneration." in admin.get(h["location"].replace(B, ""))[2])
    check("a refresh is scheduled for that page only", [e["args"] for e in events("agentwelcome_generate_post")] == [[pid]], events("agentwelcome_generate_post"))
    wpcli("cron", "event", "run", "agentwelcome_generate_post")
    check("running it brings the copy up to date", row(pid)["state"] == "fresh" and "New scan text." in row(pid)["markdown"])
    act("start")
    s = finish()
    check("a rescan is clean again", s["differs"] == [] and s["in_sync"] == s["checked"], s)

    print("--- stop")
    act("start")
    code, h, _, _ = act("stop")
    check("stop redirects with a notice", code == 302 and "agentwelcome_notice=scan_stopped" in h["location"], h.get("location"))
    s = state()
    check("the scan is no longer running and its event is gone", s["running"] is False and events("agentwelcome_scan_batch") == [], (s["running"], events("agentwelcome_scan_batch")))
    wpcli("cron", "event", "run", "agentwelcome_scan_batch")
    check("a stray event does nothing once stopped", state()["running"] is False)

    print("--- pages without a stored copy")
    pid2 = int(wpcli("post", "create", "--post_type=page", "--post_title=Scan Fresh", "--post_status=publish", "--post_content=<p>Never stored.</p>", "--porcelain").strip().splitlines()[-1])
    ids.append(pid2)
    act("start")
    s = finish()
    check("the report counts what is not stored", s["not_stored"] >= 1, s)
    page = tab()
    check("a Generate missing button appears", "Generate missing" in page and "not stored yet" in page)
    wpcli("option", "delete", "agentwelcome_batch")
    code, h, _, _ = act("generate_missing")
    check("it starts the background warm-up", code == 302 and "agentwelcome_notice=missing_started" in h["location"] and php("echo get_option('agentwelcome_batch') ? 'queued' : 'none';") == "queued", h.get("location"))
    wpcli("option", "delete", "agentwelcome_batch")

    print("--- pages read from the post content are counted")
    set_env(no_cron=True, no_loopback=True)
    act("start")
    s = finish()
    check("fallback reads are counted", s["fallback"] >= 1, s)
    check("and mentioned in the report", "post content" in tab())
    clear_env()
    set_env(no_cron=True)

    print("--- REST progress")
    hdr = {"X-WP-Nonce": admin.get("/wp-admin/admin-ajax.php?action=rest-nonce")[2].strip()}
    code, _, body, _ = admin.get("/wp-json/agentwelcome/v1/scan", headers=hdr)
    j = json.loads(body)
    check("progress for admins", code == 200 and set(j) == {"running", "checked", "in_sync", "differs"} and j["running"] is False, body)
    ehdr = {"X-WP-Nonce": editor.get("/wp-admin/admin-ajax.php?action=rest-nonce")[2].strip()}
    check("editors are refused", editor.get("/wp-json/agentwelcome/v1/scan", headers=ehdr)[0] == 403)
    check("anonymous visitors are refused", anon().get("/wp-json/agentwelcome/v1/scan")[0] in (401, 403))

    print("--- when the plugin is inactive")
    s0 = state()
    set_env(no_cron=True, type="staging")
    page = tab()
    check("the start button is disabled", re.search(r'value="start"[^>]*disabled', page) is not None or re.search(r'value="start"[^>]*disabled', page.replace("\n", " ")) is not None)
    code, h, _, _ = act("start")
    check("starting is refused with a notice", "agentwelcome_notice=inactive" in h["location"] and state()["started"] == s0["started"], h.get("location"))
    clear_env()

    print("--- permissions")
    code, _, body, _ = editor.req("/wp-admin/admin-post.php", {"action": "agentwelcome_scan", "_wpnonce": nonce(tab()), "scan_action": "start"})
    check("an editor cannot start a scan", code == 403 or "not allowed" in body.lower(), code)
    code, _, body, _ = admin.req("/wp-admin/admin-post.php", {"action": "agentwelcome_scan", "_wpnonce": "bad", "scan_action": "start"})
    check("a bad nonce is refused", code == 403 or "link you followed has expired" in body, code)
finally:
    clear_env()
    for i in ids:
        wpcli("post", "delete", str(i), "--force")
    wpcli("user", "delete", "kiteditor", "--yes")
    php("delete_option('agentwelcome_scan');")
    reset_state()
done("SCAN")
