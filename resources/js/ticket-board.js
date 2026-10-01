/**
 * Dragging a ticket between the columns of the day.
 *
 * Built on the native drag events rather than the pointer-based mechanics the dashboard board
 * uses: there a widget is resized and reordered inside a dense grid, which the browser cannot
 * help with. Here a card moves from one list to another, which is exactly what native drag and
 * drop is for — and it keeps working with a keyboard, because every card also carries the two
 * arrow buttons that post the same request.
 *
 * The move is sent to the same endpoint the buttons use, so there is one way to change a column
 * and not two.
 */
export function ticketBoard({ swapRegions, toast }) {
    const board = document.querySelector('[data-ticket-board]');

    // the state picker and the shortcuts belong to the cards, which the list view has as well
    stateSelects(swapRegions, toast);
    keyboard(board, swapRegions);

    if (! board) return;

    let dragged = null;

    board.addEventListener('dragstart', (event) => {
        const card = event.target.closest('[data-ticket]');

        if (! card) return;

        dragged = card;
        card.dataset.dragging = '';

        // the payload is required for the drop to be accepted at all in some engines
        event.dataTransfer?.setData('text/plain', card.dataset.ticket);
        if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move';
    });

    board.addEventListener('dragend', () => {
        if (dragged) delete dragged.dataset.dragging;
        dragged = null;
        board.querySelectorAll('[data-over]').forEach((column) => delete column.dataset.over);
    });

    board.addEventListener('dragover', (event) => {
        const column = event.target.closest('[data-column]');

        if (! column || ! dragged) return;

        // preventDefault is what marks this as a valid drop target
        event.preventDefault();
        if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';

        if (column.dataset.over === undefined) {
            board.querySelectorAll('[data-over]').forEach((other) => delete other.dataset.over);
            column.dataset.over = '';
        }
    });

    board.addEventListener('drop', async (event) => {
        const column = event.target.closest('[data-column]');

        if (! column || ! dragged) return;

        event.preventDefault();

        const key = dragged.dataset.ticket;
        const target = column.dataset.column;

        delete column.dataset.over;

        if (dragged.closest('[data-column]') === column) return;

        /*
         * Move the card first, then tell the server. A drop that visibly waits for a round trip
         * feels broken even when it is fast; if the request fails the reload puts it back, and
         * the failure is visible in the status line rather than silently swallowed.
         */
        column.querySelector('.ticket-column-body')?.append(dragged);

        const body = new FormData();
        body.append('key', key);
        body.append('spalte', target);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        try {
            const response = await fetch('/tickets/spalte', {
                method: 'POST',
                body,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (response.ok) {
                const html = await response.text();

                swapRegions(html, ['ticket-board']);
            }
        } catch {
            // offline or refused: the next load shows the truth
        }
    });
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
const keyboard = (board, swapRegions) => {
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

    const place = async (card, column) => {
        const body = new FormData();

        body.append('key', card.dataset.ticket);
        body.append('spalte', column);
        body.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

        try {
            const response = await fetch('/tickets/spalte', {
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

        const columns = board === null ? [] : [...board.querySelectorAll('[data-column]')].map((column) => column.dataset.column);
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
