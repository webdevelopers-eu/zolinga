---
name: system-events-client
description: Use when working with the JavaScript client-side event system in Zolinga. Covers the Event class, Api gateway, dispatching events from JS to PHP, receiving broadcastBack responses, inter-component communication via BroadcastChannel, and web component integration.
argument-hint: "<component-or-feature> [goal]"
---

# Zolinga Client-Side Events

## Use When

- Dispatching events from JavaScript to the PHP server.
- Handling server responses and broadcastBack events in JS.
- Setting up inter-component communication (BroadcastChannel).
- Working with the `api` singleton, `Event` class, or `WebComponent` base class.
- Debugging AJAX/gate communication issues.

## Architecture Overview

```
JS Event.dispatch() → api.dispatchEvent() → JSON POST /dist/system/gate
    → PHP creates WebEvent(ORIGIN_REMOTE) → dispatches through $api->dispatchEvent()
    → PHP listeners process, call $event->broadcastBack()
    → JSON response back to JS → event.status, event.response, api.broadcast()
```

## JavaScript Event Class

Located at `system/install/dist/js/event.js`.

### Constructor

```javascript
const event = new api.Event('my-module:do-thing', { key: 'value' });
```

- First argument: event type URI (e.g., `'my-module:do-thing'`)
- Second argument: request data object (default `{}`)
- Origin is always `'remote'` for client-side events
- UUID is auto-generated

### Status Constants

Mirror HTTP status codes. Access as `api.Event.STATUS_OK`, etc:

| Constant | Value |
|----------|-------|
| `STATUS_UNDETERMINED` | 0 |
| `STATUS_OK` | 200 |
| `STATUS_CREATED` | 201 |
| `STATUS_ACCEPTED` | 202 |
| `STATUS_NO_CONTENT` | 204 |
| `STATUS_BAD_REQUEST` | 400 |
| `STATUS_UNAUTHORIZED` | 401 |
| `STATUS_FORBIDDEN` | 403 |
| `STATUS_NOT_FOUND` | 404 |
| `STATUS_METHOD_NOT_ALLOWED` | 405 |
| `STATUS_TIMEOUT` | 408 |
| `STATUS_CONFLICT` | 409 |
| `STATUS_GONE` | 410 |
| `STATUS_PRECONDITION_FAILED` | 412 |
| `STATUS_I_AM_A_TEAPOT` | 418 |
| `STATUS_LOCKED` | 423 |
| `STATUS_ERROR` | 500 |
| `STATUS_NOT_IMPLEMENTED` | 501 |
| `STATUS_BAD_GATEWAY` | 502 |
| `STATUS_SERVICE_UNAVAILABLE` | 503 |
| `STATUS_GATEWAY_TIMEOUT` | 504 |

### Properties

| Property | Type | Access | Description |
|----------|------|--------|-------------|
| `type` | string | read-only | Event type URI |
| `origin` | string | read-only | Always `'remote'` for JS |
| `status` | number | read via getter | Status code (use `setStatus()` to write) |
| `message` | string\|null | read via getter | Status message |
| `request` | object | read-write | Request data sent to server |
| `response` | object | read-write | Response data from server |
| `uuid` | string | read-write | Unique event identifier |
| `ok` | boolean | computed | `status > 0 && status < 400` |
| `error` | boolean | computed | `status >= 400` |
| `undetermined` | boolean | computed | `status === 0` |
| `unauthorized` | boolean | computed | `status === 401` |
| `forbidden` | boolean | computed | `status === 403` |
| `isTrusted` | boolean | computed | `origin === 'internal' \|\| origin === 'cli'` |

### Methods

```javascript
// Set status — follows same priority rules as PHP (OK can only be overwritten by errors)
event.setStatus(200, 'Success');

// Dispatch to server — returns Promise
await event.dispatch();

// Serialize
event.toJSON(); // returns full event object
```

### Status Priority Rules (Same as PHP)

1. `UNDETERMINED` (0) can always be overwritten.
2. `OK` (200) can only be overwritten by error statuses (>= 400).
3. Among errors, lower status codes win over higher ones.

## Api Gateway Singleton

Located at `system/install/dist/js/api.js`. Exported as default singleton.

### Dispatching Events

```javascript
import api from '/dist/system/js/api.js';

// Method 1: Create and dispatch an Event object
const event = new api.Event('my-module:do-thing', { param: 'value' });
await event.dispatch();
console.log(event.status, event.response);

// Method 2: Shorthand — string type + data
const result = await api.dispatchEvent('my-module:do-thing', { param: 'value' });

// Method 3: Dispatch with URL path segments
// URL format: /dist/system/gate?{type}/{op}:{id}
// op and id are extracted from request.op and request.id
```

### Listening for Responses

```javascript
// Listen for a specific event response
api.listen('event-response:my-module:do-thing', (data) => {
    console.log('Response received:', data);
});
```

### Broadcasting Between Components

```javascript
// Send a broadcast message
api.broadcast('my-module:data-updated', { key: 'newValue' }, true);  // global = true (all tabs)
api.broadcast('my-module:data-updated', { key: 'newValue' }, false); // local = only this tab

// Listen for broadcasts
api.listen('my-module:data-updated', (data) => {
    console.log('Broadcast received:', data);
});
```

- `global: true` uses `BroadcastChannel('zolinga')` — reaches all browser tabs/windows.
- `global: false` uses `window.postMessage()` — only the current tab.

### Server-Side broadcastBack

PHP handlers can push events back to the client:

```php
// In PHP WebEvent handler
$event->broadcastBack('data-updated', ['newValue' => 42], global: true);
$event->broadcastBack('notification', ['message' => 'Saved!'], global: false);
```

The JS `api` singleton automatically processes `broadcastBack` items from server responses and calls `api.broadcast()` for each one.

## WebComponent Integration

Located at `system/install/dist/js/web-component-element.js`.

### Broadcasting and Listening in Components

```javascript
class MyComponent extends WebComponent {
    connectedCallback() {
        super.connectedCallback();
        
        // Listen for broadcasts
        this.listen('my-module:data-updated', (data) => {
            this.render(data);
        });
    }
    
    someAction() {
        // Broadcast to other components
        this.broadcast('my-module:action-taken', { id: 123 });
    }
}
```

### Component Communication Patterns

```javascript
// Pattern 1: Dispatch event to server, get response
async loadData() {
    const event = new api.Event('my-module:load', { id: this.dataset.id });
    await event.dispatch();
    if (event.ok) {
        this.render(event.response);
    }
}

// Pattern 2: Dispatch event, server broadcasts back
async saveData() {
    const event = new api.Event('my-module:save', { data: this.formData });
    await event.dispatch();
    // Server calls $event->broadcastBack('my-module:saved', ...) 
    // which triggers any listeners on 'my-module:saved'
}

// Pattern 3: Inter-component communication via broadcast
this.broadcast('my-module:selection-changed', { item: selectedItem });
// Another component listens:
this.listen('my-module:selection-changed', (data) => { ... });
```

### Modal Dialog Pattern

```javascript
// Opening component
const result = await this.watchModal('my-modal-component');
// my-modal-component calls this.resolveModal(data) or this.rejectModal(error)

// Inside modal component
this.resolveModal({ confirmed: true, value: 'yes' });  // resolves the promise
this.rejectModal({ error: 'cancelled' });               // rejects the promise
```

## Common Patterns

### Dispatching a Pipeline from JS

```javascript
async function runPipeline(params) {
    const steps = ['my-module:step1', 'my-module:step2', 'my-module:step3'];
    for (const step of steps) {
        const event = new api.Event(step, params);
        await event.dispatch();
        if (event.error) {
            console.error(`Step ${step} failed:`, event.message);
            return event;
        }
    }
    return event;
}
```

### Handling Errors

```javascript
const event = new api.Event('my-module:action', { id: 123 });
await event.dispatch();

if (event.unauthorized) {
    // 401 — redirect to login
} else if (event.forbidden) {
    // 403 — show permission error
} else if (event.error) {
    // 4xx/5xx — show generic error
} else if (event.ok) {
    // 2xx — success
}
```

## Common Pitfalls

1. **`api` is a singleton** — Import it, don't instantiate: `import api from '/dist/system/js/api.js'`.
2. **`Event` is accessed via `api.Event`** — `new api.Event('type', data)`, not `new Event()`.
3. **Origin is always `'remote'`** — Client-side events cannot claim `internal` or `cli` origin.
4. **`dispatch()` is async** — Always `await event.dispatch()` or use `.then()`.
5. **`client events` requires `origin: ["remote"]`** — PHP listener must declare remote origin in `zolinga.json`.
6. **`setStatus()` priority rules apply** — Same as PHP: OK can only be overwritten by errors.
7. **BroadcastChannel is cross-tab** — `global: true` reaches all tabs; `global: false` is same-tab only.
8. **Gate URL format** — `/dist/system/gate?{type}/{op}:{id}` where `op` and `id` come from `request.op` and `request.id`.