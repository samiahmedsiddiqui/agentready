"""llms.txt page: modes, custom text, llms-full.txt, robots.txt line, permissions, escaping."""
import html as htmllib
from kit import *

reset_state()
admin = Client()
check("admin logged in", admin.login())
wpcli("user", "create", "kiteditor", "kiteditor@example.com", "--role=editor", "--user_pass=kit-pass-2")
editor = Client()
check("editor logged in", editor.login("kiteditor", "kit-pass-2"))
site = anon()
PAGE = "/wp-admin/admin.php?page=agentwelcome-llms"
n = [0]


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def save(mode="automatic", custom="", full=False, robots=False, extra=None):
    page = admin.get(PAGE)[2]
    form = {"option_page": "agentwelcome_llms_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": PAGE,
            "agentwelcome_llms[mode]": mode, "agentwelcome_llms[custom]": custom}
    if full:
        form["agentwelcome_llms[full]"] = "1"
    if robots:
        form["agentwelcome_llms[robots_line]"] = "1"
    form.update(extra or {})
    return admin.req("/wp-admin/options.php", form, follow=False)


def llms():
    return site.get("/llms.txt" + bust())


def full():
    return site.get("/llms-full.txt" + bust())


def robots():
    return site.get("/robots.txt" + bust())


def option():
    return jphp("echo json_encode(get_option('agentwelcome_llms'));")


ids = []
try:
    print("--- the page")
    page = admin.get(PAGE)
    check("opens for an administrator", page[0] == 200 and "agentwelcome-llms" in page[2] and "<title>llms.txt" in page[2].replace("\n", " "), page[0])
    check("has the three modes, defaulting to Automatic", all(m in page[2] for m in ("Automatic", "Custom", "Hybrid")) and re.search(r'value="automatic"[^>]*checked', page[2]) is not None)
    check("the text box is hidden in Automatic mode", re.search(r"data-agentwelcome-custom\s+hidden", page[2]) is not None)
    check("loads the admin script and styles", "assets/js/admin.js" in page[2] and "assets/css/admin.css" in page[2] and "assets/js/verify.js" not in page[2])
    check("a preview of the current file is shown", "<details>" in page[2] and "agentwelcome-code" in page[2])
    check("editors cannot open it", editor.get(PAGE)[0] == 403)
    check("visitors are sent to log in", anon().get(PAGE, follow=False)[0] == 302)
    forged = editor.req("/wp-admin/options.php", {"option_page": "agentwelcome_llms_group", "action": "update", "_wpnonce": nonce_from(page[2]), "agentwelcome_llms[mode]": "custom", "agentwelcome_llms[custom]": "# Hacked"}, follow=False)
    check("an editor cannot save the settings, even with a stolen form token", forged[0] in (302, 403) and (option() or {}).get("custom", "") != "# Hacked", (forged[0], option()))

    print("--- automatic (default)")
    c, h, body, _ = llms()
    check("serves the generated file", c == 200 and body.startswith("# ") and "## Pages" in body, (c, body[:80]))
    base = body
    check("as text with nosniff and noindex", h["content-type"].startswith("text/plain") and h["x-content-type-options"] == "nosniff" and h["x-robots-tag"] == "noindex", h)
    r = save("automatic", "# Stored but unused")
    check("saving returns to the page with the saved notice", r[0] == 302 and "page=agentwelcome-llms" in r[1]["location"] and "settings-updated=true" in r[1]["location"], r[1].get("location"))
    check("Automatic ignores a stored custom text", llms()[2] == base)

    print("--- custom")
    text = "# My Site\n\n> Written by hand.\n\n## Docs\n\n- [Start](https://example.com/start.md): The basics"
    save("custom", text)
    c, h, body, _ = llms()
    check("serves exactly the text", c == 200 and body == text + "\n", body[:120])
    check("the saved option holds the mode and text", option() == {"mode": "custom", "custom": text, "full": False, "robots_line": False}, option())
    check("custom text does not query pages (no generated list)", "## Pages" not in body)
    save("custom", "# CRLF\r\n\r\nline\r\n\x00\x07tab\there\r\n  ")
    check("line endings are unified and control characters removed", llms()[2] == "# CRLF\n\nline\ntab\there\n", repr(llms()[2]))
    save("custom", "Café — 日本語 ☕")
    check("Unicode is kept", llms()[2] == "Café — 日本語 ☕\n", repr(llms()[2]))
    xss = "# T\n\n<script>alert(1)</script> <img src=x onerror=alert(2)>"
    save("custom", xss)
    c, h, body, _ = llms()
    check("HTML is served as plain text, never as a page", body == xss + "\n" and h["content-type"].startswith("text/plain") and h["x-content-type-options"] == "nosniff", h)
    p = admin.get(PAGE)[2]
    check("and escaped in the editor and the preview", "<script>alert(1)</script>" not in p and "&lt;script&gt;alert(1)&lt;/script&gt;" in p and "onerror=alert(2)>" not in p)
    save("custom", "")
    check("empty custom text falls back to the generated file", llms()[2] == base)
    save("custom", "x" * 250000)
    check("text is cut at 200,000 bytes", len(llms()[2]) == 200000 + 1, len(llms()[2]))
    save("custom", ("é" * 150000))
    got = llms()[2]
    check("a cut inside a multi-byte character leaves valid text", got.encode("utf-8", "strict") and len(got.encode()) <= 200001 and "�" not in got)
    save("bogus-mode", "# Mine")
    check("an unknown mode becomes Automatic", option()["mode"] == "automatic")

    print("--- hybrid")
    save("hybrid", "# Hand-written intro\n\nSome words.")
    c, h, body, _ = llms()
    check("your text comes first, then the generated list", body.startswith("# Hand-written intro\n\nSome words.\n\n## ") and "## Pages" in body, body[:160])
    check("the generated title and tagline are not repeated", body.count("\n# ") == 0 and "> " not in body.split("## ")[0])
    check("the list is the same as the automatic one", body.split("\n\n## ", 1)[1] == base.split("\n\n## ", 1)[1])
    save("hybrid", "")
    check("hybrid with no text is the automatic file", llms()[2] == base)

    print("--- notes on the page")
    save("custom", "No title here, see [x](https://elsewhere.test/a.md)")
    p = admin.get(PAGE)[2]
    check("a note for a missing title and for other sites", "does not start with a title line" in p and "links to other sites" in p)
    save("custom", "# Good\n\n[ok](http://127.0.0.1:8899/about.md)")
    p = admin.get(PAGE)[2]
    check("no notes for a good text on this site", "notice-info inline" not in p, re.findall(r"notice-info inline.*?</p>", p, re.S))
    save("custom", "# Big\n" + "a" * 110000)
    check("a note for a very large text", "very large" in admin.get(PAGE)[2])
    save("automatic")

    print("--- llms-full.txt")
    check("is off by default: the normal 404", full()[0] == 404)
    ids = [create_page("llms-a", "<p>Alpha body text.</p>", post_name="llms-a"), create_page("llms-b", "<p>Bravo body text.</p>", post_name="llms-b"), create_page("llms-c", "<p>Charlie body text.</p>", post_name="llms-c")]
    for slug in ("llms-a", "llms-b"):
        check("page stored by requesting " + slug + ".md", site.get("/%s.md%s" % (slug, bust()))[0] == 200)
    wpcli("post", "meta", "update", str(ids[1]), "_agentwelcome_exclude", "1")
    save("automatic", full=True)
    c, h, body, _ = full()
    check("turning it on serves the file as text", c == 200 and h["content-type"].startswith("text/plain") and h["x-content-type-options"] == "nosniff", (c, h))
    check("it starts with the site title", body.startswith("# ") and "\n---\n" in body)
    check("a stored page has its title, its source URL and its body, with the title once", "# llms-a\n\nSource: http://127.0.0.1:8899/llms-a/\n\nAlpha body text." in body.replace("\r", "") and body.count("# llms-a\n") == 1, body[:600])
    check("the metadata block is removed", 'title: "' not in body and "\nurl: " not in body and "generated" not in body.split("Alpha")[0].split("llms-a")[-1])
    check("an excluded page is left out", "Bravo" not in body)
    check("a page with no stored copy is left out, nothing is generated on request", "Charlie" not in body and row(ids[2]) is None)
    check("the settings page counts the stored copies and the missing ones", re.search(r"cover \d+ page", admin.get(PAGE)[2]) is not None and "no stored copy yet" in admin.get(PAGE)[2])
    check("and links to the full file", "http://127.0.0.1:8899/llms-full.txt" in admin.get(PAGE)[2])
    notmod = site.req("/llms-full.txt", headers={"If-None-Match": full()[1]["etag"]})
    check("ETag and 304 work", notmod[0] == 304, notmod[0])
    check("HEAD works", site.req("/llms-full.txt" + bust(), method="HEAD")[0] == 200)
    save("automatic", full=False)
    check("turning it off returns the 404 again", full()[0] == 404)

    print("--- llms-full.txt size limit")
    big = "<p>" + ("lorem ipsum dolor sit amet " * 2500) + "</p>"
    big_ids = [create_page("llms-big-%d" % i, big, post_name="llms-big-%d" % i) for i in range(3)]
    for i in range(3):
        site.get("/llms-big-%d.md%s" % (i, bust()))
    set_env(llms_full_max=100000)
    save("automatic", full=True)
    c, h, body, _ = full()
    check("the file stops at the limit and only holds whole pages", c == 200 and len(body.encode()) <= 100000 + 400 and body.count("\nSource: ") >= 1, (c, len(body)))
    check("it ends with a note saying how many pages were left out", re.search(r"Note: \d+ pages? (is|are) not included because this file reached its size limit", body) is not None, body[-300:])
    check("the note points to llms.txt", "full list is in llms.txt" in body[-200:])
    check("no half page: every long page that is included is complete", all(part.count("lorem ipsum dolor sit amet") == 2500 for part in body.split("\nSource: ")[1:] if "lorem" in part), [part.count("lorem ipsum dolor sit amet") for part in body.split("\nSource: ")[1:]])
    clear_env()
    set_env(type="production")
    php("delete_transient('agentwelcome_out_full');")
    check("without the limit all stored pages are included", full()[2].count("\nSource: ") >= 4)
    clear_env()

    print("--- inactive plugin")
    set_env(enabled=False)
    check("llms-full.txt is the normal 404 when AgentWelcome is switched off", full()[0] == 404)
    check("llms.txt too", llms()[0] == 404)
    clear_env()
    check("and both are back afterwards", full()[0] == 200 and llms()[0] == 200)

    print("--- robots.txt")
    save("automatic", robots=False)
    base_robots = robots()[2]
    check("no AgentWelcome lines by default", "AgentWelcome" not in base_robots and "User-agent" in base_robots, base_robots)
    save("automatic", full=False, robots=True)
    r = robots()[2]
    check("when on: comment lines for llms.txt", "# AgentWelcome\n# llms.txt: http://127.0.0.1:8899/llms.txt\n" in r, r)
    check("only comments are added, no allow or disallow rules", r.replace(base_robots, "").strip().splitlines() == ["# AgentWelcome", "# llms.txt: http://127.0.0.1:8899/llms.txt"], r)
    check("the original rules are untouched", r.startswith(base_robots.rstrip("\n")))
    save("automatic", full=True, robots=True)
    r = robots()[2]
    check("with llms-full.txt on, it is listed too", "# llms-full.txt: http://127.0.0.1:8899/llms-full.txt" in r, r)
    wpcli("option", "update", "blog_public", "0")
    r = robots()[2]
    check("not added when the site asks search engines to stay away", "AgentWelcome" not in r and "Disallow: /" in r, r)
    wpcli("option", "update", "blog_public", "1")
    set_env(enabled=False)
    check("not added when AgentWelcome is switched off", "AgentWelcome" not in robots()[2])
    clear_env()
    with open(WP + "/robots.txt", "w") as f:
        f.write("User-agent: *\n")
    try:
        check("a real robots.txt file is flagged on the page", "real robots.txt file exists" in admin.get(PAGE)[2])
    finally:
        os.remove(WP + "/robots.txt")
    check("and the flag is gone without it", "real robots.txt file exists" not in admin.get(PAGE)[2])

    print("--- settings stay apart")
    wpcli("option", "update", "agentwelcome_settings", '{"excluded_posts":"checkout","show_meta_box":false}', "--format=json")
    save("custom", "# Mine", full=True, robots=True)
    check("saving the llms.txt page does not touch the main settings", jphp("echo json_encode(get_option('agentwelcome_settings'));") == {"excluded_posts": "checkout", "show_meta_box": False}, jphp("echo json_encode(get_option('agentwelcome_settings'));"))
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    admin.req("/wp-admin/options.php", {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome", "agentwelcome_settings[show_meta_box]": "1"})
    check("and saving the main settings does not touch the llms.txt ones", option() == {"mode": "custom", "custom": "# Mine", "full": True, "robots_line": True}, option())
finally:
    clear_env()
    wpcli("option", "update", "blog_public", "1")
    wpcli("user", "delete", "kiteditor", "--yes")
    reset_state()
done("LLMS")
