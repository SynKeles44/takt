/*
 * The dashboard in edit mode, modelled on the iOS home screen: tiles wiggle, a minus badge
 * takes one off, a tile can be dragged to a new place, and the gallery on the right holds
 * what is not on the board. Nothing is stored while arranging: "Fertig" writes the whole
 * layout at once and pulls the page back in place, so a widget that was just pushed in stays
 * marked until exactly that moment.
 *
 * Every listener is bound to the document once, so a region swap never loses the wiring.
 */
export const createBoard = ({ swapRegions, toast }) => {
    const SPANS = [2, 3, 4, 6];

    let editing = false;
    let dirty = false;
    let drag = null;

    const root = () => document.querySelector('[data-board]');
    const grid = () => document.querySelector('[data-board-grid]');
    const slots = () => [...(grid()?.querySelectorAll('.widget-slot') ?? [])];

    const size = (slot, key) =>
        Number(slot.style.getPropertyValue(`--widget-${key}`)) || (key === 'span' ? 6 : 3);

    const layout = () => slots().map((slot) => ({
        widget: slot.dataset.widget,
        span: size(slot, 'span'),
        rows: size(slot, 'rows'),
    }));

    const put = () => fetch(root().dataset.arrangeUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ widgets: layout() }),
    });

    /** Everything stays local while arranging; this is the one moment it is written. */
    const commit = () => {
        if (! dirty) return;

        dirty = false;

        put()
            .then(() => fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }))
            .then((response) => response.text())
            .then((html) => {
                swapRegions(html);
                apply();
                toast(root()?.dataset.savedLabel ?? '');
            })
            .catch(() => {
                dirty = true;
            });
    };

    // MARK: FLIP — a tile that moved slides to its new place instead of jumping

    const rearrange = (change) => {
        const before = new Map(slots().map((slot) => [slot.dataset.widget, slot.getBoundingClientRect()]));

        change();
        dirty = true;

        slots().forEach((slot) => {
            const from = before.get(slot.dataset.widget);

            if (! from) return;

            const to = slot.getBoundingClientRect();
            const dx = from.left - to.left;
            const dy = from.top - to.top;

            if (Math.abs(dx) < 1 && Math.abs(dy) < 1) return;

            slot.animate(
                [{ transform: `translate(${dx}px, ${dy}px)` }, { transform: 'translate(0, 0)' }],
                { duration: 320, easing: 'cubic-bezier(0.34, 1.4, 0.64, 1)' },
            );
        });
    };

    // MARK: edit mode

    const apply = () => {
        const node = root();

        if (! node) return;

        node.dataset.board = editing ? 'editing' : '';
        node.querySelector('[data-board-badge]')?.classList.toggle('hidden', ! editing);
        node.querySelector('[data-board-reset]')?.classList.toggle('hidden', ! editing);
        node.querySelector('[data-board-drawer]')?.setAttribute('aria-hidden', editing ? 'false' : 'true');

        node.querySelectorAll('[data-board-toggle-label]').forEach((label) => {
            label.textContent = editing ? label.dataset.doneLabel : label.dataset.editLabel;
        });

        slots().forEach((slot) => {
            const index = SPANS.indexOf(size(slot, 'span'));

            slot.querySelectorAll('[data-widget-size]').forEach((button) => {
                const delta = Number(button.dataset.delta);

                button.disabled = delta < 0 ? index <= 0 : index >= SPANS.length - 1;
            });
        });
    };

    const setEditing = (value) => {
        editing = value;
        apply();

        // leaving the edit mode is the save
        if (! value) commit();
    };

    // MARK: dragging, with one ghost tile following the pointer

    const startDrag = (event, source, kind) => {
        const box = source.getBoundingClientRect();
        const ghost = source.cloneNode(true);

        // the clone must not look like a tile, or it would count as one
        ghost.classList.remove('widget-slot', 'board-chip');
        ghost.classList.add('board-ghost');
        ghost.style.width = `${box.width}px`;
        ghost.style.height = `${box.height}px`;
        ghost.style.left = `${box.left}px`;
        ghost.style.top = `${box.top}px`;
        ghost.querySelectorAll('.widget-tools').forEach((tools) => tools.remove());
        document.body.append(ghost);

        source.dataset.dragging = '';
        document.body.style.userSelect = 'none';

        drag = {
            kind,
            source,
            ghost,
            offsetX: event.clientX - box.left,
            offsetY: event.clientY - box.top,
            moved: false,
            dropBefore: null,
        };
    };

    const slotUnder = (x, y) => slots().find((slot) => {
        if (slot === drag?.source) return false;

        const box = slot.getBoundingClientRect();

        return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
    });

    const moveDrag = (event) => {
        if (! drag) return;

        drag.moved = true;
        drag.ghost.style.left = `${event.clientX - drag.offsetX}px`;
        drag.ghost.style.top = `${event.clientY - drag.offsetY}px`;

        const target = slotUnder(event.clientX, event.clientY);

        if (! target) return;

        const box = target.getBoundingClientRect();
        const after = event.clientX > box.left + box.width / 2;
        const reference = after ? target.nextElementSibling : target;

        if (drag.kind === 'slot') {
            if (reference === drag.source) return;

            rearrange(() => grid().insertBefore(drag.source, reference));
        } else {
            drag.dropBefore = reference;
        }
    };

    const endDrag = (event) => {
        if (! drag) return;

        const { kind, source, ghost, moved, dropBefore } = drag;

        ghost.remove();
        delete source.dataset.dragging;
        document.body.style.userSelect = '';
        drag = null;

        if (kind !== 'chip' || ! moved) return;

        const box = grid().getBoundingClientRect();
        const inside = event.clientX >= box.left && event.clientX <= box.right
            && event.clientY >= box.top && event.clientY <= box.bottom;

        if (inside) addWidget(source, dropBefore);
    };

    /** A gallery entry becomes a real tile: put it in place, then let the server fill it. */
    const addWidget = (chip, before = null) => {
        const slot = document.createElement('div');

        slot.className = 'widget-slot';
        slot.dataset.widget = chip.dataset.addWidget;
        slot.dataset.fresh = 'pending';
        slot.style.setProperty('--widget-span', chip.dataset.span);
        slot.style.setProperty('--widget-rows', chip.dataset.rows);
        const label = chip.querySelector('span span')?.textContent?.trim() ?? '';

        slot.innerHTML = `<div class="widget-body"><div class="card grid h-full place-items-center">
            <span class="text-xs text-faint">${label}</span>
        </div></div>`;

        rearrange(() => {
            grid().insertBefore(slot, before);
            chip.remove();
        });
    };

    /** A removed tile goes straight back into the gallery, so it can come right back. */
    const returnToGallery = (slot) => {
        const list = document.querySelector('[data-drawer-list]');

        if (! list) return;

        const chip = document.createElement('button');

        chip.type = 'button';
        chip.className = 'board-chip';
        chip.dataset.addWidget = slot.dataset.widget;
        chip.dataset.span = String(size(slot, 'span'));
        chip.dataset.rows = String(size(slot, 'rows'));
        chip.innerHTML = `
            <svg class="size-3.5 shrink-0 text-accent-text" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 6v12"/><path d="M6 12h12"/></svg>
            <span class="min-w-0"><span class="block truncate text-xs font-semibold text-ink">${slot.dataset.label ?? ''}</span></span>
        `;

        list.append(chip);
    };

    // MARK: wiring, once

    document.addEventListener('click', (event) => {
        if (! root()) return;

        if (event.target.closest('[data-board-toggle]')) {
            setEditing(! editing);

            return;
        }

        if (event.target.closest('[data-board-close]')) {
            setEditing(false);

            return;
        }

        const remove = event.target.closest('[data-widget-remove]');

        if (remove) {
            const slot = remove.closest('.widget-slot');

            returnToGallery(slot);
            rearrange(() => slot.remove());

            return;
        }

        const step = event.target.closest('[data-widget-size]');

        if (step) {
            const slot = step.closest('.widget-slot');
            const delta = Number(step.dataset.delta);

            rearrange(() => {
                const index = SPANS.indexOf(size(slot, 'span'));

                slot.style.setProperty('--widget-span', String(SPANS[Math.min(SPANS.length - 1, Math.max(0, index + delta))]));
            });

            apply();

            return;
        }

        const chip = event.target.closest('[data-add-widget]');

        if (chip) addWidget(chip);
    });

    document.addEventListener('pointerdown', (event) => {
        if (! root()) return;

        const chip = event.target.closest('[data-add-widget]');

        if (chip) {
            event.preventDefault();
            startDrag(event, chip, 'chip');

            return;
        }

        if (! editing) return;

        const slot = event.target.closest('.widget-slot');

        if (! slot || event.target.closest('[data-widget-remove], [data-widget-size]')) return;

        event.preventDefault();
        startDrag(event, slot, 'slot');
    });

    window.addEventListener('pointermove', moveDrag);
    window.addEventListener('pointerup', endDrag);
    window.addEventListener('pointercancel', endDrag);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && editing) setEditing(false);
    });

    // leaving the page with an unsaved arrangement still writes it, without holding it up
    window.addEventListener('pagehide', () => {
        if (! dirty || ! root()) return;

        dirty = false;

        fetch(root().dataset.arrangeUrl, {
            method: 'PUT',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ widgets: layout() }),
        }).catch(() => {});
    });

    return { apply };
};
