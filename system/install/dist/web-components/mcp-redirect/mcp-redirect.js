import WebComponent from '/dist/zolinga-intl/js/web-component-intl.js';

/**
 * MCP redirect page content.
 *
 * Displays a friendly card explaining that the /mcp/ endpoint is an AI agent
 * gateway, not a human-facing page. Shown to browsers that stumble onto the
 * MCP URL; includes a link back to the home page.
 *
 * Uses WebComponentIntl so the HTML template is locale-aware:
 * when <html lang="cs-CZ"> the loader fetches mcp-redirect.cs-CZ.html
 * instead of the default mcp-redirect.html.
 *
 * @module system
 */
export default class McpRedirect extends WebComponent {
    constructor() {
        super();
        this.ready(this.#init());
    }

    async #init() {
        const root = await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: 'closed'
        });
        this.#replaceVars(root);
    }

    /**
     * Replace every element carrying a `var` attribute with the current value
     * of that variable. Keeps the markup domain-agnostic so the same template
     * works on any host name.
     *
     * Supported variables:
     * - domain: current host name including port, e.g. "v2.ipdefender.eu"
     *
     * @param {ShadowRoot|HTMLElement|null} root loaded template root
     * @returns {void}
     */
    #replaceVars(root) {
        if (!root) return;

        const values = {
            domain: window.location.host
        };

        root.querySelectorAll('[var]').forEach((el) => {
            const name = el.getAttribute('var');
            if (Object.hasOwn(values, name)) {
                el.textContent = values[name];
            }
        });
    }
}