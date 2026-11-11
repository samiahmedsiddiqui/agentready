#!/bin/zsh
# Runs the integration suites against the running test site. Reset the site first with ./deploy.sh.
# Prints one line per suite, shows the full output of a suite that fails, and exits with 1 if anything failed.
KIT=${0:A:h}; cd $KIT
failed=0
run() {
  local name=$1; shift
  local out; out=$("$@" 2>&1); local code=$?
  echo "=== $name"
  if [[ $code -ne 0 ]]; then
    failed=1
    echo "$out" | grep -v "^ok"
  else
    echo "$out" | grep -E "RESULT" | tail -1
  fi
}
run "end to end" ./e2e.sh
run "environment" python3 env_test.py
run "cache" python3 cache_test.py
run "markdown preview renderer" node render_test.js
run "verify" python3 verify_test.py
run "scan" python3 scan_test.py
run "scan without cron" python3 scan_nocron_test.py
run "llms.txt page" python3 llms_test.py
run "integrations and change notices" python3 integrations_test.py
run "integrations without cron" python3 integrations_nocron_test.py
run "capability" python3 capability_test.py
run "hardening" python3 hardening_test.py
run "basic auth and cron" python3 basicauth_test.py
run "description" python3 description_test.py
run "homepage url" python3 homepage_test.py
run "subdirectory" python3 subdir_test.py
run "uninstall" python3 uninstall_test.py
if grep -qi "agentwelcome" $KIT/wp-test/wp/wp-content/debug.log 2>/dev/null; then
  notices=$(grep -i "agentwelcome" $KIT/wp-test/wp/wp-content/debug.log | grep -v "phar://" | head -5)
  if [[ -n $notices ]]; then
    failed=1
    echo "=== PHP notices from the plugin"; echo "$notices"
  fi
fi
exit $failed
