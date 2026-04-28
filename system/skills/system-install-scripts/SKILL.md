---
name: system-install-scripts
description: Use when creating or modifying module installation scripts, update migration scripts, or initial file assets (dist/). Covers the install-vs-update lifecycle, naming, ordering, and the rule that install scripts must stay complete while update scripts must only be added.
argument-hint: "<module-name> [script-type]"
---

# Zolinga Install & Update Scripts

## Use When

- Creating new installation scripts for a module.
- Adding incremental update/patch scripts.
- Placing static assets that ship with a module (`install/dist/`).
- Understanding why an update script didn't run or why install scripts were skipped.

## Critical: Always Bump Module Version

**Any change to `install/` artifacts — adding an update script, modifying install SQL, adding dist files, or any other install/update change — requires bumping the module version in `zolinga.json`.** The system detects version changes to trigger the install/update pipeline. Without a version bump, new update scripts will never execute on existing installations.

## Key Concept: Two-Phase Lifecycle

Every module has two script directories under `install/`:

| Directory | When scripts run | Can you edit existing scripts? |
|---|---|---|
| `install/install/` | **Only on first-ever install** of the module. Never again. | **Yes** — edits only affect new installations. |
| `install/update/` | **Only on update** (triggered when `zolinga.json` changes). Only scripts with **new filenames** run. | **No** — already-executed scripts are permanently skipped. Add new scripts instead. |

### Critical: Install skips existing update scripts

On first install, the system **notes and permanently marks all currently-present update scripts as already executed**. They will **never** run, even on future updates. Only update scripts added *after* the initial installation will execute.

This means:
- `install/install/` must always contain a **complete from-scratch setup** (full CREATE TABLE statements, initial data, etc.).
- `install/update/` must contain **only incremental deltas** (ALTER TABLE ADD COLUMN, etc.).
- Never move a script from `install/install/` to `install/update/` — it will be skipped on existing installations.

### Database schemas: complete install + incremental updates

For database schemas (and analogously for any other artifact):

1. **`install/install/`** — must contain the **full, current schema** as CREATE TABLE statements. A fresh install must produce the exact same database state as an old installation that has run all updates.
2. **`install/update/`** — each schema change gets its **own new file** (e.g., `040-add-column-foo.sql`). Never edit an existing update script.

If you add a column to a table:
- Add a new `install/update/040-add-column-foo.sql` with `ALTER TABLE … ADD COLUMN …`.
- Also update the `install/install/` CREATE TABLE statement to include the new column.

## Naming Convention

```
{NUMBER}_{DESCRIPTIVE-NAME}.{EXTENSION}
```

- `NUMBER` — controls execution order (natural sort across **all** modules).
- Examples: `010_database.sql`, `020-database.sql`, `030-data.sql`, `040-add-column-foo.sql`.

### Cross-module ordering

All scripts from **all** modules are sorted together by filename. If your script depends on a table from another module, use a higher number prefix to ensure it runs after that module's scripts.

## Supported Script Types

| Extension | Handler | Module |
|---|---|---|
| `.php` | `Zolinga\System\Installer\InstallPhpScript` | system (built-in) |
| `.sql` | `Zolinga\Database\InstallSqlScript` | zolinga-db |

### PHP scripts

PHP scripts are executed inside a function scope with `global $api` available:

```php
// install/install/050-setup.php
global $api;
$api->config['myModule']['installed'] = true;
$api->log('myModule', 'Initial setup complete');
```

### SQL scripts

SQL scripts are executed by the `zolinga-db` module against the configured database. Use standard MySQL/MariaDB DDL/DML.

### Custom script types

Any module can add support for new file types by listening to the `system:install:script:{EXTENSION}` event. See `system/wiki/ref/event/system/install/script/wildcard.md`.

## Static Assets: `install/dist/`

The `install/dist/` directory is **automatically symlinked** to `public/dist/{module-name}/`. Place publicly accessible files here:

- JavaScript, CSS, images → `install/dist/js/`, `install/dist/css/`, etc.
- Web components → `install/dist/web-components/`
- API endpoints → `install/dist/gate/`

No install script is needed for dist files — the symlink is created automatically during install/update.

## Workflow

1. **New module**: Create `install/install/` scripts for complete from-scratch setup.
2. **Schema change**: Add a new numbered script to `install/update/` AND update the corresponding `install/install/` script to reflect the current full state.
3. **Bug in update script**: Do NOT edit the broken script. Add a new fix script with a higher number.
4. **Trigger install/update**: Bump the module version in `zolinga.json` — the system detects the change and runs pending scripts.
5. **Add custom script type**: Create a listener for `system:install:script:{ext}` event with `internal` origin. See `system/wiki/Zolinga Core/Module Installation and Updates.md`.

## Related Skills

- **`system-database-schema-updates`** — DB-specific conventions (snake_case naming, `$api->db` usage, schema validation). Use alongside this skill when working with SQL schemas.

## References

- `system/wiki/Zolinga Core/Module Installation and Updates.md` — full lifecycle documentation
- `system/wiki/Zolinga Core/Module Installation and Updates/PHP Installation Scripts.md` — PHP script details
- `system/wiki/ref/event/system/install/script/wildcard.md` — custom script type extensibility
- `system/wiki/ref/event/system/install/script/php.md` — PHP handler reference
- `system/wiki/ref/event/install.md` — CLI install command