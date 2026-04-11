---
name: system-database-schema-updates
description: Use when creating or modifying database schema in Zolinga modules, including install SQL and update migration scripts with naming conventions.
argument-hint: "<module-name> [table-or-view]"
---

# Zolinga Database Schema Updates

## Use When

- Creating new schema objects.
- Updating existing tables, views, or indexes.

## Workflow

1. Use `snake_case` for DB table names, field names, and aliases.
2. Put initial install SQL in `modules/<module-name>/install/install/*.sql`.
3. Put incremental schema updates in `modules/<module-name>/install/install/updates/*.sql`.
4. Use `$api->db` (`query`, `queryExpand`) from PHP code for DB access.

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
