# AGENTS.md

## Purpose
Open Transposh is a WordPress plugin: a security-fixed, drop-in fork of the Transposh Translation Filter, which WordPress.org removed after its author declined to fix reported vulnerabilities. It translates a site's rendered pages by parsing the HTML output, mixing automatic translation (Google, Bing, Yandex, Apertium, Baidu) with corrections from signed-in translators in an in-context editor. It keeps Transposh's options, tables, and public names so it can replace Transposh on existing sites. Releases ship from the CI-built `main-built` branch.

## Quick start
- Install: `composer install`. Needed even to load the plugin: `open-transposh.php` requires `vendor/autoload.php`, and `vendor/` is not committed.
- Test: `composer phpunit`. Needs a WordPress install around the checkout and a MySQL test database; see `docs/guides/testing.md`. Not run during setup, and `phpunit.xml.dist` uses attributes that PHPUnit 10 and later removed.
- Lint: no ruleset file. WordPress Coding Standards comes with `composer install`; check the files you change with `vendor/bin/phpcs --standard=WordPress <files>` (not run during setup).
- Local site: `npx @wordpress/env start` uses `.wp-env.json` (PHP 8.4, this plugin mounted); not verified.
- Build and release: nothing to build locally. CI builds `main-built` on every push, and the version bump scripts in `build/` are broken; see `docs/guides/releasing.md`.

## Repo layout
- `open-transposh.php`: bootstrap and plugin headers. `constants.php`: global constants, including the plugin version and `DB_VERSION`. `functions.php`: functions themes call. `legacy.php`: Transposh's class names mapped to the new classes.
- `src/`: PSR-4 namespace `OpenTransposh\`, one class per file named after the class (`Plugin_Admin` lives in `src/Plugin_Admin.php`). `Plugin.php` wires hooks, Ajax, and translation engines; `Plugin_Admin.php` and `Plugin_Options.php` run the settings; `Database.php` owns the tables and caching; `Core/` holds the HTML `Parser`, `Utilities`, and the language table in `Constants`; `Libraries/SimpleHtmlDom/` is a bundled, namespaced copy of SimpleHtmlDom.
- `widgets/<style>/tpw_<style>.php`: language switcher styles. `js/`, `css/`, `img/`, `jquery-ui/`: front-end assets, shipped as is with no build step. `langs/`: the plugin's own translations (text domain `transposh`).
- `tests/`: WordPress PHPUnit tests. `build/`: release scripts. `scripts/cleanup_db.sh`: deletes Transposh options and tables from a local database.
- Runtime code must run on PHP 8.1 and WordPress 6.0 (the `Requires PHP` and `Requires at least` headers), even though CI and `.wp-env.json` use PHP 8.4 and nothing checks the floor. No PHP 8.2+ syntax: no readonly classes, DNF types, or typed class constants.

## Documentation
Durable project knowledge lives in `docs/` as Markdown with `title` and `description` frontmatter and no body H1 (Starlight-compatible). Before changing a feature's behavior, read its spec and the decisions that touch it.

| Kind | Path | Rule |
|---|---|---|
| Specs: what each feature does now | `docs/specs/<feature>.md` | Change in the same commit as the behavior; never ahead of the code |
| Decisions: why | `docs/decisions/NNNN-<title>.md` | New record for every strong decision; supersede, never rewrite |
| Plans: one change, designed and tasked | `docs/plans/YYYY-MM-DD-<topic>.md` | Write and commit before the code |
| Product | `docs/product/vision.md` | Read before scoping a change |
| Guides | `docs/guides/<topic>.md` | See Progressive disclosure |
| Changelog | `CHANGELOG.md` | Add an `## [Unreleased]` line in the same commit as any user-visible change |

Templates: `_template.md` in `docs/specs/`, `docs/decisions/`, and `docs/plans/`. A decision is strong when it is hard to reverse, adds a dependency or a pattern others will copy, changes a public interface or data model, touches security, privacy, or pricing, or departs from an accepted decision (or changes something a spec states that the change did not set out to change). Smaller calls go in the plan's `## Rulings`. Never deviate silently from a spec or an accepted decision: ask first; only when no human is in the loop, write the superseding record and call it out in the pull request.

These docs are development-only: CI strips `docs/`, `AGENTS.md`, `CLAUDE.md`, and `CHANGELOG.md` from `main-built` (`docs/decisions/0001-keep-docs-and-decisions-in-the-repo.md`).

## Progressive disclosure
Before editing, check whether the task touches one of these areas and read the guide first (a TODO line is a guide not written yet):
- Transposh compatibility, for any option, table, global, function, class, hook, cron event, shortcode, Ajax action, URL parameter, cookie, CSS class, or widget file: `docs/guides/compatibility.md`
- Security, for request input, output escaping, Ajax actions, admin forms, SQL, or outbound HTTP: `docs/guides/security.md`
- Testing: `docs/guides/testing.md`
- Releasing, for version bumps, tags, `DB_VERSION`, CI, or what ships in `main-built`: `docs/guides/releasing.md`
- TODO Database (`docs/guides/database.md`): the table schema, the `DB_VERSION` upgrade path, and the APCu, memcached, and xcache translation cache; write it with the next schema or cache change.
- TODO Translation engines (`docs/guides/translation-engines.md`): engine API keys, the server-side translation proxies, and One Hour Translation; write it with the next engine change.

## Coding standards
1. **YAGNI**: Don't build it until you need it
2. **DRY**: Extract patterns after second duplication, not before
3. **Fail Fast**: Explicit errors beat silent failures
4. **Simple First**: Write the obvious solution, optimize only if needed
5. **Delete Aggressively**: Less code = fewer bugs
6. **Semantic Naming**: Always name variables, parameters, and API endpoints with verbose, self-documenting names that optimize for comprehension by both humans and LLMs, not brevity (e.g., `wait_until_obs_is_saved=true` vs `wait=true`)

## Commits
A change starts with its plan, committed before any code together with the decision records made while designing. After that, one commit is one complete change: the code, its tests, and every doc it changes (spec, guide, changelog line, decision record). Conventional Commits subject (`feat(scope): ...`); the body says why and names the docs it touched (`Spec:`, `Decision:`, `Plan:`). Scopes in history name the class or area touched: `fix(Post_Publish): ...`, `refactor(Database): ...`, `chore(ci): ...`.

## Done criteria
Before finishing any task:
- Run `composer phpunit` and fix failures before declaring done; without a WordPress test install, say the suite was not run
- Behavior changed: the spec, changelog, and any affected guide changed in the same commit
- A strong decision was made: its decision record exists
- An identifier listed in `docs/guides/compatibility.md` changed: the old name still works, or a decision record explains why not
- A development-only path was added at the repo root: it is in the strip step of `.github/workflows/ci.yml`
- If a check was skipped, say why; flag risky assumptions explicitly
