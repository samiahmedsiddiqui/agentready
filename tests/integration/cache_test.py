"""Stored Markdown: miss, hit, stale, version change, lock and 503, invalidation, fallback, batches."""
import calendar, hashlib, threading
from kit import *

reset_state()
set_env(no_cron=True)
site = anon()
VERSION = re.search(r"Version:\s+(\S+)", open(os.path.join(WP, "wp-content/plugins/agentwelcome/agentwelcome.php")).read()).group(1)
CACHE = "x-agentwelcome-cache"


def md(path):
    code, h, body, took = site.get(path)
    return code, h.get(CACHE), body, took, h


def lock(pid, age=0):
    php('global $wpdb; $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, \'no\')", "agentwelcome_lock_' + str(pid) + '", (string) (time() - ' + str(age) + ')));')


def unlock(pid):
    php('global $wpdb; $wpdb->delete($wpdb->options, array("option_name" => "agentwelcome_lock_' + str(pid) + '"));')


def lock_row(pid):
    return php('global $wpdb; echo (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = \'agentwelcome_lock_' + str(pid) + '\'");')


def run_event(hook):
    wpcli("cron", "event", "run", hook)


print("--- miss, then hit")
pid = create_page("Cache Probe", "<h2>Original heading</h2><p>Original body.</p>")
code, state, body, _, h = md("/cache-probe.md")
check("first request generates (MISS)", code == 200 and state == "MISS", (code, state))
check("served the page", "Original heading" in body and body.startswith("---"))
r = row(pid)
check("row stored fresh from the loopback", r and r["state"] == "fresh" and r["source"] == "loopback", r)
check("row carries the plugin version", r and r["generator_version"] == VERSION, r and r["generator_version"])
check("content hash matches the Markdown", r and r["content_hash"] == hashlib.sha1(r["markdown"].encode()).hexdigest())
check("stored Markdown is what was served", r and r["markdown"] == body)
check("generated_at is a UTC datetime", r and re.match(r"\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$", r["generated_at"]) is not None)
code, state, body2, _, h = md("/cache-probe.md")
check("second request is a HIT", code == 200 and state == "HIT", state)
check("same body", body2 == body)
check("headers kept", "text/markdown" in h["content-type"] and h.get("x-robots-tag") == "noindex" and "etag" in h)
c, hh, b, _ = site.get("/cache-probe.md", method="HEAD")
check("HEAD has headers and no body", c == 200 and hh.get(CACHE) == "HIT" and b == "")
etag = h["etag"]
c, _, _, _ = site.get("/cache-probe.md", headers={"If-None-Match": etag})
check("conditional request still gets 304", c == 304, c)

print("--- editing marks it stale, serves the old copy, then refreshes")
php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \'%cron%\' AND 1=0");')
for h_ in ("agentwelcome_generate_post",):
    wpcli("cron", "event", "delete", h_)
wpcli("post", "update", str(pid), "--post_content=<h2>Changed heading</h2><p>Changed body.</p>")
check("row marked stale on save", row(pid)["state"] == "stale", row(pid)["state"])
ev = [e for e in events("agentwelcome_generate_post") if e["args"] == [pid]]
check("one background event scheduled", len(ev) == 1, ev)
wpcli("post", "update", str(pid), "--post_content=<h2>Changed heading</h2><p>Changed body.</p>")
wpcli("post", "update", str(pid), "--post_content=<h2>Changed heading</h2><p>Changed body.</p>")
ev = [e for e in events("agentwelcome_generate_post") if e["args"] == [pid]]
check("repeated saves keep a single event", len(ev) == 1, ev)
code, state, body, _, _ = md("/cache-probe.md")
check("stale copy is served (STALE) with the old content", state == "STALE" and "Original heading" in body and "Changed heading" not in body, (state, body[:200]))
run_event("agentwelcome_generate_post")
r = row(pid)
check("background job regenerated it", r["state"] == "fresh" and "Changed heading" in r["markdown"], r["state"])
code, state, body, _, _ = md("/cache-probe.md")
check("new content served as a HIT", state == "HIT" and "Changed heading" in body)
check("no event left over", [e for e in events("agentwelcome_generate_post") if e["args"] == [pid]] == [])

print("--- a plugin version change regenerates on demand")
php('global $wpdb; $wpdb->update($wpdb->prefix."agentwelcome_pages", array("generator_version" => "0.0.1"), array("post_id" => %d));' % pid)
code, state, body, _, _ = md("/cache-probe.md")
check("outdated copy is rebuilt in the request (MISS)", state == "MISS", state)
check("row now has the current version", row(pid)["generator_version"] == VERSION)

print("--- lock: waiting, 503 and stale fallback")
php('global $wpdb; $wpdb->delete($wpdb->prefix."agentwelcome_pages", array("post_id" => %d));' % pid)
lock(pid)
code, state, body, took, h = md("/cache-probe.md")
check("no copy and a busy lock gives 503", code == 503, code)
check("503 sends Retry-After: 10", h.get("retry-after") == "10", h.get("retry-after"))
check("503 waited about 5 seconds", 4.4 <= took <= 9, round(took, 1))
check("503 is not cacheable and marked noindex", "no-store" in h.get("cache-control", "") and h.get("x-robots-tag") == "noindex")
check("nothing was stored", row(pid) is None)
unlock(pid)
code, state, *_ = md("/cache-probe.md")
check("works again once the lock is gone", code == 200 and state == "MISS", (code, state))
php('global $wpdb; $wpdb->update($wpdb->prefix."agentwelcome_pages", array("generator_version" => "0.0.1"), array("post_id" => %d));' % pid)
lock(pid)
code, state, body, took, _ = md("/cache-probe.md")
check("busy lock with an old copy serves it immediately (STALE)", code == 200 and state == "STALE" and took < 3, (code, state, round(took, 1)))
unlock(pid)
php('global $wpdb; $wpdb->delete($wpdb->prefix."agentwelcome_pages", array("post_id" => %d));' % pid)
lock(pid, age=120)
code, state, *_ = md("/cache-probe.md")
check("a lock older than 60 s is taken over", code == 200 and state == "MISS", (code, state))
check("the lock is released afterwards", lock_row(pid) == "0", lock_row(pid))

print("--- two simultaneous first requests generate once")
php('global $wpdb; $wpdb->delete($wpdb->prefix."agentwelcome_pages", array("post_id" => %d));' % pid)
out = []
ts = [threading.Thread(target=lambda: out.append(md("/cache-probe.md"))) for _ in range(2)]
[t.start() for t in ts]
[t.join() for t in ts]
states = sorted(o[1] or str(o[0]) for o in out)
check("both answered 200", all(o[0] == 200 for o in out), [o[0] for o in out])
check("one MISS and one HIT", states == ["HIT", "MISS"], states)
check("same body for both", out[0][2] == out[1][2])

print("--- leaving the served set removes the copy")
wpcli("post", "update", str(pid), "--post_status=draft")
check("unpublish deletes the row", row(pid) is None)
check("draft is 404", md("/cache-probe.md")[0] == 404)
wpcli("post", "update", str(pid), "--post_status=publish")
check("publish again serves it", md("/cache-probe.md")[0] == 200)
wpcli("post", "update", str(pid), "--post_password=secret")
check("password deletes the row", row(pid) is None)
check("protected page is 404", md("/cache-probe.md")[0] == 404)
wpcli("post", "update", str(pid), "--post_password=")
md("/cache-probe.md")
check("regenerated after the password is removed", row(pid) is not None)
wpcli("post", "meta", "update", str(pid), "_agentwelcome_exclude", "1")
check("excluded page is 404 straight away", md("/cache-probe.md")[0] == 404)
check("the old row is still there until the job runs", row(pid) is not None)
wpcli("post", "update", str(pid), "--post_title=Cache Probe")
run_event("agentwelcome_generate_post")
check("the job removes the row of an excluded page", row(pid) is None)
wpcli("post", "meta", "delete", str(pid), "_agentwelcome_exclude")
wpcli("post", "update", str(pid), "--post_title=Cache Probe")
run_event("agentwelcome_generate_post")
check("un-excluding and saving regenerates it in the background", row(pid) is not None and row(pid)["state"] == "fresh")
wpcli("post", "delete", str(pid))
check("trash deletes the row", row(pid) is None)
wpcli("post", "delete", str(pid), "--force")

print("--- permanent delete removes the row")
p2 = create_page("Cache Probe Two")
md("/cache-probe-two.md")
check("row exists", row(p2) is not None)
wpcli("post", "delete", str(p2), "--force")
check("force delete removes it", row(p2) is None)

print("--- fallback copies are retried later")
p3 = create_page("Fallback Probe", "<p>Fallback body.</p>")
wpcli("cron", "event", "delete", "agentwelcome_generate_post")
set_env(no_loopback=True)
code, state, body, _, _ = md("/fallback-probe.md")
check("generated from the post content", code == 200 and state == "MISS" and "Fallback body." in body)
r = row(p3)
check("stored as fallback and stale", r["source"] == "fallback" and r["state"] == "stale", r)
code, state, *_ = md("/fallback-probe.md")
check("next request serves it as STALE", state == "STALE", state)
ev = [e for e in events("agentwelcome_generate_post") if e["args"] == [p3]]
due = (calendar.timegm(time.strptime(ev[0]["next_run_gmt"], "%Y-%m-%d %H:%M:%S")) - time.time()) if ev else 0
check("a retry is scheduled about 5 minutes out", len(ev) == 1 and 240 < due < 330, (len(ev), due))
clear_env()
run_event("agentwelcome_generate_post")
r = row(p3)
check("the retry upgrades it to a rendered page", r["source"] == "loopback" and r["state"] == "fresh", r)
wpcli("post", "delete", str(p3), "--force")

print("--- settings changes")
for p in (5, 15, 13, 1):
    md((wpcli("eval", "echo wp_parse_url(get_permalink(%d), PHP_URL_PATH);" % p).strip().rstrip("/") or "/index.html") + ".md")
c = counts()
check("some copies are fresh before the change", int(c.get("fresh", {}).get("n", 0) if isinstance(c.get("fresh"), dict) else 0) >= 3, c)
php("$o = get_option('agentwelcome_settings', array()); $o['strip_selectors'] = '.x-test'; update_option('agentwelcome_settings', $o);")
c = counts()
check("changing the strip selectors marks everything stale", "fresh" not in c and int(c["stale"]["n"]) >= 3, c)
check("a background refresh is queued", php("echo get_option('agentwelcome_batch') ? 'queued' : 'none';") == "queued")
check("the batch event is scheduled", len(events("agentwelcome_regenerate_batch")) == 1)
for _ in range(30):
    if php("echo get_option('agentwelcome_batch') ? 'queued' : 'none';") == "none":
        break
    run_event("agentwelcome_regenerate_batch")
c = counts()
check("the batch finished", php("echo get_option('agentwelcome_batch') ? 'queued' : 'none';") == "none")
check("every copy was rebuilt", "stale" not in c and int(c["fresh"]["n"]) >= 10, c)
check("rows only for pages that are served", row(10) is None and row(3) is None and row(12) is None and row(11) is None, "draft or hidden page has a row")
php("$o = get_option('agentwelcome_settings', array()); $o['excluded_post_types'] = array('book'); update_option('agentwelcome_settings', $o);")
check("excluding a post type schedules a clean-up", wpcli("cron", "event", "list", "--hook=agentwelcome_prune", "--format=count").strip() == "1")
check("the excluded type is 404 before the clean-up runs", md("/book/moby-dick.md")[0] == 404)
run_event("agentwelcome_prune")
check("the clean-up removed its row", row(16) is None)
check("other rows stay", row(5) is not None)
php("$o = get_option('agentwelcome_settings', array()); $o['excluded_post_types'] = array(); $o['strip_selectors'] = ''; update_option('agentwelcome_settings', $o);")

reset_state()
clear_env()
done("CACHE")
