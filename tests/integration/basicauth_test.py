"""Site behind HTTP Basic auth: page reads, and WP-Cron's own request, with and without saved credentials."""
import base64
from kit import *

reset_state()
AUTH = {"Authorization": "Basic " + base64.b64encode(b"site:secret").decode()}
FLAG = WP + "/wp-content/basic-auth.flag"
site = anon()


def set_plugin_auth(on):
    if on:
        php("$o = get_option('agentwelcome_settings', array()); $o['http_auth_enabled'] = true; $o['http_auth_user'] = 'site'; $o['http_auth_password'] = AgentWelcome\\Settings\\Secret::encrypt('secret'); update_option('agentwelcome_settings', $o);")
    else:
        php("$o = get_option('agentwelcome_settings', array()); $o['http_auth_enabled'] = false; $o['http_auth_user'] = ''; $o['http_auth_password'] = ''; update_option('agentwelcome_settings', $o);")


def clear_cron_lock():
    php("delete_transient('doing_cron'); delete_option('agentwelcome_batch'); wp_unschedule_hook('agentwelcome_regenerate_batch');")


def wait_for(pid, state, seconds):
    end = time.time() + seconds
    while time.time() < end:
        site.get("/llms.txt", headers=AUTH)
        r = row(pid)
        if r and r["state"] == state:
            return True
        time.sleep(2)
    return False


pid = create_page("Verify Cron Probe", "<p>Cron probe text.</p>")
try:
    print("--- the filter itself")
    set_plugin_auth(True)
    req = jphp("echo json_encode(apply_filters('cron_request', array('url' => site_url('wp-cron.php'), 'key' => 'k', 'args' => array('timeout' => 0.01, 'blocking' => false))));")
    check("credentials are added to the cron request for this site", req["args"]["headers"]["Authorization"] == AUTH["Authorization"], req)
    other = jphp("echo json_encode(apply_filters('cron_request', array('url' => 'https://other.example/wp-cron.php', 'key' => 'k', 'args' => array('timeout' => 1))));")
    check("never added for another host", "headers" not in other["args"], other)
    set_plugin_auth(False)
    off = jphp("echo json_encode(apply_filters('cron_request', array('url' => site_url('wp-cron.php'), 'key' => 'k', 'args' => array('timeout' => 1))));")
    check("not added when no credentials are saved", "headers" not in off["args"], off)

    print("--- the site asks for a password")
    open(FLAG, "w").close()
    check("a visitor without credentials gets 401", site.get("/verify-cron-probe.md")[0] == 401)
    check("wp-cron.php without credentials gets 401", site.get("/wp-cron.php")[0] == 401)
    set_plugin_auth(True)
    clear_cron_lock()
    code, h, body, _ = site.get("/verify-cron-probe.md", headers=AUTH)
    check("with credentials the page is generated from the rendered page", code == 200 and h.get("x-agentwelcome-cache") == "MISS" and row(pid)["source"] == "loopback", (code, row(pid) and row(pid)["source"]))

    print("--- background refresh with saved credentials")
    wpcli("cron", "event", "delete", "agentwelcome_generate_post")
    wpcli("post", "update", str(pid), "--post_content=<p>Cron probe text changed.</p>")
    r = row(pid)
    ev = events("agentwelcome_generate_post")
    check("the copy is stale and a refresh is scheduled", r is not None and r["state"] == "stale" and len(ev) >= 1, (r and r["state"], ev))
    clear_cron_lock()
    check("the refresh runs by itself through WP-Cron on the protected site", wait_for(pid, "fresh", 40), row(pid) and row(pid)["state"])
    check("with the new content", "changed" in row(pid)["markdown"])

    print("--- background refresh without saved credentials")
    set_plugin_auth(False)
    clear_cron_lock()
    wpcli("post", "update", str(pid), "--post_content=<p>Cron probe text changed again.</p>")
    check("stale again", row(pid)["state"] == "stale")
    check("WP-Cron cannot reach the protected site, so the refresh waits", not wait_for(pid, "fresh", 14), row(pid)["state"])
    check("the event is still pending", len([e for e in events("agentwelcome_generate_post") if e["args"] == [pid]]) == 1)
    wpcli("cron", "event", "run", "agentwelcome_generate_post")
    r = row(pid)
    check("running it by hand works, but without credentials it can only read the post content, so the copy is kept as a stale fallback", r["source"] == "fallback" and r["state"] == "stale", (r["source"], r["state"]))

    print("--- the page read without credentials falls back, with a clear reason")
    wpcli("post", "update", str(pid), "--post_content=<p>Fallback text.</p>")
    code, h, body, _ = site.get("/verify-cron-probe.md", headers=AUTH)
    admin_notice = jphp("echo json_encode((new AgentWelcome\\Rendering\\Renderer(new AgentWelcome\\Settings\\Options(), new AgentWelcome\\Http\\Urls()))->render(get_post(%d))->notice);" % pid)
    check("the notice tells the user to enable HTTP Basic in the settings", "asked for HTTP Basic authentication" in admin_notice and "Enable it in the AgentWelcome settings" in admin_notice, admin_notice)
finally:
    try:
        os.remove(FLAG)
    except FileNotFoundError:
        pass
    clear_env()
    wpcli("post", "delete", str(pid), "--force")
    clear_cron_lock()
    reset_state()
done("BASICAUTH")
