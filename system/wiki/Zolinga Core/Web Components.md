# Web Components

Zolinga includes inbuilt suport for [WHATWG Web Components](https://developer.mozilla.org/en-US/docs/Web/API/Web_components). This allows you to create custom HTML elements that can be reused across your application.

The system support consists of:

- Automatic registration of components
- Automatic loading of component scripts
- Providing an extensible base class for components

To create a component you need to declare it in section `webComponents` of the `config.json` file.

```json
{
    "webComponents": [
        {
            "tag": "my-example",
            "description": "This is a test component",
            "module": "my-components/my-example.js"
        }
    ]
}
```
- `tag`
    > This property is the name of the custom element. Note that as per WHATWG standard **it must contain a hyphen** `\-` in the name.
- `description`
    > A short description of the component.
- `module`
    > Contains the path to the JavaScript file that contains the component. The path is relative to your [dist directory](:Zolinga Core:Paths and Zolinga URI). It is located in your Zolinga module's `install/dist` directory and this directory will be symlinked to the public directory resulting in final public URL `http://example.com/dist/{zolinga module}/{javascript module}`.

## Common Attributes

The following common attributes are supported by Zolinga web components:

- `data-ready`: Set to true when the web component is ready to be used. E.g. call `WebComponent.ready()` method or set `this.dataset.ready = true`.
- `data-error`: Set to the error message when the web component fails to initialize. The system will display the error message instead of the component.
- `disabled`: Set to true to disable the web component. For descendants of `WebComponent`, the web component won't load the content.
- `hidden`: Set to true to hide the web component.

# Example

Considering the example declaration above the system expects that the file `{your module}/install/dist/my-components/my-example.js` is [ECMAScript module](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules) that default-exports a class that extends `HTMLElement` class. The default export is the class that will be used to [define the custom element](https://developer.mozilla.org/en-US/docs/Web/API/CustomElementRegistry/define).


Example of your component file `my-example.js`:

```javascript
export default class MyExample extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({mode: 'open'});
        this.shadowRoot.innerHTML = `<p>This is my example component</p>`;
        this.dataset.ready = 'true'; // Hide the default loading animation
        this.dispatchEvent(new CustomEvent('web-component-ready'));
    }
}
```

This is all you need to do. Now you need to [include on the page](:Zolinga Core:Running the System:Page Request:Processing Page Content) support for Zolinga web components by adding the following line to your page:

```html
<script type="module" src="/dist/system/js/web-components.js"></script>
```

Now if you place the element `<my-example></my-example>` on your page Zolinga will detect it and automatically load the your component script `/dist/{your module}/my-components/my-example.js` and register the component with the browser. As the result, the browser will render the custom element as defined in your component script. For more refer to the [MDN Web Components documentation](https://developer.mozilla.org/en-US/docs/Web/Web_Components).

You don't need to use ShadowDOM in your component. You can embed the component's HTML directly as children which has a lot of benefits if you use Zolinga's web components only in your application. Using ShadowDOM is recommended if you plan to distribute your components as standalone components that can be used in any application or if you want to protect your component from the outside world. That is rarely the case. So the more common scenario is to use the component's HTML directly as children.

```javascript
export default class MyExample extends HTMLElement {
    constructor() {
        super();
        // this.attachShadow({mode: 'open'});
        // this.shadowRoot.innerHTML = `<p>This is my example component</p>`;
        this.innerHTML = `<p>This is my example component</p>`;
        this.dataset.ready = 'true'; // Hide the default loading animation
        this.dispatchEvent(new CustomEvent('web-component-ready'));
    }
}
```

As the registered web component you can reap the benefits of the [Custom Elements API](https://developer.mozilla.org/en-US/docs/Web/Web_Components/Using_custom_elements) and/or [Shadow DOM API](https://developer.mozilla.org/en-US/docs/Web/Web_Components/Using_shadow_DOM) to create powerful and reusable components.

## Loading Animation

By default the Zolinga system will display a loading animation for the component while the component script is being loaded. After you are done initializing your component you are supposed to set the `data-ready` attribute on your component element which will remove the loading animation. You can do it simply by `this.dataset.ready = 'true';` in your component.

Tip: If you are reading this article using web interface then you can use DOM Inspector in your browser to see the content. The page is using web components to render the content.

# Extending the Base Component Class

Zolinga provides a base class `WebComponent` that you can extend to create your components. This class provides a number of useful methods and properties that you can use in your components. For more information refer to the [WebComponent Class](:Zolinga Core:Web Components:WebComponent Class) article.

When use `WebComponent` class you should call `this.ready()` method after you are done initializing your component. You can pass optional `Promise` as an argument to the `ready()` method. The `ready()` method will wait for the `Promise` to resolve before marking the component as ready.

```javascript
import WebComponent from '/dist/system/js/web-component.js';

export default class MyExample extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        // my initialization
    }
}
```

# Related

{{Web Components Related}}