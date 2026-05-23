---
name: system-debug-database
description: Use when you need to inspect database schema, list tables, view table structure, or run read-only SELECT queries for debugging purposes using the mysql CLI.
argument-hint: ""
---

# System Debug Database

## Use When

- You need to list all databases or tables in the system.
- You need to inspect a table's schema (columns, types, indexes).
- You need to run a SELECT query to debug data issues.
- You need to check row counts, sample data, or foreign key relationships.
- You need to understand the database structure for development or troubleshooting.

## CRITICAL: Read-Only Constraint

**You are FORBIDDEN from making ANY changes to the database.** This includes:

- `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `DROP`, `ALTER`, `CREATE`, `RENAME`
- `GRANT`, `REVOKE`, `FLUSH`, `KILL`
- `SELECT ... INTO OUTFILE`, `SELECT ... INTO DUMPFILE`, `LOAD DATA`
- Any stored procedure/function that modifies data
- Any DDL or DML statement

If you need to modify the database schema or data, you must report your findings to the user and let them execute the changes manually.

**Never chain multiple statements in a single `mysql -e` call.** Each invocation must contain exactly one read-only command. This prevents accidental destructive operations from being appended.

## Authentication

- The `mysql` command **must** be called without any `-u`, `-p`, `--user`, `--password`, `--host`, or `--protocol` flags.
- Authentication is handled exclusively via `~/.my.cnf` configured by the user.
- **Never** read credentials from `config/*.json` files or any other configuration file.
- **Never** echo, print, or log any credentials.

## Common Commands

### List all databases

```bash
mysql -e "SHOW DATABASES;"
```

### List tables in a database

```bash
mysql <database> -e "SHOW TABLES;"
```

### Show table schema (columns, types, keys)

```bash
mysql <database> -e "SHOW CREATE TABLE <table>\G"
mysql <database> -e "DESCRIBE <table>;"
mysql <database> -e "SHOW FULL COLUMNS FROM <table>;"
```

### Show indexes

```bash
mysql <database> -e "SHOW INDEX FROM <table>;"
```

### Show foreign keys

```bash
mysql <database> -e "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '<database>' AND TABLE_NAME = '<table>' AND REFERENCED_TABLE_NAME IS NOT NULL;"
```

### Sample data (read-only SELECT)

```bash
mysql <database> -e "SELECT * FROM <table> LIMIT 10;"
mysql <database> -e "SELECT COUNT(*) AS total FROM <table>;"
```

### Show table status (row estimates, engine, collation)

```bash
mysql <database> -e "SHOW TABLE STATUS\G"
```

### Show database size

```bash
mysql -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES GROUP BY table_schema;"
```

### Show running processes (read-only)

```bash
mysql -e "SHOW FULL PROCESSLIST;"
```

## Troubleshooting

If a `mysql` command fails, advise the user to check:

### mysql-client not installed

```bash
which mysql || echo "mysql-client is not installed"
```

If not installed, ask the user to install it (e.g., `apt install mysql-client` or `dnf install mysql`).

### ~/.my.cnf not configured

```bash
mysql -e "SELECT 1 AS test;"
```

If this fails with `ERROR 1045` (access denied) or `ERROR 2002` (can't connect to socket), advise the user to create or fix `~/.my.cnf`:

```ini
[client]
host = 127.0.0.1
user = db_user
password = db_password
```

Tell the user to set the correct host, user, and password for their MySQL server. The file should have restricted permissions:

```bash
chmod 600 ~/.my.cnf
```

**Important: the MySQL user in `~/.my.cnf` should be configured for read-only access.** Advise the user to create a dedicated MySQL user with only `SELECT` privilege (and optionally `SHOW VIEW`, `PROCESS` for `SHOW FULL PROCESSLIST`). Example SQL for the user to run:

```sql
CREATE USER 'ai'@'127.0.0.1' IDENTIFIED BY '...';
GRANT SELECT, SHOW VIEW, PROCESS ON *.* TO 'debug'@'127.0.0.1';
FLUSH PRIVILEGES;
```

This ensures that even if a write command is accidentally issued, the database is protected.

**Never** read or suggest reading credentials from `config/*.json` or any other configuration file. Only the user knows the correct credentials for their `~/.my.cnf`.

## Safety Notes

- Always use `LIMIT` on SELECT queries against large tables.
- Use `\G` (vertical format) for wide tables to avoid line wrapping.
- Prefer `INFORMATION_SCHEMA` queries over `SHOW` commands when you need machine-parseable output.
- If a query times out on a large table, add tighter WHERE conditions or reduce the LIMIT.
- Never use `SELECT *` on tables with BLOB/TEXT columns unless necessary — those columns can be huge.

## References

- `~/.my.cnf` — user-configured MySQL client credentials
- `man mysql` — MySQL CLI documentation
- `INFORMATION_SCHEMA` tables: `TABLES`, `COLUMNS`, `KEY_COLUMN_USAGE`, `STATISTICS`
