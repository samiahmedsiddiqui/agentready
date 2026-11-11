#!/bin/zsh
# Builds a throwaway WordPress in ./wp-test with the content the test scripts expect. The database is SQLite unless DB_ENGINE=mysql,
# which uses an existing MySQL or MariaDB server (DB_HOST, DB_NAME, DB_USER and DB_PASS, default 127.0.0.1, wordpress, root and root).
# Needs php, wp-cli (set WP_CLI to its path if it is not /usr/local/bin/wp), curl, unzip and network access. Takes about a minute.
KIT=${0:A:h}; ROOT=$KIT/wp-test; PORT=8899
wpcli() { php -d error_reporting=0 -d display_errors=0 ${WP_CLI:-/usr/local/bin/wp} "$@" 2>&1 | grep -v "^Deprecated\|^PHP Deprecated"; }
rm -rf $ROOT && mkdir -p $ROOT && cd $ROOT || exit 1
curl -sSL -o wordpress.zip https://wordpress.org/latest.zip && unzip -q wordpress.zip && mv wordpress wp
if [[ ${DB_ENGINE:-sqlite} != mysql ]]; then
curl -sSL -o sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.zip && unzip -q sqlite.zip -d wp/wp-content/plugins
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$ROOT/wp/wp-content/plugins/sqlite-database-integration#g" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#g" wp/wp-content/plugins/sqlite-database-integration/db.copy > wp/wp-content/db.php
fi
cp $KIT/router.php $ROOT/router.php
mkdir -p wp/wp-content/mu-plugins wp/wp-content/themes
cp $KIT/mu-plugins/test-setup.php wp/wp-content/mu-plugins/
cp -R $KIT/themes/agentwelcome-test wp/wp-content/themes/
cd wp
if [[ ${DB_ENGINE:-sqlite} == mysql ]]; then
  wpcli config create --dbhost=${DB_HOST:-127.0.0.1} --dbname=${DB_NAME:-wordpress} --dbuser=${DB_USER:-root} --dbpass=${DB_PASS:-root} --force | tail -1
  wpcli db reset --yes | tail -1
else
  wpcli config create --dbname=agentwelcome --dbuser=x --dbpass=x --skip-check --force | tail -1
fi
wpcli config set WP_DEBUG true --raw >/dev/null; wpcli config set WP_DEBUG_LOG true --raw >/dev/null; wpcli config set WP_DEBUG_DISPLAY false --raw >/dev/null
wpcli core install --url=http://127.0.0.1:$PORT --title="AgentWelcome Test" --admin_user=admin --admin_password=agentwelcome-test-pw --admin_email=admin@example.test --skip-email | tail -1
wpcli rewrite structure '/%postname%/' >/dev/null
curl -sSL -o ../plugin-check.zip https://downloads.wordpress.org/plugin/plugin-check.zip && unzip -q ../plugin-check.zip -d wp-content/plugins && wpcli plugin activate plugin-check >/dev/null
wpcli theme activate agentwelcome-test | tail -1

# The creation order matters: the test scripts rely on these post IDs (home 4, about 5, ..., book 16, polish 17, empty 18).
mk() { wpcli post create --post_type="${1}" --post_status="${2}" --post_title="${3}" --post_name="${4}" --post_content="${5}" ${6} --porcelain | tail -1; }
HOME_C='<!-- wp:paragraph --><p>Welcome to the <strong>AgentWelcome</strong> test site.</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>Fast</li><li>Clean</li></ul><!-- /wp:list -->'
CLASSIC='<h2>Who we are</h2><p>We build <em>great</em> things. Visit <a href="/contact/">our contact page</a>.</p><ul><li>Design</li><li>Code<ul><li>PHP</li></ul></li></ul><table><tr><th>Plan</th><th>Price</th></tr><tr><td>Basic</td><td>$10</td></tr></table>'
GUT='<!-- wp:heading --><h2 class="wp-block-heading">Block heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Paragraph with <a href="https://example.org/x">a link</a>.</p><!-- /wp:paragraph --><!-- wp:code --><pre class="wp-block-code"><code>echo "hi";</code></pre><!-- /wp:code --><!-- wp:quote --><blockquote class="wp-block-quote"><p>Quoted words</p></blockquote><!-- /wp:quote --><!-- wp:table --><figure class="wp-block-table"><table><thead><tr><th>Name</th><th>Qty</th></tr></thead><tbody><tr><td>Apple</td><td>3</td></tr></tbody></table></figure><!-- /wp:table -->'
HOME_ID=$(mk page publish "Home" "home" "$HOME_C")
mk page publish "About" "about" "$CLASSIC" >/dev/null
mk page publish "Gutenberg Page" "gutenberg-page" "$GUT" >/dev/null
ACF=$(mk page publish "ACF Page" "acf-page" "<!-- wp:paragraph --><p>Intro in the editor.</p><!-- /wp:paragraph -->")
wpcli post meta add $ACF subtitle "Hello from a custom field" >/dev/null; wpcli post meta add $ACF cards "Alpha|Beta|Gamma" >/dev/null
SERV=$(mk page publish "Services" "services" "<p>Our services.</p>")
mk page publish "Web Design" "web-design" "<p>We design the web.</p>" "--post_parent=$SERV" >/dev/null
mk page draft "Draft Page" "draft-page" "<p>Secret draft.</p>" >/dev/null
mk page publish "Locked Page" "locked-page" "<p>Locked.</p>" "--post_password=hunter2" >/dev/null
HIDDEN=$(mk page publish "Hidden Page" "hidden-page" "<p>Hidden by editor checkbox.</p>"); wpcli post meta add $HIDDEN _agentwelcome_exclude 1 >/dev/null
DESC=$(mk page publish "Described Page" "described-page" "<p>Body.</p>"); wpcli post meta add $DESC _agentwelcome_description "Custom llms.txt description." >/dev/null
mk page publish "Checkout" "checkout" "<p>Cart.</p>" >/dev/null
mk post publish "Hello Post" "hello-post" "<p>A blog post.</p>" >/dev/null
mk book publish "Moby Dick" "moby-dick" "<p>Call me Ishmael.</p>" >/dev/null
POLISH='<p>✓</p><p>Feature one</p><div>✗</div><div>Not included</div><p><a href="/about/">About <img src="https://example.org/a.svg" alt="About"></a></p><p>Launch the lab<sup>1</sup></p><h2><strong>Bold heading</strong></h2><p>Some text.</p>'
mk page publish "Polish Page" "polish-page" "$POLISH" >/dev/null
EID=$(mk page publish "Empty Page" "empty-page" ""); wpcli post meta add $EID subtitle "This subtitle is a long enough sentence to be used as the page summary." >/dev/null
wpcli option update show_on_front page >/dev/null; wpcli option update page_on_front $HOME_ID >/dev/null
NEWS=$(wpcli term create category "News" --porcelain | tail -1); wpcli term create category "Product News" --parent=$NEWS >/dev/null; wpcli term create category "Release Notes" --parent=$NEWS >/dev/null; wpcli term create category "Guides" >/dev/null
for t in ai markdown llms agents mcp wordpress seo "Change Data Capture"; do wpcli term create post_tag "$t" >/dev/null; done
wpcli post term set 15 category news "product-news" >/dev/null; wpcli post term set 15 post_tag ai markdown llms >/dev/null; wpcli post term set 1 post_tag ai agents >/dev/null
wpcli rewrite flush >/dev/null
echo "Done. Next: ./deploy.sh then ./serve.sh start then ./run.sh"
