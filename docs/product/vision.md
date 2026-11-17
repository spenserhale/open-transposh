---
title: Vision
description: Who Open Transposh is for, the problem it solves, and what it will not become.
---

## Who it is for

WordPress site owners who run the Transposh Translation Filter plugin, or who want what it offers: a multilingual site where automatic translation covers every page and signed-in users the owner trusts correct translations in place.

## The problem

Transposh's author declined to fix reported security vulnerabilities, and WordPress.org removed the plugin from its directory. Sites that depend on it were left on an unmaintained plugin with known vulnerabilities. Moving to a different translation plugin means leaving behind the translations stored in Transposh's own tables and settings.

The fixes so far are marked in the code by CVE: CVE-2021-24910, CVE-2021-24912, CVE-2022-25810, CVE-2022-25811, CVE-2022-25812, and CVE-2022-2461 and CVE-2022-2462 (closed by removing anonymous editing).

## What it is

Open Transposh is a drop-in, security-fixed fork of Transposh. It translates a WordPress site's rendered pages into other languages by mixing automatic translation (Google, Bing, Yandex, Apertium, Baidu) with human translation through an in-context editor. It reads and writes the same options, tables, and public names as Transposh, so it can replace Transposh on an existing site and keep its translations.

## What it is not

- Not a rewrite of Transposh: Transposh's stored data and public names keep working (see [Transposh compatibility](../guides/compatibility.md)). The one deliberate break is security: anonymous editing is gone, and every translator needs a WordPress account.
- Not a translation engine: automatic translations come from external services, and human translations come from the site's own users.
- Not a `.po`/`.mo` localization workflow: it translates the rendered page, so other plugins' output needs no translation files.

## How we know it works

TODO: name the outcome to measure. The README lists goals (performance, security, ease of use, flexibility, SEO) but no measure; candidates are open security reports against the latest release, or Transposh sites switching without losing translations.
