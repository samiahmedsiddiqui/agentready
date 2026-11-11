"""A site whose address has a path (WordPress in a subdirectory)."""
from kit import *

reset_state()
site = anon()
wpcli("option", "update", "home", B + "/blog")
try:
    check("moving the site to a subdirectory pauses AgentWelcome until it is resumed", site.get("/blog/about.md")[0] == 404)
    php("AgentWelcome\\Environment\\SiteAddress::remember();")
    check("page .md under the subdirectory", site.get("/blog/about.md")[0] == 200)
    c, h, body, _ = site.get("/blog/about.md")
    check("cached on the second request", h.get("x-agentwelcome-cache") == "HIT")
    check("front matter and canonical use the subdirectory URL", 'url: "http://127.0.0.1:8899/blog/about/"' in body and "/blog/about/>" in h.get("link", ""), h.get("link"))
    check("aliases work", site.get("/blog/about/index.html.md")[0] == 200 and site.get("/blog/about/index.md")[0] == 200)
    check("homepage", site.get("/blog/index.html.md")[0] == 200 and site.get("/blog/index.md")[0] == 200)
    c, h, llms, _ = site.get("/blog/llms.txt")
    check("llms.txt under the subdirectory lists subdirectory URLs", c == 200 and "(http://127.0.0.1:8899/blog/about.md)" in llms, llms[:200])
    check("discovery tag points at the subdirectory .md", 'type="text/markdown" href="http://127.0.0.1:8899/blog/about.md"' in site.get("/blog/about/")[2])
    check("unknown page is 404", site.get("/blog/nope.md")[0] == 404)
    wpcli("option", "update", "agentwelcome_settings", '{"excluded_posts":"checkout"}', "--format=json")
    check("URL-pattern exclusions ignore the subdirectory", site.get("/blog/checkout.md")[0] == 404 and site.get("/blog/about.md")[0] == 200)
    wpcli("post", "update", "5", "--post_title=About Us")
    check("editing works the same", site.get("/blog/about.md")[1].get("x-agentwelcome-cache") == "STALE")
finally:
    wpcli("option", "update", "home", B)
    php("AgentWelcome\\Environment\\SiteAddress::remember();")
    wpcli("post", "update", "5", "--post_title=About")
    reset_state()
done("SUBDIR")
