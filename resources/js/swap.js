import { slidingMarkers } from './marker';
import { toast } from './toast';

export const NAV_SAFE = ['/login', '/registrieren', '/logout'];

export const calmed = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 * A view transition makes a region swap read as one movement instead of a jump. It is opt-in by
 * capability and by preference: without startViewTransition, or with reduced motion asked for,
 * the swap happens exactly as it did before. The names are set per region, so only the parts
 * that actually changed animate — the rest of the page stays still.
 *
 * One transition at a time.
 *
 * `startViewTransition` throws `InvalidStateError` when one is already running, and the mutation
 * then never happens — the swap is simply lost. That became visible once a page could swap twice
 * in quick succession (the deferred content landing while a live form's response arrives): 47 of
 * those errors in one session, and a header that moved to the wrong place and stayed there.
 *
 * A swap that arrives mid-transition is applied WITHOUT one rather than queued. It is already the
 * newer state; animating it after the fact would animate from a frame nobody saw.
 */
let transitioning = false;

const withTransition = (mutate) => {
    if (! document.startViewTransition || calmed() || transitioning) {
        mutate();

        return;
    }

    /*
     * The sidebar is never named: WebKit captures it as a blank image, so a named nav vanished
     * for the length of every swap that touched it. Unnamed it belongs to the root, which does
     * not animate, and simply shows its new state.
     */
    document.querySelectorAll('[data-region]:not([data-region="nav"])').forEach((node) => {
        node.style.viewTransitionName = 'region-' + node.dataset.region;
    });

    transitioning = true;

    const transition = document.startViewTransition(mutate);

    /*
     * A transition skipped by the browser — because a cross-document one is still running, which
     * this flag cannot see — rejects `ready`. The swap itself still happens; only the animation is
     * dropped. Unhandled, that rejection was one console error per swap, and the error made the
     * real cause of a lost swap impossible to spot among them.
     */
    transition.ready.catch(() => {});
    transition.updateCallbackDone?.catch(() => {});

    transition.finished
        .catch(() => {})
        .finally(() => {
            transitioning = false;

            document.querySelectorAll('[data-region]').forEach((node) => {
                node.style.viewTransitionName = '';
            });

            delete document.documentElement.dataset.swapDirection;
        });
};

/**
 * Swaps the marked regions of the current page for the ones in `html`. With `only` given,
 * exactly those regions are replaced — that is how paging through the days leaves the
 * reviews alone instead of fetching them from GitHub again.
 *
 * Every module that has to look at the page again afterwards listens for `takt:swapped` on the
 * document. The event is fired INSIDE the mutation, after the fresh nodes are in place: with a
 * view transition running, code standing after the `swapRegions` call still sees the old DOM.
 */
export const swapRegions = (html, only = null) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    // decided before anything moves, so the caller still gets a synchronous yes or no
    const wanted = [...document.querySelectorAll('[data-region]')]
        .filter((node) => only === null || only.includes(node.dataset.region));

    /*
     * A region INSIDE another region is brought along by the outer swap and must not be swapped
     * again. `replaceWith` detaches its argument from wherever it currently sits, so replacing the
     * old inner node — which the outer swap already dropped out of the document — tears the fresh
     * inner node back OUT of the page that just received it. The ticket page has exactly this
     * shape (`ticket-board` lives inside `main`), and the symptom was the whole board vanishing
     * after a search: header updated, content gone.
     */
    const pairs = wanted
        .filter((node) => ! wanted.some((other) => other !== node && other.contains(node)))
        .map((node) => [node, doc.querySelector(`[data-region="${node.dataset.region}"]`)])
        .filter(([, fresh]) => fresh !== null);

    const swapped = pairs.length > 0;

    if (swapped) {
        // what actually changed, read before the swap so it can be pointed at afterwards
        const changed = pairs.flatMap(([node, fresh]) => {
            const before = [...node.querySelectorAll('.metric')].map((m) => m.textContent.trim());

            return [...fresh.querySelectorAll('.metric')]
                .map((metric, index) => (metric.textContent.trim() === before[index] ? null : metric))
                .filter(Boolean);
        });

        withTransition(() => {
            pairs.forEach(([node, fresh]) => {
                node.replaceWith(fresh);

                /*
                 * The entrance animation belongs to a page load; an in-place update only fades.
                 * Direct children are cleared inline, everything deeper by the `[data-entered]`
                 * rules — a card two levels down replayed its lift on every swap otherwise.
                 */
                fresh.querySelectorAll(':scope > *').forEach((child) => {
                    child.style.animation = 'none';
                });

                // stays: this region has entered, and will not enter again on a later swap either
                fresh.dataset.entered = '';
                fresh.dataset.swapped = '';
                requestAnimationFrame(() => requestAnimationFrame(() => delete fresh.dataset.swapped));
            });

            slidingMarkers();

            document.dispatchEvent(new CustomEvent('takt:swapped', {
                detail: { regions: pairs.map(([node]) => node.dataset.region) },
            }));
        });

        /*
         * A number that changed says so once. Without this a live update is silent: the value is
         * simply different afterwards and nothing tells the eye where to look.
         */
        if (! calmed()) {
            changed.forEach((metric) => {
                metric.dataset.changed = '';
                metric.addEventListener('animationend', () => delete metric.dataset.changed, { once: true });
            });
        }
    }

    const title = doc.querySelector('title')?.textContent;

    if (title) {
        document.title = title;
    }

    const flash = doc.querySelector('[data-flash]')?.textContent?.trim();

    if (flash) {
        toast(flash);
    }

    return swapped;
};
