import { swapRegions } from './swap';

/*
 * Links that only change part of the page: the day navigation in the development section
 * replaces the header and the commits, and leaves everything else — above all the reviews,
 * which come from GitHub — untouched.
 */
const partialLink = (url, regions, push = true) =>
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => (response.ok ? response.text() : Promise.reject()))
        .then((html) => {
            if (! swapRegions(html, regions)) return Promise.reject();

            if (push) window.history.pushState({ regions }, '', url);

            return true;
        });

/*
 * Which way the content should travel. A link that carries a date or a week says where it goes,
 * so paging forward slides the new content in from the right and back from the left — the same
 * reading direction the dates have.
 */
const direction = (href) => {
    const now = new URL(window.location.href).searchParams;
    const next = new URL(href, window.location.origin).searchParams;

    for (const key of ['tag', 'from', 'woche', 'monat', 'jahr']) {
        const a = now.get(key);
        const b = next.get(key);

        if (a && b && a !== b) return b > a ? 'forward' : 'back';
    }

    return null;
};

export function partialLinks() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-partial]');

        if (! link || event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank') return;

        event.preventDefault();

        const way = direction(link.href);

        if (way) document.documentElement.dataset.swapDirection = way;

        partialLink(link.href, link.dataset.partial.split(' ')).catch(() => {
            window.location.href = link.href;
        });
    });

    window.addEventListener('popstate', (event) => {
        const regions = event.state?.regions;

        if (! Array.isArray(regions)) return;

        partialLink(window.location.href, regions, false).catch(() => window.location.reload());
    });
}
