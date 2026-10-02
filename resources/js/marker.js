/**
 * The highlight that travels to whatever is current.
 *
 * One model for every row in the app, and the model is the whole point: the marker is a child of
 * its row, parked at the row's top-left corner, and moved onto an item by translating it by that
 * item's OWN OFFSET INSIDE THE ROW. Offsets are what layout has already computed — `offsetLeft`,
 * `offsetTop`, `offsetWidth` — so every placement is an absolute statement about where the item
 * is, derived fresh, with nothing carried over from the placement before it.
 *
 * That is the rebuild. The previous version measured viewport rectangles and accumulated deltas
 * onto the marker's current transform, which is correct exactly as long as nothing underneath it
 * changes: an ancestor with a transform silently becomes the containing block, a resize moves
 * every rectangle at once, and a window narrow enough to re-stack the sidebar invalidates the
 * whole chain. It drifted, and the drift showed as a marker wider than the item it sat on,
 * hanging out of the sidebar. Offsets cannot drift — there is no previous value in them.
 *
 * Where the current item comes from is equally uniform: the row watches which of its items carries
 * the current-state (`aria-current`, `.segment-active`, `.nav-item-active`, `data-marked`) and
 * follows it. Nobody tells the marker to move. Whoever flips that state — a page load, the filter
 * code, a segmented control — gets the travel for free, and there is no second path to keep in
 * sync with the first.
 */

const DURATION = 340;
const HANDOVER_DURATION = 380;
const EASING = 'cubic-bezier(0.34, 1.4, 0.64, 1)';

/** A click starts a navigation; the position it had is handed to the page that answers. */
const HANDOVER = 'takt.marker.handover';

/** Four minutes of travel is never a navigation — a stale entry must not animate. */
const HANDOVER_MAX_AGE = 4000;

const calm = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Which item is the current one.
 *
 * Four spellings because the app already had four, and converting them all would be a bigger
 * change than the marker. `data-marked` is the one this module writes itself, for rows whose
 * current item is only expressed as a marker element in the server's HTML.
 */
const isCurrent = (item) => item.getAttribute('aria-current') === 'page'
    || item.classList.contains('segment-active')
    || item.classList.contains('nav-item-active')
    || item.dataset.marked !== undefined;

/**
 * Which item was current, in a form the next page can find again.
 *
 * Its position in the row, with the row's length beside it. Two earlier answers were wrong in the
 * same way — both encoded something that is allowed to differ between two pages. Viewport
 * coordinates failed because the development pages lay the same tab row out differently depending
 * on whether they carry a date stepper; the item's own link failed because the period links carry
 * the anchor date, and picking a different period changes it, so nothing on the next page matched.
 * A position is the one thing these rows agree on — and the length is the guard, because a row
 * that gained or lost an item would make a position mean something else.
 */
const store = (key, index, count) => {
    try {
        sessionStorage.setItem(HANDOVER, JSON.stringify({ key, index, count, at: Date.now() }));
    } catch {
        // private browsing, blocked storage: the marker simply appears in place
    }
};

const take = (key) => {
    let saved = null;

    try {
        saved = JSON.parse(sessionStorage.getItem(HANDOVER) ?? 'null');
    } catch {
        return null;
    }

    if (! saved || saved.key !== key) return null;

    try {
        sessionStorage.removeItem(HANDOVER);
    } catch { /* nothing to clean up */ }

    return Date.now() - saved.at > HANDOVER_MAX_AGE ? null : saved;
};

/** The rows wired on this page, so a region swap can drop the ones that left the document. */
const rows = new Set();

/** Names whose transition rules are already written; the rules outlive the rows that needed them. */
const ruled = new Set();

/**
 * Take a row out of the page transition, so it stays put while the content changes.
 *
 * This is what makes the travel visible at all. Without it a navigation row is part of the page
 * capture: the old page flies out carrying the old highlight, the new page flies in carrying the
 * new one, and the marker's own travel happens inside a card that is moving — so what you see is a
 * page change, not a highlight moving. Named, the row is its own transition group, stays where it
 * is, and the only thing that moves in it is the marker.
 *
 * The outgoing capture is dropped rather than cross-faded. A cross-document transition paints the
 * old capture over the new document, so for its length there were two rows stacked — the old one
 * still marking where you came from. Two marks at once, which is exactly how it was reported.
 */
const standStill = (host, key) => {
    if (! ('startViewTransition' in document)) return;

    // the sidebar's own wrapper is already named and already stands still; two nested names fight
    if (host.closest('.nav-aside')) return;

    const name = `takt-row-${key.replace(/[^a-z0-9-]/gi, '-')}`;

    /*
     * Two elements under one name abort the transition for the whole document, so a clash means
     * this row simply goes without. Asked of the rows still in the document rather than of a list
     * of names ever handed out — a region swap replaces a row with a fresh one, and that one needs
     * the name its predecessor just took with it.
     */
    if ([...rows].some((other) => other.isConnected && other.style.viewTransitionName === name)) return;

    // not in the app window: the system WebKit renders a named row blank for the whole transition
    if (document.documentElement.dataset.shell !== 'native') host.style.viewTransitionName = name;

    /*
     * The rule is written per name rather than through `view-transition-class`. The class exists
     * and is the obvious tool, and the measurement said it does not apply here — the outgoing
     * capture still computed to `display: block` under a `::view-transition-old(.takt-still)`
     * rule. A rule naming the group directly is not in doubt, and the names are generated here
     * anyway, so this is where it belongs.
     */
    if (ruled.has(name)) return;

    ruled.add(name);

    sheet().insertRule(`::view-transition-old(${name}) { display: none }`);
    sheet().insertRule(`::view-transition-new(${name}) { animation: none; mix-blend-mode: normal }`);
};

/** One stylesheet for the generated transition rules, created the first time one is needed. */
let generated = null;

const sheet = () => {
    if (! generated) {
        generated = new CSSStyleSheet();
        document.adoptedStyleSheets = [...document.adoptedStyleSheets, generated];
    }

    return generated;
};

const wire = (host, selector, markerClass, key) => {
    if (host.dataset.markerHost !== undefined) return;

    const items = () => [...host.querySelectorAll(selector)];
    const all = items();

    if (all.length === 0) return;

    /*
     * The server marks the current item by putting the marker element inside it. Read that before
     * the element moves — afterwards the only thing left saying which item it was is this flag.
     */
    const carrier = all.find((item) => item.querySelector(`.${markerClass}`));

    if (carrier && ! isCurrent(carrier)) {
        carrier.dataset.marked = '';
    }

    const marker = host.querySelector(`.${markerClass}`) ?? document.createElement('span');

    marker.className = markerClass;
    marker.setAttribute('aria-hidden', 'true');
    host.prepend(marker);

    host.dataset.markerHost = '';
    host.dataset.markerKey = key;

    /*
     * Positioned from here rather than from the stylesheet, because a marker anchored by a rule
     * that may or may not have shipped is a marker that silently does not move. Inline styles
     * cannot be missing.
     */
    Object.assign(marker.style, {
        position: 'absolute',
        insetBlockStart: '0px',
        insetInlineStart: '0px',
        insetBlockEnd: 'auto',
        insetInlineEnd: 'auto',
        opacity: '0',
    });

    // the row has to be the offset parent, or every number below is about something else
    if (getComputedStyle(host).position === 'static') {
        host.style.position = 'relative';
    }

    standStill(host, key);

    /** Where the marker is right now — mid-animation included, which is what makes interrupts work. */
    const read = () => {
        const matrix = new DOMMatrixReadOnly(getComputedStyle(marker).transform);

        return { x: matrix.e, y: matrix.f, w: marker.offsetWidth, h: marker.offsetHeight };
    };

    /**
     * Put the marker on an item.
     *
     * `offsetLeft` is measured from the border box of the offset parent while `inset-inline-start`
     * resolves against its padding box, so the row's own border width is the difference between
     * the two — `clientLeft` and `clientTop` are exactly that.
     */
    const place = (item, animated) => {
        if (! item || item.offsetWidth === 0) {
            marker.style.opacity = '0';

            return;
        }

        const to = {
            x: item.offsetLeft - host.clientLeft,
            y: item.offsetTop - host.clientTop,
            w: item.offsetWidth,
            h: item.offsetHeight,
        };

        const wanted = {
            transform: `translate(${to.x}px, ${to.y}px)`,
            width: `${to.w}px`,
            height: `${to.h}px`,
        };

        /*
         * Already there — do nothing at all, and in particular do not cancel what is running.
         *
         * This is load-bearing rather than an optimisation. A ResizeObserver delivers a first
         * callback as soon as it starts observing, and that callback arrives just after the
         * handover animation was started: without this the row would place itself on the item it
         * is already on, cancel the travel on the way, and the marker would appear in place on
         * every page load. Asking whether anything actually changed is also the honest form of the
         * question — the old code answered it by counting callbacks, which is only ever a guess
         * about what the first one means.
         */
        if (marker.style.opacity === '1'
            && marker.style.transform === wanted.transform
            && marker.style.width === wanted.width
            && marker.style.height === wanted.height) {
            return;
        }

        const from = animated && ! calm() && marker.style.opacity === '1' ? read() : null;

        marker.getAnimations().forEach((animation) => animation.cancel());

        Object.assign(marker.style, { opacity: '1', ...wanted });

        if (! from) return;

        travel(from, to, DURATION);
    };

    const travel = (from, to, duration) => marker.animate(
        [
            { transform: `translate(${from.x}px, ${from.y}px)`, width: `${from.w}px`, height: `${from.h}px` },
            { transform: `translate(${to.x}px, ${to.y}px)`, width: `${to.w}px`, height: `${to.h}px` },
        ],
        { duration, easing: EASING, fill: 'none' },
    );

    const current = () => items().find(isCurrent) ?? null;

    const settle = (animated) => place(current(), animated);

    settle(false);

    /*
     * The travel across a page load.
     *
     * Clicking a section starts the marker moving and starts a navigation at the same time, and
     * the navigation wins: these pages answer in tens of milliseconds, so the animation is thrown
     * away with the old document long before it is visible. So the click records which item WAS
     * current, the new page puts the marker there, and only then lets it travel to the item that
     * is current now. The movement spans the page load instead of being cut off by it.
     */
    const handover = take(key);

    if (handover !== null && ! calm()) {
        const list = items();
        const previous = list.length === handover.count ? list[handover.index] : null;
        const now = current();

        if (previous && now && previous !== now && now.offsetWidth > 0) {
            travel(
                {
                    x: previous.offsetLeft - host.clientLeft,
                    y: previous.offsetTop - host.clientTop,
                    w: previous.offsetWidth,
                    h: previous.offsetHeight,
                },
                {
                    x: now.offsetLeft - host.clientLeft,
                    y: now.offsetTop - host.clientTop,
                    w: now.offsetWidth,
                    h: now.offsetHeight,
                },
                HANDOVER_DURATION,
            );
        }
    }

    host.addEventListener('click', (event) => {
        /*
         * Asked of the row's own items rather than with `closest(selector)`.
         *
         * Most of these selectors are `:scope > a`, and `:scope` means nothing to `closest` — it
         * matched nothing, so for every row that used it no handover was ever stored and the
         * marker arrived already in place. The rows that did work were the two whose selector
         * happens to be a plain class, which is why the sidebar travelled and the tab rows did not.
         */
        const list = items();
        const item = list.find((candidate) => candidate.contains(event.target));
        const now = current();

        if (! item || ! now || item === now) return;

        store(key, list.indexOf(now), list.length);
    }, true);

    /*
     * Nobody calls the marker. It watches the state and follows — so the filter code, a segmented
     * control and a fresh page all get the same travel without knowing this module exists.
     */
    new MutationObserver(() => settle(true)).observe(host, {
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'aria-current', 'data-marked'],
    });

    /*
     * A row reflows: the window resizes, the sidebar collapses, a row inside a closed menu is
     * opened for the first time. The last one is why this asks about the layout rather than
     * counting callbacks — a hidden row gets no callback at all while it is hidden, so the one
     * that arrives when the menu opens is its first.
     */
    new ResizeObserver(() => settle(false)).observe(host);

    // web fonts land after the first paint and change every width in the row
    document.fonts?.ready.then(() => settle(false));

    rows.add(host);
};

export function slidingMarkers() {
    rows.forEach((host) => {
        if (! host.isConnected) rows.delete(host);
    });

    document.querySelectorAll('.nav-list').forEach((row) => wire(row, '.nav-item', 'nav-marker', 'nav'));
    document.querySelectorAll('[data-tab-row]').forEach((row) => wire(row, ':scope > a', 'tab-marker', 'tabs'));
    document.querySelectorAll('[data-subtab-row]').forEach((row) => wire(row, ':scope > a', 'subtab-marker', 'subtabs'));
    document.querySelectorAll('[data-period-row]').forEach((row) => wire(row, ':scope > a', 'tab-marker', 'period'));

    document.querySelectorAll('[data-marker-row]').forEach(
        (row) => wire(row, row.dataset.markerItem || ':scope > a', 'tab-marker', row.dataset.markerKey || 'row'),
    );

    /*
     * Segmented controls run on the same model. They used to have their own implementation in the
     * motion layer — a second marker, a second placement routine, a second handover — which is
     * one more thing to keep in step than there is any reason for.
     */
    document.querySelectorAll('.segmented').forEach((row) => {
        row.dataset.indicator = '';

        wire(row, '.segment', 'segment-marker', `seg:${row.dataset.markerKey || 'segments'}`);
    });
}
