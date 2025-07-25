/**
 * This script is used to watch for new elements being added to the DOM and
 * lazy load the web components needed for those elements as defined in zolinga.json
 * manifest's "webComponents" section.
 * 
 * Classes used:
 * 
 *    .web-component-loading - class added to elements while their web component is being loaded
 * 
 * Attributes supported:
 *  
 *    data-ready - set to true when the web component is ready to be used
 *    data-error - set to the error message when the web component fails to initialize
 *    disabled - set to true to disable the web component - for descendants of WebComponent: WebComponent won't load the content. 
 *    hidden - set to true to hide the web component
 * 
 * Usage:
 * 
 *    import {default as components} '/data/system/js/web-components.js';
 * 
 * Note: when you create Shadow documents you need to init the observer there too:
 * 
 *    this.attachShadow();
 *    components.observe(this.shadowRoot);
 * 
 *  It will load autogenerated /data/system/web-components.json manifest and use it to
 *  lazy load web component constructors as needed. Refer to the zolinga.json manifests
 *  for more information on how to define web components.
 * 
 *  E.g. when <new-element> is added to the DOM, the script will check if the web component
 *  for <new-element> is already defined in web-components.json. If yes, it will import the
 *  web component's constructor from the file listed in web-components.json and will use it
 *  to define new custom element <new-element> using the default import from the file: 
 *  customElements.define('new-element', importedConstructor).  
 * 
 * Each custom element is supposed to set element.dataset.ready = true when it is ready to be used.
 * If you use the WebComponent class then you should call ready() method instead.
 * 
 * 
 * When it fails to initialize it should set the error on element.dataset.error.
 * 
 * When element.dataset.ready or element.dataset.error is set the events 'web-component-ready' or
 * 'web-component-error' are dispatched once on the element automatically. 
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-10 
 */
class WebComponentLoader {
    registryURL;
    #mapping = [];
    #loaded = [];
    #observer;
    stylesheet;

    constructor(registryURL) {
        this.registryURL = registryURL;
        this.#init();
    }

    async #init() {
        try {
            await this.#loadMapping();
            this.#initStylesheet();
            this.#initObserver();
            this.observe(document.documentElement);
        } catch (error) {
            console.error('WebComponents: Error initializing WebComponentLoader:', error);
        }
    }

    async #loadMapping() {
        try {
            const response = await fetch(this.registryURL);
            const data = await response.json();
            this.#mapping = data;
        } catch (error) {
            throw new Error('WebComponents: Error loading mapping:', error);
        }
    }

    #initObserver() {
        this.#observer = new MutationObserver(mutationsList => {
            mutationsList.forEach(mutation => {
                if (mutation.type === 'childList') {
                    mutation.addedNodes.forEach(node => {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            this.#findAttachedElements(node);
                        }
                    });
                }
            });
        });
    }

    #initStylesheet() {
        const customElements = this.#mapping.map(m => m.tag);
        this.stylesheet = new CSSStyleSheet();
        const selector = customElements.join(',');

        // IMPORTANT: Using nested CSS crashes all Safari browsers up to OSX 14.5 - we need to repeat the selector...
        this.stylesheet.replaceSync(`
            *:where(${selector}):where(:not([data-ready="true"], [data-error])) { /* least specific */
                display: inline-block;
            }

            *:is(${selector}) {
                --web-component-loader: url("data:image/svg+xml;random=0,%3Csvg xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='xMidYMid meet' viewBox='0 0 200 200'%3E%3Ccircle cx='100' cy='100' r='0' fill='none' stroke='%23888888' stroke-width='.5' opacity='0' %3E%3Canimate restart='always' attributeName='opacity' dur='5s' from='0' to='1' fill='freeze' begin='.5s' /%3E%3Canimate restart='always' attributeName='r' calcMode='spline' dur='2' keySplines='0 .2 .5 1' keyTimes='0;1' repeatCount='indefinite' values='1;80'/%3E%3Canimate restart='always' attributeName='stroke-width' calcMode='spline' dur='2' keySplines='0 .2 .5 1' keyTimes='0;1' repeatCount='indefinite' values='0;25'/%3E%3Canimate restart='always' attributeName='stroke-opacity' calcMode='spline' dur='2' keySplines='0 .2 .5 1' keyTimes='0;1' repeatCount='indefinite' values='1;0'/%3E%3C/circle%3E%3C/svg%3E%0A");
            }

            *:is(${selector}):not([data-ready="true"]) [slot] {
                display: none !important;
            }

            *:is(${selector})[hidden] {
                display: none !important;
            }

            *:is(${selector}):not(:defined), *:is(${selector}):not([data-error], [data-ready="true"], [disabled]) {
                cursor: wait;
                content-visibility: hidden;
                contain-intrinsic-size: 32px 32px;
                font-size: 0;

                &:not([no-load-anim]) {
                    background-position: center;
                    background-repeat: no-repeat;
                    background-image: var(--web-component-loader);    
                }
            }

            *:is(${selector})[data-error] {
                cursor: not-allowed;
            }

            *:is(${selector})[data-error]::before {
                content: "⚠️";
                font-size: max(1rem, min(2rem, 80%));
                display: inline-block;
                position: absolute;
                z-index: 10000;
            }

            *:is(${selector})[data-error]:hover::after {
                position: fixed;
                max-width: calc(100% - 2em);
                top: 1em;
                left: 1em;
                font-size: 1rem;
                background-color: color-mix(in oklab, rgb(var(--color-bg, 243, 243, 243)), red);
                color: rgb(var(--color-fg, 0, 0, 0));
                content: "WebComponent Error: " attr(data-error);
                padding: 0.5em;
            }
            
        `.replace(/random=\d+/, `random=${Math.random().toString().slice(2)}`));
        document.adoptedStyleSheets.push(this.stylesheet);
    }

    observe(node) {
        this.#observer.observe(node, { childList: true, subtree: true });
        this.#findAttachedElements(node);

        if (node instanceof ShadowRoot) {
            node.adoptedStyleSheets.push(this.stylesheet);
        }
    }

    #findAttachedElements(node) {
        if (node instanceof DocumentFragment) {
            for (let i = 0; i < node.children.length; i++) {
                this.#findAttachedElements(node.children[i]);
            }
            return;
        }

        // Use xpath selector to find any elements where local-name() contains a hyphen
        const customElements = this.#mapping.map(m => m.tag).filter(tag => tag !== 'translate-me');
        const queryList = customElements.map(tag => `local-name() = '${tag}'`);
        const query = `(.|.//*)[${queryList.join(" or ")}]`;

        const elements = node.ownerDocument.evaluate(query, node, null, XPathResult.ORDERED_NODE_SNAPSHOT_TYPE, null);
        for (let i = 0; i < elements.snapshotLength; i++) {
            this.#checkElement(elements.snapshotItem(i));
        }
    }

    #checkElement(element) {
        // console.log("WebComponents: Found custom element", element);
        element.classList.add('web-component');
        this.#monitorLoading(element);

        // First element of its kind, let's load it
        if (!window.customElements.get(element.localName) && !this.#loaded.includes(element.localName)) {
            this.#loaded.push(element.localName);
            // console.log("WebComponent: Loading a custom component %s", element.localName);
            this.#loadComponent(element);
        }
    }

    // The issue here is that this method can be called before
    // and sometimes after the custom element is initialized.
    #monitorLoading(element) {
        const observer = new MutationObserver((mutationList) => {
            mutationList.forEach((mutation) => {
                switch (mutation.attributeName) {
                    case 'data-ready':
                        if (element.dataset.ready == 'true') {
                            element.dispatchEvent(new CustomEvent('web-component-ready'));
                            element.style.removeProperty('--web-component-loader');
                        } else { // data-ready was there and was removed again
                            // Restart the background svg animation in case data-ready attr is removed in the future
                            const computedStyle = window.getComputedStyle(element);
                            const oldBg = computedStyle.getPropertyValue('--web-component-loader');
                            element.style.setProperty('--web-component-loader', oldBg.replace(/random=\d+/, `random=${Math.random().toString().slice(2)}`));
                        }
                        break;
                    case 'data-error':
                        if (element.dataset.error) {
                            element.dispatchEvent(new CustomEvent('web-component-error'));
                            element.style.removeProperty('--web-component-loader');
                        }
                        break;
                }
                // observer.disconnect();
            });
        });
        observer.observe(element, { attributes: true, attributeFilter: ['data-ready', 'data-error'] });
    }

    async #loadComponent(element) {
        const src = this.#mapping.find((m) => m.tag == element.localName)?.module;

        try {
            const { default: construct } = await import(src);

            if (!construct) {
                throw new Error(`WebComponents: The web component ${element.localName} is not defined in ${src} (missing \`export default\` statement in the module?)`);
            }

            if (customElements.getName && customElements.getName(construct)) { // somebody else already defined it ?
                throw new Error(`WebComponents: The custom element constructor is already registered for tag ${element.localName}`);
            }

            customElements.define(element.localName, construct);
        } catch (error) {
            console.error(`WebComponents: Error loading custom element ${element.localName} from ${src}: ${error}`);
            element.dataset.error = error;
        }
    }
}

// Instantiate the class to start watching for elements
const component = new WebComponentLoader('/data/system/web-components.json');
export default component;
