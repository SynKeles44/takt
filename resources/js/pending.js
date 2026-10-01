/**
 * The wait, made visible — but only when there is one.
 *
 * A ticket page reads Linear, GitHub and the local repositories before it renders, so on a cold
 * cache a click sits there doing nothing for a second or two. Showing a spinner immediately would
 * be worse than showing none: on the warm path the page is already there, and a marker that
 * flashes on every single navigation is noise that teaches the eye to ignore it.
 *
 * So it waits. Under the delay the navigation simply happens; over it, Takti turns up.
 */
const DELAY = 300;

const eligible = (link) => {
    if (! link || ! link.href || link.target === '_blank') return false;
    if (link.hasAttribute('download') || link.dataset.partial !== undefined) return false;
    if (link.origin !== location.origin) return false;

    const url = new URL(link.href);

    // a hash on the page you are already on is not a navigation
    if (url.pathname === location.pathname && url.search === location.search) return false;

    return ! /\.(csv|pdf|png|svg|zip|ics)$/i.test(url.pathname);
};

export function pendingMarker() {
    const marker = document.querySelector('[data-pending]');

    if (! marker) return;

    let timer = null;

    const show = () => {
        marker.hidden = false;
        /*
         * A forced reflow rather than requestAnimationFrame: the transition needs the element to
         * have been laid out at its start value before the class lands, and rAF does not run at
         * all while the tab is hidden — which is exactly when a slow navigation is most likely.
         */
        void marker.offsetWidth;
        marker.classList.add('pending-on');
    };

    const hide = () => {
        clearTimeout(timer);
        marker.classList.remove('pending-on');
        marker.hidden = true;
    };

    const arm = () => {
        clearTimeout(timer);
        timer = setTimeout(show, DELAY);
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        if (eligible(event.target.closest?.('a[href]'))) arm();
    });

    // a form that leaves the page is the same wait; one marked data-live stays put and is not
    document.addEventListener('submit', (event) => {
        if (! event.defaultPrevented && event.target.dataset?.live === undefined) arm();
    });

    /*
     * Coming back from the back/forward cache restores the document as it was — marker included,
     * if it was showing when the page left. `pageshow` is the only event that fires in that case.
     */
    addEventListener('pageshow', hide);
    addEventListener('pagereveal', hide);
    addEventListener('popstate', hide);
}
