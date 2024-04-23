import api from '/dist/system/js/api.js';
import zTemplate from '/dist/system/js/z-template.js';
import WebComponent from '/dist/system/js/web-component.js';

class WikiArticle extends WebComponent {
    #main; // <main> content element inside a shadow root
    #baseURL;

    constructor() {
        super();
        this.#init();
        this.listen('wiki:article:show', (detail) => this.#loadArticle(detail.uri, true));
        this.#baseURL = document.querySelector('head base').href;
    }

    async #init() {
        await this.loadContent(new URL('wiki-article.html', import.meta.url), { mode: 'open' });
        this.#main = this.shadowRoot.querySelector('main');

        // Load the URL from the url bar - take last part of the path
        this.#loadArticleFromURL();
        // Listen to popstate events
        window.addEventListener('popstate', (e) => {
            this.#loadArticleFromURL();
        });

        this.listen('wiki:nav:update', (nav) => this.#updateNav(nav));

        // Hijack all links to wiki articles
        this.shadowRoot.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (!link) return;

            if (link.matches('a[href^="#"]')) {
                e.preventDefault();
                const id = link.getAttribute('href').substring(1);
                let el =
                    this.shadowRoot.querySelector('#' + id)
                    ||
                    Array.from(this.shadowRoot.querySelectorAll('h1, h2, h3, h4, h5, h6'))
                        .find((el) => el.textContent.trim().toLowerCase().replaceAll(" ", "-") === id);
                if (el) {
                    el.scrollIntoView({ behavior: 'smooth' });
                }
            } else if (link.matches('a[href^=":"]')) {
                e.preventDefault();
                const uri = link.getAttribute('href');
                // this.broadcast('wiki:article:show', { uri });
                this.#loadArticle(uri, true);
            }
        });
    }

    #updateNav(nav) {
        const next = this.shadowRoot.querySelector('li.wiki-nav-next a');
        next.textContent = nav.next.title || '';
        next.href = nav.next.uri || '';
        next.style.visibility = nav.next.title ? 'visible' : 'hidden';

        const prev = this.shadowRoot.querySelector('li.wiki-nav-prev a');
        prev.textContent = nav.prev.title || '';
        prev.href = nav.prev.uri || '';
        prev.style.visibility = nav.prev.title ? 'visible' : 'hidden';
    }

    #loadArticleFromURL() {
        const path = decodeURIComponent(location.pathname);
        const uri = path.match('/:') ? path.replace(/^.+?\/:/, ':') : ':'; // All after first colon
        this.#loadArticle(uri, false);
    }

    async #loadArticle(uri, updateHistory) {
        delete this.dataset.ready;

        let event;
        try {
            event = await api.dispatchEvent('wiki:article', { uri });
        } catch (e) {
            console.error(e);
            this.dataset.error = e.message;
            return;
        }

        if (event.ok) {
            document.title = event.response.title + ' - Zolinga Wiki';
            // Push to history URL event.response.uri
            // There is a <base> tag in the head of the document - we use relative paths.
            if (updateHistory) {
                const url = new URL(event.response.uri, this.#baseURL);
                console.log('Pushing to history', url.href);
                history.pushState({ uri: event.response.uri }, document.title, url.href);
            }
        } else {
            document.title = 'Error - Zolinga Wiki';
        }


        zTemplate(this.#main, {
            "ok": event.ok,
            "error": !event.ok,
            "message": event.message,
            "status": event.status,
            ...event.response
        });


        if (event.unauthorized || event.forbidden) {
            console.log('Unauthorized or forbidden', event);
            location.reload();
        }

        this.broadcast('wiki:article:loaded', { uri });
        this.dataset.ready = true;
    }
}

export default WikiArticle;