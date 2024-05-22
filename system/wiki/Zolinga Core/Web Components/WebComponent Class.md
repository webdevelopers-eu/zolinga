Priority: 0.5

# WebComponent Class

## Overview

The [WebComponent Class](/dist/system/js/web-component.js) is the optional base class for your web components that you can extend instead of `HTMLElement`. This class provides a number of useful methods and properties that you can use in your components.

You will get following benefits by extending this class:
- `this.ready(promise)` method to mark the component as ready after the initialization is done
    - waits for the `Promise` to resolve before marking the component as ready and firing the `web-component-ready` event.
    - if `Promise` is not provided the component is marked as ready immediately unless other calls to `this.ready()` are pending with `Promise`. E.g. you can make multiple calls to `this.ready()` with `Promise` and the component will be marked as ready only after all `Promise`s are resolved.
- `this.loadContent(url, options)` method to load HTML and embed it in your component
    - optionally executes embedded JavaScripts
    - autoload linked CSS files
    - postpones loading if the "disabled" attribute is set on the component
    - supports embedding the content into open/closed ShadowDOMs or directly into the component
    - resolves after the content is fully loaded
- `this.broadcast()` method to broadcast events to all components
    - allows to broadcast either to all other components on the same page or to all components in all open windows/tabs of the same origin.
- `this.listen()` method to listen to events from all components
    - allows to listen to events broadcasted using `this.broadcast()`
- `this.rejectModal(data)` reject all Promises created using `this.watchModal()`
- `this.resolveModal(data)` resolve all Promises created using `this.watchModal()`
- `this.watchModal(element)` method to create a Promise that is settled when the watched component calls `this.resolveModal()` or `this.rejectModal()` 
- `this.waitEnabled()` method to wait until the component is enabled (does not have the "disabled" attribute set)
    - resolves immediately if the component is enabled
    - resolves after the component is enabled if it is disabled
- `this.waitForComponent(element)` method to wait until the component is ready
    - resolves immediately if the component has the `data-ready` attribute set
    - resolves after the component dispatches the `web-component-ready` event

## Usage

To use it import it and extend it in your component class:

```javascript
import WebComponent from '/dist/system/js/web-component.js';

class MyComponent extends WebComponent {
    constructor() {
        super();
        ...
    }
}
```

Tip: If you are reading this article using web interface then you can use DOM Inspector in your browser to see the content. The page is using web components to render the content.

## Modals

Sometime one component will need to spawn another component and wait for it to finish before continuing. This can be done using modals. The `watchModal()` method creates a Promise that is settled when the watched component calls `this.resolveModal()` or `this.rejectModal()`. The watched component can be any component on the page
that uses the `WebComponent` class and supports calling `this.resolveModal()` and `this.rejectModal()`.

One example is a login box that needs to be resolved before the main content is shown. The main content can be hidden until the login box is resolved.

```javascript
async function showLoginBox() {
    const loginBox = document.createElement('login-box');
    document.body.appendChild(loginBox);

    try {
        await loginBox.watchModal();
        console.log('Login successful');
        // Show the main content
    } catch (error) {
        console.error('Login failed:', error);
    } finally {
      loginBox.remove();
    }
}
```

The login box component can be implemented like this:

```javascript
class LoginBox extends WebComponent {
    constructor() {
        super();
        ...
    }

    async login() {
        try {
            // Perform login
            this.resolveModal(userData);
        } catch (error) {
            this.rejectModal(error);
        }
    }
}
```

## Methods

### loadContent() 

```
  async loadContent(url, options = { mode: 'open', allowScripts: false, timeout: 60000 })
```

The loadContent function loads the content of an HTML file into the component. It provides options for handling scripts, initializing web components, and resolving relative links.

#### Parameters

- `url` (string): URL of the HTML file to load. Usually something like `new URL('wiki-article.html', import.meta.url)` will be used to load .html from the same directory as loading script.
- `options` (Object): Options for loading the content.
  - `mode` (string): Specifies the mode of loading the content. Possible values are:
    - `"open"`: Create a Shadow Root in this mode and append it.
    - `"closed"`: Create a Shadow Root in this mode and append it.
    - `"seamless"`: Append the content directly.
  - `allowScripts` (boolean): Indicates whether to allow executing scripts in the loaded content.
  - `timeout` (number): Timeout in milliseconds for loading the content.
  - `filter` (function): A function that filters the content before appending it. The function receives the content as a parameter and should return the filtered content.
  - `inheritStyles` (boolean): Indicates whether to inherit the styles from the parent document. Default is false. Valid only for "open" and "closed" modes as "seamless" mode always inherits styles. It inherits styles from the main Document only.

#### Return Value

Returns a `Promise` that resolves when the content is loaded and all scripts and styles are ready. The Promise will resolve to the Shadow Root or the component itself if the mode is 'seamless'.

#### Behavior

If the loaded HTML file contains `<script>` tags, they will be executed if the allowScripts option is set to true. 

The function automatically calls components.observe() on the content so that any web components in the content are initialized.

Relative links in src and href attributes of the content will be resolved against the URL of the HTML file.

#### Example Usage

```javascript
async function exampleUsage() {
  try {
    const content = await loadContent('example.html', {
      mode: 'open',
      allowScripts: true,
      timeout: 10000
    });
    console.log('Content loaded successfully:', content);
  } catch (error) {
    console.error('Error loading content:', error);
  }
}
```
#### Notes

The function expects the component to be enabled before loading content (`await this.waitEnabled()`). If the component is disabled (has the "disabled" attribute set), the function will postpone loading the content until the component is enabled.

The function handles errors by setting the dataset error attribute.

# Related

{{Web Components Related}}