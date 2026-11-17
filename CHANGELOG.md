# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Releases up to 2.1.7 predate this file; see the [GitHub releases](https://github.com/spenserhale/open-transposh/releases).

## [Unreleased]

### Fixed

- Silence a PHP 8.4 deprecation warning on fresh installs: unset plugin options (`get_option()` returning `false`) are now treated as an empty array before use.
- URL helpers no longer emit PHP 8.1+ deprecations for pathless or unparseable URLs: `cleanup_url()` and the language-parameter rewrite return the input unchanged when `parse_url()` fails, and a missing path is treated as empty (`Utilities.php`, issues #17 and #24).
- Language detection handles a null request URL and an absent `Accept-Language` header without passing null to `parse_url()`/`preg_match_all()` (#19, #23).
- Rendering translation history with a missing author no longer passes null to `str_contains()` (#31).
- The parser no longer reads past the end of a text segment when a number ends the string, e.g. `B2,` (#26).
- Splitting a pathless URL for language detection no longer passes null to `explode()` (#22).
- Google translation tokens are generated with the integer seed component explicitly; the computed token is unchanged and the float-string deprecation is gone (#20).
- A failed PHP session start no longer feeds language-redirect logic; the plugin falls back to its no-session behavior (#33).
- The `transposh` textdomain is loaded before the widget is constructed, so booting the plugin no longer triggers WordPress 6.7's `_load_textdomain_just_in_time` notice (#21).
- Table setup creates the translation tables before introspecting their indexes, so a fresh install no longer logs `Table ... doesn't exist` query errors (#27).
- Translation history joins the shared `$wpdb->users` table, fixing missing-users-table errors on multisite (#28).
- History deletion rejects empty or malformed timestamps instead of building an invalid comparison; the queries are now prepared (#29).
- A MySQL deadlock during a translation update is retried up to three times instead of failing silently (decision record 0002, #30).
- The optional memcached connection is deferred to the first cache operation instead of running on every request (#32).
- Posted translation parameters (`items`, `ln0`, `sr0`, `token`) are sanitized at read, completing the 2022 Plugin Directory review finding (#13).
