import components from '/dist/system/web-components.js';

/**
 * A base class for web components that provides a few useful methods.
 *
 * This class provides a few useful methods for web components:
 *
 * - loadContent(url, options): Load the content of an HTML file into the component.
 * - broadcast(name, detail, global): Send a broadcast message to all subscribers of the name.
 * - listen(name, callback): Listen to a broadcast message with the given name.
 * - waitEnabled(): Wait until the "disabled" attribute is removed if it exists.
 *
 * If you create a new custom element with attribute "disabled",
 * the component will not initialize until the attribute is removed.
 *
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-02-10
 */
export default class WebComponent extends HTMLElement {
  /**
     * The scope ID of the current window. This is used to filter out broadcast messages.
     */
  static #scopeId = Math.random().toString(36).slice(2);

  /**
     * The list of attributes to observe for changes.
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
     * The broadcast channel to send and receive messages.
     */
  #broadcast;

  /**
     * The list of listeners for broadcast messages.
     */
  #listeners = new Set();

  constructor() {
    super();

    // This promise is returned from this.ready() and resolves when the component is ready.
    const { promise, resolve } = Promise.withResolvers();
    this.#readyPromise = promise.then(() => this.dataset.ready = 'true');
    this.#readyResolve = resolve;

    if (this.hasAttribute('disabled')) {
      this.#installWaitEnabledPromise();
    }
    this.#broadcast = new BroadcastChannel('zolinga');
    this.#broadcast.addEventListener('message', (ev) => {
      if (ev.data.scope && ev.data.scope !== WebComponent.#scopeId) return;

      const name = ev.data.name;
      const detail = ev.data.detail;

      this.#listeners.forEach((listener) => {
        if (listener.name === name) {
          listener.callback(detail);
        }
      });
    });
  }

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
     * @param {string} url - URL of the HTML to load
     * @param {Object} options - Options for loading the content
     * @param {string} options.mode - 'open', 'closed': create Shadow Root in this mode and append it, 'seamless': append the content directly
     * @param {boolean} options.allowScripts - Whether to allow executing scripts in the loaded content
     * @param {number} options.timeout - Timeout in milliseconds for loading the content
     * @return {Promise} - Promise that resolves when the content is loaded and all scripts and styles are ready.
     *                      Promise will resolve to the Shadow Root or the component itself if mode is 'seamless'.
     */
  async loadContent(url, options = { mode: 'open', allowScripts: false, timeout: 60000 }) {
    await this.waitEnabled();
    return fetch(url)
      .then((response) => response.text())
      .then((html) => this.#parseHtmlResolveLinks(html, url))
      .then((html) => {
        let root;
        if (options.mode === 'seamless') {
          root = this;
          root.innerHTML = html;
        } else {
          root = this.attachShadow({ mode: options.mode });
          root.innerHTML = html;
          components.observe(root);
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
     * Send a broadcast message to all subscribers of the name.
     * The broadcast message will not trigger the listeners in this object.
     *
     * @param {String} name Event name that will be broadcasted.
     * @param {Object} detail Serializable object that will be broadcasted. See BroadcastChannel.postMessage() for more information.
     * @param {boolean} global Send the name to all subscribers in all windows, not just in the current window.
     * @returns {WebComponent} this object for chaining
     */
  broadcast(name, detail = null, global = false) {
    this.#broadcast.postMessage({
      name,
      "detail": typeof detail?.toJSON === 'function' ? detail.toJSON() : detail,
      "scope": global ? null : WebComponent.#scopeId
    });
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
          node.setAttribute(attr, node[attr]); // node.src or node.href are already resolved by HTMLDocument.
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
    root.querySelectorAll('link').forEach((node) => {
      if (node.rel == 'stylesheet' && node.href) {
        promises.push(this.#waitForLoad(node, timeout));
      }
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
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        const error = new Error(`Resource ${element.href ?? element.src} load timeout after ${timeout}ms`);
        console.warn(error);
        reject(error);
      }, 10000);
      element.onload = () => {
        clearTimeout(timer); resolve();
      };
      element.onerror = reject;
    });
  }
}
