import api from '/dist/system/js/api.js';
import WebComponent from '/dist/system/js/web-component.js';
import zTemplate from '/dist/system/js/z-template.js';

class WikiSearch extends WebComponent {
    #input;
    #results;

    constructor() {
        super();
        this.#init();
    }

    async #init() {
        await this.loadContent(new URL('wiki-search.html', import.meta.url), { mode: 'seamless' });
        this.#input = this.querySelector('input[type="search"]');
        this.#results = this.querySelector('.search-results');

        this.#input.addEventListener('change', () => this.#search());
        this.#results.addEventListener('click', (event) => {
            const li = event.target.closest('li[data-uri]');
            if (li) {
                this.broadcast('wiki:article:show', { uri: li.dataset.uri });
                document.activeElement.blur();
            }
        });

        this.dataset.ready = true;
    }

    async #search() {
        this.#input.classList.add('searching');
        const event = await api.dispatchEvent('wiki:search', { search: this.#input.value });
        zTemplate(this, event.response);
        this.#input.classList.remove('searching');
    }

}

export default WikiSearch;