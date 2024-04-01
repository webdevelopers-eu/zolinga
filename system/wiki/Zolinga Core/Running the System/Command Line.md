# Command Line (CLI)

You can dispatch any event from command line using the `bin/zolinga` script.

After specifying the event type and optional parameters the script will dispatch the standard `Zolinga\System\Events\RequestResponseEvent` event with origin set to *cli*. The `$event->request` array will be filled with any parameters specified on command line and event listeners are expected to fill the array `$event->response` with the response data.

The syntax of specifying events and optional parameters is fairly simple.
```
zolinga [options] [event1 [params]] [event2 [params]] ...
```

The event type is in the format of an URI. Example: `example.org:api:myEvent` and the parameters can be either in JSON format or in Javascript dot notation.

Example:
```shell
zolinga example.org:api:myEvent --system.db.password=123 --system.db.user=me
zolinga example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}'
```

Both lines are equivalent. You can chain more events with their parameters.

```shell
zolinga \
    example.org:api:myEvent '{"system":{"db":{"password":"123","user":"me"}}' \
    example.org:api:anotherEvent --test.param=123 \
    example.org:api:yetAnotherEvent;
```

Run `bin/zolinga --help` to see the available options.

# Declaring Event Listeners

The event listeners are same as any other listeners except they need to listen for events from `cli` origin. Example of `zolinga.json` file listener declaration:

```json
{
    "listen": {
        "example.org:api:myEvent": {
            "description": "My test listener",
            "class": "\\Example\\MyListener",
            "method": "onMyEvent"
            "origin": ["cli"]
        }
    }
}

```

The implementation may look as follows:

```php
namespace Example;
use Zolinga\System\Events\RequestResponseEvent;
use Zolinga\System\Events\ListenerInterface;

class MyListener implements ListenerInterface
{
    public function onMyEvent(RequestResponseEvent $event)
    {
        if ($event->request['hello'] === 'zolinga') {
            $event->response["hello"] = "Hello world!";
        }
        $event->setStatus($event::STATUS_OK, "All good");
    }
}
```

Now when you run the `zolinga example.org:api:myEvent --hello=zolinga` the listener will be triggered and the response will be printed to the console.

# Related
{{Running the System}}