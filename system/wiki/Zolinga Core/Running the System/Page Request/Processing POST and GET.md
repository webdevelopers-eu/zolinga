# Processing POST and GET

Any requests for non-existent resources are routed to the `./public/index.php` script. 

If there is either a `GET` or `POST` request, the `index.php` will run a simple code similar to this:

```php
foreach ($_REQUEST as $key => $value) {
    (new RequestResponseEvent(
        type: "system:request:$key",
        origin: RequestEvent::ORIGIN_REMOTE,
        request: new ArrayObject($value)
    ))->dispatch();
}
```

This code will create a `RequestEvent` object for each request parameter and dispatch it. The event will be processed by any listeners that are registered for the *remote* `system:request:$key` event type.

As you can see, the event type is constructed from the [system:request:](:ref:event:system:request:) prefix and the request parameter name. This is a simple way to route the request to the appropriate listener.

### Example

`zolinga.json`
```json
{
    "listen": [
        {
            "type": "system:request:hello",
            "class": "\\Example\\HelloRequestListener",
            "method": "onRequest",
            "origin": ["remote"]
        }
    ]
}
```

This listener definition hooks your custom listener to any `?hello=...` request. The listener class may look like this:

```php
namespace Example;
use Zolinga\System\Events\RequestEvent;
use Zolinga\System\Events\ListenerInterface;

class HelloRequestListener implements ListenerInterface
{
    public function onRequest(RequestEvent $event)
    {
        echo "Hello, the request is: " . json_encode($event->request) . "\n";
        $event->setStatus($event::STATUS_OK, "Request processed.");
    }
}
```

Now any POST or GET request with `?hello=...` parameter will be processed by this listener.

# Related
{{Running the System}}