## Syntax

```javascript
// Front-end (remote origin, requires system:wiki:read right)
const resp = await api.dispatchEvent('wiki:article', { uri: '/Zolinga Core/Services' });
```

## Description

Fetches a single WIKI article by URI. Used by the `<wiki-article>` web component.

- **Event:** `wiki:article`
- **Class:** `Zolinga\System\Wiki\WebComponents\WikiArticle`
- **Method:** `onArticle`
- **Origin:** `remote`
- **Right:** `system:wiki:read`
- **Event Type:** `\Zolinga\System\Events\WebEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `uri` | `string` | Article URI path |

## Response

| Field | Type | Description |
|---|---|---|
| `uri` | `string` | Article URI |
| `title` | `string` | Article title |
| `files` | `array` | Related files |
| `content` | `string` | Rendered HTML content |
