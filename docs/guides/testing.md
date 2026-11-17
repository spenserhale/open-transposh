---
title: Testing
description: Read before writing or running tests. Covers the wp-env Docker environment the suite runs in, the bare-metal fallback, and test file naming.
---

## Purpose

Read this guide when adding or changing tests, or when running the test suite.

## Policy

- Tests are WordPress integration tests. Each test class extends `WP_UnitTestCase` and runs against a real WordPress install and MySQL database, with the plugin loaded on `muplugins_loaded` and activated in `tests/bootstrap.php` so its tables exist before the first test.
- Put tests in `tests/<Area>/<Class>_Tests.php` under the namespace `Tests\<Area>`, mirroring `src/`: `tests/Core/Parser_Tests.php` tests `src/Core/Parser.php`. PHPUnit only collects files whose names end in `Tests.php` (`phpunit.xml.dist`), so a file named `ParserTest.php` is silently skipped.
- Write tests for PHPUnit 12, the version locked in `composer.lock` (with Yoast PHPUnit Polyfills 4 and the test library from `wp-phpunit/wp-phpunit`). Use attributes such as `#[DataProvider]`; PHPUnit 12 no longer reads doc-comment annotations.
- CI does not run tests; `.github/workflows/ci.yml` only builds `main-built`. Run the suite locally before calling a change done, and say so when you could not.

## Running the suite

The supported path is the [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) Docker environment defined by `.wp-env.json` and `package.json` (latest WordPress on PHP 8.4). The suite runs inside the `tests-cli` container against an isolated tests database:

```bash
composer install   # PHP dependencies (also bind-mounted into the container)
npm install        # @wordpress/env and the npm scripts
npm run env:start  # dev site on :8888, test site on :8889
npm run test:php   # ./vendor/bin/phpunit inside tests-cli
```

`npm run test:php:install` reruns `composer install` inside the container; with host `vendor/` present it is a no-op. Useful lifecycle scripts: `npm run env:stop`, `npm run env:destroy` (removes all containers and data).

The suite drops every table with the `wptests_` prefix in the tests database. The wp-env tests database is dedicated to this; never point a bare-metal run at a database a real site uses.

### Bare-metal fallback

Without Docker, provide a WordPress install and an empty test database: place the checkout at `<wordpress>/wp-content/plugins/<folder>/` (`tests/wp-tests-config.php` sets `ABSPATH` four directories up) and run `composer phpunit`. The config reads `WORDPRESS_DB_*` environment variables when set and otherwise connects to database `wp_tests` as user `wp` with password `wp` on `127.0.0.1`. For other paths or credentials, copy `phpunit.xml.dist` to `phpunit.xml` (gitignored) and point `WP_PHPUNIT__TESTS_CONFIG` at your own config file.

## Known problems

- Only `Parser` and `Utilities` have tests (27 test methods in `tests/Core/`).

## Related docs

- [Releasing](releasing.md): what CI does instead of testing
- [Security](security.md)
