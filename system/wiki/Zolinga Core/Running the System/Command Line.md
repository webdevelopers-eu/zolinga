# Command Line (CLI)

You can dispatch any event from command line using the `bin/zolinga` script.

After specifying the event type and optional parameters the script will dispatch the standard `Zolinga\System\Events\CliRequestResponseEvent` event with origin set to *cli*. The `$event->request` array will be filled with any parameters specified on command line and event listeners are expected to fill the array `$event->response` with the response data.

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

Non-JSON values (e.g. `--test.param=123`) will be converted to int, float, bool, null or string as follows:

- `null` - if the value is `null`
- `true` - if the value is `true` or `yes`
- `false` - if the value is `false` or `no`
- `int` - if the value is an integer
- `float` - if the value is a float
- `string` - everything else.

Examples:
- `--test.param=yes` will be converted into `{ "test": { "param": true } }`
- `--test.param=true` will be converted into `{ "test": { "param": true } }`
- `--test.param=123` will be converted into `{ "test": { "param": 123 } }`
- `--test.param=123.45` will be converted into `{ "test": { "param": 123.45 } }`
- `--test.param=null` will be converted into `{ "test": { "param": null } }`
- `--test.param=string` will be converted into `{ "test": { "param": "string" } }`


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
use Zolinga\System\Events\CliRequestResponseEvent;
use Zolinga\System\Events\ListenerInterface;

class MyListener implements ListenerInterface
{
    public function onMyEvent(CliRequestResponseEvent $event)
    {
        if ($event->request['hello'] === 'zolinga') {
            $event->response["hello"] = "Hello world!";
        }
        $event->setStatus($event::STATUS_OK, "All good");
    }
}
```

Now when you run the `zolinga example.org:api:myEvent --hello=zolinga` the listener will be triggered and the response will be printed to the console.


# Producing Output

If your event listener is supposed to produce some output to stdout and you don't want it to be mixed with the default output that prints the response array, you can use the `$event->preventDefault()` method to stop the default output.

```php
namespace Example;
use Zolinga\System\Events\CliRequestResponseEvent;
use Zolinga\System\Events\ListenerInterface;

class MyListener implements ListenerInterface
{
    public function onMyEvent(CliRequestResponseEvent $event)
    {
        echo "Hello world!";
        $event->preventDefault();
        $event->setStatus($event::STATUS_OK, "All good");
    }
}
```

# Stdout vs Stderr

`bin/zolinga` writes system logs, warnings, and debug messages to **stderr**, while the actual event response (JSON) and any listener `echo` output go to **stdout**. This lets you separate structured output from diagnostic noise using shell redirection:

```bash
# Capture only the JSON response
bin/zolinga my:event > response.json

# Capture response and suppress logs
bin/zolinga my:event > response.json 2> /dev/null

# Capture response and logs separately
bin/zolinga my:event > response.json 2> debug.log

# Extract a single value from the response using built-in --single
bin/zolinga --single=response.data my:event

# Print raw JSON responses only (no human-readable wrapper)
bin/zolinga --json my:event
```


# Related
{{Running the System}}