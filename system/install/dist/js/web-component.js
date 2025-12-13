import components from '/dist/system/js/web-components.js';

/**
 * A base class for web components that provides a few useful methods.
 *
 * This class provides a few useful methods for web components:
 *
 * - loadContent(url, options): Load the content of an HTML file into the component.
 * - broadcast(name, detail, global): Send a broadcast message to all subscribers of the name.
 * - listen(name, callback): Listen to a broadcast message with the given name.
 * - waitEnabled(): Wait until the "disabled" attribute is removed if it exists and the component is attached to the DOM.
 *
 * If you create a new custom element with attribute "disabled",
 * the component will not initialize until the attribute is removed.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-10
 */
/**
 * A base class for creating custom web components with advanced features including
 * content loading, broadcasting, modal handling, and lifecycle management.
 * 
 * This class extends HTMLElement and provides a foundation for building complex
 * web components with features like:
 * - Asynchronous content loading from external HTML files
 * - Inter-component communication via broadcast messaging
 * - Modal component patterns with promise-based resolution
 * - Automatic script and stylesheet loading with timeout handling
 * - Shadow DOM support with style inheritance options
 * - Component readiness tracking and waiting mechanisms
 * - Automatic URL rewriting with cache-busting revision parameters
 * 
 * @class WebComponent
 * @extends HTMLElement
 * 
 * @example
 * // Basic usage - extend WebComponent to create custom components
 * class MyComponent extends WebComponent {
 *   constructor() {
 *     super();
 *     this.loadContent('./templates/my-template.html')
 *       .then(() => this.ready());
 *   }
 * }
 * customElements.define('my-component', MyComponent);
 * 
 * @example
 * // Using broadcast messaging
 * component.listen('user-action', (data) => console.log('Received:', data));
 * component.broadcast('user-action', { action: 'click', target: 'button' });
 * 
 * @example
 * // Modal component pattern
 * const result = await WebComponent.watchModal(modalComponent);
 * // In modal component: this.resolveModal(data) or this.rejectModal(error)
 * 
 * @example
 * // Loading content with options
 * await component.loadContent('./content.html', {
 *   mode: 'open',
 *   allowScripts: true,
 *   inheritStyles: true,
 *   timeout: 10000
 * });
 */
export default class WebComponent extends HTMLElement {
  /**
     * The unique web component ID. This is used to filter out broadcast messages.
     */
  #componentId;

  /**
   * The list of attributes to observe for changes.
   * 
   * When extending this class use in your class:
   * 
   *     static observedAttributes = ['data-my-attribute', ...WebComponent.observedAttributes];
   * 
   * or using a getter
   * 
   *     static get observedAttributes() {
   *        return ['data-my-attribute', ...WebComponent.observedAttributes];
   *    }
   */
  static observedAttributes = ['disabled'];

  /**
     * The promise that resolves when the component is enabled.
     * If the component is not disabled, it is null.
     */
  #enabledPromise;

  /**
   * The promise that resolves when the component is ready.
   * @type {Promise}
   */
  #readyPromiseCumulative;
  #readyPromise;
  #readyResolve;

  /** 
   * Resolves when the component is attached to the DOM.
   */
  #connectedPromise;
  #connectedResolve;

  /**
     * The broadcast channel to send and receive messages.
     */
  #broadcast;

  /**
     * The list of listeners for broadcast messages.
     */
  #listeners = new Set();

  constructor() {
    super();

    this.#componentId = Math.random().toString(36).slice(2);

    // This promise is returned from this.ready() and resolves when the component is ready.
    const { promise, resolve } = Promise.withResolvers();
    this.#readyPromise = promise.then(() => {
      this.dataset.ready = 'true';
      this.dispatchEvent(new CustomEvent('web-component-ready'));
    });
    this.#readyResolve = resolve;

    this.#connectedPromise = new Promise((resolve) => {
      this.#connectedResolve = resolve;
    });
    if (this.isConnected) {
      this.#connectedResolve();
    }

    if (this.hasAttribute('disabled')) {
      this.#installWaitEnabledPromise();
    }
    this.#broadcast = new BroadcastChannel('zolinga');
    this.#broadcast.addEventListener('message', this.#onMessage.bind(this)); // global messages
    window.addEventListener('message', this.#onMessage.bind(this)); // local messages

    // // Implement destructor using FinalizationRegistry
    // if (typeof FinalizationRegistry !== 'undefined') {
    //   const registry = new FinalizationRegistry(() => this.destructor());
    //   registry.register(this);
    // }
  }

  // destructor() {
  //   console.log('WebComponent.destructor(): ', this.#componentId);
  //   this.#broadcast.close();
  //   window.removeEventListener('message', this.#onMessage);
  // }

  /**
   * Mark the component as ready.
   * 
   * Promise can be Promise or anything that can be converted to Promise.
   * 
   * If the parameter is a Promise the component will be set as ready when the Promise is resolved.
   * 
   * this.ready(new Promise((accept) => setTimeout(accept, 1000)));
   * this.ready(new Promise((accept) => setTimeout(accept, 3000)));
   * await this.ready();
   * 
   * @param {Promise|any} promise - Promise that resolves when the component is ready.
   * @return {Promise} - Promise that resolves when the component is ready.
   */
  async ready(promise) {
    if (this.#readyPromiseCumulative) {
      this.#readyPromiseCumulative.makeReady = function () { };
    }
    let newPromise = new Promise((accept, reject) => {
      Promise.all([this.#readyPromiseCumulative, promise]).then(accept, reject);
    }
    );
    newPromise.makeReady = this.#readyResolve;
    newPromise.then(() => newPromise.makeReady());

    this.#readyPromiseCumulative = newPromise;
    return this.#readyPromise;
  }

  /**
     * Load the content of an HTML file into the component.
     * If it contains <script> tags, they will be executed.
     * It will automatically call components.observe() on the content
     * so that any web components in the content are initialized.
     *
     * The content may contain relative links in src and href attributes, which will be resolved
     * against the URL of the HTML file.
     *
     * @param {string|URL} url - URL of the HTML to load
     * @param {Object} options - Options for loading the content
     * @param {string} options.mode - 'open', 'closed': create Shadow Root in this mode and append it, 'seamless': append the content directly
     * @param {boolean} options.allowScripts - Whether to allow executing scripts in the loaded content
     * @param {number} options.timeout - Timeout in milliseconds for loading the content
     * @param {function} options.filter - Filter function to modify the content before loading
     * @param {boolean} options.inheritStyles - Whether to inherit the styles from the parent document, only for "closed" and "open" document modes. "seamless" mode always inherits styles.
     * @return {Promise} - Promise that resolves when the content is loaded and all scripts and styles are ready.
     *                      Promise will resolve to the Shadow Root or the component itself if mode is 'seamless'.
     */
  async loadContent(url, options = { mode: 'open', allowScripts: false, timeout: 60000, filter: null, inheritStyles: false }) {
    if (url instanceof URL) {
      url = url.toString();
    }
    url = this.rewriteURL(url, 'content');
    await this.waitEnabled();
    return fetch(url)
      .then((response) => response.text())
      .then((html) => this.#parseHtmlResolveLinks(html, url))
      .then(async (html) => {
        if (options.filter) {
          html = options.filter(html);
        }

        const root = options.mode === 'seamless' ? this : this.attachShadow({ mode: options.mode });
        root.innerHTML = html;

        // Shadow DOM
        if (options.mode !== 'seamless') {
          components.observe(root);
          if (options.inheritStyles) {
            await this.#waitForStyles(document.documentElement, options.timeout);
            this.#inheritStyles(root);
          }
        }

        // Wait for styles and scripts to load
        const promises = [this.#waitForStyles(root, options.timeout)];
        if (options.allowScripts) {
          promises.push(this.#execScripts(root, options.timeout));
        }

        return Promise.all(promises)
          .then(() => root);
      })
      .catch((error) => {
        this.dataset.error = error;
      });
  }

  /**
   * This is meant to be extended by subclasses to rewrite URLs before loading them.
   * 
   * @param String url 
   * @param String type - for now supported only 'content' for content HTML URLs
   * @return String
   */
  rewriteURL(url, type) {
    return this.addUrlRevParam(url);
  }

  addUrlRevParam(url) {
    // Add ?rev=XY to the URL to prevent caching
    const rev = document.documentElement.dataset.revision;
    if (!rev || url.match(/^javascript:|\?rev=/)) return url;

    // Way to invalidate cache - append revision number to the URL
    const o = new URL(url, window.location);    

    if (o.host !== window.location.host) return url;

    o.searchParams.set('rev', rev);
    return o.toString();
  }

  // Inherit all styles except those with noinherit attribute
  async #inheritStyles(root) {
    // const doc = this.getRootNode();
    const doc = document; // always inherit only from main document

    Array.from(doc.adoptedStyleSheets.values()).forEach((styleSheet) => {
      root.adoptedStyleSheets.push(styleSheet);
    });

    Array.from(doc.styleSheets).forEach((styleSheet) => {
      if (styleSheet.ownerNode) {
        if (!styleSheet.ownerNode.hasAttribute('noinherit')) {
          root.appendChild(styleSheet.ownerNode.cloneNode(true));
        }
      } else {
        const sheet = new CSSStyleSheet();
        sheet.replace(styleSheet.cssText);
        root.adoptedStyleSheets.push(sheet);
      }
    });
  }

  /**
     * Send a broadcast message to all subscribers of the name.
     * The broadcast message will not trigger the listeners in this object.
     *
     * @param {String} name Event name that will be broadcasted.
     * @param {Object} detail Serializable object that will be broadcasted. See BroadcastChannel.postMessage() for more information.
     * @param {boolean} global Send the name to all subscribers in all windows, not just in the current window.
     * @returns {WebComponent} this object for chaining
     */
  broadcast(name, detail = null, global = false) {
    const payload = {
      name,
      "detail": typeof detail?.toJSON === 'function' ? detail.toJSON() : detail,
      "source": this.#componentId
    };

    const origin = window.location.origin;
    if (global) {
      this.#broadcast.postMessage(payload);
    } else {
      window.postMessage(payload, window.location.origin);
    }

    return this;
  }

  /**
     * Listen to a broadcast message with the given name.
     *
     * @param {String} name Broadcast name to listen to.
     * @param {Function} callback the callback function that will be called when the broadcast message is received.
     * @returns {WebComponent} this object for chaining
     */
  listen(name, callback) {
    this.#listeners.add({ name, callback });
    return this;
  }

  /**
    * Wait until the "disabled" attribute is removed if it exists.
    */
  async waitEnabled() {
    await this.#enabledPromise;
  }

  /**
   * Sends event that this component was rejected. Other components listening to this modal component will get their 
   * Promises rejected. E.g. this.watchModal(otherComponent).then(...).catch(...): Promise
   * 
   * @param {Object} data - The data to reject the Promise with
   * @returns {void}
   */
  rejectModal(data) {
    this.dispatchEvent(new CustomEvent('web-component-modal-settled', { detail: { data, settled: false } }));
  }

  /**
   * Sends event that this component was resolved. Other components listening to this modal component will get their
   * Promises resolved. E.g. this.watchModal(otherComponent).then(...).catch(...): Promise
   * 
   * @param {Object} data - The data to resolve the Promise with
   * @returns {void}
   */
  resolveModal(data) {
    this.dispatchEvent(new CustomEvent('web-component-modal-settled', { detail: { data, settled: true } }));
  }

  /**
   * Waits for the modal component to resolve or reject.
   * 
   * Example: WebComponent.watchModal(document.querySelector('my-component')).then((data) => console.log(data));
   *  
   * @param {WebComponent|HTMLElement|null} component - The component to watch for resolve or reject. If not provided, this component is used.
   * @returns {Promise} - A promise that resolves when the modal component resolves or rejects when target components calls this.resolveModal() or this.rejectModal()
   */
  static async watchModal(component) {
    return new Promise((resolve, reject) => {
      const target = component || this;
      target.addEventListener('web-component-modal-settled', (ev) => {
        if (ev.srcElement === target) {
          if (ev.detail.settled) {
            resolve(ev.detail.data);
          } else {
            reject(ev.detail.data);
          }
        }
      });
    });
  }

  /**
   * Waits for the modal component to resolve or reject. This is a shorthand for WebComponent.watchModal(component).
   * 
   * Example: this.watchModal(document.querySelector('my-component')).then((data) => console.log(data));
   * 
   * Note: there is also a static method WebComponent.watchModal(component).
   * 
   * @param {WebComponent|HTMLElement} component - The component to watch for resolve or reject
   * @returns {Promise} - A promise that resolves when the modal component resolves or rejects when target components calls this.resolveModal() or this.rejectModal()
   */
  async watchModal(component) {
    return WebComponent.watchModal(component);
  }

  /**
   * Wait for web component to load.
   * 
   * Web-component is loaded when it has the 'data-ready' attribute and 
   * it dispatches the 'web-component-ready' event.
   * 
   * @param {HTMLElement} component 
   * @returns {Promise} - A promise that resolves when the component is ready.
   */
  static async waitForComponent(component) {
    return new Promise((resolve, reject) => {
      if (component.dataset.ready === 'true') {
        resolve(component);
      } else {
        component.addEventListener('web-component-ready', () => resolve(component), { once: true });
      }
    });
  }


  /**
   * Waits for a web component to be fully initialized and ready.
   * 
   * @async
   * @param {WebComponent} [component] - The web component to wait for. If not provided, defaults to the current instance (this).
   * @returns {Promise<WebComponent>} A promise that resolves when the component is ready.
   */
  async waitForComponent(component) {
    return WebComponent.waitForComponent(component || this);
  }

  /**
   * Handles changes to observed attributes of the web component.
   * When the 'disabled' attribute changes, manages the enabled/disabled state
   * by installing or resolving the wait enabled promise.
   * 
   * The list of monitored attributes is defined in the static `observedAttributes` property.
   * 
   * @param {string} name - The name of the attribute that changed
   * @param {string|null} oldValue - The previous value of the attribute
   * @param {string|null} newValue - The new value of the attribute
   */
  attributeChangedCallback(name, oldValue, newValue) {
    if (name === 'disabled') {
      if (newValue !== null) {
        this.#installWaitEnabledPromise();
      } else if (this.#enabledPromise) {
        this.#enabledPromise.resolve();
        this.#enabledPromise = null;
      }
    }
  }

  connectedCallback() {
    this.#connectedResolve();
    this.#connectedPromise = null; // Clear the promise after resolving it
  }

  #onMessage(ev) {
    if (ev.origin !== window.location.origin || this.#componentId === ev.data.source) {
      return;
    }

    const name = ev.data.name;
    const detail = ev.data.detail;

    this.#listeners.forEach((listener) => {
      if (listener.name === name) {
        listener.callback(detail);
      }
    });
  }

  #installWaitEnabledPromise() {
    if (!this.#enabledPromise) {
      const { promise, resolve } = Promise.withResolvers();
      this.#enabledPromise = promise;
      this.#enabledPromise.resolve = resolve;
    }
  }

  /**
     * Resolve all relative links in the given HTML code against the given base URL.
     *
     * @param {string} html the HTML code to resolve links in
     * @param {string} baseUrl the base URL to resolve links against
     * @return {string} the HTML code with all links resolved
     */
  async #parseHtmlResolveLinks(html, baseUrl) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    let base;

    // Is there a <base> tag in the document? If not, add one.
    if (!doc.querySelector('base')) {
      base = doc.createElement('base');
      base.href = baseUrl;
      doc.head.appendChild(base);
    }

    // Now replace all src and href attributes with the resolved URL
    doc.querySelectorAll('[src],[href]').forEach((node) => {
      const attributes = ['src', 'href'];
      attributes.forEach((attr) => {
        if (node.hasAttribute(attr)) {
          let url = new URL(node.getAttribute(attr), baseUrl);
          url = this.addUrlRevParam(url.toString());
          node.setAttribute(attr, url); // node.src or node.href are already resolved by HTMLDocument.
        }
      });
    });

    if (base) {
      base.remove();
    }

    // Return the modified HTML string back
    return doc.documentElement.outerHTML;
  }

  /**
     * Executes the scripts within the given root element.
     * @param {Element} root - The root element to search for scripts.
     * @param {number} [timeout=30000] - The timeout duration in milliseconds.
     * @return {Promise<Array>} - A promise that resolves when all scripts have been executed.
     */
  async #execScripts(root, timeout = 30000) {
    const promises = [];
    const scripts = root.querySelectorAll('script');
    scripts.forEach((script) => {
      const newScript = document.createElement('script');
      for (let i = 0; i < script.attributes.length; i++) {
        newScript.setAttribute(script.attributes[i].name, script.attributes[i].value);
      }
      newScript.text = script.text;
      if (script.src) {
        promises.push(this.#waitForLoad(newScript, timeout));
      }
      root.appendChild(newScript);
      script.remove();
    });
    return Promise.all(promises);
  }

  /**
     * Waits for all stylesheets to be loaded in the given root element.
     * @param {Element} root - The root element to search for stylesheets.
     * @param {number} [timeout=30000] - The timeout duration in milliseconds.
     * @return {Promise} - A promise that resolves when all stylesheets are loaded.
     */
  async #waitForStyles(root, timeout = 30000) {
    const promises = [];
    root.querySelectorAll('link[rel~="stylesheet"][href]:not([disabled])')
      .forEach((node) => {
        promises.push(this.#waitForLoad(node, timeout));
      });
    return Promise.all(promises);
  }

  /**
     * Waits for the specified element to load.
     * @param {HTMLElement} element - The element to wait for.
     * @param {number} [timeout=30000] - The timeout duration in milliseconds.
     * @return {Promise<void>} - A promise that resolves when the element is loaded successfully.
     * @throws {Error} - If the element fails to load within the specified timeout.
     */
  async #waitForLoad(element, timeout = 30000) {
    const { promise, resolve, reject } = Promise.withResolvers();

    await this.#connectedPromise; // Wait until the component is connected to the DOM

    if (!element.isConnected) {
       console.warn(`WebComponent.#waitForLoad(): Element ${element.tagName} is not connected to the DOM.`, element);
       resolve();
    } else if (element instanceof HTMLImageElement && (element.complete || element.loading == 'lazy')) { 
      // HTMLImageElement.complete is true when the image is loaded
      resolve();
    } else if (element instanceof HTMLLinkElement && element.sheet) { 
      // HTMLLinkElement.sheet is null until the stylesheet is loaded
      resolve();
    } else {
      const timer = setTimeout(() => {
        const error = new Error(`Resource ${element.href ?? element.src} load timeout after ${timeout}ms`);
        console.warn(error);
        reject(error);
      }, timeout);

      element.addEventListener('load', resolve, { once: true });
      element.addEventListener('error', reject, { once: true });

      promise.finally(() => {
        // console.log('WebComponent.#waitForLoad(): ', element.href ?? element.src);
        clearTimeout(timer);
        element.removeEventListener('load', resolve);
        element.removeEventListener('error', reject);
      });
    }

    return promise;
  }
}
