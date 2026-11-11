#!/bin/zsh
# ./deploy.sh [repo path]  Builds the zip from the repo's HEAD (committed files only, as CI and wp.org do) and installs it into the test site.
KIT=${0:A:h}; ROOT=$KIT/wp-test; REPO=${1:-${KIT:h:h}}
ZIP=$ROOT/plugin.zip
wpcli() { php -d error_reporting=0 -d display_errors=0 ${WP_CLI:-/usr/local/bin/wp} --path=$ROOT/wp "$@" 2>&1 | grep -v "^Deprecated\|^PHP Deprecated"; }
rm -f $ZIP && git -C $REPO archive --format=zip --prefix=agentwelcome/ -o $ZIP HEAD
wpcli plugin deactivate agentwelcome >/dev/null; rm -rf $ROOT/wp/wp-content/plugins/agentwelcome; unzip -q $ZIP -d $ROOT/wp/wp-content/plugins
cp $KIT/mu-plugins/*.php $ROOT/wp/wp-content/mu-plugins/; rm -f $ROOT/wp/wp-content/kit-env.json $ROOT/wp/wp-content/mu-plugins/zz-filters.php
wpcli plugin activate agentwelcome | tail -1; wpcli option delete agentwelcome_settings >/dev/null; : > $ROOT/wp/wp-content/debug.log
