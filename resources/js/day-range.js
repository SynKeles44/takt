/*
 * Marking days in the calendar: press, drag across the days you mean, let go — the absence
 * window opens with exactly that range. A plain click still follows the day's own link, and
 * a long press marks a single day.
 *
 * Bound to the document once, so a region swap keeps it working.
 */
export const dayRange = () => {
    const LONG_PRESS = 280;

    let picking = null;
    let pressTimer = null;
    let suppressClick = false;

    const picker = () => document.querySelector('[data-day-picker]');
    const dialog = () => document.querySelector('[data-absence-dialog]');
    const days = () => [...(picker()?.querySelectorAll('[data-day]') ?? [])];

    const dayAt = (x, y) => days().find((day) => {
        const box = day.getBoundingClientRect();

        return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom;
    });

    const paint = () => {
        if (! picking) return;

        const [from, to] = [picking.from, picking.to].sort();

        days().forEach((day) => {
            const inRange = day.dataset.day >= from && day.dataset.day <= to;

            if (inRange) {
                day.dataset.selected = '';
            } else {
                delete day.dataset.selected;
            }
        });
    };

    const clear = () => {
        days().forEach((day) => delete day.dataset.selected);
    };

    const open = (from, to) => {
        const node = dialog();

        if (! node) return;

        const first = days().find((day) => day.dataset.day === from);
        const last = days().find((day) => day.dataset.day === to);

        node.querySelector('[data-absence-start]').value = from;
        node.querySelector('[data-absence-end]').value = to;
        node.querySelector('[data-absence-range]').textContent = from === to
            ? (first?.dataset.dayLabel ?? from)
            : `${first?.dataset.dayLabel ?? from} – ${last?.dataset.dayLabel ?? to}`;

        node.classList.remove('hidden');
        node.classList.add('flex');
        node.querySelector('input[name="note"]')?.focus();
    };

    const close = () => {
        const node = dialog();

        node?.classList.add('hidden');
        node?.classList.remove('flex');
        node?.querySelector('form')?.reset();
        clear();
    };

    const start = (day, event) => {
        picking = { from: day.dataset.day, to: day.dataset.day, dragged: false };
        suppressClick = true;
        paint();

        // hold on one day to mark just that day
        pressTimer = setTimeout(() => {
            if (picking) picking.dragged = true;
        }, LONG_PRESS);

    };

    document.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || ! picker()) return;

        const day = event.target.closest('[data-day]');

        if (! day) return;

        /*
         * A day is a link, and a browser answers a press-and-move on a link with its own
         * drag-and-drop. Stopping the default here is what makes marking possible at all;
         * the click event still fires, so a plain click keeps following the link.
         */
        event.preventDefault();

        start(day, event);
    });

    // belt and braces: even a stray dragstart must not turn into a link drag
    document.addEventListener('dragstart', (event) => {
        if (event.target.closest('[data-day]')) event.preventDefault();
    });

    document.addEventListener('pointermove', (event) => {
        if (! picking) return;

        const day = dayAt(event.clientX, event.clientY);

        if (! day || day.dataset.day === picking.to) return;

        picking.to = day.dataset.day;
        picking.dragged = true;
        paint();
    });

    document.addEventListener('pointerup', () => {
        clearTimeout(pressTimer);

        if (! picking) return;

        const { from, to, dragged } = picking;

        picking = null;

        if (! dragged) {
            // a plain click on a day: let the link do its job
            suppressClick = false;
            clear();

            return;
        }

        const [start, end] = [from, to].sort();

        open(start, end);
    });

    // a marked range must not navigate away
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-absence-cancel]')) {
            close();

            return;
        }

        if (! event.target.closest('[data-day]') || ! suppressClick) return;

        const marked = days().some((day) => day.dataset.selected !== undefined);
        suppressClick = false;

        if (marked) event.preventDefault();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        if (dialog()?.classList.contains('flex')) close();
    });

    // saving swaps the page back in; the dialog belongs to the old markup
    document.addEventListener('submit', (event) => {
        if (event.target.closest('[data-absence-form]')) clear();
    });
};
