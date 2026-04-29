---
name: system-concurrency
description: Use when implementing inter-process locking or concurrency control in Zolinga modules. Covers the registry-based MySQL lock API.
argument-hint: "<module-name> <lock-name>"
---

# Zolinga Concurrency Control

## Summary

Zolinga provides database-backed locking via the Registry service using MySQL `GET_LOCK()` / `RELEASE_LOCK()`.

## Use When

- Preventing concurrent execution of CLI events, cron jobs, or long-running handlers.
- Coordinating access to shared resources across multiple PHP processes or servers.

## Registry Locks

```php
global $api;

if (!$api->registry->acquireLock('autoblog:generate', 0)) {
    $event->setStatus(
        CliRequestResponseEvent::STATUS_LOCKED,
        "Already running."
    );
    return;
}

try {
    // ... do work ...
} finally {
    $api->registry->releaseLock('autoblog:generate');
}
```

### API

- `acquireLock(string $name, int|string $timeout = 0): int|false`
  - `$timeout = 0` — fail immediately if lock is held.
  - `$timeout = 5` — wait up to 5 seconds.
  - `$timeout = "+1 minute"` — wait up to 1 minute.
  - Returns Unix timeout timestamp on success, `false` on failure.
- `releaseLock(string $name): void`
  - Releases the lock. Safe to call even if lock was not acquired.

### Characteristics

- **Connection-bound**: lock is tied to the DB connection; same connection can re-acquire without blocking.
- **Auto-release**: released implicitly when the DB connection closes (normal or abnormal termination).
- **Cross-server**: works across multiple web servers sharing the same database.

## References

- `modules/zolinga-db/src/RegistryService.php`
- MariaDB `GET_LOCK()` docs: https://mariadb.com/kb/en/get_lock/
