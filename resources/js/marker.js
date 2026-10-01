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
const wire = (host, selector, markerClass) => {
    if (host.dataset.markerHost !== undefined) return;

    const items = [...host.querySelectorAll(selector)];
    const active = items.find((item) => item.querySelector(`.${markerClass}`));

    if (! active) return;

    host.dataset.markerHost = '';

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

    move(host, active, false);

    // the row reflows on resize and when the sidebar collapses
    let settled = false;

    const observer = new ResizeObserver(() => {
        // the first callback arrives before the row has a layout; placing from it is placing from noise
        if (! settled) {
            settled = true;

            return;
        }

        move(host, items.find((item) => item.getAttribute('aria-current') === 'page') ?? active, false);
    });

    observer.observe(host);

    rows.push({ host, selector });
};

/** Every wired row, so one delegated listener can serve all of them. */
const rows = [];

/*
 * Delegated on the document rather than bound per row. A listener on the row itself did not fire
 * — and a marker whose movement depends on a binding that may not have taken is worse than no
 * marker. This is also the pattern the rest of this app uses, for the same reason: it survives a
 * region swap replacing the row underneath it.
 */
document.addEventListener('click', (event) => {
    const target = event.target;

    if (! target?.closest) return;

    rows.forEach(({ host, selector }) => {
        const item = target.closest(selector);

        if (item && host.contains(item)) {
            move(host, item, true);
        }
    });
}, true);

export function slidingMarkers() {
    document.querySelectorAll('.nav-list').forEach((row) => wire(row, '.nav-item', 'nav-marker'));

    document.querySelectorAll('[data-tab-row]').forEach((row) => wire(row, 'a', 'tab-marker'));
    document.querySelectorAll('[data-subtab-row]').forEach((row) => wire(row, 'a', 'subtab-marker'));
    document.querySelectorAll('[data-period-row]').forEach((row) => wire(row, 'a', 'tab-marker'));
}
