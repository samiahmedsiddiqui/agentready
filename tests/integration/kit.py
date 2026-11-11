"""Helpers shared by the integration suites: HTTP clients, WP-CLI calls and the check counters."""
import json, os, re, subprocess, sys, time, urllib.error, urllib.parse, urllib.request, http.cookiejar

B = "http://127.0.0.1:8899"
KIT = os.path.dirname(os.path.abspath(__file__))
WP = KIT + "/wp-test/wp"
WP_CLI = os.environ.get("WP_CLI", "/usr/local/bin/wp")
res = {"pass": 0, "fail": 0}


def check(name, cond, extra=""):
    res["pass" if cond else "fail"] += 1
    print(("ok   " if cond else "FAIL ") + name + ("" if cond else "  " + str(extra)))


def done(label):
    print(f"{label} RESULT: {res['pass']} passed, {res['fail']} failed")
    sys.exit(1 if res["fail"] else 0)


def wpcli(*args):
    out = subprocess.run(["php", "-d", "error_reporting=0", "-d", "display_errors=0", WP_CLI, "--path=" + WP, *args],
                         capture_output=True, text=True)
    lines = (re.sub(r"(PHP )?Deprecated:.*$", "", l) for l in (out.stdout + out.stderr).splitlines())
    return "\n".join(l for l in lines if l.strip())


def php(code):
    return wpcli("eval", code).strip()


def jphp(code):
    out = php(code)
    return json.loads(out.splitlines()[-1]) if out else None


class _NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.follow = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
        self.stay = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), _NoRedirect)

    def req(self, path, data=None, method=None, headers=None, follow=True):
        if isinstance(data, dict):
            data = urllib.parse.urlencode(data, doseq=True).encode()
        r = urllib.request.Request(B + path, data=data, method=method, headers=headers or {})
        t = time.time()
        try:
            resp = (self.follow if follow else self.stay).open(r, timeout=60)
            code, hdr, body = resp.status, resp.headers, resp.read()
        except urllib.error.HTTPError as e:
            code, hdr, body = e.code, e.headers, e.read()
        h = {k.lower(): v for k, v in hdr.items()}
        return code, h, body.decode("utf-8", "replace"), time.time() - t

    def get(self, path, **k):
        return self.req(path, **k)

    def login(self, user="admin", pwd="agentwelcome-test-pw"):
        self.req("/wp-login.php")
        self.req("/wp-login.php", {"log": user, "pwd": pwd, "wp-submit": "Log In", "redirect_to": B + "/wp-admin/", "testcookie": "1"})
        return any(c.name.startswith("wordpress_logged_in") for c in self.jar)


def anon():
    return Client()


def set_env(**kw):
    with open(WP + "/wp-content/kit-env.json", "w") as f:
        json.dump(kw, f)


def clear_env():
    try:
        os.remove(WP + "/wp-content/kit-env.json")
    except FileNotFoundError:
        pass


def reset_state():
    clear_env()
    php('foreach ( get_posts( array( "post_type" => "any", "post_status" => "any", "numberposts" => -1 ) ) as $p ) { if ( preg_match( "/^(cache|fallback|gate|dbg|list|rest|bulk)-probe|^(desc|verify|scan|llms|integ|nc|hard)-/", $p->post_name ) ) { wp_delete_post( $p->ID, true ); } }')
    wpcli("option", "delete", "agentwelcome_settings")
    wpcli("option", "delete", "agentwelcome_llms")
    wpcli("option", "delete", "agentwelcome_changes")
    php('global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}agentwelcome_pages"); $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \'agentwelcome\\_lock\\_%\'"); delete_option("agentwelcome_batch"); foreach ( array("agentwelcome_generate_post","agentwelcome_regenerate_batch","agentwelcome_prune") as $h ) { wp_unschedule_hook($h); }')


def row(post_id):
    return jphp('global $wpdb; echo json_encode($wpdb->get_row("SELECT * FROM {$wpdb->prefix}agentwelcome_pages WHERE post_id=%d", ARRAY_A));' % post_id)


def counts():
    return jphp('global $wpdb; echo json_encode($wpdb->get_results("SELECT state, COUNT(*) AS n FROM {$wpdb->prefix}agentwelcome_pages GROUP BY state", OBJECT_K));') or {}


def events(hook):
    out = wpcli("cron", "event", "list", "--hook=" + hook, "--format=json", "--fields=hook,args,next_run_gmt")
    try:
        return json.loads(out.strip().splitlines()[-1])
    except Exception:
        return []


def create_page(title, content="<p>Probe content.</p>", **extra):
    args = ["post", "create", "--post_type=page", "--post_title=" + title, "--post_status=publish", "--post_content=" + content, "--porcelain"]
    args += [f"--{k}={v}" for k, v in extra.items()]
    return int(wpcli(*args).strip().splitlines()[-1])


def nonce_from(page, form_hint=None):
    m = re.search(r'name="_wpnonce" value="([^"]+)"', page)
    return m.group(1) if m else ""
