Priority: 0.85

# Remote Events

Remote events let you dispatch an event to another Zolinga instance over HTTP and receive the response back as a normal `RequestResponseEvent`.

This is useful when you have multiple Zolinga installations (or environments) and you want to call a remote listener using the same event model.

## Requirements

- The remote server must expose the Zolinga gateway at `/dist/system/gate/`.

## Example

```php
use Zolinga\System\Events\RemoteEvent;
use Zolinga\System\Types\OriginEnum;

$remoteServer = 'https://example.com';

$event = new RemoteEvent(
    $remoteServer,
    'example.org:api:myEvent',
    [
        'op' => 'get',
        'id' => 123,
        'foo' => 'bar',
    ]
);

$event->dispatch();

if ($event->isOk()) {
    // $event->response is populated from the remote gateway response
    var_dump($event->response);
} else {
    // Remote listener set a non-OK status
    echo $event->statusNiceName . ': ' . $event->message;
}
```

## What Is Sent

`RemoteEvent` sends a JSON POST request to:

- `https://example.com/dist/system/gate/?example.org:api:myEvent/get:123`

with the same body format used by the JavaScript client gateway:

```json
[
  {
    "uuid": "...",
    "type": "example.org:api:myEvent",
    "origin": "remote",
    "request": { "op": "get", "id": 123, "foo": "bar" }
  }
]
```

The properties will be populated with the server answer: `$event->status`, `$event->message`, and `$event->response`.

# Related

- [Events and Listeners](:Zolinga Core:Events and Listeners)
- `\Zolinga\System\Events\RemoteEvent`
- `\Zolinga\System\Events\WebEvent`
