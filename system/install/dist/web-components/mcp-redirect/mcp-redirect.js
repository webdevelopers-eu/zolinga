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
        await this.loadContent(import.meta.url.replace('.js', '.html'), {
            mode: 'closed'
        });
    }
}