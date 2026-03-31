## Syntax

```javascript
// Front-end (remote origin, requires system:wiki:read right)
const resp = await api.dispatchEvent('wiki:search', { search: 'query text' });
```

## Description

Searches WIKI articles by text query. Used by the `<wiki-search>` web component.

- **Event:** `wiki:search`
- **Class:** `Zolinga\System\Wiki\WebComponents\WikiSearch`
- **Method:** `onSearch`
- **Origin:** `remote`
- **Right:** `system:wiki:read`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `search` | `string` | Search query text |

## Response

List of matching wiki articles with titles and URIs.
