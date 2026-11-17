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
