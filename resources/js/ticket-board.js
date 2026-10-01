/**
 * The ticket board: its state picker, its shortcuts, and dragging between its columns.
 *
 * Every one of those writes the same thing — a workflow state, to Linear — through the same
 * endpoint, so there is one way to change a state and not three. There used to be a second board
 * here with columns of its own that were stored locally; it is gone, and so is the only reason
 * this file ever had two ways to move a card.
 */
export function ticketBoard({ swapRegions, toast }) {
    stateSelects(swapRegions, toast);
    keyboard(swapRegions);
    stateBoard(swapRegions, toast);
}

/**
 * Setting Linear's own state from a card.
 *
 * One delegated listener for every select on the page, with the token read from the page's meta
 * tag — the alternative is a form per card, and that was measured at 300 KB of CSRF fields the
 * last time this page carried one. The select keeps the value it was changed to while the request
 * is in flight; a reload is what corrects it if Linear refuses.
 */
const stateSelects = (swapRegions, toast) => {
    document.addEventListener('change', async (event) => {
        const select = event.target.closest('[data-state-select]');

        if (! select) return;

        const body = new FormData();

        body.append('aktion', 'felder');
        body.append('status', select.value);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        select.disabled = true;

        try {
            const response = await fetch(`/tickets/${encodeURIComponent(select.dataset.key)}/linear`, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                const html = await response.text();
                const flash = new DOMParser().parseFromString(html, 'text/html')
                    .querySelector('[data-flash]')?.textContent?.trim();

                if (flash) toast?.(flash);

                swapRegions(html, ['ticket-board']);
            }
        } catch {
            // offline or refused: the next load shows the truth
        } finally {
            select.disabled = false;
        }
    });
};

/**
 * The board from the keyboard, the way Linear's is.
 *
 * j/k and the arrows walk the cards in the order they are drawn, 1 to 5 drop the selected one into
 * that column, Enter opens it and t starts its timer. The selection is an attribute on the card
 * rather than focus, because focus lands on the links and buttons inside a card and would make
 * every second keypress mean something else.
 */
const keyboard = (swapRegions) => {
    const board = document.querySelector('[data-state-board]');
    /*
     * Every card on the page, not only the ones in the columns. Most of a real board's tickets sit
     * in the not-yet-sorted list below it — walking only the columns meant j and k moved between
     * the two or three cards already placed and looked like they did nothing at all.
     */
    const cards = () => [...document.querySelectorAll('[data-ticket]')];
    const selected = () => document.querySelector('[data-ticket][data-selected]');

    const select = (card) => {
        if (! card) return;

        document.querySelectorAll('[data-selected]').forEach((other) => delete other.dataset.selected);
        card.dataset.selected = '';
        card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    const step = (by) => {
        const all = cards();
        const at = all.indexOf(selected());

        select(all[at === -1 ? 0 : Math.min(all.length - 1, Math.max(0, at + by))]);
    };

    /** The number keys write a state to Linear, exactly as a drop onto that column does. */
    const place = async (card, state) => {
        const body = new FormData();

        body.append('aktion', 'felder');
        body.append('status', state);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        card.dataset.pending = '';

        try {
            const response = await fetch(`/tickets/${encodeURIComponent(card.dataset.ticket)}/linear`, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) swapRegions(await response.text(), ['ticket-board']);
        } catch {
            // offline or refused: the next load shows the truth
        }
    };

    document.addEventListener('keydown', (event) => {
        // never while something is being typed into, and never over a shortcut the browser owns
        if (event.metaKey || event.ctrlKey || event.altKey) return;

        /*
         * `closest` only exists on elements, and a key event's target is not always one — it is
         * the document itself when nothing is focused in some engines, and that threw, which took
         * the whole handler with it and made the shortcuts look unimplemented.
         */
        if (event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable]')) return;

        const columns = board === null
            ? []
            : [...board.querySelectorAll('[data-state-column]')].map((column) => column.dataset.stateColumn);
        const card = selected();

        if (['j', 'ArrowDown'].includes(event.key)) return event.preventDefault(), step(1);
        if (['k', 'ArrowUp'].includes(event.key)) return event.preventDefault(), step(-1);

        if (! card) return;

        if (event.key === 'Enter') {
            event.preventDefault();
            card.querySelector('a[href*="/tickets/"]')?.click();

            return;
        }

        if (event.key === 't') {
            event.preventDefault();
            card.querySelector('form[action*="/timer"] button')?.click();

            return;
        }

        const slot = Number.parseInt(event.key, 10);

        if (slot >= 1 && slot <= columns.length) {
            event.preventDefault();
            place(card, columns[slot - 1]);
        }
    });
};

/**
 * Dragging between the columns of the Linear-shaped board.
 *
 * Same mechanics as the day board next to it and a different destination: there a drop writes a
 * column of mine into this app's own table, here it writes a state into Linear, where the team
 * will see it. So this one does not move the card first. An optimistic move is right when the
 * write is local and certain; against someone else's API it means showing a state the team does
 * not have yet, and a card that springs back is worse than a card that waits a moment.
 */
const stateBoard = (swapRegions, toast) => {
    const board = document.querySelector('[data-state-board]');

    if (! board) return;

    let dragged = null;

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-ticket]');

        if (! card) return;

        dragged = card;
        card.dataset.dragging = '';
        event.dataTransfer?.setData('text/plain', card.dataset.ticket);
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });

    board.addEventListener('dragend', () => {
        if (dragged) delete dragged.dataset.dragging;
        dragged = null;
        board.querySelectorAll('[data-over]').forEach((column) => delete column.dataset.over);
    });

    board.addEventListener('dragover', (event) => {
        const column = event.target.closest('[data-state-column]');

        if (! column || ! dragged) return;

        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';

        if (column.dataset.over === undefined) {
            board.querySelectorAll('[data-over]').forEach((other) => delete other.dataset.over);
            column.dataset.over = '';
        }
    });

    board.addEventListener('drop', async (event) => {
        const column = event.target.closest('[data-state-column]');

        if (! column || ! dragged) return;

        event.preventDefault();
        delete column.dataset.over;

        const key = dragged.dataset.ticket;
        const state = column.dataset.stateColumn;

        if (dragged.closest('[data-state-column]') === column) return;

        dragged.dataset.pending = '';

        const body = new FormData();

        body.append('aktion', 'felder');
        body.append('status', state);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        try {
            const response = await fetch(`/tickets/${encodeURIComponent(key)}/linear`, {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                const html = await response.text();
                const flash = new DOMParser().parseFromString(html, 'text/html')
                    .querySelector('[data-flash]')?.textContent?.trim();

                if (flash) toast?.(flash);

                swapRegions(html, ['ticket-board']);
            }
        } catch {
            // offline or refused: the next load shows the truth
        }
    });
};
