import api from '/dist/system/api.js';
import WebComponent from '/dist/system/lib/web-component.js';

export default class WikiToc extends WebComponent {

    constructor() {
        super();
        this.#init();
    }

    async #init() {
        const root = await this.loadContent(new URL('example-acme.html', import.meta.url), {mode: 'seamless' }); // embed HTML
        const event = await api.dispatchEvent('example:acme'); // request server data
        this.dataset.ready = true; // stop loading animation
    }
}
