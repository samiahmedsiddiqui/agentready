#!/bin/zsh
B=http://127.0.0.1:8899
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); }
bad()  { FAIL=$((FAIL+1)); echo "FAIL: $1"; }
code() { curl -s -o /dev/null -w "%{http_code}" "$@"; }
body() { curl -s "$@"; }
eq()   { [[ "$2" == "$3" ]] && ok || bad "$1 (got '$2', want '$3')"; }
has()  { [[ "$2" == *"$3"* ]] && ok || bad "$1 (missing '$3')"; }
not()  { [[ "$2" != *"$3"* ]] && ok || bad "$1 (should not contain '$3')"; }

echo "--- routing and aliases"
eq "about.md 200" "$(code $B/about.md)" 200
eq "about/index.html.md 200" "$(code $B/about/index.html.md)" 200
eq "about/index.md 200" "$(code $B/about/index.md)" 200
eq "home index.html.md alias 200" "$(code $B/index.html.md)" 200
eq "home index.md 200" "$(code $B/index.md)" 200
eq "nested services/web-design.md" "$(code $B/services/web-design.md)" 200
eq "plain html page unchanged" "$(code $B/about/)" 200
eq "post .md" "$(code $B/hello-post.md)" 200
eq "CPT .md" "$(code $B/book/moby-dick.md)" 200
eq "missing page 404" "$(code $B/nope.md)" 404
eq "wrong nesting 404" "$(code $B/web-design.md)" 404

echo "--- content"
H=$(body $B/home.md 2>/dev/null); HOME_MD=$(body $B/index.html.md)
has "home title" "$HOME_MD" '# Home'
has "home bold" "$HOME_MD" '**AgentWelcome**'
has "home list" "$HOME_MD" '- Fast'
not "no nav" "$HOME_MD" 'Contact'
not "no footer" "$HOME_MD" 'Footer text'
G=$(body $B/gutenberg-page.md)
has "gut heading" "$G" '## Block heading'
has "gut link" "$G" '[a link](https://example.org/x)'
has "gut code" "$G" $'```\necho "hi";\n```'
has "gut quote" "$G" '> Quoted words'
has "gut table" "$G" '| Apple | 3 |'
A=$(body $B/acf-page.md)
has "acf editor text" "$A" 'Intro in the editor.'
has "acf template field" "$A" 'Subtitle field: Hello from a custom field'
has "acf cards" "$A" '### Beta'
has "acf card text" "$A" 'Card text for Gamma'
SM=$(body $B/services/web-design.md)
has "nested canonical" "$SM" 'url: "http://127.0.0.1:8899/services/web-design/"'

echo "--- headers"
HDR=$(curl -s -D - -o /dev/null $B/about.md)
has "content-type" "$HDR" 'Content-Type: text/markdown; charset=utf-8'
has "nosniff" "$HDR" 'X-Content-Type-Options: nosniff'
has "noindex" "$HDR" 'X-Robots-Tag: noindex'
has "canonical" "$HDR" 'rel="canonical"'
ETAG=$(echo "$HDR" | grep -i '^etag' | awk '{print $2}' | tr -d '\r')
eq "304 on etag" "$(code -H "If-None-Match: $ETAG" $B/about.md)" 304
eq "200 on other etag" "$(code -H 'If-None-Match: "x"' $B/about.md)" 200
eq "HEAD 200" "$(code -I $B/about.md)" 200
eq "HEAD no body" "$(curl -s -I $B/about.md | wc -c | awk '{print ($1<450)?"small":"big"}')" small
eq "POST not intercepted" "$(code -X POST $B/about.md)" 404

echo "--- discovery tag"
has "link rel alternate" "$(body $B/about/)" '<link rel="alternate" type="text/markdown" href="http://127.0.0.1:8899/about.md" />'
has "home link" "$(body $B/)" 'href="http://127.0.0.1:8899/index.md"'
not "no link on hidden" "$(body $B/hidden-page/)" 'text/markdown'

echo "--- exclusions"
eq "draft 404" "$(code $B/draft-page.md)" 404
eq "password 404" "$(code $B/locked-page.md)" 404
eq "editor checkbox 404" "$(code $B/hidden-page.md)" 404
eq "pattern/id still 200 before" "$(code $B/checkout.md)" 200

echo "--- llms.txt"
L=$(body $B/llms.txt)
has "llms title" "$L" '# AgentWelcome Test'
has "llms pages section" "$L" '## Pages'
has "llms about" "$L" '[About](http://127.0.0.1:8899/about.md)'
has "llms home" "$L" '(http://127.0.0.1:8899/index.md)'
has "llms nested" "$L" '(http://127.0.0.1:8899/services/web-design.md)'
has "llms books" "$L" '## Books'
has "llms custom description" "$L" 'Custom llms.txt description.'
not "llms no draft" "$L" 'Draft Page'
not "llms no locked" "$L" 'Locked Page'
not "llms no hidden" "$L" 'Hidden Page'
eq "llms content-type" "$(curl -s -D - -o /dev/null $B/llms.txt | grep -ic 'content-type: text/plain')" 1


echo "--- converter polish and front matter description"
P=$(curl -s $B/polish-page.md)
has "icon joined to text" "$P" '✓ Feature one'
has "cross joined" "$P" '✗ Not included'
[[ "$P" != *"![About]"* ]] && echo "ok   logo alt repeating link text dropped" || echo "FAIL logo image kept"
has "link kept" "$P" '[About](http://127.0.0.1:8899/about/)'
has "superscript" "$P" 'lab^1'
has "bold heading unwrapped" "$P" '## Bold heading'
[[ "$P" != *"## **"* ]] && echo "ok   no bold markers in headings" || echo "FAIL heading bold"
E=$(curl -s $B/empty-page.md)
not "no description taken from the page body" "$E" 'description:'

echo "RESULT: $PASS passed, $FAIL failed"

[[ $FAIL -eq 0 ]]
