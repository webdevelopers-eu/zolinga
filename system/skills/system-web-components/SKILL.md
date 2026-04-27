---
name: system-web-components
description: Use when creating or modifying Zolinga web components in install/dist, registering them through module zolinga.json, and documenting them correctly.
argument-hint: "<module-name> <component-tag>"
---

# Zolinga Web Components

## Use When

- Creating new web components or modifying existing ones.
- Registering components in `zolinga.json`.
- Writing component `.md` docs.
- Debugging component loading, readiness, or inter-component communication.

## File Structure

```
modules/<module>/install/dist/web-components/<tag>/<tag>.js   # component class
modules/<module>/install/dist/web-components/<tag>/<tag>.html  # template (if using loadContent)
modules/<module>/install/dist/web-components/<tag>/<tag>.css   # styles (linked from .html)
modules/<module>/install/dist/web-components/<tag>/<tag>.md    # documentation
```

The `install/dist/` directory is symlinked to `public/dist/<module>/` at install time, so the public URL becomes `/dist/<module>/web-components/<tag>/<tag>.js`.

## Registration (zolinga.json)

Add to the module's `webComponents` array — **never** call `customElements.define()` manually; the loader does it:

```json
"webComponents": [
    {
        "tag": "my-widget",
        "description": "Short description of the component.",
        "module": "web-components/my-widget/my-widget.js"
    }
]
```

- `tag` — must contain a hyphen (WHATWG rule).
- `module` — path relative to the module's `install/dist/` directory.
- After changing `zolinga.json`, bump the module version to trigger cache refresh.

## Page Bootstrap

Every page that uses web components must include:

```html
<script type="module" src="/dist/system/js/web-components.js"></script>
```

Or use `<c-resources assets="web-components">` for server-rendered content.

## Base Classes

### `HTMLElement` — lightweight, no framework features

```javascript
export default class MyWidget extends HTMLElement {
    constructor() {
        super();
        this.innerHTML = `<p>Hello</p>`;
        this.dataset.ready = 'true';
        this.dispatchEvent(new CustomEvent('web-component-ready'));
    }
}
```

### `WebComponent` — full-featured base (`/dist/system/js/web-component.js`)

```javascript
import WebComponent from '/dist/system/js/web-component.js';

export default class MyWidget extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }
    async #init() { /* ... */ }
}
```

### `WebComponentIntl` — localized base (`/dist/zolinga-intl/js/web-component-intl.js`)

Extends `WebComponent`. Overrides `rewriteURL()` to insert locale before the file extension when `document.documentElement.lang` is not `en-US`. Use for any component that loads locale-specific HTML/CSS templates:

```javascript
import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';
// If lang="cs-CZ", loadContent('template.html') → fetches 'template.cs-CZ.html'
```

## Architecture: Shadow DOM vs Seamless

| Mode | How | Style isolation | Slots work | Use when |
|------|-----|-----------------|------------|----------|
| **Seamless** | `this.innerHTML = ...` or `loadContent(url, {mode:'seamless'})` | Inherits page styles | N/A (direct children) | Most app-internal components |
| **Open Shadow** | `this.attachShadow({mode:'open'})` or `loadContent(url, {mode:'open'})` | Isolated, opt-in inherit | Yes via `<slot>` | Reusable/distributable components |
| **Closed Shadow** | `loadContent(url, {mode:'closed'})` | Isolated, opt-in inherit | Yes via `<slot>` | Encapsulated components (login, modals) |

**Prefer seamless** for internal app components — simpler, inherits page styles, no Shadow DOM overhead.

## loadContent()

```javascript
this.#root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
    mode: 'closed',        // 'open'|'closed'|'seamless'
    allowScripts: false,   // execute <script> tags in loaded HTML
    timeout: 60000,        // ms
    filter: null,          // (html) => html  — transform before insert
    inheritStyles: false   // copy parent <style>/<link> into shadow root
});
```

- Returns the Shadow Root (open/closed) or `this` (seamless).
- Automatically calls `components.observe()` on loaded content so nested web components initialize.
- Resolves relative `src`/`href` against the HTML file's URL.
- Waits for `disabled` attribute to be removed before loading.
- Adds `?rev=` cache-busting param from `<html data-revision="...">`.

## Readiness & Loading Animation

The loader shows a pulsing animation on undefined components. To dismiss it:

```javascript
// Plain HTMLElement:
this.dataset.ready = 'true';
this.dispatchEvent(new CustomEvent('web-component-ready'));

// WebComponent base:
this.ready();                    // mark ready immediately
this.ready(somePromise);         // mark ready after promise resolves
this.ready(promiseA);            // multiple calls: waits for ALL
this.ready(promiseB);
await this.ready();              // returns Promise that resolves when ready
```

Common attributes the loader reacts to:
- `data-ready="true"` — removes loading animation, fires `web-component-ready`
- `data-error="message"` — shows ⚠️ icon, hover shows error, fires `web-component-error`
- `disabled` — prevents `loadContent()` from loading; component waits
- `hidden` — `display: none`
- `no-load-anim` — suppress loading animation

## CSS Loading Strategies

1. **Inline in JS** — `CSSStyleSheet` for small scoped styles:
   ```javascript
   const sheet = new CSSStyleSheet();
   sheet.replaceSync(`:host { display: block; }`);
   this.shadowRoot.adoptedStyleSheets = [sheet];
   ```

2. **Linked from HTML template** — `<link rel="stylesheet" href="my-widget.css">` inside the `.html` file. `loadContent()` auto-waits for linked stylesheets.

3. **Inherited from page** — `loadContent(url, {inheritStyles: true})` copies parent document styles into shadow root. Stylesheets with `noinherit` attribute are excluded.

4. **c-resources** — load shared CSS bundles inside HTML templates:
   ```html
   <c-resources assets="forms/css"></c-resources>
   ```

## Localization

### In HTML templates (gettext attributes)

```html
<meta name="gettext" content="translate" />  <!-- enable translation in this doc -->
<h1 gettext=".">Sign in</h1>                  <!-- translate text content -->
<input gettext="placeholder" placeholder="E-mail">  <!-- translate attribute -->
```

### In JavaScript

```javascript
import { gettext, ngettext } from '/dist/zolinga-intl/gettext.js?zolinga-commons';
// ?zolinga-commons = module name for dgettext domain
const msg = gettext('An error occurred.');
const items = ngettext('%d item', '%d items', count);
```

### Locale-aware template loading (WebComponentIntl)

When using `WebComponentIntl`, `loadContent()` automatically rewrites URLs to include the locale before the extension: `template.html` → `template.cs-CZ.html` (when `<html lang="cs-CZ">`). Create per-locale HTML files alongside the default.

## Inter-Component Communication

### Broadcast (same window or cross-tab)

```javascript
// Send to same window:
this.broadcast('message', { message: 'Saved!', type: 'success', timeout: 5000 });

// Send to all tabs/windows of same origin:
this.broadcast('rms:login-changed', { loggedIn: true }, true);
```

### Listen

```javascript
this.listen('message', (data) => { /* handle */ });
```

Broadcast does **not** trigger the sender's own listeners (filtered by component ID).

### Static API (outside components)

```javascript
import api from '/dist/system/js/api.js';
api.broadcast('message', { message: 'Hello' });
```

## Modal Pattern

Spawn a component and await its resolution:

```javascript
// Caller:
const popup = document.createElement('popup-container');
document.body.appendChild(popup);
const result = await popup.watchModal();  // or WebComponent.watchModal(popup)
// Resolved → result is the data passed to resolveModal()
// Rejected → catch gets data passed to rejectModal()

// Inside the modal component:
this.resolveModal(userData);  // success
this.rejectModal(error);      // failure
```

## Waiting for Other Components

```javascript
// Wait for a child component to be ready:
await this.waitForComponent(someElement);

// Wait for c-resources inside loadContent HTML:
const res = this.#root.querySelector('c-resources');
await new Promise(resolve => {
    if (res.dataset.ready) resolve();
    else res.addEventListener('web-component-ready', resolve, { once: true });
});
```

## observedAttributes

Extend the base class list to react to custom attributes:

```javascript
static observedAttributes = [...WebComponent.observedAttributes, 'show-card', 'width'];

attributeChangedCallback(name, oldValue, newValue) {
    super.attributeChangedCallback(name, oldValue, newValue);
    // handle custom attributes
}
```

## Slots

Used in Shadow DOM components. Named slots in HTML template:

```html
<slot name="title"></slot>
<slot name="content"></slot>
<slot></slot>  <!-- default slot -->
```

Usage from outside:

```html
<my-widget>
    <div slot="title">Title</div>
    <p>Default slot content</p>
</my-widget>
```

Slots are hidden while component is loading (loader CSS rule).

## Shadow DOM in Other Components

When a component creates a Shadow Root manually (not via `loadContent`), you must register the observer so nested web components auto-load:

```javascript
import components from '/dist/system/js/web-components.js';
this.attachShadow({ mode: 'open' });
components.observe(this.shadowRoot);
```

`loadContent()` does this automatically.

## Component Documentation (.md)

Place a `.md` file next to the `.js` file. Required sections:

- **Syntax** — HTML tag with all attributes/slots
- **Usage** — practical examples
- **Slots** — if any
- **Events** — custom DOM events fired
- **Methods** — public API
- **Attributes** — custom attributes and their effect

## Common Patterns Cheat Sheet

| Pattern | Code |
|---------|------|
| Basic component | `export default class X extends HTMLElement { constructor() { super(); this.innerHTML='...'; this.dataset.ready='true'; } }` |
| WebComponent + async init | `constructor() { super(); this.ready(this.#init()); }` |
| Load HTML template | `this.#root = await this.loadContent(import.meta.url.replace('.js','.html'), {mode:'closed'});` |
| Localized component | `import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';` |
| Broadcast message | `this.broadcast('name', data, global?)` |
| Listen for broadcast | `this.listen('name', callback)` |
| Modal: spawn & await | `const el = document.createElement('x'); body.append(el); await el.watchModal();` |
| Modal: resolve | `this.resolveModal(data)` |
| Wait for child ready | `await this.waitForComponent(el)` |
| Observe shadow root | `components.observe(this.shadowRoot)` |
| Import gettext | `import {gettext} from '/dist/zolinga-intl/gettext.js?module-name';` |
| Load shared CSS | `<c-resources assets="forms/css">` in HTML template |
| Custom observed attrs | `static observedAttributes = [...WebComponent.observedAttributes, 'my-attr'];` |
| Disable loading anim | Add `no-load-anim` attribute on the element |

## References

- `system/wiki/Zolinga Core/Web Components.md`
- `system/wiki/Zolinga Core/Web Components/WebComponent Class.md`
- `system/install/dist/js/web-component-element.js` (base class source)
- `system/install/dist/js/web-components-loader.js` (auto-loader source)
- `modules/zolinga-intl/install/dist/js/web-component-intl.js` (localized base)
