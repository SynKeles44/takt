/*
 * The command palette. The dialog itself stands outside every region, but the button that opens
 * it lives in the sidebar — which IS a region — so the opener is a delegated click, and the
 * dialog is looked up when it is needed rather than kept from the first paint.
 */
const node = () => document.querySelector('[data-palette]');

const isOpen = (palette) => palette !== null && ! palette.classList.contains('hidden');

const items = (palette) => [...palette.querySelectorAll('[data-palette-item]')];

const visible = (palette) => items(palette).filter((item) => ! item.classList.contains('hidden'));

let active = 0;
let lookup;
let request = 0;

/*
 * The highlight behind the keyboard cursor, as one element that travels.
 *
 * It is positioned against the scroll container rather than animated per row, so it follows
 * the list while it scrolls and does not have to be rebuilt when the results are replaced —
 * which happens on every keystroke. Offsets, not bounding boxes: the container is the offset
 * parent, so the numbers stay right no matter how far the list has scrolled.
 */
const cursor = (palette) => {
    const scroll = palette.querySelector('[data-palette-scroll]');

    if (! scroll) return null;

    let element = scroll.querySelector(':scope > .palette-marker');

    if (! element) {
        element = document.createElement('span');
        element.className = 'palette-marker';
        element.setAttribute('aria-hidden', 'true');
        scroll.prepend(element);
        scroll.dataset.paletteMarked = '';
    }

    return element;
};

const place = (palette, target) => {
    const marker = cursor(palette);

    if (! marker) return;

    if (! target) {
        marker.style.opacity = '0';

        return;
    }

    // size without transition, travel with one: animating the box would repaint the whole row
    marker.style.width = `${target.offsetWidth}px`;
    marker.style.height = `${target.offsetHeight}px`;
    marker.style.transform = `translate(${target.offsetLeft}px, ${target.offsetTop}px)`;

    // the first placement must not slide in from the corner
    if (marker.style.opacity !== '1') {
        marker.getAnimations().forEach((animation) => animation.cancel());
        marker.style.opacity = '1';
    }
};

const mark = (palette) => {
    const shown = visible(palette);
    active = Math.max(0, Math.min(active, shown.length - 1));

    items(palette).forEach((item) => item.querySelector('a, button')?.removeAttribute('data-active'));

    const target = shown[active]?.querySelector('a, button');
    target?.setAttribute('data-active', '');
    target?.scrollIntoView({ block: 'nearest' });

    place(palette, target ?? null);
};

const render = (palette, rows) => {
    const results = palette.querySelector('[data-palette-results]');
    const template = palette.querySelector('[data-palette-template]');

    results.replaceChildren();

    rows.forEach((row) => {
        const item = template.content.firstElementChild.cloneNode(true);
        item.dataset.label = row.label.toLowerCase();

        const link = item.querySelector('a');

        if (row.copy) {
            link.href = '#';
            link.dataset.copy = row.copy;

            if (row.ping) {
                link.dataset.copyPing = row.ping;
            }
        } else {
            link.href = row.url;
        }

        item.querySelector('[data-slot="label"]').textContent = row.label;
        item.querySelector('[data-slot="hint"]').textContent = row.hint ?? '';
        item.querySelector('[data-slot="group"]').textContent = row.group;
        results.append(item);
    });

    results.classList.toggle('hidden', rows.length === 0);
    palette.querySelector('[data-palette-empty]')?.classList.toggle('hidden', visible(palette).length > 0);
    mark(palette);
};

const search = (palette, needle) => {
    const endpoint = palette.querySelector('[data-palette-scroll]')?.dataset.paletteSearch;

    if (! endpoint || needle.length < 2) {
        render(palette, []);

        return;
    }

    const ticket = ++request;

    fetch(`${endpoint}?q=${encodeURIComponent(needle)}`, { headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : { results: [] }))
        .then((payload) => {
            if (ticket === request) {
                render(palette, payload.results ?? []);
            }
        })
        .catch(() => {});
};

const filter = (palette) => {
    const needle = palette.querySelector('[data-palette-input]').value.trim().toLowerCase();

    palette.querySelectorAll('[data-palette-item]:not([data-remote])').forEach((item) => {
        item.classList.toggle('hidden', needle !== '' && ! item.dataset.label.includes(needle));
    });

    palette.querySelector('[data-palette-empty]')?.classList.toggle('hidden', visible(palette).length > 0);
    active = 0;
    mark(palette);

    clearTimeout(lookup);
    lookup = setTimeout(() => search(palette, needle), 180);
};

const open = (palette) => {
    const input = palette.querySelector('[data-palette-input]');

    palette.classList.remove('hidden');
    palette.classList.add('flex');
    input.value = '';
    render(palette, []);
    filter(palette);
    input.focus();
};

const close = (palette) => {
    palette.classList.add('hidden');
    palette.classList.remove('flex');
};

export function palette() {
    document.addEventListener('keydown', (event) => {
        const dialog = node();

        if (! dialog) return;

        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            isOpen(dialog) ? close(dialog) : open(dialog);

            return;
        }

        if (! isOpen(dialog)) {
            return;
        }

        if (event.key === 'Escape') {
            close(dialog);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            active = (active + 1) % Math.max(1, visible(dialog).length);
            mark(dialog);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            const shown = Math.max(1, visible(dialog).length);
            active = (active - 1 + shown) % shown;
            mark(dialog);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            visible(dialog)[active]?.querySelector('a, button')?.click();
        }
    });

    document.addEventListener('input', (event) => {
        if (! event.target.matches('[data-palette-input]')) return;

        filter(event.target.closest('[data-palette]'));
    });

    document.addEventListener('click', (event) => {
        const dialog = node();

        if (! dialog) return;

        if (event.target.closest('[data-palette-open]')) {
            open(dialog);
        } else if (event.target.closest('[data-palette-close]')) {
            close(dialog);
        }
    });

    // an action chosen from the palette has happened once the page answered
    document.addEventListener('takt:swapped', () => {
        const dialog = node();

        if (isOpen(dialog)) close(dialog);
    });
}
