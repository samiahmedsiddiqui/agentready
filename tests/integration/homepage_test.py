"""Homepage Markdown URL: /index.md by default, /index.html.md as a setting."""
from kit import *

reset_state()
admin = Client()
check("logged in", admin.login())
site = anon()


def save(value):
    page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page),
            "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome", "agentwelcome_settings[show_meta_box]": "1"}
    if value is not None:
        form["agentwelcome_settings[home_markdown_url]"] = value
    admin.req("/wp-admin/options.php", form)
    return jphp("echo json_encode(get_option('agentwelcome_settings')['home_markdown_url'] ?? null);")


def llms():
    return site.get("/llms.txt")[2]


def tag():
    return re.search(r'<link rel="alternate" type="text/markdown" href="([^"]+)"', site.get("/")[2])


print("--- default is /index.md")
check("llms.txt lists /index.md", "(http://127.0.0.1:8899/index.md)" in llms() and "(http://127.0.0.1:8899/index.html.md)" not in llms())
check("homepage tag points at /index.md", tag() and tag().group(1) == B + "/index.md")
check("both URLs are served", site.get("/index.html.md")[0] == 200 and site.get("/index.md")[0] == 200)
c, h, body, _ = site.get("/index.md")
check("homepage Markdown is the front page", "# Home" in body, body[:120])
page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
check("settings show both choices, /index.md selected and marked default", re.search(r'value="index.md"\s+checked', page) is not None and 'value="index.html.md"' in page and page.count("(default)") == 1)
check("no collision warning", "would be ambiguous" not in page)
check("Status card names the homepage URL", "The homepage is /index.md." in page)

print("--- switched to /index.html.md")
check("saved", save("index.html.md") == "index.html.md")
check("llms.txt lists /index.html.md", "(http://127.0.0.1:8899/index.html.md)" in llms() and "(http://127.0.0.1:8899/index.md)" not in llms())
check("homepage tag points at /index.html.md", tag() and tag().group(1) == B + "/index.html.md")
check("both URLs still served", site.get("/index.html.md")[0] == 200 and site.get("/index.md")[0] == 200)
check("other pages unaffected", "(http://127.0.0.1:8899/about.md)" in llms())
page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
check("settings show it selected", re.search(r'value="index.html.md"\s+checked', page) is not None and "The homepage is /index.html.md." in page)

print("--- junk and empty values fall back to the default")
check("junk value", save("../etc/passwd") == "index.md")
check("missing value", save(None) == "index.md")

print("--- a page called index")
pid = int(wpcli("post", "create", "--post_type=page", "--post_title=Index", "--post_name=index", "--post_status=publish", "--post_content=<p>I am the index page.</p>", "--porcelain").strip().splitlines()[-1])
page = admin.get("/wp-admin/admin.php?page=agentwelcome")[2]
check("settings warn about the collision", "would be ambiguous" in page)
check("llms.txt falls back to /index.html.md for the homepage", "(http://127.0.0.1:8899/index.html.md)" in llms())
check("tag falls back too", tag() and tag().group(1) == B + "/index.html.md")
c, h, body, _ = site.get("/index.md")
check("/index.md is the real page called index", c == 200 and "I am the index page." in body and "# Home" not in body, body[:160])
c, h, body, _ = site.get("/index.html.md")
check("/index.html.md is still the homepage", c == 200 and "# Home" in body)
wpcli("post", "delete", str(pid), "--force")
check("without the page, /index.md is used again", "(http://127.0.0.1:8899/index.md)" in llms())

reset_state()
done("HOMEPAGE")
