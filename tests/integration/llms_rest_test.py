"""The REST route that reads and changes the llms.txt and llms-full.txt settings, used with application passwords."""
import base64
from kit import *

ROUTE = "/?rest_route=/agentwelcome/v1/llms"
CAP = "agentwelcome_manage_files"

reset_state()
admin = Client()
check("admin logged in", admin.login())
admin.get("/wp-admin/index.php")
wpcli("user", "create", "restedit", "restedit@example.com", "--role=editor", "--user_pass=kit-pass-2")
wpcli("user", "create", "restfiles", "restfiles@example.com", "--role=editor", "--user_pass=kit-pass-2")
wpcli("user", "add-cap", "restfiles", CAP)


def token(user):
    pw = php("$u = get_user_by('login', '%s'); $r = WP_Application_Passwords::create_new_application_password($u->ID, array('name' => 'kit')); echo $r[0];" % user).splitlines()[-1]
    return {"Authorization": "Basic " + base64.b64encode(("%s:%s" % (user, pw)).encode()).decode()}


def call(headers, method="GET", body=None):
    h = dict(headers)
    data = None
    if body is not None:
        data = json.dumps(body).encode()
        h["Content-Type"] = "application/json"
    code, _, text, _ = anon().req(ROUTE, data=data, method=method, headers=h)
    try:
        return code, json.loads(text)
    except ValueError:
        return code, text


def saved():
    return jphp("echo json_encode(get_option('agentwelcome_llms'));") or {}


try:
    editor, files, boss = token("restedit"), token("restfiles"), token("admin")

    print("--- who may use it")
    check("without a login it is refused", call({})[0] in (401, 403))
    check("an editor without the capability is refused", call(editor)[0] == 403)
    check("an editor without the capability cannot save", call(editor, "POST", {"mode": "custom", "custom": "# Nope"})[0] == 403 and saved().get("custom", "") == "")
    code, data = call(files)
    check("an editor with the capability can read it", code == 200 and data["mode"] == "automatic", (code, data))
    code, data = call(boss)
    check("an administrator can read it", code == 200)

    print("--- saving")
    code, data = call(files, "POST", {"mode": "hybrid", "custom": "# Studio\r\n\r\nWelcome.", "full": True})
    check("an editor with the capability can save", code == 200 and data["mode"] == "hybrid" and data["full"] is True, (code, data))
    check("the text is cleaned like the form does", saved()["custom"] == "# Studio\n\nWelcome.", saved())
    llms = anon().get("/llms.txt?t=%d" % int(time.time() * 1000))[2]
    check("llms.txt shows the new text at once", "Welcome." in llms)
    code, data = call(boss, "POST", {"full_mode": "custom", "full_custom": "# Full\n\nOnly this."})
    check("fields that are not sent keep their value", code == 200 and data["mode"] == "hybrid" and data["custom"].startswith("# Studio") and data["full"] is True, (code, data))
    full = anon().get("/llms-full.txt?t=%d" % int(time.time() * 1000))
    check("llms-full.txt shows its own text", full[0] == 200 and "Only this." in full[2], full[0])
    check("a PUT works too", call(boss, "PUT", {"robots_line": True})[1]["robots_line"] is True)

    print("--- bad input")
    code, data = call(boss, "POST", {"mode": "wild"})
    check("an unknown mode is refused", code == 400 and saved()["mode"] == "hybrid", (code, data))
    code, data = call(boss, "POST", {"custom": "x" * 200001})
    check("a text over the limit is refused, not cut", code == 413 and len(saved()["custom"]) < 100, code)
    code, data = call(boss, "POST", {"custom": ["a"]})
    check("a text that is not a string is refused", code == 400, code)
    code, data = call(boss, "POST", {"mode": "custom", "custom": "Plain text with [a link](https://other.example/x)"})
    check("notes are returned for the text", code == 200 and "no_title" in data["notes"] and "external" in data["notes"], (code, data))
finally:
    for name in ("restedit", "restfiles"):
        wpcli("user", "delete", name, "--yes")
    php("get_role('administrator')->remove_cap('%s'); delete_option('agentwelcome_files_capability_granted');" % CAP)
    reset_state()
done("LLMS REST")
