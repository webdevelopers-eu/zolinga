# system:start

Fired by `\Zolinga\System\Loader\Bootstrap::start()` once per request, after the full bootstrap sequence completes. Listeners use this event to perform one-time initialization that requires all services and configuration to already be available.

- **Origin:** `internal`
- **Event class:** `\Zolinga\System\Events\Event`

## When It Fires

`system:start` is dispatched at the very end of `loader.php` — after the manifest is loaded, the autoloader is ready, the logger is online, the session is initialized, and `system:install` has run (if the manifest changed). It fires on every request: web, CLI, and API gateway.

## Usage

Register a listener in your module's `zolinga.json`:

```json
{
  "listen": [
    {
      "event": "system:start",
      "description": "Bootstrap my service on every request.",
      "class": "\\My\\Module\\StartListener",
      "method": "onStart",
      "origin": ["internal"],
      "priority": 0.5
    }
  ]
}
```

Implement the listener:

```php
declare(strict_types=1);

namespace My\Module;

use Zolinga\System\Events\{Event, ListenerInterface};

class StartListener implements ListenerInterface
{
    public function onStart(Event $event): void
    {
        global $api;
        // All services are available here.
        $api->log->info('my-module', 'System started.');
    }
}
```

## When NOT to Use This Event

**In the vast majority of cases you should not listen to `system:start`.**

Prefer a more specific event that matches what you actually need:

| Goal | Use instead |
|---|---|
| Provide a reusable service | `system:service:myService` (sugar: `"service": "myService"`) |
| Handle a web/API request | `system:request:myAction` (sugar: `"request": "myAction"`) |
| React to module installation | `system:install` |
| Serve a CMS page | `system:content:html` / `cms:content:myTag` |
| Run a CLI command | your own `myModule:myCommand` with origin `cli` |

Hooking on `system:start` runs your code on **every single request** — web, CLI, cron, API — whether your module is relevant or not. This wastes resources and makes boot time slower for everyone.

Legitimate reasons to use `system:start` are rare and include things like installing global PHP error handlers, registering stream wrappers, or other truly cross-cutting concerns that cannot be deferred.

## Other Notes

- Do **not** dispatch `system:start` yourself — it is reserved for the bootstrap sequence.
- Keep any `system:start` handler as fast as possible; defer real work to later events.

## See Also

- [:Zolinga Core:Events and Listeners]
- [system:install](:ref:event:system:install)
