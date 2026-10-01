/**
 * The sliding highlight behind a navigation row.
 *
 * One marker per row, moved to whichever item is current — and moved on the CLICK, before the next
 * page has even been requested. That ordering is the whole point: the feedback belongs to the
 * moment you decide, not to the moment the document arrives.
 *
 * Built by hand rather than through a view transition, which was tried first: naming the marker
 * so the browser could morph it between documents aborted the page transition outright.
 */
/*
 * Move the marker onto a target.
 *
 * The offset is measured against the marker's OWN current position, not against the row's box.
 * Computing `target.left - row.left` looks right and is not: an absolutely positioned element
 * resolves against its containing block, and any ancestor carrying a transform, a filter or a
 * backdrop-filter silently becomes that block instead of the row. Measured here: the row started
 * at 11px, the target at 11px, and translate(0,0) put the marker at 53px.
 *
 * Measuring the delta from where the marker actually IS has no such assumption in it.
 */
const current = new WeakMap();

/**
 * Put the marker on a target.
 *
 * Animated calls add to the offset the marker already carries; unanimated ones throw that offset
 * away and measure from zero. The difference matters: a ResizeObserver fires once as soon as it
 * starts observing — before the row has been laid out — and an accumulating call at that moment
 * adds a bogus delta that never comes back. That is how the highlight ended up parked below the
 * last item instead of behind the current one.
 */
const move = (host, target, animated) => {
    const marker = host.querySelector('[data-marker]');

    if (! marker || ! target) return;

    const box = target.getBoundingClientRect();

    if (box.width === 0) return;

    marker.style.width = `${box.width}px`;
    marker.style.height = `${box.height}px`;

    if (! animated) {
        // zero it, let the browser place it, then measure the real distance once
        const previous = marker.style.transition;

        marker.style.transition = 'none';
        marker.style.transform = 'translate(0px, 0px)';
        marker.offsetHeight;

        const base = marker.getBoundingClientRect();
        const next = { x: box.left - base.left, y: box.top - base.top };

        current.set(marker, next);
        marker.style.transform = `translate(${next.x}px, ${next.y}px)`;
        marker.offsetHeight;
        marker.style.transition = previous;

        return;
    }

    const at = current.get(marker) ?? { x: 0, y: 0 };
    const now = marker.getBoundingClientRect();
    const next = { x: at.x + (box.left - now.left), y: at.y + (box.top - now.top) };

    current.set(marker, next);

    const from = marker.style.transform || 'translate(0px, 0px)';
    const to = `translate(${next.x}px, ${next.y}px)`;

    marker.style.transform = to;

    marker.animate(
        [{ transform: from }, { transform: to }],
        { duration: 340, easing: 'cubic-bezier(0.34, 1.4, 0.64, 1)', fill: 'none' },
    );
};

/**
 * Turn a row into one that carries a travelling marker.
 *
 * `selector` finds the items, `current` the one that is active. The marker element is taken out of
 * the active item and re-parented onto the row, because a marker that lives inside an item can
 * only ever be as wide as that item.
 */
const wire = (host, selector, markerClass, key) => {
    if (host.dataset.markerHost !== undefined) return;

    const items = [...host.querySelectorAll(selector)];
    const active = items.find((item) => item.querySelector(`.${markerClass}`));

    if (! active) return;

    host.dataset.markerHost = '';
    host.dataset.markerKey = key;

    const marker = active.querySelector(`.${markerClass}`);

    marker.dataset.marker = '';
    host.prepend(marker);

    /*
     * Positioned from here rather than from the stylesheet. The rule that did this lived in CSS
     * and simply was not in the loaded sheet — and a marker anchored by a rule that may or may not
     * have shipped is a marker that silently does not move. Inline styles cannot be missing.
     */
    Object.assign(marker.style, {
        position: 'absolute',
        insetBlockStart: '0px',
        insetInlineStart: '0px',
        insetBlockEnd: 'auto',
        insetInlineEnd: 'auto',
    });

    // the row has to be the containing block, or the offsets mean nothing
    if (getComputedStyle(host).position === 'static') {
        host.style.position = 'relative';
    }

    const from = handover(host);

    if (from) {
        /*
         * Put the marker where it stood on the previous page, in that page's coordinates, and let
         * it find its way to the current item. The jump to the old spot is instant and invisible;
         * what is seen is the travel from there.
         */
        move(host, active, false);

        const now = marker.getBoundingClientRect();
        const at = current.get(marker) ?? { x: 0, y: 0 };

        marker.animate(
            [
                { transform: `translate(${at.x + (from.left - now.left)}px, ${at.y + (from.top - now.top)}px)` },
                { transform: `translate(${at.x}px, ${at.y}px)` },
            ],
            { duration: 380, easing: 'cubic-bezier(0.34, 1.4, 0.64, 1)', fill: 'none' },
        );
    } else {
        move(host, active, false);
    }

    /*
     * The row reflows on resize, when the sidebar collapses, and when a row that started inside a
     * closed menu is opened for the first time.
     *
     * The test is the row's own width, not the callback's ordinal. Skipping the first callback was
     * the earlier guard against placing before there is a layout — and it broke exactly the menu
     * case: a row inside `display: none` gets no callback at all while it is hidden, so the one
     * that arrives when the menu opens IS the first one, and it was the one being thrown away.
     * Measured: the account menu's marker stayed 0 pixels wide and never appeared. Asking about the
     * layout directly covers both, and an extra unanimated placement is free — it measures from
     * zero every time rather than adding to what is already there.
     */
    const observer = new ResizeObserver(() => {
        if (host.getBoundingClientRect().width === 0) return;

        move(host, items.find((item) => item.getAttribute('aria-current') === 'page') ?? active, false);
    });

    observer.observe(host);

    /*
     * A date navigator is the case where the item you click is not reliably the item that ends up
     * marked: stepping forward out of the past can land in the past again. Moving the marker on
     * the press would then send it right and the next page would send it back — so these rows opt
     * out of the optimistic move and only hand their position over. The pages answer in tens of
     * milliseconds; the travel starts on the next document instead of this one, and it is always
     * the travel that actually happened.
     */
    // a region swap replaces whole rows; the ones that left the document are no longer anybody's
    for (let i = rows.length - 1; i >= 0; i--) {
        if (! rows[i].host.isConnected) rows.splice(i, 1);
    }

    rows.push({ host, selector, optimistic: host.dataset.markerOptimistic !== 'false' });
};

/** Every wired row, so one delegated listener can serve all of them. */
const rows = [];

/*
 * The handover across a page load.
 *
 * This is the part three attempts missed. Clicking a section starts the marker travelling and
 * starts a navigation at the same time — and the navigation wins: these pages answer in 10 to 90
 * milliseconds, so a 340 ms animation is thrown away with the old document long before it is
 * visible. Every measurement said the marker moved; nobody could ever see it.
 *
 * So the position is handed to the next page instead. The click records where the marker is in
 * viewport coordinates, the new document puts it back there, and only then lets it travel to the
 * item that is now current. The movement spans the page load rather than being cut off by it.
 */
const HANDOVER = 'takt.marker.from';

/**
 * Record where a marker stands, under the key of the row it belongs to.
 *
 * Exported because the segmented controls carry their own marker, built in `motion.js` with its
 * own geometry — and a second storage format for the same idea is a second thing to get wrong.
 * They hand over through exactly this entry.
 */
export const rememberMarkerPosition = (key, box) => {
    try {
        sessionStorage.setItem(HANDOVER, JSON.stringify({
            key,
            top: box.top,
            left: box.left,
            width: box.width,
            height: box.height,
            at: Date.now(),
        }));
    } catch {
        // private browsing, blocked storage: the marker simply appears in place
    }
};

/**
 * Take the handover meant for this key, if there is one. Reading it consumes it.
 *
 * Only the row the handover belongs to consumes it. Rows are wired in order, and the sidebar goes
 * first — it used to clear the entry on its way past, so a click on a tab row handed its position
 * to a row that then threw it away. The tab row found nothing and appeared in place.
 */
export const takeMarkerHandover = (key) => {
    let saved = null;

    try {
        saved = JSON.parse(sessionStorage.getItem(HANDOVER) ?? 'null');
    } catch {
        return null;
    }

    // a stale entry — a reload, a back button, a tab opened an hour ago — must not animate
    if (! saved || Date.now() - saved.at > 4000) {
        try {
            sessionStorage.removeItem(HANDOVER);
        } catch { /* nothing to clean up */ }

        return null;
    }

    if (saved.key !== key) {
        return null;
    }

    try {
        sessionStorage.removeItem(HANDOVER);
    } catch { /* nothing to clean up */ }

    return saved;
};

const remember = (host) => {
    const marker = host.querySelector('[data-marker]');

    if (! marker) return;

    rememberMarkerPosition(host.dataset.markerKey, marker.getBoundingClientRect());
};

const handover = (host) => takeMarkerHandover(host.dataset.markerKey);

/*
 * Delegated on the document rather than bound per row. A listener on the row itself did not fire
 * — and a marker whose movement depends on a binding that may not have taken is worse than no
 * marker. This is also the pattern the rest of this app uses, for the same reason: it survives a
 * region swap replacing the row underneath it.
 */
document.addEventListener('click', (event) => {
    const target = event.target;

    if (! target?.closest) return;

    rows.forEach(({ host, selector, optimistic }) => {
        const item = target.closest(selector);

        if (item && host.contains(item)) {
            // hand the position to the next document first, then move for the same-page case
            remember(host);

            if (optimistic) move(host, item, true);
        }
    });
}, true);

export function slidingMarkers() {
    document.querySelectorAll('.nav-list').forEach((row) => wire(row, '.nav-item', 'nav-marker', 'nav'));

    document.querySelectorAll('[data-tab-row]').forEach((row) => wire(row, 'a', 'tab-marker', 'tabs'));
    document.querySelectorAll('[data-subtab-row]').forEach((row) => wire(row, 'a', 'subtab-marker', 'subtabs'));
    document.querySelectorAll('[data-period-row]').forEach((row) => wire(row, 'a', 'tab-marker', 'period'));

    /*
     * The open hook. Three rows above are named one by one because they predate it; anything else
     * in the app that wants a travelling highlight says so in its own markup instead of being
     * added to this list — which is the difference between a row that was remembered and a row
     * that cannot be forgotten.
     */
    document.querySelectorAll('[data-marker-row]').forEach(
        (row) => wire(row, row.dataset.markerItem || 'a', 'tab-marker', row.dataset.markerKey || 'row'),
    );
}
