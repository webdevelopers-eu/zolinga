// Just to keep loading ./web-components.js with single URL yet have cache busting.
const rev = document.documentElement.getAttribute('data-revision') || 'dev';
const moduleUrl = new URL('./web-components-loader.js', import.meta.url);
moduleUrl.searchParams.set('rev', rev);

const { default: WebComponentLoader } = await import(moduleUrl.href);
export default WebComponentLoader;
