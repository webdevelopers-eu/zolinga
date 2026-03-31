## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('system:wiki:login', { password: 'secret' });
```

## Description

WIKI login or authorization status inquiry. Used by the `<wiki-login>` web component to authenticate access to the WIKI.

- **Event:** `system:wiki:login`
- **Class:** `Zolinga\System\Wiki\WikiAuth`
- **Method:** `onLogin`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\WebEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `password` | `string` | WIKI password |

## Behavior

Validates the password against the configured WIKI password. Implements rate limiting via `maxAttempts` and `maxAttemptsTimeframe` configuration.
