Priority: 0.8

# Health Check System

The Health Check system allows you to monitor the health of your Zolinga application and its components. It can check for issues like low disk space and send notifications when problems are detected.

## Running Health Checks

You can run a health check using the CLI:

```bash
bin/zolinga healthcheck
```

To receive email notifications when issues are detected, use the `--notify` parameter:

```bash
bin/zolinga healthcheck --notify=your.email@example.com
```

## Extending the Health Check System

You can create your own health monitors by adding listeners for the `healthcheck` event in mode `internal`.

Example:

```php
// In your module's listener class
public function onHealthcheck(HealthCheckEvent $event): void
{
    // Check some component health
    if ($someIssueDetected) {
        $event->report(
            'My Component',
            StatusEnum::ERROR,
            'Description of the issue detected'
        );
    } else {
        $event->report(
            'My Component',
            StatusEnum::OK,
            'Component is working correctly'
        );
    }
}
```

Then register your listener in your module's `zolinga.json`:

```json
{
  "listen": [
    {
      "description": "Custom health monitor",
      "event": "healthcheck",
      "class": "\\Your\\Module\\Namespace\\YourListener",
      "method": "onHealthcheck",
      "origin": [
        "internal"
      ]
    }
  ]
}
```
