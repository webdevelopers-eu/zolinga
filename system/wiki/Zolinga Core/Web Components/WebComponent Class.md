# WebComponent Class

The [WebComponent Class](/dist/system/lib/web-component.js) is the optional base class for your web components that you can extend instead of `HTMLElement`. This class provides a number of useful methods and properties that you can use in your components.

You will get following benefits by extending this class:

- `this.loadContent()` method to load HTML and embed it in your component
    - optionally executes embedded JavaScripts
    - autoload linked CSS files
    - postpones loading if the "disabled" attribute is set on the component
    - supports embedding the content into open/closed ShadowDOMs or directly into the component
    - resolves after the content is fully loaded
- `this.broadcast()` method to broadcast events to all components
    - allows to broadcast either to all other components on the same page or to all components in all open windows/tabs of the same origin.
- `this.listen()` method to listen to events from all components
    - allows to listen to events broadcasted using `this.broadcast()`

To use it import it and extend it in your component class:

```javascript
import WebComponent from '/dist/system/lib/web-component.js';

class MyComponent extends WebComponent {
    constructor() {
        super();
        ...
    }
}
```

Tip: If you are reading this article using web interface then you can use DOM Inspector in your browser to see the content. The page is using web components to render the content.

# Related

{{Web Components Related}}