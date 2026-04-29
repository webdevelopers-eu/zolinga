---
name: system-database-schema-updates
description: Use when creating or modifying database schema in Zolinga modules, including install SQL and update migration scripts with naming conventions.
argument-hint: "<module-name> [table-or-view]"
---

# Zolinga Database Schema Updates

## Use When

- Creating new schema objects.
- Updating existing tables, views, or indexes.

> **See also:** `system-install-scripts` — covers the full install-vs-update lifecycle, naming, ordering, and the rule that install scripts must stay complete while update scripts must only be added. Essential reading before creating any install or update script.

## Critical: Always Bump Module Version

**Any schema change — adding an update script, modifying install SQL, or altering any `install/` artifact — requires bumping the module version in `zolinga.json`.** The system detects version changes to trigger the install/update pipeline. Without a version bump, new update scripts will never execute on existing installations.

## Workflow

1. Use `camelCase` for DB table names, field names, and aliases (e.g. `rmsUsers`, `cronJobs`, `ipdAccounts`, `trialStart`, `subscriptionEnd`).
2. Prefix every table name with the **module abbreviation** (e.g. `ipd` for `ipdefender`, `rms` for `zolinga-rms`, `cron` for `zolinga-cron`). The prefix is derived from the module folder name, shortened to a short lowercase identifier. Examples: `ipdAccounts`, `ipdWatchlist`, `rmsUsers`, `cronJobs`.
3. Put initial install SQL in `modules/<module-name>/install/install/*.sql`.
4. Put incremental schema updates in `modules/<module-name>/install/update/*.sql`.
5. Use `$api->db` (`query`, `queryExpand`) from PHP code for DB access.
6. **Bump the module version** in `modules/<module-name>/zolinga.json` (e.g. `"version": "1.3"` → `"1.4"`), then run `bin/zolinga` (no parameters) to trigger the install/update pipeline.

## Idempotency (IF NOT EXISTS / IF EXISTS)

All DDL statements that support `IF NOT EXISTS` or `IF EXISTS` **must** use them so scripts are safe to run multiple times without failing:

- `CREATE TABLE IF NOT EXISTS ...`
- `CREATE VIEW IF NOT EXISTS ...`
- `CREATE INDEX IF NOT EXISTS ...` (MySQL 8.0+; for older versions, check existence first)
- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...` (MySQL 8.0+; for older versions, use a separate check)
- `DROP TABLE IF EXISTS ...`
- `DROP VIEW IF EXISTS ...`
- `DROP INDEX IF EXISTS ...` (MySQL 8.0+)

For MySQL versions that do not support `IF NOT EXISTS` on a given statement (e.g. `ADD COLUMN` before 8.0), wrap the operation in a conditional check using a `DO` block or a stored procedure that queries `INFORMATION_SCHEMA`.

## Validation

- Ensure update scripts are additive and ordered.
- Ensure schema changes map to expected model/query usage.

## Documentation Abstract

- Start with `Module Installation and Updates.md` for install/update lifecycle.
- Use `PHP Installation Scripts.md` for mixed SQL/PHP installation flows.
- Use `Configuration.md` when schema behavior is controlled by config values.

## References

- `system/wiki/Zolinga Core/Module Installation and Updates.md`
- `system/wiki/Zolinga Core/Module Installation and Updates/PHP Installation Scripts.md`
- `system/wiki/ref/event/install.md`
- `system/wiki/ref/event/system/install/script/php.md`
- `system/wiki/ref/event/system/install/script/wildcard.md`
- `system/wiki/Zolinga Core/Configuration.md`
