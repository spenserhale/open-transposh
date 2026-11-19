---
title: Transposh compatibility
description: Read before renaming, removing, or changing the meaning of any option, table, global, function, class, hook, cron event, shortcode, Ajax action, URL parameter, cookie, CSS class, or widget file that Transposh sites rely on.
---

## Purpose

Read this guide when a change touches anything an existing Transposh site, theme, or add-on can see: stored data, public PHP names, hooks, URLs, markup, or Ajax actions. Open Transposh is a drop-in replacement for Transposh (see the README), so a site must be able to swap plugins and keep its translations, settings, widgets, scheduled jobs, and theme code.

## Policy

- Treat every identifier in the table below as a public interface, even when the name looks dated. Do not rename it, remove it, or change what it means.
- To modernize a name, add the new one beside the old one and keep the old one working.
- Changing or removing one anyway is a strong decision: it needs a decision record and a changelog entry.
- The one precedent for breaking compatibility is security: anonymous editing was removed to close CVE-2022-2461 and CVE-2022-2462 (the "Reported Vulnerabilities" note in `src/Plugin_Admin.php`). A future break needs a reason of that weight.
- Stored data only moves forward. A schema change edits the `CREATE TABLE` statements in `Database::setup_db()` and bumps `DB_VERSION` in `constants.php`; on upgrade, `dbDelta()` adds columns and indexes but never drops them.
- A new setting gets a default in its `register_option()` call (`src/Plugin_Options.php`), so `transposh_options` arrays saved by Transposh or older releases still load.

## Identifiers to keep

| Kind | Identifiers | Defined in |
|---|---|---|
| Options | `transposh_options` (one array; its keys are the `register_option()` names), `transposh_db_version`, `transposh_inside_dbupgrade`, `transposh_options_oht`, `transposh_options_oht_projects`, `transposh_options_googleproxy`, `transposh_options_yandexproxy` | `constants.php`, `src/Plugin_Options.php` |
| Tables | `{$wpdb->prefix}translations`, `{$wpdb->prefix}translations_log` | `constants.php`, `src/Database.php` |
| Globals | `$my_transposh_plugin`, `$tp_logger` | `open-transposh.php` |
| Functions | `transposh_widget()`, `transposh_get_current_language()`, `transposh_echo()`, `tp_logger()` | `functions.php` |
| Classes | `transposh_plugin`, `tp_logger`, `transposh_consts`, `transposh_utils`, `tp_parser`, `tp_parserstats`, `transposh_base_widget`, `transposh_plugin_widget` | `legacy.php` |
| Actions fired | `transposh_translation_posted`, `transposh_human_translation`, `transposh_oht_callback` | `src/Ajax_Controller.php`, `src/Database.php`, `src/Plugin.php` |
| Filters applied | `tp_language_name`, `tp_language_origname`, `tp_language_flag`, `tp_language_locale` | `src/Core/Constants.php` |
| Cron events | `transposh_backup_event`, `transposh_oht_event`, `transposh_digest_event`, `transposh_buffered_mail_event`, `superproxy_reg_event` | `src/Plugin.php`, `src/Mail.php` |
| Shortcodes | `[tp]`, `[tpe]` | `src/Plugin.php` |
| Ajax actions | `tp_history`, `tp_translation`, `tp_tp`, `tp_oht`, `tp_ohtcallback`, `tp_trans_alts`, `tp_cookie`, `tp_cookie_bck`, `proxy`, and the admin `tp_*` actions | `src/Ajax_Controller.php`, `src/Plugin.php`, `src/Plugin_Admin.php` |
| URLs and cookies | query vars `lang` and `tpedit`, cookie `TR_LNG` | `constants.php`, `src/Plugin.php` |
| Markup and scripts | classes `no_translate`, `notranslate`, `only_thislanguage`; segment prefix `tr_`; script handle `transposh` with its `t_jp` object | `constants.php`, `src/Plugin.php` |
| Widget styles | `widgets/<style>/tpw_<style>.php`, saved per widget as `widget_file` (for example `default/tpw_default.php`) | `src/Widgets/Plugin_Widget.php` |
| Roles and strings | capability `translator`, text domain `transposh`, nonce field `transposh_nonce` | `constants.php` |

## Implementation notes

- `legacy.php` maps Transposh's class names onto the namespaced classes. `$my_transposh_plugin` and `$tp_logger` are `Legacy_Adapter` wrappers that forward property reads, writes, and method calls to `OpenTransposh\Plugin` and the logger. New code uses `OpenTransposh\Plugin::get_instance()` (also in `$open_transposh_plugin`); the wrappers stay for theme and add-on code.
- Renaming a folder or file under `widgets/` breaks every saved widget that points at it, because the path itself is the saved setting.
- WordPress stores scheduled events by hook name, so renaming a cron hook orphans jobs already scheduled on live sites.

## Related docs

- [Security](security.md): the public Ajax endpoints in this table are also attack surface
- [Releasing](releasing.md): `DB_VERSION` bumps and what ships
- [Vision](../product/vision.md)
