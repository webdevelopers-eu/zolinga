## Syntax

```javascript
// Front-end (remote origin, requires system:wiki:read right)
const resp = await api.dispatchEvent('wiki:toc');
```

## Description

Fetches the WIKI table of contents tree. Used by the `<wiki-toc>` web component.

- **Event:** `wiki:toc`
- **Class:** `Zolinga\System\Wiki\WebComponents\WikiToc`
- **Method:** `onToc`
- **Origin:** `remote`
- **Right:** `system:wiki:read`
- **Event Type:** `\Zolinga\System\Events\RequestResponseEvent`

## Parameters

None.

## Response

Tree structure of all wiki articles organized by module and section.
