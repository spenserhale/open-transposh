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

### End-to-end smoke tests

`tests/e2e/` holds a Playwright suite that drives the dev site on :8888 and fails on any PHP notice, warning, or deprecation rendered into the HTML (wp-env displays errors), on browser console errors, and on a missing language switcher. It needs `@playwright/test` (`npm install`) and its Chromium once (`npx playwright install chromium`):

```bash
npm run env:start
npx playwright test -c tests/e2e/playwright.config.ts
```

The suite expects an administrator `qa_admin` (password in `tests/e2e/smoke.spec.ts`), the plugin's widget in a classic-theme sidebar, and French among the viewable languages. Set that up with `npx wp-env run cli` (not `npm run env run cli` — npm's script runner drops `--flags` unless preceded by `--`):

```bash
npx wp-env run cli wp user create qa_admin qa_admin@example.com --role=administrator --user_pass=...
npx wp-env run cli wp theme activate twentytwenty
npx wp-env run cli wp widget add transposh sidebar-1 --title=Languages
npx wp-env run cli wp option update transposh_options --format=json '{"viewable_languages":"en,fr","default_language":"en"}'
```

### Bare-metal fallback

Without Docker, provide a WordPress install and an empty test database: place the checkout at `<wordpress>/wp-content/plugins/<folder>/` (`tests/wp-tests-config.php` sets `ABSPATH` four directories up) and run `composer phpunit`. The config reads `WORDPRESS_DB_*` environment variables when set and otherwise connects to database `wp_tests` as user `wp` with password `wp` on `127.0.0.1`. For other paths or credentials, copy `phpunit.xml.dist` to `phpunit.xml` (gitignored) and point `WP_PHPUNIT__TESTS_CONFIG` at your own config file.

## Known problems

- `Parser`, `Utilities`, `Plugin`, `Database`, and `Widgets` have integration tests (`tests/Core/`, `tests/Plugin/`, `tests/Database/`, `tests/Widgets/`); the other src areas have none.
- The whole suite must override `WP_UnitTestCase::setUp()` with an empty body: the pinned wp-phpunit library calls a PHPUnit 12 API that no longer exists, and a `parent::setUp()` call fatals. Existing and new tests follow this convention.
- The Playwright smoke suite (`tests/e2e/`) needs the dev environment running and a `qa_admin` administrator; see `tests/e2e/smoke.spec.ts`.

## Related docs

- [Releasing](releasing.md): what CI does instead of testing
- [Security](security.md)
