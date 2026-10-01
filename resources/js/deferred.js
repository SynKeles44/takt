/**
 * The second half of the deferred page: ask again, with the data this time.
 *
 * The server sent the layout and skeletons and marked the document with `data-defer`. This fetches
 * the same URL with the `X-Defer` header, which is the server's signal to do the slow read, and
 * swaps the regions in. The URL does not change and nothing is pushed onto the history — it is the
 * same page, arriving in two pieces.
 */
export function deferredRegions({ swapRegions }) {
    const load = () => {
        const url = document.documentElement.dataset.defer;

        if (! url) return;

        // cleared first: a swap that brings its own marker starts its own round, and without this
        // a failed fetch would be retried on every region swap for the rest of the session
        delete document.documentElement.dataset.defer;

        fetch(url, { headers: { 'X-Defer': '1', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
            .then((html) => {
                /*
                 * Set BEFORE the swap: the page is already standing, so the entry animations have
                 * played. Without this the swapped-in cards run `wb-rise` a second time and the
                 * header visibly hops 6px the moment the content lands — which reads as a bug,
                 * because from the reader's side it is one.
                 */
                document.documentElement.dataset.settled = '';

                swapRegions(html, ['main']);
                document.dispatchEvent(new CustomEvent('takt:deferred'));
            })
            .catch(() => {
                // the skeletons would sit there forever, so the plain page is the honest fallback
                window.location.reload();
            });
    };

    load();

    // a cross-document view transition keeps the document, so the next page needs its own round
    addEventListener('pagereveal', load);
    addEventListener('pageshow', load);
}
