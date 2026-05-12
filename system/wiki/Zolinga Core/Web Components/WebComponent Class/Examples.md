# WebComponent JavaScript Class

The `WebComponent` class (`/dist/system/js/web-component.js`) is the base class for all Zolinga web components. It provides essential features for content loading, inter-component communication, and lifecycle management.

## Import

```javascript
import WebComponent from '/dist/system/js/web-component.js';

export default class MyComponent extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }
    
    async #init() {
        // initialization code
    }
}
```

## Core Features

### `ready(promise)` — Mark Component Ready

Marks the component as ready when the promise resolves. Sets `data-ready="true"` and fires `web-component-ready` event.

```javascript
// Mark ready immediately
this.ready();

// Mark ready after async operation completes
this.ready(this.#loadData());

// Wait for readiness
await this.ready();
```

### `loadContent(url, options)` — Load HTML Template

Loads an HTML file into the component. Automatically resolves relative URLs, waits for scripts/styles, and observes nested web components.

```javascript
async #init() {
    this.#root = await this.loadContent(
        import.meta.url.replace('.js', '.html'),
        {
            mode: 'open',         // 'open'|'closed'|'seamless'
            allowScripts: false,  // execute <script> tags
            timeout: 60000,       // ms
            filter: null,         // (html) => html transform
            inheritStyles: false  // copy parent styles
        }
    );
}
```

**Modes:**
- `'open'` — Shadow DOM, accessible via JavaScript
- `'closed'` — Shadow DOM, private
- `'seamless'` — No Shadow DOM, inherits page styles directly

### `rewriteURL(url, type)` — Customize URLs

Override to modify URLs before loading. Called by `loadContent()` for content HTML.

```javascript
rewriteURL(url, type) {
    // Add locale to URL for localized templates
    const lang = document.documentElement.lang;
    if (lang !== 'en-US') {
        return url.replace('.html', `.${lang}.html`);
    }
    return url;
}
```

### `broadcast(name, detail, global)` — Send Messages

Sends a message to all listeners. Does not trigger own listeners.

```javascript
// Send to same window
this.broadcast('user-action', { action: 'click', target: 'button' });

// Send to all windows/tabs
this.broadcast('user-action', { action: 'click' }, true);
```

### `listen(name, callback)` — Receive Messages

Listens for broadcast messages.

```javascript
this.listen('user-action', (data) => {
    console.log('Received:', data);
});
```

### `waitEnabled()` — Wait for Disabled Removal

If the component has `disabled` attribute, waits until it's removed.

```javascript
async #init() {
    await this.waitEnabled();  // blocks until disabled is removed
    this.#load();
}
```

### `resolveModal(data)` / `rejectModal(data)` — Modal Completion

Signals that a modal component is complete.

```javascript
// Inside modal component
this.resolveModal({ userId: 123 });  // success
this.rejectModal(new Error('Cancelled'));  // failure
```

### `watchModal(component)` — Await Modal Result

Waits for a modal component to resolve or reject.

```javascript
// Instance method
const result = await this.watchModal(popupElement);

// Static method
const result = await WebComponent.watchModal(popupElement);
```

### `waitForComponent(component)` — Wait for Component Ready

Waits for another web component to become ready.

```javascript
await this.waitForComponent(childElement);
```

## Lifecycle Hooks

### `connectedCallback()`

Called when component is attached to DOM. Calls `#connectedResolve()` internally.

### `attributeChangedCallback(name, oldValue, newValue)`

Monitors `disabled` attribute by default. Extend to monitor custom attributes:

```javascript
static observedAttributes = ['disabled', 'show-card', 'width'];

attributeChangedCallback(name, oldValue, newValue) {
    super.attributeChangedCallback(name, oldValue, newValue);
    // handle custom attributes
}
```

## Common Patterns

### Basic Component with Template

```javascript
import WebComponent from '/dist/system/js/web-component.js';

export default class MyWidget extends WebComponent {
    #root;
    
    constructor() {
        super();
        this.ready(this.#init());
    }
    
    async #init() {
        this.#root = await this.loadContent(
            import.meta.url.replace('.js', '.html'),
            { mode: 'open' }
        );
        this.#initListeners();
    }
    
    #initListeners() {
        this.listen('refresh', () => this.#load());
        this.#root.querySelector('button').addEventListener('click', () => {
            this.broadcast('action', { type: 'save' });
        });
    }
    
    async #load() {
        // load data logic
    }
}
```

### Modal Component

```javascript
import WebComponent from '/dist/system/js/web-component.js';

export default class ConfirmDialog extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }
    
    async #init() {
        this.#root = await this.loadContent(
            import.meta.url.replace('.js', '.html'),
            { mode: 'closed' }
        );
        
        this.#root.querySelector('[data-confirm]').addEventListener('click', () => {
            this.resolveModal(true);
        });
        
        this.#root.querySelector('[data-cancel]').addEventListener('click', () => {
            this.rejectModal(false);
        });
    }
}

// Usage
const dialog = document.createElement('confirm-dialog');
document.body.appendChild(dialog);
try {
    const confirmed = await WebComponent.watchModal(dialog);
    console.log('User confirmed:', confirmed);
} catch (e) {
    console.log('User cancelled');
}
```

### Disabled Component

```javascript
export default class DataFetcher extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }
    
    async #init() {
        await this.waitEnabled();  // waits for disabled attribute removal
        this.#fetch();
    }
    
    async #fetch() {
        // fetch data
    }
}
```

```html
<!-- Component waits until disabled is removed -->
<data-fetcher disabled></data-fetcher>

<!-- Remove disabled via JS to trigger loading -->
<script>
    document.querySelector('data-fetcher').removeAttribute('disabled');
</script>
```

### Inter-Component Communication

```javascript
// Sender component
this.broadcast('data-updated', { id: 123, action: 'refresh' });

// Receiver component
this.listen('data-updated', (data) => {
    if (data.action === 'refresh') {
        this.#reload(data.id);
    }
});
```

## Reference

- `/dist/system/js/web-component-element.js` — full implementation source
- `/dist/system/js/web-components.js` — component loader and observer
- [Web Components](:Zolinga Core:Web Components) — registration and general usage
