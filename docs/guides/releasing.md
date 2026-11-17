---
title: Releasing
description: Read before bumping the version, tagging a release, bumping DB_VERSION, or changing CI, .deploy.gitignore, or which files ship in the installable plugin.
---

## Purpose

Read this guide when cutting a release, bumping the version, or changing what ships to sites.

## How a release reaches sites

- Every push to any branch runs `.github/workflows/ci.yml`. It installs production Composer dependencies (`composer install --no-dev`, which generates the `vendor/autoload.php` that `open-transposh.php` requires), deletes development paths, commits `built from <source sha>`, and force-pushes the result as `<branch>-built`.
- `main-built` is the installable plugin. Release tags are plain `X.Y.Z` (no `v`) and point at a `main-built` commit, not at `main`. Each GitHub release targets `main-built` with generated notes, and its source archive is the install package.

## Policy

- The version lives in two places, changed together in one commit titled `chore: update plugin version to X.Y.Z`: `TRANSPOSH_PLUGIN_VER` in `constants.php` and the `Version:` header in `open-transposh.php`. Versions follow Semantic Versioning.
- In the same commit, move the `## [Unreleased]` entries in `CHANGELOG.md` under a new `## [X.Y.Z] - YYYY-MM-DD` heading.
- Tag only after CI has built your version commit: check that the head of `main-built` reads `built from <your version commit sha>`, then tag that commit and publish the GitHub release from the tag. Release 2.1.5 was tagged on the build of the 2.1.4 commit, so it shipped code that reports version 2.1.4.
- Bump `DB_VERSION` in `constants.php` only when the schema in `Database::setup_db()` changes. The plugin reruns `dbDelta()` when the stored `transposh_db_version` differs from `DB_VERSION`.
- Add every development-only file or folder to the "Prepare for production" step in `ci.yml`. `.deploy.gitignore`, which replaces `.gitignore` in the build, only keeps untracked files out: a tracked file listed there still ships, which is why `README.md` and `.wp-env.json` are in `main-built` today.

## Known problems

- `build/bump-{major,minor,patch}-version.sh` call `build/bump-version.ts`, which uses `Deno.run` (removed in Deno 2) and also rewrites `readme.txt`, which does not exist. On Deno 2 it fails at once; on Deno 1 it edits the first two files and then fails. Bump by hand.
- `build/build-legacy.sh` is upstream Transposh's packager. It expects files this repo lacks (`readme.txt`, `index.html`, `build/generateversions.php`) and tools at fixed paths. Do not use it.
- The `README.md` headers are stale (`Stable tag: 2.1.1`, `Tested up to: 6.1.1`), and the release steps above do not update them.

## Related docs

- [Transposh compatibility](compatibility.md): stored data and `DB_VERSION`
- [Testing](testing.md)
- [0001: Keep docs and decisions in the repo](../decisions/0001-keep-docs-and-decisions-in-the-repo.md): why the docs are stripped from `main-built`
