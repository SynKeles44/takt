import { rememberBlocks } from './filters';

/*
 * The review sections arrive after the page: fetching them from GitHub costs more than a
 * second, and the rest of the development page has no reason to wait for it. A swap that
 * brings a fresh, empty slot starts the fetch again; a swap that leaves the slot alone does not.
 */
const load = () => {
    const slot = document.querySelector('[data-reviews-slot]:not([data-loaded])');

    if (! slot || slot.querySelector('[data-review-sections]')) return;

    fetch(slot.dataset.reviewsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => (response.ok ? response.text() : Promise.reject()))
        .then((html) => {
            slot.innerHTML = html;
            slot.dataset.loaded = '';
            rememberBlocks(slot);
        })
        .catch(() => {});
};

export function reviews() {
    load();
    document.addEventListener('takt:swapped', load);
}
