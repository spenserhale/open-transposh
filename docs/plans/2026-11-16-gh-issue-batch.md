---
title: "Plan: Fix the reported GitHub issues (security review + Rollbar deprecations)"
description: "Fix issues #13–#33 (sanitize/escape findings and PHP 8.1+ runtime defects), close #25 and #34 as verified no-ops, validate with PHPUnit and Playwright."
status: in-progress
date: 2026-11-16
size: bounded
specs: []
---

## Intent

The tracker holds two kinds of open reports against `main` (verified at f0860d2):

- **#13/#14 (2022 WordPress Plugin Directory review)**: remaining unsanitized superglobal reads (`src/Database.php`, `src/Ajax_Controller.php`; the `Post_Publish` reads named in #13 are already fixed) and unescaped widget-template output across five `widgets/<style>/tpw_*.php` files.
- **#17–#33 (Rollbar evidence, Nov 2026)**: PHP 8.1+ deprecations and warnings (null passed to string functions, false-to-array, uninitialized string offsets), WordPress 6.7 `_load_textdomain_just_in_time` notice, query errors against missing tables, multisite users-table joins, an unvalidated deletion timestamp, unhandled deadlocks, an eager Memcache connection, and an unchecked `session_start()`.

#25 is already fixed at HEAD (6623701, `src/Plugin_Options.php:182-185`) and #34 has no Open Transposh frames (no `mysqli_ping` in the repo; owned by HyperDB). Both close with a comment, no code.

Success: every code issue has a minimal fix with regression tests, the PHPUnit suite is green, and Playwright QA on the wp-env dev site shows the plugin working (widget renders, language switch works, edit mode loads) with no PHP notices in output.

## Acceptance criteria

- #13: the remaining `$_POST` reads in `src/Database.php` and `src/Ajax_Controller.php` are sanitized at read.
- #14: every echoed variable in the five affected widget templates is escaped (`esc_url`, `esc_html`, `esc_attr` as fits).
- #17: `Utilities::cleanup_url()` handles a pathless absolute URL and an unparseable URL without warnings; existing query/fragment/language behavior preserved.
- #18: widget CSS/JS collectors handle an empty `widget_file` without an uninitialized-offset warning; the default widget still renders.
- #19: `Utilities::get_language_from_url()` accepts null and returns the no-language fallback without a deprecation.
- #20: `Plugin::iq()` XORs with the integer seed component; token output unchanged from the (implicit) truncation behavior; no deprecation.
- #21: booting the plugin no longer triggers `_load_textdomain_just_in_time` on WordPress 6.7+ (reproduced live on the dev site).
- #22: `Plugin::split_url()` handles a pathless or unparseable URL without a deprecation.
- #23: absent `Accept-Language` header yields the normal fallback without passing null to `preg_match_all()`.
- #24: `Utilities::rewrite_url_lang_param()` returns the URL unchanged when `parse_url()` fails instead of writing to false.
- #26: `Parser` numeric-break compensation never reads past end-of-string (e.g. input `B2,`).
- #27: `Database::setup_db()` creates tables before introspecting their indexes; no query errors on a fresh install.
- #28: translation history joins `$wpdb->users` (shared on multisite, identical on single site).
- #29: an empty/omitted history timestamp is rejected at the boundary; no `TIMESTAMP value: ''` errors; no bulk delete.
- #30: a deadlock during translation update is retried a bounded number of times; success/failure is reported.
- #31: `Utilities::wordpress_user_by_by()` tolerates missing author metadata.
- #32: no Memcache connection attempt unless the plugin's cache option wants memcache; failure remains non-fatal.
- #33: `session_start()` failure skips session use instead of relying on `$_SESSION`.
- #25, #34: closed with an explanatory comment, referenced in the PR.
- `npm run test:php` green; Playwright QA passes on :8888.

## Non-goals

- No schema change, no `DB_VERSION` bump, no index changes beyond what exists.
- No transaction/locking redesign of the translation update beyond bounded retry.
- No rewrite of the ~80 legacy superglobal reads the security guide already tracks — only the ones #13 names.
- No replacement of `parse_url()` with a full RFC 3986 parser.
- No translation-engine behavior change (#20 only removes the deprecation; the token value is preserved).

## Design

Per issue, the minimal guard at the cited site, following the surrounding style:

- **Utilities (#17, #19, #23, #24, #31)**: after every `parse_url()`, validate `is_array()` before reads/writes; unparseable → return the input unchanged (or the empty fallback `get_language_from_url()` already returns). Missing `path` → `''` where a string is required. Null-safe: reject null input up front. `wordpress_user_by_by()`: null/empty `$by` → the existing non-user display path. `Accept-Language` missing → return the existing no-header fallback.
- **Parser (#26)**: add the `?? ''` end-of-string fallback already used by the neighboring lookaheads.
- **Plugin (#20, #22, #33, #21a)**: `iq()` uses `(int) $e[0]` for the final XOR — identical to PHP's implicit truncation, so tokens are unchanged. `split_url()` passes `?? ''` to `explode()`. Session: capture `@session_start()`'s return and gate `$_SESSION` access on it. Textdomain: call `load_plugin_textdomain()` in the `Plugin` constructor (before the widget is built) instead of inside `on_init()`, so the widget's `__()` no longer triggers JIT loading; the later `init` registration is removed, not duplicated.
- **Database (#27, #28, #29, #30, #32, #13b)**: reorder `setup_db()` so `dbDelta()` runs before the `SHOW INDEX` introspection. Replace `{$wpdb->prefix}users` joins with `$wpdb->users`. `del_translation_history()` returns false on empty/invalid timestamp (never a wildcard delete), validates the format, and the rewritten query uses `$wpdb->prepare()` per the security guide; `Ajax_Controller` rejects empty timestamps at the boundary. Deadlock: a small retry wrapper around the copy→delete→insert sequence (3 attempts, short sleep, match `Deadlock ... try restarting transaction` in `$wpdb->last_error`). Memcache: attempt the connection only when the plugin's cache option selects memcache; keep the suppressed attempt and existing unavailable marking. Posted values in `Database` (`items`, `ln0`, `sr0`) and `Ajax_Controller` (`token`) sanitized at read (`sanitize_text_field`, stripslashes order preserved where it matters).
- **Widgets (#14, #18, #21b)**: normalize an empty `widget_file` to the default style before the `$key[0]` checks in both collectors. Escape output in the five templates that echo `$langrecord` fields raw: `flagslist/tpw_list_with_flags_css.php`, `flags/tpw_flags_css.php`, `flags/tpw_flags.php`, `default/tpw_default.php`, `select2/tpw_select2.php`.
- **Testing**: extend `tests/Core/` (Utilities, Parser); add `tests/Plugin/` and `tests/Database/` classes following `docs/guides/testing.md` naming (`*_Tests.php`). Warning-class assertions via a temporary `set_error_handler` where the defect was a warning. Playwright specs under `tests/e2e/` (devDependency `@playwright/test`, config inside `tests/e2e/` so nothing new lands at the repo root; `tests/` and `package.json` are already stripped by CI).

Runner-up for #21 (deferred widget construction to `widgets_init`) lost: `$my_transposh_plugin->widget` is public surface themes may touch before `widgets_init`; moving the textdomain load is one line and keeps construction order.

## Spec delta

None. `docs/specs/` has no feature specs yet (template only); these fixes restore intended behavior rather than change it. Ruling below.

## Docs impact

- `CHANGELOG.md`: one `### Fixed` line per issue group, added with each fix commit.
- `docs/guides/testing.md`: "Known problems" — Parser/Utilities-only coverage claim becomes stale once new test classes land.
- `docs/guides/security.md`, `docs/guides/compatibility.md`: no change; fixes follow them (no identifier renames, SQL hardening per policy).

## Decisions

- [0002: Retry deadlocked translation updates without wrapping them in a transaction](../decisions/0002-retry-deadlocked-translation-updates.md)

## Tasks

Each block = one commit (code + tests + changelog + this plan's status):

1. **Utilities+Parser** (`src/Core/Utilities.php`, `src/Core/Parser.php`, `tests/Core/*`): #17, #19, #23, #24, #31, #26. Commit: `fix(Core): harden URL, language, and parser helpers against null and unparseable input`.
2. **Plugin** (`src/Plugin.php`, `tests/Plugin/*`): #20, #22, #33, #21. Commit: `fix(Plugin): guard pathless URLs, session start, Google token seed; load textdomain before widget`.
3. **Database** (`src/Database.php`, `src/Ajax_Controller.php`, `src/Editor_Table.php`, `tests/Database/*`): #13b, #27, #28, #29, #30, #32. Commit: `fix(Database): table setup order, shared users table, timestamp validation, deadlock retry, memcache gating`.
4. **Widgets** (`src/Widgets/Plugin_Widget.php`, `widgets/*`, tests if cheap): #14, #18, #21b verification. Commit: `fix(Widgets): escape template output and handle empty widget_file`.
5. **QA + docs**: Playwright specs + testing.md update; changelog consolidation; issue comments for #25/#34. Commit: `test(e2e): add Playwright smoke suite for the dev environment`.

## Rulings

- [docs] no new specs in this batch — specs folder is empty; changelog carries user-visible notes — cost if wrong: low, specs can be backfilled.
- [i18n #21] early explicit `load_plugin_textdomain()` over deferred widget construction — public `$widget` property timing — low.
- [session #33] `@session_start()` with return check, not a handler swap — hosting-owned handler stays — low.
- [memcache #32] gate on cache option, keep suppressed connect attempt — preserves existing fallback marking — low.
- [db #29] `del_translation_history()` returns false on empty timestamp; callers treat as no-op — prevents bulk delete — medium if wrong: history loss.
- [security #14] fix all five widget templates, not only the file named in the issue — same one-line pattern, same review finding — low.
- [db #27] reorder dbDelta before SHOW INDEX, not error suppression — issue asks for existence first — low.

## Deferred

- Automated test for the session-start guard (CLI test process already has a session; would need process isolation).
- Multisite PHPUnit run (`tests/phpunit/multisite.xml`) — noted for CI future work; manual check only.
- Google token end-to-end (network) verification — `iq()` covered by unit test; engine call exercised only if network allows during QA.
