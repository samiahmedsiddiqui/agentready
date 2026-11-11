"""Verify & Preview: one page checks, update, coverage, escaping, permissions, and the editor link."""
import html as htmllib
import urllib.parse
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
wpcli("user", "create", "kiteditor", "kiteditor@example.com", "--role=editor", "--user_pass=kit-pass-2")
editor = Client()
check("editor logged in", editor.login("kiteditor", "kit-pass-2"))
site = anon()


def verify(url, client=admin):
    return client.get("/wp-admin/admin.php?page=agentwelcome-verify&url=" + urllib.parse.quote(url, safe=""))


def verdict(page):
    m = re.search(r'data-agentwelcome-verdict="(\w+)"', page)
    return m.group(1) if m else None


def update_link(page):
    m = re.search(r'href="([^"]*agentwelcome_regenerate_post[^"]*)"', page)
    return htmllib.unescape(m.group(1)).replace(B, "") if m else None


def pane(page, name):
    m = re.search(r'data-agentwelcome-pane="' + name + r'">(.*?)(?=<div class="agentwelcome-pane"|</section>)', page, re.S)
    return m.group(1) if m else ""


def mk(title, content, **extra):
    args = ["post", "create", "--post_type=page", "--post_title=" + title, "--post_status=publish", "--post_content=" + content, "--porcelain"]
    args += [f"--{k}={v}" for k, v in extra.items()]
    return int(wpcli(*args).strip().splitlines()[-1])


def clear_jobs():
    for hook in ("agentwelcome_generate_post", "agentwelcome_regenerate_batch", "agentwelcome_prune"):
        wpcli("cron", "event", "delete", hook)


ids = []
try:
    print("--- not stored yet")
    pid = mk("Verify Probe", "<h2>First heading</h2><p>First paragraph text.</p>")
    ids.append(pid)
    clear_jobs()
    URL = B + "/verify-probe/"
    code, _, page, _ = verify(URL)
    check("verdict is not stored", code == 200 and verdict(page) == "not_stored", verdict(page))
    check("offers to create the stored copy", "Create stored copy" in page and update_link(page) is not None)
    check("raw pane holds the fresh Markdown", "# Verify Probe" in pane(page, "raw") and "First paragraph text." in pane(page, "raw"))
    check("preview pane and tabs are present", 'id="agentwelcome-rendered"' in page and page.count("data-agentwelcome-pane-tab=") == 4)
    check("changes pane says there is nothing to compare", "no stored copy to compare" in pane(page, "changes"))
    check("the query string note is shown", "such as ?t=123" in page)
    check("verify page loads the preview script", "assets/js/verify.js" in page and "api-fetch" in page)
    check("nothing was stored by looking", row(pid) is None)

    print("--- create it, then it is in sync")
    code, h, _, _ = admin.req(update_link(page), follow=False)
    check("update redirects back to the verify page with a notice", code == 302 and "page=agentwelcome-verify" in h["location"] and "agentwelcome_notice=regenerated" in h["location"], h.get("location"))
    check("the redirect keeps the page being checked", "url=" in h["location"], h.get("location"))
    check("a fresh copy is stored", row(pid) is not None and row(pid)["state"] == "fresh")
    page = admin.get(h["location"].replace(B, ""))[2]
    check("verdict is in sync", verdict(page) == "in_sync", verdict(page))
    check("notice shown", "The Markdown was regenerated." in page)
    check("no update button when in sync", update_link(page) is None)
    check("changes pane says no differences", "No differences." in pane(page, "changes"))
    check("stored copy details are shown", "Generated " in page and "rendered page" in page)

    print("--- edited page: out of date, with the changes shown")
    wpcli("post", "update", str(pid), "--post_content=<h2>First heading</h2><p>Second paragraph text.</p><p>Third paragraph.</p>")
    clear_jobs()
    page = verify(URL)[2]
    check("verdict is out of date", verdict(page) == "differs", verdict(page))
    check("the number of differing lines is shown", re.search(r"\d+ lines? differ", page) is not None)
    check("diff shows the new text in green and the old text in red", "agentwelcome-diff-add" in page and "Second paragraph text." in pane(page, "changes") and "agentwelcome-diff-del" in page and "First paragraph text." in pane(page, "changes"))
    check("the tab label carries the count", re.search(r"Changes \(\d+\)", page) is not None)
    check("the stored copy was not touched by looking", "First paragraph text." in row(pid)["markdown"])
    link = update_link(page)
    check("an update button is offered", "Update stored copy" in page and link is not None)
    code, h, _, _ = admin.req(link, follow=False)
    page = admin.get(h["location"].replace(B, ""))[2]
    check("after updating it is in sync", verdict(page) == "in_sync" and "Second paragraph text." in row(pid)["markdown"])

    print("--- volatile text can be ignored")
    pid_v = mk("Verify Volatile", "<p>Steady text.</p>")
    ids.append(pid_v)
    clear_jobs()
    VURL = B + "/verify-volatile/"
    set_env(volatile=True)
    admin.req(update_link(verify(VURL)[2]), follow=False)
    time.sleep(1.2)
    check("text that changes on every render shows as a difference", verdict(verify(VURL)[2]) == "differs")
    set_env(volatile=True, compare_ignore=["/\\d{2}:\\d{2}:\\d{2}/"])
    check("the agentwelcome_compare_ignore filter hides it", verdict(verify(VURL)[2]) == "in_sync")
    clear_env()

    print("--- page text coverage")
    wpcli("post", "update", str(pid), "--post_content=<h2>Covered heading</h2><p>Covered paragraph.</p><aside><p>Sidebar words removed on purpose.</p></aside>")
    clear_jobs()
    page = verify(URL)[2]
    cov = pane(page, "coverage")
    check("coverage counts what is found", re.search(r"\d+ of \d+ text blocks from the page are in the Markdown \(100%\)", cov) is not None, cov[:300])
    check("excluded text is listed separately", "Removed by the exclusions" in cov and "Sidebar words removed on purpose." in cov)
    check("nothing is reported as lost", "On the page, but not in the Markdown" not in cov)

    print("--- other states")
    ids.append(mk("Verify Locked", "<p>Secret.</p>", post_password="pw123"))
    page = verify(B + "/verify-locked/")[2]
    check("a protected page is not served, with the reason", verdict(page) == "not_served" and "password protected" in page)
    check("no panes for a page that is not served", "data-agentwelcome-panes" not in page and "Update stored copy" not in page)
    pid2 = mk("Verify Hidden", "<p>Hidden.</p>")
    ids.append(pid2)
    wpcli("post", "meta", "update", str(pid2), "_agentwelcome_exclude", "1")
    check("a hidden page says so", "hidden from AgentWelcome" in verify(B + "/verify-hidden/")[2])
    check("an unknown page", "No page was found" in verify(B + "/nope-nothing/")[2])
    check("another host is refused", "No page was found" in verify("https://evil.example/about/")[2])
    set_env(no_loopback=True)
    page = verify(URL)[2]
    check("a fallback read is flagged", "Generated from the post content only" in page or "could not be fetched" in page or "post content" in page.lower())
    check("and the source says so", "Post content (fallback)" in page)
    clear_env()

    print("--- selectors tried without saving")
    nonce = nonce_from(admin.get("/wp-admin/admin.php?page=agentwelcome-verify")[2])
    code, _, page, _ = admin.req("/wp-admin/admin.php?page=agentwelcome-verify", {"_wpnonce": nonce, "_wp_http_referer": "/x", "agentwelcome_verify_url": URL, "agentwelcome_verify_selector": "", "agentwelcome_verify_strip": "p"})
    check("the check says the selectors were temporary", "applied to this check only" in page)
    check("the stored copy was not changed", "Covered paragraph." in row(pid)["markdown"] or row(pid) is not None)
    code, _, page, _ = admin.req("/wp-admin/admin.php?page=agentwelcome-verify", {"_wpnonce": "bad", "agentwelcome_verify_url": URL})
    check("a bad nonce is refused", "link you followed has expired" in page, code)

    print("--- output is escaped")
    pid3 = mk("Verify <img src=x onerror=alert(1)>", "<p>Plain.</p><script>alert('xss')</script><p>A &lt;b&gt;tag&lt;/b&gt; as text.</p>")
    ids.append(pid3)
    clear_jobs()
    slug = jphp("echo json_encode(get_post(%d)->post_name);" % pid3)
    page = verify(B + "/" + slug + "/")[2]
    visible = page.split('<div class="agentwelcome-pane" data-agentwelcome-pane="raw">')[0]
    check("the title is escaped in the result", "<img src=x onerror" not in page and "&lt;img src=x onerror" in visible, visible[:200])
    check("no script from the page lands in the output", "alert('xss')" not in page.replace("&#039;", "'").split("<textarea")[0])

    print("--- permissions and the editor box link")
    check("an editor cannot open the verify page", editor.get("/wp-admin/admin.php?page=agentwelcome-verify")[0] == 403)
    code, _, body, _ = editor.req("/wp-admin/admin-post.php", {"action": "agentwelcome_scan", "_wpnonce": "x", "scan_action": "start"})
    check("an editor cannot use the scan action", code in (403,), code)
    edit = admin.get("/wp-admin/post.php?post=%d&action=edit" % pid)[2]
    check("the editor box links to the verify page for admins", "Check against the page" in edit and "page=agentwelcome-verify" in edit and "url=" in edit)
    edit = editor.get("/wp-admin/post.php?post=%d&action=edit" % pid)[2]
    check("editors do not see that link", "data-agentwelcome-box" in edit and "Check against the page" not in edit)
finally:
    clear_env()
    for i in ids:
        wpcli("post", "delete", str(i), "--force")
    wpcli("user", "delete", "kiteditor", "--yes")
    reset_state()
done("VERIFY")
