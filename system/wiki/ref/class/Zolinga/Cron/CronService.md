Priority: 0.6

# Cron Service

The Cron Service is a service that allows you to schedule tasks to be executed at a specific time or at regular intervals. It is a very useful tool for automating repetitive tasks, such as sending out emails, updating data, or performing maintenance tasks.

## Usage

```php
use Zolinga\System\Events\RequestEvent;

$myEvent = new RequestEvent(
    "my-event", 
    RequestEvent::ORIGIN_INTERNAL, 
    ["param1" => "value1"]
);

$api->cron->schedule(
    $myEvent, 
    start: "tomorrow 12:00", 
    recurring: "+1 day", 
    end: "1st of next month"
);
```

# Related
{{Cron Related}}