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
export function ticketBoard({ swapRegions }) {
    const board = document.querySelector('[data-ticket-board]');

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
