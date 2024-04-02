class WikiColor extends HTMLElement {
    #input;

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this.#input = this.shadowRoot.appendChild(document.createElement('input'));
        this.#input.type = 'color';
        
        // Make the input a circle
        this.#input.style.display = 'block';
        this.#input.style.opacity = 0;
        this.#input.style.width = '100%';
        this.#input.style.height = '100%';
        this.#input.style.cursor = 'pointer';


        this.style.backgroundColor = `rgb(var(--${this.name}, #f3f3f3))`;
        this.style.display = 'inline-block';
        this.style.width = '1.2em';
        this.style.height = '1.2em';
        this.style.borderRadius = '50%';
        this.style.position = 'relative';
        this.style.cursor = 'pointer';
        this.style.border = '0.2em solid color-mix(in oklab, rgb(var(--color-bg, 243, 243, 243)), rgb(var(--color-primary, 52, 153, 200)))';

        // Restore previous if saved
        if (!this.#restore()) {
            this.value = this.#getVariableColor();
        }

        this.#input.addEventListener('input', () => this.#onChange());
        this.#input.addEventListener('change', () => this.#onChange());

        this.dataset.ready = true;
    }

    #restore() {
        const val = window.localStorage.getItem(`wiki:color:${this.name}`);

        if (val) {
            this.value = val;
            this.#setVariableColor();
        }

        return val;
    }

    #onChange() {
        this.#setVariableColor();
    }

    get name() {
        return this.getAttribute('name');
    }

    set name(value) {
        this.setAttribute('name', value);
    }

    get value() {
        return this.#input.value;
    }

    set value(value) {
        this.#input.value = value;
    }

    reset() {
        document.documentElement.style.removeProperty(`--${this.name}`);
        window.localStorage.removeItem(`wiki:color:${this.name}`);
    }

    #getVariableColor() {
        const val = getComputedStyle(document.documentElement).getPropertyValue(`--${this.name}`);
        const [r, g, b] = val.split(',').map(v => parseInt(v, 10).toString(16).padStart(2, '0'));

        return `#${r}${g}${b}`;
    }

    #setVariableColor() {
        const [r, g, b] = this.value.match(/[0-9a-z]{2}/gi).map(v => parseInt(v, 16));
        document.documentElement.style.setProperty(`--${this.name}`, `${r}, ${g}, ${b}`);
        // Persist to local storage
        window.localStorage.setItem(`wiki:color:${this.name}`, this.value);
    }
}

export default WikiColor;