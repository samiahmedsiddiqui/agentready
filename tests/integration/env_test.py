"""Environment gating: constants, environment type, allowed URLs, and what stays working when inactive."""
import html as htmllib
import re
from kit import *

LINK = 'rel="alternate" type="text/markdown"'
reset_state()
admin = Client()
check("logged in", admin.login())
site = anon()


def served(path="/about.md"):
    code, h, body, _ = site.get(path)
    return code == 200 and "text/markdown" in h.get("content-type", "")


def settings_page():
    return admin.get("/wp-admin/admin.php?page=agentwelcome")[2]


def save(extra):
    page = settings_page()
    form = {"option_page": "agentwelcome_group", "action": "update", "_wpnonce": nonce_from(page),
            "_wp_http_referer": "/wp-admin/admin.php?page=agentwelcome", "agentwelcome_settings[show_meta_box]": "1"}
    form.update(extra)
    admin.req("/wp-admin/options.php", form)
    return jphp("echo json_encode(get_option('agentwelcome_settings'));")


print("--- default: environment type unset counts as production")
check("about.md served", served())
check("llms.txt served", site.get("/llms.txt")[0] == 200)
check("discovery tag present", LINK in site.get("/about/")[2])
page = settings_page()
check("no inactive banner when active", "AgentWelcome is inactive on this site" not in page)
check("status says active", "AgentWelcome is active." in page)
check("system info lists the environment type", "<dt>Environment</dt>" in page and "<dd>production</dd>" in page)
check("system info lists WordPress, PHP and plugin version", all(x in page for x in ("<dt>WordPress</dt>", "<dt>PHP</dt>", "<dt>AgentWelcome</dt>")))

print("--- environment types")
set_env(type="production")
check("explicit production is served", served())
for t in ("staging", "development", "local"):
    set_env(type=t)
    check(f"{t}: .md falls through to 404", site.get("/about.md")[0] == 404)
    check(f"{t}: llms.txt falls through to 404", site.get("/llms.txt")[0] == 404)
    check(f"{t}: no discovery tag", LINK not in site.get("/about/")[2])
    page = settings_page()
    check(f"{t}: banner explains why", "AgentWelcome is inactive on this site" in page and f'environment type is "{t}"' in page.replace("&quot;", '"'))
    check(f"{t}: Environment tab shows the type", f"<code>{t}</code>" in page)
    check(f"{t}: Regenerate all is disabled", re.search(r'value="regenerate_all"[^>]*disabled', page) is not None)

print("--- ticking an environment turns it on")
set_env(type="staging")
opt = save({"agentwelcome_settings[allowed_environments][]": ["staging", "bogus"]})
check("ticked staging saved, junk dropped", opt["allowed_environments"] == ["staging"], opt.get("allowed_environments"))
check("staging now served", served())
check("llms.txt served on staging", site.get("/llms.txt")[0] == 200)
check("discovery tag on staging", LINK in site.get("/about/")[2])
page = settings_page()
check("staging box shown ticked", re.search(r'value="staging"\s+checked', page) is not None)
set_env(type="development")
check("development still off when only staging is ticked", site.get("/about.md")[0] == 404)
save({})
set_env(type="staging")
check("unticking turns it off again", site.get("/about.md")[0] == 404)

print("--- preview keeps working when inactive")
prev = admin.get("/wp-admin/admin.php?page=agentwelcome-verify")[2]
code, _, out, _ = admin.req("/wp-admin/admin.php?page=agentwelcome-verify",
                            {"_wpnonce": nonce_from(prev), "_wp_http_referer": "/x", "agentwelcome_verify_url": "/about/", "submit": "Preview"})
check("verify page renders Markdown while inactive", code == 200 and 'data-agentwelcome-verdict="not_served"' not in out and 'id="agentwelcome-markdown"' in out and "# About" in out)
check("verify page shows the inactive banner", "AgentWelcome is inactive on this site" in out)

print("--- AGENTWELCOME_ENABLED")
set_env(enabled=False)
check("false switches it off in production", site.get("/about.md")[0] == 404 and LINK not in site.get("/about/")[2])
check("banner names the constant", "AGENTWELCOME_ENABLED is set to false" in settings_page())
set_env(enabled=True)
check("true leaves it on", served())
set_env(enabled="false")
check('the string "false" also switches it off', site.get("/about.md")[0] == 404)
set_env(enabled=False, type="staging")
save({"agentwelcome_settings[allowed_environments][]": ["staging"]})
check("the constant wins over a ticked environment", site.get("/about.md")[0] == 404)

print("--- allowed URLs: constant")
clear_env()
set_env(urls="example.com")
check("non-matching host switches it off", site.get("/about.md")[0] == 404)
check("banner says the address does not match", "does not match the allowed URLs" in settings_page())
set_env(urls="127.0.0.1")
check("matching host is served", served())
set_env(urls=["example.com", "https://127.0.0.1:8899/"])
check("array form, URL with scheme and port", served())
set_env(urls="*.example.com")
check("wildcard that does not match", site.get("/about.md")[0] == 404)
set_env(urls="127.*")
check("wildcard that matches", served())
set_env(urls="")
check("empty constant means no restriction", served())

print("--- allowed URLs: settings textarea")
clear_env()
opt = save({"agentwelcome_settings[allowed_urls]": "HTTPS://Staging.Example.com/path\nexample.org:8080, bad_host!"})
check("normalised to hosts", opt["allowed_urls"] == "staging.example.com\nexample.org", opt.get("allowed_urls"))
check("non-matching setting switches it off", site.get("/about.md")[0] == 404)
opt = save({"agentwelcome_settings[allowed_urls]": "127.0.0.1"})
check("matching setting is served", served())
page = settings_page()
check("textarea shows the saved hosts", ">127.0.0.1</textarea>" in page)

print("--- the constant overrides the setting")
set_env(urls="example.com")
page = settings_page()
check("textarea is locked and shows the constant", re.search(r'<textarea id="agentwelcome-allowed-urls"[^>]*disabled[^>]*>example.com</textarea>', page) is not None)
check("lock message shown", "set with AGENTWELCOME_ALLOWED_URLS" in page)
check("constant wins over the saved setting", site.get("/about.md")[0] == 404)
opt = save({"agentwelcome_settings[allowed_urls]": "something.test"})
check("saving while locked keeps the stored value", opt["allowed_urls"] == "127.0.0.1", opt.get("allowed_urls"))
clear_env()
save({"agentwelcome_settings[allowed_urls]": ""})

print("--- nothing runs when inactive")
php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}agentwelcome_pages");')
for h in ("agentwelcome_generate_post", "agentwelcome_regenerate_batch"):
    wpcli("cron", "event", "delete", h)
set_env(type="staging")
pid = create_page("Gate Probe")
wpcli("post", "update", str(pid), "--post_title=Gate Probe 2")
check("saving a page schedules no generation", events("agentwelcome_generate_post") == [])
check("no row is stored", row(pid) is None)
out = wpcli("agentwelcome", "cache", "regenerate", "--all")
check("CLI regenerate refuses", "inactive" in out.lower() and "Success" not in out, out)
out = wpcli("agentwelcome", "cache", "status")
check("CLI status reports inactive", "Active: no" in out, out)
code, _, body, _ = admin.get("/wp-admin/admin-ajax.php?action=rest-nonce")
rest = {"X-WP-Nonce": body.strip()}
code, _, body, _ = admin.req(f"/wp-json/agentwelcome/v1/pages/{pid}/regenerate", {}, method="POST", headers=rest)
check("REST regenerate returns 409 with the reason", code == 409 and "agentwelcome_inactive" in body, body[:200])
code, _, body, _ = admin.get(f"/wp-json/agentwelcome/v1/pages/{pid}", headers=rest)
check("REST status says not served", code == 200 and '"state":"unavailable"' in body.replace(" ", ""), body[:200])
code, h, _, _ = admin.req("/wp-admin/edit.php?post_type=page")
edit = admin.get("/wp-admin/edit.php?post_type=page")[2]
check("no row action while inactive", "agentwelcome_regenerate_post" not in edit)
check("no bulk action while inactive", 'value="agentwelcome_regenerate"' not in edit)
wpcli("post", "delete", str(pid), "--force")

print("--- a copied or moved site pauses itself")
FP = lambda: php("echo (string) get_option('agentwelcome_site_fingerprint');")
TAMPER = "update_option('agentwelcome_site_fingerprint', AgentWelcome\\Environment\\SiteAddress::fingerprint('https://staging-copy.example.com/'));"
clear_env()
php("delete_option('agentwelcome_site_fingerprint');")
check("with no saved address the first load saves it and stays active", site.get("/about.md")[0] == 200 and re.fullmatch(r"[0-9a-f]{64}", FP() or "") is not None, FP())
check("what is saved is a hash that holds no host name", "127.0.0.1" not in FP() and "8899" not in FP())
php(TAMPER)
check("a changed address pauses it: .md is 404", site.get("/about.md")[0] == 404)
check("llms.txt is 404 and the discovery tag is gone", site.get("/llms.txt")[0] == 404 and LINK not in site.get("/about/")[2])
page = settings_page()
check("the banner says why", "AgentWelcome is inactive on this site." in page and "The site address changed since AgentWelcome was set up." in page)
check("with a Resume button", "Resume on this address" in page and "agentwelcome_resume" in page)
check("Verify & Preview still works and shows the same banner", "Resume on this address" in admin.get("/wp-admin/admin.php?page=agentwelcome-verify")[2])
check("the CLI reports it too", "Active: no" in wpcli("agentwelcome", "cache", "status") and "address changed" in wpcli("agentwelcome", "cache", "status"))
link = htmllib.unescape(re.search(r'href="([^"]*agentwelcome_resume[^"]*)"', page).group(1)).replace(B, "")
code, h, _, _ = admin.req("/wp-admin/admin-post.php?action=agentwelcome_resume&_wpnonce=bad", follow=False)
check("a bad link is refused and changes nothing", code == 403 and site.get("/about.md")[0] == 404, code)
code, h, _, _ = admin.req(link, follow=False)
check("Resume redirects with a notice", code == 302 and "agentwelcome_notice=resumed" in h["location"], h.get("location"))
check("and the plugin is active again", site.get("/about.md")[0] == 200 and site.get("/llms.txt")[0] == 200 and LINK in site.get("/about/")[2])
check("the saved address now matches this site", php("echo AgentWelcome\\Environment\\SiteAddress::matches() ? 'yes' : 'no';") == "yes")
check("the notice is shown", "AgentWelcome is resumed on this address." in admin.get(h["location"].replace(B, ""))[2])

wpcli("user", "create", "kiteditor", "kiteditor@example.com", "--role=editor", "--user_pass=kit-pass-2")
editor = Client()
editor.login("kiteditor", "kit-pass-2")
php(TAMPER)
code, _, body, _ = editor.req(link)
check("an editor cannot resume it", code == 403 or "link you followed has expired" in body or "not allowed" in body.lower(), code)
check("still paused after that attempt", site.get("/about.md")[0] == 404)
wpcli("user", "delete", "kiteditor", "--yes")

print("--- the copy check steps aside when you choose")
set_env(urls="127.0.0.1")
check("an AGENTWELCOME_ALLOWED_URLS constant overrides the automatic check", site.get("/about.md")[0] == 200)
clear_env()
check("without it the copy is paused again", site.get("/about.md")[0] == 404)
set_env(no_address_check=True)
check("the agentwelcome_check_site_address filter turns the check off", site.get("/about.md")[0] == 200)
clear_env()
admin.req(link, follow=False)
check("resumed for the next suites", site.get("/about.md")[0] == 200)

clear_env()
reset_state()
done("ENV")
