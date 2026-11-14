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

Every DDL statement in `install/update/` **must** be re-runnable. A given update script can execute more than once against the same database, because "already applied" is recorded per deployment in `data/system/installed/{module}.json`, not in the database. Two deployments sharing one database each replay the script.

Supported by MariaDB (verified against 10.11):

- `CREATE TABLE IF NOT EXISTS ...`
- `CREATE VIEW IF NOT EXISTS ...` or `CREATE OR REPLACE VIEW ...` (the two are mutually exclusive)
- `CREATE INDEX IF NOT EXISTS ...` / `CREATE OR REPLACE INDEX ...`
- `ALTER TABLE ... ADD COLUMN IF NOT EXISTS ...`
- `ALTER TABLE ... ADD INDEX IF NOT EXISTS ...`
- `ALTER TABLE ... ADD UNIQUE INDEX IF NOT EXISTS ...`
- `ALTER TABLE ... ADD FOREIGN KEY IF NOT EXISTS ...`
- `ALTER TABLE ... DROP COLUMN IF EXISTS ...`
- `ALTER TABLE ... DROP INDEX IF EXISTS ...`
- `ALTER TABLE ... MODIFY COLUMN IF EXISTS ...` / `CHANGE COLUMN IF EXISTS ...`
- `DROP TABLE IF EXISTS ...`, `DROP VIEW IF EXISTS ...`, `DROP INDEX IF EXISTS ...`, `DROP TRIGGER IF EXISTS ...`

MySQL does not accept `IF (NOT) EXISTS` on `ADD COLUMN`, `ADD INDEX`, or `CREATE INDEX`. On MySQL, guard those by querying `INFORMATION_SCHEMA.COLUMNS` / `STATISTICS` first.

### Never drop columns in an update script

Leave a superseded column in place as an unused nullable column instead of `DROP COLUMN`. A drop is destructive, unrepeatable on a deployment that already ran the script, and breaks any build still reading the column. An unused column costs storage and nothing else.

Changing a unique key is the one legitimate exception: `DROP INDEX IF EXISTS old_name` followed by `ADD UNIQUE INDEX IF NOT EXISTS new_name`.

### Guard every backfill

A `DML` statement in an update script must produce the same result when run twice. Predicate it on the state it is intended to initialise:

```sql
-- Wrong: a second run resets the marker and erases what was already accounted for.
UPDATE ipdInvoices SET billingCountedUntil = UNIX_TIMESTAMP() WHERE end IS NULL;

-- Right: only drafts that were never initialised.
UPDATE ipdInvoices SET billingCountedUntil = UNIX_TIMESTAMP()
WHERE end IS NULL AND billingCountedUntil IS NULL;
```

### Additive changes keep older builds working

When one database serves two builds, an added column must be `NULL`able with no default, because an older build's `INSERT` omits it. Read paths are usually already safe: explicit column lists tolerate extra columns, and models that map a fixed field set ignore columns they do not declare. Check for `SELECT *` consumers before relying on that.

## Validation

- Ensure update scripts are additive and ordered.
- Re-read the finished script for any statement lacking `IF (NOT) EXISTS`.
- Confirm no `DROP COLUMN` and that every `UPDATE`/`INSERT` backfill is predicated.
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
