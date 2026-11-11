# Integration tests

These suites run the plugin on a real WordPress with a SQLite database, served by PHP's built-in web server. They check what the unit tests cannot: serving `.md` and `llms.txt`, the stored copies and their refresh, noindex handling, permissions, the admin pages, WP-Cron on and off, and uninstall. GitHub Actions runs them for every pull request on the lowest and the highest supported PHP version (`.github/workflows/integration-tests.yml`).

## Run them

You need `php` with the DOM and SQLite extensions, [WP-CLI](https://wp-cli.org), `curl`, `unzip`, `zsh`, Python 3 and Node.js. WP-CLI is expected at `/usr/local/bin/wp`; set `WP_CLI` to another path if needed.

```bash
tests/integration/setup.sh      # builds the test site in tests/integration/wp-test (about a minute)
tests/integration/deploy.sh     # installs the plugin from the last commit and resets its state
tests/integration/serve.sh start
tests/integration/run.sh        # runs every suite, and exits with 1 if a check fails
tests/integration/serve.sh stop
```

`deploy.sh` installs the committed files (the same ones a release zip holds), so commit your change first. Run `deploy.sh` again before every full run, because some suites change the site's settings.

A single suite can be run on its own after `deploy.sh`, for example `python3 tests/integration/cache_test.py`. The suites in `run.sh` run in a fixed order, and a few of them assume the state that `deploy.sh` leaves.

## Layout

- `setup.sh`, `deploy.sh`, `serve.sh`, `router.php`: build, reset and serve the test site.
- `kit.py`: helpers shared by the Python suites (HTTP clients, WP-CLI calls, the check counters).
- `mu-plugins/`: switches the suites use to change the environment (for example to turn WP-Cron off).
- `themes/`: a minimal theme.
- `*_test.py`, `e2e.sh`, `render_test.js`: the suites.
