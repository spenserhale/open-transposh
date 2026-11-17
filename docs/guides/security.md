---
title: Security
description: Read before changing request handling, Ajax actions, admin forms, SQL, output escaping, the debug log, or calls to outside services.
---

## Purpose

Read this guide when a change reads request data, prints output, adds or changes an Ajax action, admin form, or SQL query, or calls an outside service. Fixing the vulnerabilities Transposh's author left unfixed is why this fork exists (see the README), so a regression here undoes the project's purpose.

## Policy

- Translation editing requires a WordPress account. Anonymous editing was removed to close CVE-2022-2461 and CVE-2022-2462; do not bring it back. Translators hold the `translator` capability (`Plugin::is_translator()`), and users with `manage_options` can always edit.
- A handler that changes state checks a capability and a nonce before it does anything. Follow the existing patterns:
  - Admin Ajax (`on_ajax_tp_*` in `src/Plugin_Admin.php`): the first line is `$this->admins_only()`, which verifies the `nonce` POST field and `manage_options`.
  - Settings form: `on_save_changes()` checks `manage_options`, then `check_admin_referer( -1, self::TR_NONCE )` against the `transposh_nonce` field.
- `wp_ajax_nopriv_*` actions are public endpoints that anyone can call without logging in. Today they are `tp_tp`, `tp_oht`, `tp_ohtcallback`, `tp_trans_alts`, `tp_cookie`, `tp_cookie_bck`, and `proxy` when the super proxy option is on (all in `src/Plugin.php`). Adding or loosening one changes the security posture and needs a decision record.
- Sanitize request input where you read it: `sanitize_text_field()` or a stricter sanitizer for `$_GET`, `$_POST`, `$_REQUEST`, and `$_COOKIE`, and `Utilities::get_clean_server_var()` for `$_SERVER`. About 80 legacy reads still use superglobals raw; sanitize the ones your change touches.
- Escape at output for the context: `esc_html()`, `esc_attr()`, `esc_url()`, and `wp_json_encode()` for data placed in JavaScript. Many legacy `echo` statements skip escaping; escape the ones your change touches.
- SQL: existing queries interpolate values escaped with `esc_sql()`, and none use `$wpdb->prepare()`. Write new or rewritten queries with `$wpdb->prepare()` (WordPress Coding Standards, already a dev dependency, flags interpolated SQL). When editing an old query, keep every interpolated value escaped.
- Name the CVE or advisory at the fix, as existing fixes do (`// FIX - CVE-2021-24912` in `src/Plugin_Admin.php`), and in the commit message (`Fix CVE-2022-25811`).

## Implementation notes

- Fixes in place; do not regress them:
  - CVE-2021-24910: translation results returned as JSON are escaped (`src/Plugin.php`).
  - CVE-2021-24912 and CVE-2022-25810: the nonce and capability checks in `admins_only()` (`src/Plugin_Admin.php`).
  - CVE-2022-25811: `src/Editor_Table.php`.
  - CVE-2022-25812: `php` in the debug log file name is rewritten to `npn`, so the log cannot become an executable PHP file (`src/Plugin_Admin.php`).
- `tp_history` and `tp_translation` (`src/Ajax_Controller.php`) are registered for logged-in users only, and their responses send `Access-Control-Allow-Origin: *`.
- Outbound HTTP uses `wp_remote_get()`, `wp_remote_post()`, or `wp_remote_request()`, except one raw `curl_init()` in `src/Plugin.php`. Use the WordPress functions in new code.
- Backup and restore (`src/Backup.php`) talk to the original Transposh service over plain HTTP (`TRANSPOSH_BACKUP_SERVICE_URL` and `TRANSPOSH_RESTORE_SERVICE_URL` in `constants.php`). Restore sends the site's `transposh_key` and home URL in the query string.

## Related docs

- [Transposh compatibility](compatibility.md): the public names and endpoints other code depends on
- [Testing](testing.md)
