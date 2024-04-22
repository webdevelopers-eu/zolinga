import WebComponent from '/dist/system/js/web-component.js';
import api from '/dist/system/js/api.js';

export default class WikiLogin extends WebComponent {
    #form;

    constructor() {
        super();
        this.#init();
    }

    async #init() {
        this.#queryLogin();
        await this.loadContent(new URL('wiki-login.html', import.meta.url), { mode: 'seamless' });
        this.#form = this.querySelector('form');
        
        this.#form.password.addEventListener('input', () => {
            this.#form.password.setCustomValidity('');
        });

        this.#form.addEventListener('submit', async (ev) => {
            ev.preventDefault();
            if (!await this.#queryLogin(ev.target.password.value)) {
                this.#form.password.setCustomValidity('Invalid password. Try again.');
                this.#form.password.reportValidity();
                this.#form.password.focus();
            }
        });

        this.dataset.ready = true;
    }

    async #queryLogin(password) {
        this.dataset.show = "loading";
        const resp = await api.dispatchEvent('system:wiki:login', { password });

        if (resp.ok) {
            this.setAttribute('hidden', true);
            document.querySelectorAll('[disabled]').forEach(el => el.removeAttribute('disabled'));
            this.setAttribute('disabled', true);
        } else if (resp.unauthorized) {
            this.dataset.show = "form";
        } else {
            this.dataset.error = "Unexpected response from server. Please try again. Response: " + resp.status + " " + resp.message;
        }

        return resp.ok;
    }
}