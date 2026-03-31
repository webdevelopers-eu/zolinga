## Description

System logger facility. Always available via `$api->log`.

- **Service:** `$api->log`
- **Class:** `Zolinga\System\Logger\LogService`
- **Module:** system
- **Event:** `system:service:log`

## Usage

```php
$api->log->info("tag", "Message");
$api->log->error("tag", "Error message");
$api->log->warning("tag", "Warning message");
```
