"""The capability that controls the llms.txt files: who gets it, what it opens, how a filter maps it, and uninstall."""
from kit import *

CAP = "agentwelcome_manage_files"
LLMS = "/wp-admin/admin.php?page=agentwelcome-llms"
GRANTED = "agentwelcome_files_capability_granted"

reset_state()
admin = Client()
check("admin logged in", admin.login())
for name, role in (("kitfiles", "editor"), ("kitplain", "editor")):
    wpcli("user", "create", name, name + "@example.com", "--role=" + role, "--user_pass=kit-pass-2")
files = Client()
plain = Client()
check("users log in", files.login("kitfiles", "kit-pass-2") and plain.login("kitplain", "kit-pass-2"))


def role_has(role):
    return php("echo get_role('%s')->has_cap('%s') ? 'yes' : 'no';" % (role, CAP)) == "yes"


def denied(client, path):
    code, _, body = client.get(path)[:3]
    return code in (403, 302) or "not allowed" in body.lower()


def save_as(client, text):
    page = client.get(LLMS)[2]
    form = {"option_page": "agentwelcome_llms_group", "action": "update", "_wpnonce": nonce_from(page), "_wp_http_referer": LLMS, "agentwelcome_llms[mode]": "custom", "agentwelcome_llms[custom]": text}
    return client.req("/wp-admin/options.php", form, follow=False)


def saved_text():
    return (jphp("echo json_encode(get_option('agentwelcome_llms'));") or {}).get("custom", "")


try:
    print("--- administrators get the capability once")
    admin.get("/wp-admin/index.php")
    check("the administrator role has it after the first admin request", role_has("administrator"))
    check("and the grant is recorded", php("echo get_option('%s');" % GRANTED) == "1")
    php("get_role('administrator')->remove_cap('%s');" % CAP)
    admin.get("/wp-admin/index.php")
    check("a removal by the site owner is not undone", not role_has("administrator"))
    php("delete_option('%s');" % GRANTED)
    first = admin.get("/wp-admin/index.php")[2]
    check("it is granted again when the record is gone", role_has("administrator"))
    check("and the llms.txt page is already in the menu of that first request", "href='admin.php?page=agentwelcome-llms'" in first)

    print("--- a user without it")
    check("an editor cannot open the llms.txt page", denied(plain, LLMS))
    before = saved_text()
    forged = plain.req("/wp-admin/options.php", {"option_page": "agentwelcome_llms_group", "action": "update", "_wpnonce": "x", "agentwelcome_llms[mode]": "custom", "agentwelcome_llms[custom]": "# Nope"}, follow=False)
    check("and cannot save it", forged[0] in (302, 403) and saved_text() == before)
    check("the menu has nothing of this plugin for them", "page=agentwelcome" not in plain.get("/wp-admin/index.php")[2])

    print("--- a user who has it")
    wpcli("user", "add-cap", "kitfiles", CAP)
    page = files.get(LLMS)
    check("the llms.txt page opens", page[0] == 200 and "agentwelcome_llms_group" in page[2], page[0])
    check("it is the only page in their menu", "page=agentwelcome-llms" in files.get("/wp-admin/index.php")[2] and "page=agentwelcome-verify" not in files.get("/wp-admin/index.php")[2])
    check("the page loads its style and script", "agentwelcome/assets/js/admin.js" in page[2] or "agentwelcome-admin" in page[2])
    r = save_as(files, "# Delegated text")
    check("they can save the llms.txt text", r[0] == 302 and saved_text() == "# Delegated text", (r[0], saved_text()))
    check("the settings page is closed to them", denied(files, "/wp-admin/admin.php?page=agentwelcome"))
    check("and so is Verify & Preview", denied(files, "/wp-admin/admin.php?page=agentwelcome-verify"))
    cache = files.req("/wp-admin/admin-post.php", {"action": "agentwelcome_cache", "cache_action": "clear", "_wpnonce": "x"}, follow=False)
    check("and the cache actions", cache[0] in (302, 403) and "stored" not in cache[2].lower())
    check("without manage_options the page hides the link to Verify", "page=agentwelcome-verify" not in page[2])
    check("the public file shows the saved text", "# Delegated text" in anon().get("/llms.txt?t=%d" % int(time.time() * 1000))[2])

    print("--- an administrator with the capability keeps every page")
    check("the llms.txt page opens for them", admin.get(LLMS)[0] == 200)
    check("the settings page opens for them", admin.get("/wp-admin/admin.php?page=agentwelcome")[0] == 200)
    check("the menu lists llms.txt under AgentWelcome", "page=agentwelcome-llms" in admin.get("/wp-admin/index.php")[2])

    print("--- the filter maps it to another capability")
    set_env(files_cap="edit_pages")
    check("an editor without the plugin capability can now open it", plain.get(LLMS)[0] == 200)
    check("and save", save_as(plain, "# Via filter")[0] == 302 and saved_text() == "# Via filter")
    set_env(files_cap="manage_network_options")
    check("a capability that nobody here has closes it for the administrator", denied(admin, LLMS))
    clear_env()
    check("with the filter gone the administrator has it again", admin.get(LLMS)[0] == 200)

    print("--- uninstall")
    wpcli("option", "update", "agentwelcome_settings", '{"delete_data":false}', "--format=json")
    wpcli("plugin", "deactivate", "agentwelcome")
    wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
    wpcli("plugin", "activate", "agentwelcome")
    check("without Delete all data the capability stays on the role and the user", role_has("administrator") and php("echo user_can(get_user_by('login','kitfiles'), '%s') ? 'yes' : 'no';" % CAP) == "yes")
    wpcli("option", "update", "agentwelcome_settings", '{"delete_data":true}', "--format=json")
    wpcli("plugin", "deactivate", "agentwelcome")
    wpcli("plugin", "uninstall", "agentwelcome", "--skip-delete")
    wpcli("plugin", "activate", "agentwelcome")
    check("with it, the roles lose the capability", not role_has("administrator"))
    check("and so does a user who had it directly", php("echo user_can(get_user_by('login','kitfiles'), '%s') ? 'yes' : 'no';" % CAP) == "no")
    check("and the grant record is removed", php("echo get_option('%s') === false ? 'gone' : 'there';" % GRANTED) == "gone")
finally:
    clear_env()
    for name in ("kitfiles", "kitplain"):
        wpcli("user", "delete", name, "--yes")
    php("get_role('administrator')->remove_cap('%s'); delete_option('%s');" % (CAP, GRANTED))
    wpcli("option", "delete", "agentwelcome_settings")
    wpcli("post", "meta", "update", "12", "_agentwelcome_exclude", "1")
    wpcli("post", "meta", "update", "13", "_agentwelcome_description", "Custom llms.txt description.")
    reset_state()
done("CAPABILITY")
