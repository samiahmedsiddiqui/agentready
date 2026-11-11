"""llms.txt descriptions: editor override, then SEO meta description, then the excerpt, never the page content."""
from kit import *

reset_state()
site = anon()
LONG = "<p>This long body text must never appear in llms.txt. " + "More body words. " * 12 + "</p>"
BLOCKS = '<!-- wp:paragraph --><p>Block paragraph that must stay out.</p><!-- /wp:paragraph --><!-- wp:heading {"level":2} --><h2>Block heading</h2><!-- /wp:heading -->'


def mk(title, content=LONG, excerpt=None, **meta):
    args = ["post", "create", "--post_type=page", "--post_title=" + title, "--post_status=publish", "--post_content=" + content, "--porcelain"]
    if excerpt is not None:
        args.append("--post_excerpt=" + excerpt)
    pid = int(wpcli(*args).strip().splitlines()[-1])
    for k, v in meta.items():
        wpcli("post", "meta", "update", str(pid), k, v)
    return pid


def entry(title):
    for line in site.get("/llms.txt")[2].splitlines():
        if line.startswith("- [" + title + "]"):
            return line
    return None


ids = []
ids.append(mk("Desc Override", excerpt="Excerpt text.", _yoast_wpseo_metadesc="Yoast text.", _agentwelcome_description="Editor text."))
ids.append(mk("Desc Yoast", excerpt="Excerpt text.", _yoast_wpseo_metadesc="Yoast text for %%sitename%% page."))
ids.append(mk("Desc Rank", rank_math_description="Rank Math text."))
ids.append(mk("Desc Aioseo", _aioseo_description="All in One text."))
ids.append(mk("Desc Excerpt", excerpt="Hand written excerpt."))
ids.append(mk("Desc None"))
ids.append(mk("Desc Blocks", content=BLOCKS))
ids.append(mk("Desc Html", excerpt="<b>Bold</b> [gallery] and &amp; more."))
ids.append(mk("Desc Blank Seo", excerpt="Fallback excerpt.", _yoast_wpseo_metadesc="   "))
ids.append(mk("Desc Only Tokens", excerpt="Token fallback.", _yoast_wpseo_metadesc="%%title%% %%sep%% %%sitename%%"))
ids.append(mk("Desc Raw Aioseo", excerpt="Raw tag fallback.", _aioseo_description="Welcome to #site_title today."))
ids.append(mk("Desc Order", rank_math_description="Rank first.", _yoast_wpseo_metadesc="Yoast first."))

try:
    print("--- order of sources")
    check("editor description wins", entry("Desc Override").endswith(": Editor text."), entry("Desc Override"))
    check("an SEO description with tags nothing can resolve is skipped, the excerpt is used", entry("Desc Yoast").endswith(": Excerpt text."), entry("Desc Yoast"))
    check("Rank Math description", entry("Desc Rank").endswith(": Rank Math text."), entry("Desc Rank"))
    check("All in One SEO description", entry("Desc Aioseo").endswith(": All in One text."), entry("Desc Aioseo"))
    check("the excerpt is the last source", entry("Desc Excerpt").endswith(": Hand written excerpt."), entry("Desc Excerpt"))
    check("Yoast is read before Rank Math", entry("Desc Order").endswith(": Yoast first."), entry("Desc Order"))
    check("a blank SEO description falls through to the excerpt", entry("Desc Blank Seo").endswith(": Fallback excerpt."), entry("Desc Blank Seo"))
    check("a description that is only variables falls through", entry("Desc Only Tokens").endswith(": Token fallback."), entry("Desc Only Tokens"))
    check("HTML, shortcodes and entities are cleaned", entry("Desc Html").endswith(": Bold and & more."), entry("Desc Html"))

    check("an AIOSEO tag nothing can resolve is skipped too", entry("Desc Raw Aioseo").endswith(": Raw tag fallback."), entry("Desc Raw Aioseo"))

    print("--- the SEO plugin fills in its own tags")
    tag_ids = [
        mk("Desc Tag Yoast", _yoast_wpseo_metadesc="Hello from %%sitename%%."),
        mk("Desc Tag Aioseo", _aioseo_description="AIOSEO welcome to #site_title."),
        mk("Desc Tag Rank", rank_math_description="RM welcome to %sitename%."),
        mk("Desc Tag Seopress", _seopress_titles_desc="SP welcome to %%sitetitle%%."),
        mk("Desc Tag Plain", excerpt="Excerpt only.", _seopress_titles_desc="Mixed %%sitetitle%% and %%unknown_tag%%."),
    ]
    ids.extend(tag_ids)
    set_env(fake_seo=True)
    check("Yoast variables are resolved by Yoast", entry("Desc Tag Yoast").endswith(": Hello from Kit Site."), entry("Desc Tag Yoast"))
    check("AIOSEO tags are resolved by AIOSEO", entry("Desc Tag Aioseo").endswith(": AIOSEO welcome to Kit Site."), entry("Desc Tag Aioseo"))
    check("Rank Math variables are resolved by Rank Math", entry("Desc Tag Rank").endswith(": RM welcome to Kit Site."), entry("Desc Tag Rank"))
    check("SEOPress tags are resolved by SEOPress", entry("Desc Tag Seopress").endswith(": SP welcome to Kit Site."), entry("Desc Tag Seopress"))
    check("a tag no plugin knows still skips the description", entry("Desc Tag Plain").endswith(": Excerpt only."), entry("Desc Tag Plain"))
    wpcli("agentwelcome", "cache", "regenerate", str(tag_ids[0]))
    c, h, body, _ = site.get("/desc-tag-yoast.md")
    check("front matter gets the resolved text", c == 200 and 'description: "Hello from Kit Site."' in body, body[:300])
    set_env(fake_seo="throw")
    c, h, llms_body, _ = site.get("/llms.txt")
    check("a resolver that throws does not break llms.txt", c == 200 and entry("Desc Tag Yoast") == "- [Desc Tag Yoast](http://127.0.0.1:8899/desc-tag-yoast.md)", entry("Desc Tag Yoast"))
    clear_env()

    print("--- never the page content")
    check("no excerpt and no SEO text gives no description", entry("Desc None") == "- [Desc None](http://127.0.0.1:8899/desc-none.md)", entry("Desc None"))
    check("block content gives no description", entry("Desc Blocks") == "- [Desc Blocks](http://127.0.0.1:8899/desc-blocks.md)", entry("Desc Blocks"))
    llms = site.get("/llms.txt")[2]
    check("body text and block markup never appear in llms.txt", "must never appear" not in llms and "Block paragraph" not in llms and "wp:paragraph" not in llms)

    print("--- Markdown front matter uses the same chain first")
    c, h, body, _ = site.get("/desc-yoast.md")
    check("front matter skips an unresolvable SEO description too", 'description: "Excerpt text."' in body, body[:300])
    c, h, body, _ = site.get("/desc-override.md")
    check("front matter takes the editor description", 'description: "Editor text."' in body, body[:300])
    c, h, body, _ = site.get("/desc-excerpt.md")
    check("front matter takes the excerpt", 'description: "Hand written excerpt."' in body, body[:300])
    for slug in ("desc-none", "desc-blocks"):
        c, h, body, _ = site.get("/" + slug + ".md")
        head = body.split("---")[1] if body.startswith("---") else body[:300]
        check(slug + ": no description line, nothing taken from the page body", c == 200 and "description:" not in head and "must never appear" not in head and "wp:" not in head, head)

finally:
    for i in ids:
        wpcli("post", "delete", str(i), "--force")
    reset_state()
done("DESCRIPTION")
