# wp-env tests

These PHPUnit tests run inside [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), a Docker setup with a real WordPress and a real MySQL database. They use the WordPress test library, so each test runs in a database transaction that is rolled back afterwards. They check what the unit tests cannot: the table creation, the stored-copy queries, the lock rows, the saved llms files being cleared by real WordPress events, which pages are served for real posts and permalinks, and the capability on real roles.

Docker, Node.js and Composer are needed. From the repository root:

```bash
composer install          # installs the PHPUnit polyfills that the WordPress test library needs
npx @wordpress/env start
npx @wordpress/env run tests-cli --env-cwd=wp-content/plugins/agentwelcome vendor/bin/phpunit -c tests/wp-env/phpunit.xml.dist
npx @wordpress/env stop
```

Use the folder name of your checkout in place of `agentwelcome` if it differs. To test another PHP version, set `WP_ENV_PHP_VERSION` before `start`, for example `WP_ENV_PHP_VERSION=8.4`.

GitHub Actions runs them for every pull request on the lowest and the highest supported PHP version (`.github/workflows/wp-env-tests.yml`). The end-to-end suites that run the whole plugin over HTTP are in `tests/integration`.
