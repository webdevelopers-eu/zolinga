import api from '/dist/system/js/api.js';
import zTemplate from '/dist/system/js/z-template.js';
import WebComponent from '/dist/system/js/web-component.js';

class WikiToc extends WebComponent {
    clickingTimeouts = [];
    
    
    constructor() {
        super();
        this.#init();
    }
    
    async #init() {
        const root = await this.loadContent(new URL('wiki-toc.html', import.meta.url), { mode: 'seamless' });
        const event = await api.dispatchEvent('wiki:toc');
        
        this.recursiveTemplate = this.querySelector('#toc-recursive-template').cloneNode(true);
        this.#render(event.response.toc);
        this.listen('wiki:article:loaded', (detail) => this.#onArticleLoaded(detail.uri));
        
        
        // Double click listener
        this.addEventListener('click', (e) => {
            if (e.target.matches('[role~="wiki-toc-expand-all"]')) {
                this.querySelectorAll('menu').forEach(m => this.#toggleMenu(m, true));
                return;
            } else if (e.target.matches('[role~="wiki-toc-collapse-all"]')) {
                this.querySelectorAll('menu').forEach(m => this.#toggleMenu(m, false));
                return;
            } else if (e.target.matches('li')) {
                this.#toggleMenu(e.target.querySelector(':scope > menu'));
            }
            
            if (e.target.matches('[role~="wiki-toc-link"]')) {
                this.#loadContent(e.target.getAttribute('wiki-uri'));
            }
            
            e.preventDefault();
        });
        
        this.dataset.ready = true;
    }
    
    #toggleMenu(menu, state, openParents = true) {
        if (menu && menu.matches('menu')) {
            menu.classList.toggle('open', state);
            state = this.#getMenuState(menu); // in case state was undefined
            menu.classList.toggle('close', !state);

            if (openParents && state) { // open parents
                let parent = menu;
                do {
                    parent = parent.closest('li:not(:has(> menu.open))')?.querySelector(':scope > menu');
                    if (parent && !this.#getMenuState(parent)) {
                        this.#toggleMenu(parent, true, false);
                    }
                } while (parent);
            }
        }
    }

    #getMenuState(menu) {
        return menu.classList.contains('open');
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
                
        this.#updateNavigation(activeLink);
    }
    
    #updateNavigation(activeLink) {
        // Determine next/prev page and notify other widgets
        const prevAnchor = document.evaluate('(preceding::a)[last()]', activeLink, null, XPathResult.FIRST_ORDERED_NODE_TYPE).singleNodeValue;
        const nextAnchor = document.evaluate('following::a', activeLink, null, XPathResult.FIRST_ORDERED_NODE_TYPE).singleNodeValue;
        
        const prev = this.contains(prevAnchor) ? prevAnchor : null;
        const next = this.contains(nextAnchor) ? nextAnchor : null;

        this.#toggleMenu(activeLink.closest('li').querySelector(':scope > menu'), true, true);

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
