---
name: system-tracking
description: How to fire and listen for tracking events (analytics pixels, conversion tracking) using the unified 'tracking' broadcastBack pattern. Use when adding tracking pixels, conversion events, or any client-side analytics triggered by server-side actions.
argument-hint: "<goal>"
---

# Tracking Events

## Use When

- Adding analytics pixels (OpenAI, Google, Microsoft, etc.) that fire after server-side actions.
- Firing conversion events from the backend to the frontend.
- Any server-side action that should trigger client-side tracking/measurement.

## Rule: Unified `tracking` Event Name

**Always use `tracking` as the broadcast name.** Never invent per-action names like `tracking:registered` or `tracking:purchased`.

Distinguish the specific action in the `detail.event` field:

```php
$event->broadcastBack('tracking', ['event' => 'rms:register'], false);
$event->broadcastBack('tracking', ['event' => 'ipdefender:purchase'], false);
$event->broadcastBack('tracking', ['event' => 'rms:login'], false);
```

## Backend: Firing a Tracking Event

In any `WebEvent` handler, call `broadcastBack()` after a successful action:

```php
$event->broadcastBack('tracking', ['event' => 'rms:register'], false);
```

Parameters:
- Name: always `'tracking'`
- Detail: array with `event` key identifying the action (e.g. `'rms:register'`)
- Global: `false` for local-only (same window). Use `true` if tracking should fire across all open windows/tabs.

Requirements:
- The event class must be `WebEvent` (implements `BroadcastBackInterface`).
- The handler method parameter must be typed as `WebEvent`, not `RequestResponseEvent`.
- Document the emitted event in the module's `zolinga.json` `emit` section.

## Frontend: Listening for Tracking Events

In a JavaScript module (loaded via `<script type="module">`), use `api.listen()`:

```javascript
import api from '/dist/system/js/api.js';

api.listen('tracking', (detail) => {
    if (detail?.event === 'rms:register') {
        oaiq("measure", "registration_completed", { type: "customer_action" });
    }
});
```

The `detail` object contains:
- `detail.event` - the action identifier (e.g. `'rms:register'`)

Switch on `detail.event` to route to the correct pixel/measurement call.

## Script Loading

The tracking script must be loaded as a module in the page template (path varies per project):

```html
<script type="module" src="{{designPath}}/js/your-tracking-file.js"></script>
```

The `import api from '/dist/system/js/api.js'` statement requires `type="module"`.

## Manifest Declaration

Add the `tracking` event to the emitting module's `zolinga.json`:

```json
{
    "event": "tracking",
    "description": "Broadcast locally after user actions. Detail contains 'event' key identifying the action.",
    "class": "\\Zolinga\\System\\Events\\WebEvent",
    "origin": ["remote"]
}
```

## Complete Example

### Backend (`UserApi.php`)

```php
public function onRegister(WebEvent $event): void
{
    // ... validation, user creation, login ...

    $event->response['user'] = $api->user->getPublicUserData();
    $event->broadcastBack('tracking', ['event' => 'rms:register'], false);
    $event->setStatus($event::STATUS_OK, dgettext("zolinga-rms", "Welcome!"));
}
```

### Frontend (any `.js` file loaded as `type="module"`)

```javascript
import api from '/dist/system/js/api.js';

api.listen('tracking', (detail) => {
    if (detail?.event === 'rms:register') {
        // fire your pixel/measurement here
    }
});
```

## References

- `system/src/Events/WebEvent.php` - `broadcastBack()` method
- `system/src/Events/BroadcastBackInterface.php` - interface definition
- `system/install/dist/js/api.js` - `api.listen()` and `broadcast()` implementation
- `modules/zolinga-rms/src/Api/UserApi.php` - `onRegister()` example