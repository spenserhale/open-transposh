---
title: "0002: Retry deadlocked translation updates without wrapping them in a transaction"
description: "A bounded in-place retry when MySQL reports a deadlock during the translation update sequence, rather than restructuring it into a transaction."
status: proposed
date: 2026-11-16
decision-makers: "agent (unattended run)"
---

## Context

Issue #30 (Rollbar #14944): `Database::update_translation()` (entry near `src/Database.php:324`) performs the history copy (`INSERT ... SELECT` into the log table, ~line 443), the delete (~453), and the insert (~463) as three separate `$wpdb->query()` calls in autocommit mode. Under concurrent edits of the same translation, MySQL reported `Deadlock found when trying to get lock; try restarting transaction`, which `$wpdb` surfaces in `last_error` while the caller (`Plugin.php:1706`, the `tp_translation` Ajax path) proceeds as if the update succeeded.

Constraint: `$wpdb` here runs autocommit; the surrounding code also fires hooks (`transposh_translation_posted`, `transposh_human_translation`) between steps, and hosts in the evidence run query-analyzing proxies (VIP HyperDB) where plugin-managed `START TRANSACTION` is discouraged. This is a resilience pattern later fixes may copy.

## Options considered

1. **Wrap the sequence in `START TRANSACTION` / `COMMIT` with rollback on error**: atomic across the three statements. Costs: changes lock timing (likely makes deadlocks rarer but longer-held), interacts badly with hosts that route or filter transactions, and the interleaved hooks/logging would need auditing for side effects inside a transaction.
2. **Bounded retry of the existing sequence**: after the final statement, if `last_error` matches the MySQL deadlock message, re-run the three statements up to 3 times with a short sleep. Costs: the sequence is not atomic, so a retry re-runs statements that may have partly applied; the statements are already idempotent for this purpose (history copy may duplicate a row if the retry hits after the copy but before the delete — accepted, history duplicates are benign and visible).
3. **Do nothing / log only**: leaves data loss on a production-reproduced deadlock.

## Decision

We will retry the existing three-statement sequence in place (option 2), because it is the smallest change that recovers from the reproduced failure without changing lock behavior on hosts we cannot test against.

## Consequences

- A deadlocked update no longer silently loses the translation while reporting success; after 3 attempts the failure propagates to the caller as a failed update.
- History may gain a duplicate row when a retry partially applies; accepted over losing the edit.
- No transaction management is introduced; if a future change needs true atomicity, it must supersede this record.

## Revisit when

Duplicate history rows become a reported problem, a host requires atomic updates, or the retry loop shows up in error reporting (meaning 3 attempts no longer suffice).

## Links

- Plan: [gh-issue-batch](../plans/2026-11-16-gh-issue-batch.md)
- Guide: [Security](../guides/security.md) (SQL policy)
