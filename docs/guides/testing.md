---
title: Testing
description: Read before writing or running tests. Covers the WordPress install and database the suite needs, test file naming, and the known problem in the PHPUnit config.
---

## Purpose

Read this guide when adding or changing tests, or when running `composer phpunit`.

## Policy

- Tests are WordPress integration tests. Each test class extends `WP_UnitTestCase` and runs against a real WordPress install and MySQL database, with the plugin loaded on `muplugins_loaded` (`tests/bootstrap.php`).
- Put tests in `tests/<Area>/<Class>_Tests.php` under the namespace `Tests\<Area>`, mirroring `src/`: `tests/Core/Parser_Tests.php` tests `src/Core/Parser.php`. PHPUnit only collects files whose names end in `Tests.php` (`phpunit.xml.dist`), so a file named `ParserTest.php` is silently skipped.
- Write tests for PHPUnit 12, the version locked in `composer.lock` (with Yoast PHPUnit Polyfills 4 and the WordPress 6.8 test library from `wp-phpunit/wp-phpunit`). Use attributes such as `#[DataProvider]`; PHPUnit 12 no longer reads doc-comment annotations.
- CI does not run tests; `.github/workflows/ci.yml` only builds `main-built`. Run the suite locally before calling a change done, and say so when you could not.

## Running the suite

1. Run `composer install`.
2. Provide a WordPress install and an empty test database. `tests/wp-tests-config.php` sets `ABSPATH` to four directories above `tests/`, so the checkout must sit at `<wordpress>/wp-content/plugins/<folder>/`. It connects to the database `wp_tests` as user `wp` with password `wp` on `127.0.0.1`.
3. Run `composer phpunit`.

The suite drops every table with the `wptests_` prefix in that database. Never point it at a database a real site uses.

For other paths or credentials, copy `phpunit.xml.dist` to `phpunit.xml` (gitignored) and point `WP_PHPUNIT__TESTS_CONFIG` at your own config file. `.wp-env.json` defines a `@wordpress/env` environment (PHP 8.4, this plugin mounted), but running the suite inside it has not been tried.

## Known problems

- `phpunit.xml.dist` still sets `convertErrorsToExceptions`, `convertNoticesToExceptions`, and `convertWarningsToExceptions`. PHPUnit 10 removed these attributes, so PHPUnit 12 reports that the file fails schema validation.
- Only `Parser` and `Utilities` have tests (27 test methods in `tests/Core/`).

## Related docs

- [Releasing](releasing.md): what CI does instead of testing
- [Security](security.md)
