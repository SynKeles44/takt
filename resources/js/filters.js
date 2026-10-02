/* Collapsible blocks start closed and remember what was opened, per key. */
export const rememberBlocks = (root = document) => {
    root.querySelectorAll('details[data-remember]:not([data-wired])').forEach((block) => {
        const key = 'takt.open.' + block.dataset.remember;

        block.dataset.wired = '';
        block.open = localStorage.getItem(key) === '1';

        block.addEventListener('toggle', () => localStorage.setItem(key, block.open ? '1' : '0'));
    });
};

/* The package list, filtered down to what is actually behind. */
const filterPackages = (button) => {
    const group = button.closest('[data-package-filter]');

    // the marker follows this class, so the filter says which of its buttons is current
    group.querySelectorAll('.segment').forEach((other) => other.classList.toggle('segment-active', other === button));

    const outdated = button.dataset.filter === 'outdated';

    document.querySelectorAll('[data-package-row]').forEach((row) => {
        const behind = ['minor', 'major', 'abandoned', 'vulnerable'].includes(row.dataset.status);

        row.hidden = outdated && ! behind;
    });

    document.querySelectorAll('[data-package-project]').forEach((card) => {
        const rows = [...card.querySelectorAll('[data-package-row]')];

        /*
         * A project whose every row is hidden has nothing left to say. It is hidden, never
         * opened: opening a card the user did not click is the page deciding what they wanted
         * to look at, and the summary line already carries the answer most visits need.
         */
        card.hidden = rows.length > 0 && rows.every((row) => row.hidden);
    });
};

/*
 * Filtering the make targets. Typing opens every project that still has a match and closes
 * the ones that have none, so the list reads as one flat result while a filter is active.
 */
const filterCommands = (term) => {
    document.querySelectorAll('[data-command-project]').forEach((card) => {
        const block = card.querySelector('details');
        const targets = [...card.querySelectorAll('[data-search]')];
        let hits = 0;

        targets.forEach((target) => {
            const match = term === '' || target.dataset.search.includes(term);

            target.classList.toggle('hidden', ! match);

            if (match) hits += 1;
        });

        const nameMatches = term !== '' && card.dataset.name.includes(term);

        card.classList.toggle('hidden', term !== '' && hits === 0 && ! nameMatches);
        card.querySelector('[data-command-empty]')?.classList.toggle('hidden', hits > 0 || term === '');

        if (block && term !== '') {
            block.open = hits > 0 || nameMatches;
        } else if (block) {
            block.open = localStorage.getItem('takt.open.' + block.dataset.remember) === '1';
        }
    });
};

export function filters() {
    rememberBlocks();
    document.addEventListener('takt:swapped', () => rememberBlocks());

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-package-filter] [data-filter]');

        if (button) filterPackages(button);
    });

    document.addEventListener('input', (event) => {
        if (! event.target.matches('[data-command-filter]')) return;

        filterCommands(event.target.value.trim().toLowerCase());
    });
}
