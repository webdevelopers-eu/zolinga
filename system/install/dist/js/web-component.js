// Just to keep loading ./web-components.js with single URL yet have cache busting.
const rev = document.documentElement.getAttribute('data-revision') || 'dev';
const moduleUrl = new URL('./web-component-element.js', import.meta.url);
moduleUrl.searchParams.set('rev', rev);

const { default: WebComponent } = await import(moduleUrl.href);
export default WebComponent;
