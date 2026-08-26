/*
 * The dashboard in edit mode, modelled on the iOS home screen: tiles wiggle, a minus badge
 * takes one off, a tile can be dragged to a new place, and the gallery on the right holds
 * what is not on the board. Nothing is stored while arranging: "Fertig" writes the whole
 * layout at once, the x next to the gallery throws the changes away, and a widget that was
 * just pushed in shows its real content — fetched on its own — while it stays marked as new.
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

    /** Pulls the page back in place, so the board shows exactly what the server has. */
    const reload = () => fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.text())
        .then((html) => {
            swapRegions(html);

            // the tiles are new nodes after the swap; letting them fly in again looks like
            // the page rebuilt itself, which is exactly what it should not look like
            slots().forEach((slot) => {
                slot.style.animation = 'none';
            });

            apply();
        });

    /** Everything stays local while arranging; this is the one moment it is written. */
    const commit = () => {
        if (! dirty) return;

        const label = root()?.dataset.savedLabel ?? '';

        dirty = false;

        put()
            .then(reload)
            .then(() => toast(label))
            .catch(() => {
                dirty = true;
            });
    };

    /** The x throws the arrangement away — nothing was stored, so the server still knows. */
    const discard = () => {
        const label = root()?.dataset.discardedLabel ?? '';
        const changed = dirty;

        dirty = false;
        editing = false;

        // leave the mode at once — waiting for the server keeps the wobble on screen
        apply();

        if (! changed) return;

        reload().then(() => toast(label));
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

        if (value) filterGallery(); else hidePeek();

        // leaving the edit mode is the save
        if (! value) commit();
    };

    // MARK: dragging, with one ghost tile following the pointer

    const startDrag = (event, source, kind) => {
        const box = source.getBoundingClientRect();
        const ghost = source.cloneNode(true);

        // the clone must not look like a tile, or it would count as one
        ghost.classList.remove('widget-slot', 'board-chip', 'gallery-card');
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

        const label = chip.dataset.label ?? chip.querySelector('span span')?.textContent?.trim() ?? '';
        const preview = chip.querySelector('[data-preview-scale]');
        const seen = preview && ! preview.querySelector('.gallery-skeleton') ? preview.innerHTML : '';

        slot.dataset.label = label;

        // the card the pointer just left becomes the tile: same markup, so nothing flashes
        slot.innerHTML = seen !== ''
            ? `<div class="widget-body">${seen}</div>`
            : `<div class="widget-body"><div class="card grid h-full place-items-center">
                <span class="text-xs text-faint">${label}</span>
            </div></div>`;

        rearrange(() => {
            grid().insertBefore(slot, before);
            chip.remove();
        });

        fill(slot);
    };

    /** The widget's own markup, rendered by the server without storing anything. */
    const fill = (slot) => {
        const template = root()?.dataset.widgetUrl;

        if (! template) return;

        fetch(template.replace('__widget__', slot.dataset.widget), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((html) => {
                const body = slot.querySelector('.widget-body');

                if (! body) return;

                body.innerHTML = html;
                body.querySelectorAll('[aria-hidden="true"][tabindex="-1"]').forEach((node) => {
                    node.removeAttribute('aria-hidden');
                    node.removeAttribute('tabindex');
                });
                apply();
            })
            .catch(() => {});
    };

    // MARK: gallery

    /*
     * The cards show a schematic of the tile — a scaled-down copy of the real widget is
     * unreadable at gallery width. Pointing at a card opens the peek: the widget itself,
     * rendered at the width its span gets on the board and scaled into the panel. That keeps
     * the list scannable and still answers "what will this actually look like".
     */
    const BOARD_ROW = 80;
    const BOARD_GAP = 20;

    const peeked = new Map();
    let peekTimer = null;
    let scrolledAt = 0;

    const peek = () => document.querySelector('[data-gallery-peek]');

    const boardWidth = () => {
        const width = grid()?.getBoundingClientRect().width ?? 0;

        return width > 0 ? width : 1080;
    };

    const renderSize = (span, rows) => {
        const columns = (boardWidth() - BOARD_GAP * 5) / 6;

        return {
            width: Math.max(240, columns * span + BOARD_GAP * (span - 1)),
            height: rows * BOARD_ROW + BOARD_GAP * (rows - 1),
        };
    };

    /*
     * The frame is sized to what the widget actually renders, not to the height its rows
     * reserve on the board — a tile that needs less leaves the rest of the frame empty, which
     * is what made the peek look broken. The board height stays the cap.
     */
    const fitStage = (span, rows) => {
        const panel = peek();
        const stage = panel?.querySelector('[data-peek-stage]');

        if (! stage) return;

        const size = renderSize(span, rows);

        /*
         * The room is computed, not measured: the panel is sized to the scaled widget below,
         * so measuring the panel to scale its own content would chase its own tail.
         */
        const available = Math.min(480, window.innerWidth * 0.42) - 28;

        if (available <= 0) return;

        const factor = Math.min(1, available / size.width);
        const content = stage.scrollHeight || size.height;
        const width = Math.round(size.width * factor);

        stage.style.setProperty('--peek-width', `${size.width}px`);
        stage.style.transform = `scale(${factor.toFixed(4)})`;

        // the frame wraps the scaled widget exactly, and the panel wraps the frame
        stage.parentElement.style.width = `${width}px`;
        stage.parentElement.style.height = `${Math.round(Math.min(size.height, content) * factor)}px`;
        panel.style.width = `${width + 28}px`;
    };

    /** Centred on the card it belongs to, and never past the edge of the window. */
    const placePeek = (card) => {
        const panel = peek();

        if (! panel) return;

        const box = card.getBoundingClientRect();
        const height = panel.offsetHeight;
        const centred = box.top + box.height / 2 - height / 2;

        panel.style.top = `${Math.round(Math.min(Math.max(12, centred), Math.max(12, window.innerHeight - height - 12)))}px`;
    };

    const showPeek = (card) => {
        const panel = peek();

        // a peek that opens while the list is moving under the pointer is noise, not help
        if (! panel || card.dataset.addWidget === undefined || Date.now() - scrolledAt < 220) return;

        const widget = card.dataset.addWidget;
        const stage = panel.querySelector('[data-peek-stage]');
        const span = Number(card.dataset.span) || 2;
        const rows = Number(card.dataset.rows) || 3;

        panel.querySelector('[data-peek-label]').textContent = card.dataset.label ?? '';
        panel.dataset.open = '';
        panel.dataset.widget = widget;

        const cached = peeked.get(widget);

        stage.innerHTML = cached ?? '';
        fitStage(span, rows);
        placePeek(card);

        if (cached !== undefined) return;

        const template = root()?.dataset.widgetUrl;

        if (! template) return;

        fetch(template.replace('__widget__', widget), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((html) => {
                peeked.set(widget, html);

                // the pointer may have moved on while this was in flight
                if (panel.dataset.open === undefined || panel.dataset.widget !== widget) return;

                stage.innerHTML = html;
                fitStage(span, rows);
                placePeek(card);
            })
            .catch(() => {});
    };

    const hidePeek = () => {
        const panel = peek();

        if (! panel) return;

        delete panel.dataset.open;
    };

    const cards = () => [...document.querySelectorAll('[data-drawer-list] .gallery-card')];

    const filterGallery = () => {
        const group = document.querySelector('[data-gallery-filter] .segment-active')?.dataset.filter ?? 'all';
        const term = (document.querySelector('[data-gallery-search]')?.value ?? '').trim().toLowerCase();
        let shown = 0;

        cards().forEach((card) => {
            const matches = (group === 'all' || card.dataset.group === group)
                && (term === '' || (card.dataset.search ?? '').includes(term));

            card.classList.toggle('is-hidden', ! matches);

            if (matches) shown++;
        });

        // the group headings only help while nothing is filtered
        document.querySelectorAll('[data-group-label]').forEach((label) => {
            label.classList.toggle('hidden', group !== 'all' || term !== '');
        });

        document.querySelector('[data-drawer-empty]')?.classList.toggle('hidden', shown > 0);
    };

    /** A removed tile comes back as the full card from the catalogue pool. */
    const returnToGallery = (slot) => {
        const list = document.querySelector('[data-drawer-list]');
        const pool = document.querySelector('[data-gallery-pool]');

        if (! list || ! pool) return;

        const source = pool.content.querySelector(`[data-pool-widget="${slot.dataset.widget}"]`);

        if (! source) return;

        const card = source.cloneNode(true);

        card.dataset.addWidget = slot.dataset.widget;
        delete card.dataset.poolWidget;
        card.dataset.span = String(size(slot, 'span'));
        card.dataset.rows = String(size(slot, 'rows'));
        card.querySelector('.gallery-size').textContent = `${card.dataset.span}×${card.dataset.rows}`;
        const span = Number(card.dataset.span);
        const rows = Math.max(1, Number(card.dataset.rows));

        card.querySelector('.gallery-frame').style.setProperty(
            '--frame-ratio',
            ((163 * span + 20 * (span - 1)) / (80 * rows + 20 * (rows - 1))).toFixed(3),
        );

        list.insertBefore(card, document.querySelector('[data-drawer-empty]'));

        filterGallery();
    };

    // MARK: wiring, once

    document.addEventListener('click', (event) => {
        if (! root()) return;

        if (event.target.closest('[data-board-toggle]')) {
            setEditing(! editing);

            return;
        }

        if (event.target.closest('[data-board-cancel]')) {
            discard();

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

        const filter = event.target.closest('[data-gallery-filter] .segment');

        if (filter) {
            document.querySelectorAll('[data-gallery-filter] .segment').forEach((node) => {
                node.classList.toggle('segment-active', node === filter);
            });

            filterGallery();

            return;
        }

        const chip = event.target.closest('[data-add-widget]');

        if (chip) addWidget(chip);
    });

    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-gallery-search]')) filterGallery();
    });

    // pointing at a card opens the peek; a short delay keeps a scroll past it quiet
    document.addEventListener('pointerover', (event) => {
        if (! editing || event.pointerType === 'touch') return;

        const card = event.target.closest('[data-drawer-list] .gallery-card');

        clearTimeout(peekTimer);

        if (! card) {
            if (! event.target.closest('[data-gallery-peek]')) peekTimer = setTimeout(hidePeek, 120);

            return;
        }

        peekTimer = setTimeout(() => showPeek(card), 90);
    });

    document.addEventListener('focusin', (event) => {
        const card = event.target.closest('[data-drawer-list] .gallery-card');

        if (card && editing) showPeek(card);
    });

    /*
     * Scrolling closes the peek instead of dragging it along: the list moves under a still
     * pointer, so every card it passes would fire pointerover and open a panel nobody asked
     * for. A short pause afterwards keeps those stray events quiet, and the next real pointer
     * move opens the peek again.
     */
    window.addEventListener('scroll', () => {
        scrolledAt = Date.now();

        clearTimeout(peekTimer);
        hidePeek();
    }, { capture: true, passive: true });

    window.addEventListener('wheel', () => {
        scrolledAt = Date.now();
    }, { passive: true });

    window.addEventListener('resize', hidePeek);

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
        if (event.key === 'Escape' && editing) discard();
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
