/**
 * Fetch the next page before it is asked for.
 *
 * The transition makes a navigation look smooth; this is what makes it BE quick. A pointer resting
 * on a link is a reliable signal — measured across the web, the gap between hover and click is
 * usually a few hundred milliseconds, which is most of what these pages need to render.
 *
 * Deliberately modest: same origin only, GET only, once per URL, and never on a slow or metered
 * connection. A prefetcher that guesses wrong costs the user bandwidth for nothing.
 */
const seen = new Set();

const eligible = (link) => {
    if (! link || ! link.href || link.target === '_blank') return false;
    if (link.hasAttribute('download') || link.dataset.partial !== undefined) return false;
    if (link.origin !== location.origin) return false;

    const url = new URL(link.href);

    // a hash on the current page is not a navigation
    if (url.pathname === location.pathname && url.search === location.search) return false;

    // printable views and downloads are not worth pulling in the background
    return ! /\.(csv|pdf|png|svg|zip|ics)$/i.test(url.pathname);
};

const frugal = () => {
    const net = navigator.connection;

    if (! net) return false;

    return net.saveData === true || /(^|-)2g$/.test(net.effectiveType ?? '');
};

export function prefetchLinks() {
    if (frugal()) return;

    let timer = null;

    const warm = (link) => {
        const href = link.href;

        if (seen.has(href) || ! eligible(link)) return;

        seen.add(href);

        const tag = document.createElement('link');

        tag.rel = 'prefetch';
        tag.href = href;
        tag.as = 'document';
        document.head.append(tag);
    };

    document.addEventListener('pointerover', (event) => {
        const link = event.target.closest?.('a[href]');

        if (! link) return;

        // a pointer passing over a list of links should not pull every one of them
        clearTimeout(timer);
        timer = setTimeout(() => warm(link), 90);
    }, { passive: true });

    document.addEventListener('pointerout', () => clearTimeout(timer), { passive: true });

    // touch has no hover, so the press itself is the signal — it still buys the tap delay
    document.addEventListener('touchstart', (event) => {
        const link = event.target.closest?.('a[href]');

        if (link) warm(link);
    }, { passive: true });
}
