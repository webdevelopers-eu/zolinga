import api from '/dist/system/api.js';
import zTemplate from '/dist/system/lib/z-template.js';
import WebComponent from '/dist/system/lib/web-component.js';

class WikiToc extends WebComponent {
    clickingTimeouts = [];


    constructor() {
        super();
        this.#init();
    }

    async #init() {
        const root = await this.loadContent(new URL('wiki-toc.html', import.meta.url), { mode: 'seamless' });
        const event = await api.dispatchEvent('wiki:toc');

        this.recursiveTemplate = this.querySelector('#toc-recursive-template');
        this.#render(event.response.toc);
        this.listen('wiki:article:loaded', (detail) => this.#onArticleLoaded(detail.uri));

        // Listen on all events that clicked on element *[role~="wiki-toc-expand-all"]
        this.addEventListener('click', async (e) => {
            const supportsDoubleClick = e.target.matches('[role~="wiki-toc-link"]');

            this.clickingTimeouts.push(setTimeout(() => {
                // we assume that this is the oldest timeout in the stack so we remove it
                this.clickingTimeouts.shift();

                if (e.target.matches('[role~="wiki-toc-expand-all"]')) {
                    this.querySelectorAll('menu').forEach(m => {
                        m.classList.add('open');
                        m.classList.remove('close');
                    });
                } else if (e.target.matches('[role~="wiki-toc-collapse-all"]')) {
                    this.querySelectorAll('menu').forEach(m => {
                        m.classList.remove('open');
                        m.classList.add('close');
                    });
                } else if (e.target.matches('[role~="wiki-toc-toggle"]')) {
                    const el = Array.from(e.target.parentNode.children)
                        .find(el => el.localName == 'menu');
                    if (el) { // has submenu
                        el.classList.toggle('open');
                        el.classList.toggle('close', !el.classList.contains('open'));
                    }
                }
            }, supportsDoubleClick ? 200 : 0));

            e.preventDefault();
        });

        // Double click listener
        this.addEventListener('dblclick', (e) => {
            // Cancel last 2 single-clicks, cause it is a doubleclick
            clearTimeout(this.clickingTimeouts.pop());
            clearTimeout(this.clickingTimeouts.pop());

            if (e.target.matches('[role~="wiki-toc-link"]')) {
                this.#loadContent(e.target.getAttribute('wiki-uri'));
            }
        });

        this.dataset.ready = true;
    }

    async #loadContent(uri) {
        this.broadcast("wiki:article:show", { uri });
    }

    #onArticleLoaded(uri) {
        this.querySelectorAll('*.current').forEach(el => el.classList.remove('current'));
        const activeLink = Array.from(this.querySelectorAll('a')).find(a => a.getAttribute('wiki-uri') == uri);

        if (!activeLink) {
            console.log(`WIKI TOC: No link found for uri: ${uri}`);
            return;
        }

        activeLink.classList.add('current');
        this.querySelectorAll('a.active').forEach(a => a.classList.remove('active'));
        activeLink.classList.add('active');
        activeLink.scrollIntoView({ behavior: 'smooth', block: "center" });

        // Remove all parent .close classes
        (function* (el) { const c = el.closest('menu.close'); if (c) yield; })(activeLink)
            .forEach(el => el.classList.remove('close'));

        this.#updateNavigation(activeLink);
    }

    #updateNavigation(activeLink) {
        // Determine next/prev page and notify other widgets
        const prevAnchor = document.evaluate('(preceding::a)[last()]', activeLink, null, XPathResult.FIRST_ORDERED_NODE_TYPE).singleNodeValue;
        const nextAnchor = document.evaluate('following::a', activeLink, null, XPathResult.FIRST_ORDERED_NODE_TYPE).singleNodeValue;

        const prev = this.contains(prevAnchor) ? prevAnchor : null;
        const next = this.contains(nextAnchor) ? nextAnchor : null;

        const nav = {
            "current": {
                "uri": activeLink.getAttribute('wiki-uri'),
                "title": activeLink.textContent
            },
            "next": {
                "uri": next?.getAttribute('wiki-uri'),
                "title": next?.textContent
            },
            "prev": {
                "uri": prev?.getAttribute('wiki-uri'),
                "title": prev?.textContent
            }
        };

        this.broadcast('wiki:nav:update', nav);
    }

    #render(toc) {
        zTemplate(this, toc, {
            // Clones 
            'tocRecursion': (el) => {
                if (!el.querySelector(this.recursiveTemplate.tagName)) {
                    el.appendChild(this.recursiveTemplate.cloneNode(true));
                }
            }
        });
    }
}

export default WikiToc;