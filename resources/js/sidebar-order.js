/**
 * The order of the sidebar's fixed sections.
 *
 * Dragging and the two arrows do the same thing deliberately: a drag is faster with a mouse and
 * impossible with a keyboard, and the arrows are the half that still works on a phone. Both only
 * move rows — the order is read off the DOM when the form is submitted, so nothing is stored
 * until the save button is pressed and a cancelled edit is a page reload.
 */
export function sidebarOrder() {
    const list = () => document.querySelector('[data-sidebar-order]');
    const rows = () => [...(list()?.querySelectorAll('[data-order-item]') ?? [])];

    let dragged = null;

    document.addEventListener('click', (event) => {
        const button = event.target.closest?.('[data-order-move]');

        if (! button) return;

        const row = button.closest('[data-order-item]');
        const all = rows();
        const next = all.indexOf(row) + Number(button.dataset.orderMove);

        if (next < 0 || next >= all.length) return;

        // insertBefore with the row after the target is how "move down" and "move up" are one line
        Number(button.dataset.orderMove) < 0
            ? all[next].before(row)
            : all[next].after(row);

        button.focus();
    });

    document.addEventListener('dragstart', (event) => {
        const row = event.target.closest?.('[data-order-item]');

        if (! row || ! list()?.contains(row)) return;

        dragged = row;
        row.classList.add('opacity-50');
        event.dataTransfer.effectAllowed = 'move';
    });

    document.addEventListener('dragover', (event) => {
        if (! dragged) return;

        const over = event.target.closest?.('[data-order-item]');

        if (! over || over === dragged || ! list()?.contains(over)) return;

        event.preventDefault();

        // the midpoint decides which side of the row under the pointer the dragged one lands on
        const box = over.getBoundingClientRect();

        event.clientY < box.top + box.height / 2 ? over.before(dragged) : over.after(dragged);
    });

    document.addEventListener('dragend', () => {
        dragged?.classList.remove('opacity-50');
        dragged = null;
    });
}
