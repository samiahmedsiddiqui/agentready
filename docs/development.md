# Development

## Setup and checks

```bash
composer install
```

| Command | What it does |
| --- | --- |
| `composer lint` | Runs PHP_CodeSniffer with the WordPress Coding Standards and PHP 8.0+ compatibility checks. It also checks that every file, class, function, constant and class variable has a comment. |
| `composer format` | Fixes the violations PHP_CodeSniffer can fix automatically. |
| `composer analyse` | Runs PHPStan. |
| `composer test` | Runs the PHPUnit unit tests (no WordPress needed). |

Every pull request runs these checks plus [Plugin Check](https://github.com/WordPress/plugin-check). See [CONTRIBUTING.md](../CONTRIBUTING.md) for how to send a change.

## How this plugin is developed

Developed with AI coding tools and maintained by Sami Ahmed Siddiqui. Every change is reviewed by the author and checked with automated tests, PHPCS, PHPStan and Plugin Check before release.
