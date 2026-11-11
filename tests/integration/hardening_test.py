"""Short-lived copy of llms.txt and llms-full.txt, and the cap on pages rendered at the same time."""
from kit import *

reset_state()
site = anon()
n = [0]


def bust():
    n[0] += 1
    return "?t=%d%d" % (int(time.time() * 1000), n[0])


def get(path):
    return site.get(path + bust())


def saved(name):
    return php("echo get_transient('%s') === false ? 'no' : 'yes';" % name) == "yes"


def slots(count, on):
    for i in range(1, 4):
        if on and i <= count:
            php("global $wpdb; $wpdb->query(\"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES ('agentwelcome_slot_%d', '\" . time() . \"', 'no')\");" % i)
        else:
            php("global $wpdb; $wpdb->delete($wpdb->options, array('option_name' => 'agentwelcome_slot_%d'));" % i)


old_title = php("echo get_option('blogname');")
set_env(no_cron=True)
try:
    wpcli("option", "update", "agentwelcome_llms", '{"mode":"automatic","custom":"","full":true,"robots_line":false}', "--format=json")
    pid = create_page("hard-a", "<p>Body of hard-a.</p>", post_name="hard-a")
    get("/hard-a.md")
    php("do_action('agentwelcome_generate_post', %d);" % pid)

    print("--- llms.txt and llms-full.txt are kept for a few minutes")
    check("nothing is saved before the first request", not saved("agentwelcome_out_llms"))
    first = get("/llms.txt")
    check("llms.txt lists the page", first[0] == 200 and "/hard-a.md" in first[2])
    check("and is now saved", saved("agentwelcome_out_llms"))
    php("global $wpdb; $wpdb->update($wpdb->posts, array('post_title' => 'Changed behind the plugin'), array('ID' => %d));" % pid)
    check("a change made without any WordPress hook shows the saved copy", "Changed behind the plugin" not in get("/llms.txt")[2])
    full = get("/llms-full.txt")
    check("llms-full.txt is served and saved", full[0] == 200 and saved("agentwelcome_out_full"))

    print("--- the saved copies are cleared when something changes")
    wpcli("post", "update", str(pid), "--post_title=Hard A renamed")
    check("saving a page clears them", not saved("agentwelcome_out_llms") and not saved("agentwelcome_out_full"))
    check("and the new title shows at once", "Hard A renamed" in get("/llms.txt")[2])
    get("/llms-full.txt")
    wpcli("post", "meta", "update", str(pid), "_yoast_wpseo_metadesc", "A fresh description")
    check("an SEO description change clears them", not saved("agentwelcome_out_llms"))
    get("/llms.txt")
    wpcli("post", "meta", "update", str(pid), "_thumbnail_id", "5")
    check("an unrelated meta change does not", saved("agentwelcome_out_llms"))
    title = "Hardening %d" % int(time.time())
    wpcli("option", "update", "blogname", title)
    check("a new site title clears them", not saved("agentwelcome_out_llms") and title in get("/llms.txt")[2])
    get("/llms-full.txt")
    php("do_action('agentwelcome_generate_post', %d);" % pid)
    check("regenerating a stored copy clears llms-full.txt", not saved("agentwelcome_out_full"))
    get("/llms-full.txt")
    wpcli("agentwelcome", "cache", "clear")
    check("clearing the cache clears them", not saved("agentwelcome_out_full"))
    get("/llms.txt")
    wpcli("option", "update", "agentwelcome_llms", '{"mode":"custom","custom":"# Mine\\n\\nHello","full":false,"robots_line":false}', "--format=json")
    check("saving the llms.txt settings clears them", not saved("agentwelcome_out_llms") and "# Mine" in get("/llms.txt")[2])

    print("--- only a few pages are rendered at the same time")
    pid2 = create_page("hard-b", "<p>Body of hard-b.</p>", post_name="hard-b")
    php("delete_option('agentwelcome_batch'); wp_unschedule_hook('agentwelcome_generate_post');")
    wpcli("agentwelcome", "cache", "clear")
    slots(3, True)
    r = get("/hard-b.md")
    check("with every slot taken a page that is not stored gets a 503", r[0] == 503, r[0])
    check("it asks the client to retry", "retry-after" in {k.lower() for k in r[1]} if isinstance(r[1], dict) else True)
    check("and its generation is queued for later", any(str(pid2) in str(e.get("args")) for e in events("agentwelcome_generate_post")))
    check("nothing was stored while the slots were full", row(pid2) is None)
    slots(2, True)
    check("with a slot free it is rendered", get("/hard-b.md")[0] == 200)
    check("the slot is given back afterwards", php("global $wpdb; echo (int) $wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'agentwelcome_slot_%'\");") == "2")
    slots(0, False)
    check("a stored page is served even when every slot is taken", (slots(3, True), get("/hard-b.md")[0])[1] == 200)
finally:
    clear_env()
    wpcli("option", "update", "blogname", old_title)
    slots(0, False)
    reset_state()
done("HARDENING")
