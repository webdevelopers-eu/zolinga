## Syntax

```bash
bin/zolinga healthcheck [--notify=EMAIL]
```

## Description

Runs system health checks across all modules. Dispatches internal health monitor events and collects results. Sends email alerts if errors are detected.

## Parameters

- `--notify=EMAIL` — Email address to send alerts to when health checks fail.

## Examples

Run health check:

```bash
bin/zolinga healthcheck
```

Run and notify on failure:

```bash
bin/zolinga healthcheck --notify=admin@example.com
```
