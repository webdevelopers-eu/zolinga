---
name: system-events-server
description: Use when creating, dispatching, or handling PHP server-side events in Zolinga. Covers event class hierarchy, status/message access, origin types, stoppable events, authorization, manifest wiring, and common pitfalls.
argument-hint: "<module-name> <event-type> [goal]"
---

# Zolinga Server-Side Events

## Use When

- Creating, dispatching, or handling PHP events.
- Choosing the right event class for a listener or emitter.
- Wiring event listeners in `zolinga.json`.
- Debugging event status, message, or propagation issues.
- Implementing CLI commands, AJAX handlers, or internal event dispatchers.

## Event Class Hierarchy

```
Event (base)
├── RequestEvent (+ $request)
│   └── RequestResponseEvent (+ $response)
│       ├── CliRequestResponseEvent (stoppable)
│       └── WebEvent (+ $broadcastBack)
├── ContentEvent (abstract, stoppable — base for all content events)
│   ├── HtmlContentEvent (system:content:html, DOM-based page content)
│   ├── JsonContentEvent (system:content:json)
│   ├── TextContentEvent (system:content:text)
│   └── PreflightEvent (system:content:preflight — determines MIME type before content event)
├── AuthorizeEvent (stoppable, rights checking)
├── InstallScriptEvent (stoppable)
├── HealthCheckEvent (stoppable)
└── RemoteEvent (dispatches to remote Zolinga over HTTP)
```

## Choosing the Right Event Class

The **emitter** decides the event class, not the listener. Choose based on what the event carries:

| Use Case | Event Class | Origin |
|----------|-------------|--------|
| Simple signal, no data | `Event` | `internal` |
| Internal request with data | `RequestEvent` | `internal` |
| Internal request+response | `RequestResponseEvent` | `internal` |
| CLI command | `CliRequestResponseEvent` | `cli` |
| AJAX/web gate | `WebEvent` | `remote` |
| Page content rendering | `HtmlContentEvent` / `JsonContentEvent` / `TextContentEvent` (chosen by `PreflightEvent`) | `remote` |
| Rights checking | `AuthorizeEvent` | `internal` |
| Remote server call | `RemoteEvent` | `remote` |

## Critical API Details

### Status and Message — READ-ONLY via `__get()`

`$event->status` and `$event->message` are **private properties** exposed via `__get()`. There are **no getter methods** like `getStatus()` or `getMessage()`.

```php
// CORRECT
if ($event->status === $event::STATUS_OK) { ... }
echo $event->message;

// WRONG — these methods do not exist
if ($event->getStatus() === $event::STATUS_OK) { ... }
echo $event->getMessage();
```

To set status, use `setStatus()`:

```php
$event->setStatus($event::STATUS_OK, 'Done');
$event->setStatus($event::STATUS_NOT_FOUND, 'No results');
```

### Status Priority Rules

`setStatus()` uses priority logic — it is NOT a simple setter:

1. `UNDETERMINED` (0) can always be overwritten.
2. `OK` (200) can only be overwritten by error statuses (>= 400).
3. Among errors, **lower status codes win** over higher ones (e.g., NOT_FOUND beats ERROR).
4. This enables fail-over patterns: if 3 listeners handle an event and 2 return 404 but 1 returns 200, the event is considered OK.

### Dispatching Events

```php
// Method 1: dispatch() on the event object (preferred)
$event = new RequestResponseEvent('my-module:do-thing', OriginEnum::INTERNAL, ['key' => 'value']);
$event->dispatch();

// Method 2: via $api (equivalent)
$api->dispatchEvent($event);
```

**There is no `$api->dispatch()` method.** Use `$event->dispatch()` or `$api->dispatchEvent($event)`.

### Constructor Signatures

```php
// Event — simplest, no request/response
new Event(string $type, OriginEnum $origin)

// RequestEvent — carries request data
new RequestEvent(string $type, OriginEnum $origin = OriginEnum::INTERNAL, array|ArrayObject $request = new ArrayObject)

// RequestResponseEvent — carries request + response
new RequestResponseEvent(string $type, OriginEnum $origin = OriginEnum::INTERNAL, array|ArrayObject $request = new ArrayObject, array|ArrayObject $response = new ArrayObject)

// CliRequestResponseEvent — same as RequestResponseEvent but stoppable
new CliRequestResponseEvent(string $type, OriginEnum $origin = OriginEnum::CLI, array|ArrayObject $request = new ArrayObject, array|ArrayObject $response = new ArrayObject)

// WebEvent — AJAX, has broadcastBack
new WebEvent(string $type, OriginEnum $origin = OriginEnum::REMOTE, array|ArrayObject $request = new ArrayObject, array|ArrayObject $response = new ArrayObject)

// RemoteEvent — dispatches to remote server
new RemoteEvent(string $server, string $type, array|ArrayObject $request = new ArrayObject, array|ArrayObject $response = new ArrayObject)
```

### Request Data Access

`$event->request` can be either `array` or `ArrayObject` depending on how the event was constructed. Always access it as an array:

```php
// CORRECT — works for both array and ArrayObject
$domains = $event->request['domains'] ?? null;

// WRONG — ArrayObject does not have getArrayCopy() in all contexts
$domains = $event->request->getArrayCopy();
```

When forwarding request data to a sub-event, pass the raw property:

```php
$subEvent = new CliRequestResponseEvent('step:name', OriginEnum::CLI, $event->request);
```

### Stoppable Events

Events implementing `StoppableInterface` support:

```php
$event->stopPropagation();  // No further listeners will be called
$event->isPropagationStopped();  // Check if stopped
$event->preventDefault();    // Mark default action as prevented
$event->isDefaultPrevented(); // Check if default was prevented
```

To create a stoppable event:

```php
class MyEvent extends Event implements StoppableInterface {
    use StoppableTrait;
}
```

### Authorization (`right` in zolinga.json)

When a listener declares `"right": "some-right"`, the dispatcher fires `system:authorize` before calling the listener. If no authorizer grants the right, the listener is skipped and the event gets `STATUS_UNAUTHORIZED`.

### Event Name Conventions

- Prefix with module/domain: `my-module:action`, `system:service:name`
- Lowercase, colon-separated segments
- Sugar syntax in `zolinga.json`:
  - `"service": "name"` → `system:service:name` with origin `internal`
  - `"request": "name"` → `system:request:name` with origin `remote`

### OriginEnum Values

| Value | Meaning |
|-------|---------|
| `internal` | Trusted in-process event |
| `remote` | HTTP/AJAX request from browser |
| `cli` | Command-line invocation |
| `custom` | Reserved for third-party use |
| `*` (ANY) | Matches any origin (for listeners) |

### Status Constants

Available as class constants on any event: `$event::STATUS_OK`, `$event::STATUS_NOT_FOUND`, `$event::STATUS_ERROR`, etc. Mirror HTTP status codes. See `StatusEnum` for full list.

## Common Patterns

### Dispatching a Pipeline of Sub-Events

```php
public function run(CliRequestResponseEvent $event): void
{
    $steps = ['my-module:step1', 'my-module:step2', 'my-module:step3'];

    foreach ($steps as $eventName) {
        $subEvent = new CliRequestResponseEvent($eventName, OriginEnum::CLI, $event->request);
        $subEvent->dispatch();

        if ($subEvent->status !== $subEvent::STATUS_OK) {
            $event->setStatus($subEvent->status, "Step $eventName failed: " . $subEvent->message);
            return;
        }
    }

    $event->setStatus($event::STATUS_OK, 'Pipeline completed');
}
```

### AJAX Handler with broadcastBack

```php
// In zolinga.json: origin must be ["remote"]
public function handleAjax(WebEvent $event): void
{
    $result = doSomething($event->request['param']);
    $event->response['result'] = $result;
    $event->broadcastBack('data-updated', ['key' => $result], global: true);
    $event->setStatus($event::STATUS_OK, 'Done');
}
```

### Service Listener

```php
// In zolinga.json: "service": "myService", "class": "MyModule\\MyService"
class MyService implements ServiceInterface
{
    public function someMethod(): string { return 'hello'; }
}
// Accessed via: $api->myService->someMethod()
```

## Common Pitfalls

1. **No `getStatus()`/`getMessage()`** — Use `$event->status` and `$event->message` (magic `__get`).
2. **No `$api->dispatch()`** — Use `$event->dispatch()` or `$api->dispatchEvent($event)`.
3. **`$event->request` is `array|ArrayObject`** — Don't call `->getArrayCopy()` on it blindly; access as array.
4. **Origin is `OriginEnum`, not string** — Use `OriginEnum::CLI`, `OriginEnum::INTERNAL`, etc.
5. **`setStatus()` is not a simple setter** — It follows priority rules; OK can only be overwritten by errors.
6. **`method` is required for non-service listeners** — Runtime throws if missing from `zolinga.json`.
7. **Services use `"service"` sugar** — Don't write `"event": "system:service:myService"` manually; use `"service": "myService"`.