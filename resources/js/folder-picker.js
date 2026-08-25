/*
 * Picking a project folder inside the page. A browser never gives out an absolute path, so
 * the local server lists the folders and this walks them: click a folder to go in, the arrow
 * to go up, "take this folder" to hand it to the form. Same dialog in the app window — one
 * path that works everywhere beats a second one through the Finder that only works sometimes.
 */
export const folderPicker = ({ toast }) => {
    let current = null;

    const dialog = () => document.querySelector('[data-folder-dialog]');
    const list = () => document.querySelector('[data-folder-list]');
    const field = () => document.querySelector('[data-scan-path]');

    const open = () => {
        const node = dialog();

        if (! node) return;

        node.classList.remove('hidden');
        node.classList.add('flex');

        load(field()?.value?.trim() || null);
    };

    const close = () => {
        const node = dialog();

        node?.classList.add('hidden');
        node?.classList.remove('flex');
    };

    const load = (path) => {
        const node = dialog();
        const url = new URL(node.dataset.foldersUrl, window.location.origin);

        if (path) url.searchParams.set('pfad', path);

        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.json() : Promise.reject()))
            .then(render)
            .catch(() => toast(node.dataset.failedLabel ?? ''));
    };

    const render = (data) => {
        current = data;

        const node = dialog();

        crumbs(data);

        const target = list();

        target.innerHTML = '';

        if (data.entries.length === 0) {
            const empty = document.createElement('p');

            empty.className = 'rounded-[var(--radius-control)] border border-dashed border-line px-3 py-6 text-center text-xs text-faint';
            empty.textContent = node.dataset.emptyLabel ?? '';
            target.append(empty);

            return;
        }

        data.entries.forEach((entry) => {
            const row = document.createElement('button');

            row.type = 'button';
            row.className = 'row flex w-full items-center gap-3 px-3 py-2 text-left';
            row.dataset.folder = entry.path;
            row.innerHTML = `
                <svg class="size-4 shrink-0 ${entry.git ? 'text-accent-text' : 'text-dim'}" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.1a2 2 0 0 1 1.6.8l.9 1.2h7.4A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/>
                </svg>
                <span class="min-w-0 flex-1 truncate text-sm text-ink"></span>
                ${entry.git ? '<span class="pill shrink-0 text-[10px]">git</span>' : ''}
            `;
            row.querySelector('span.min-w-0').textContent = entry.name;
            target.append(row);
        });
    };

    /** The path as clickable crumbs — one click goes up as many levels as you want. */
    const crumbs = (data) => {
        const target = dialog().querySelector('[data-folder-crumbs]');

        target.innerHTML = '';

        const parts = data.label === '~' ? [] : data.label.replace(/^~\//, '').split('/');
        let path = data.home;

        const add = (name, at, last) => {
            const crumb = document.createElement(last ? 'span' : 'button');

            if (! last) {
                crumb.type = 'button';
                crumb.dataset.folder = at;
            }

            crumb.className = last ? 'folder-crumb is-current' : 'folder-crumb';
            crumb.textContent = name;
            target.append(crumb);

            if (! last) {
                const sep = document.createElement('span');

                sep.className = 'folder-crumb-sep';
                sep.textContent = '/';
                target.append(sep);
            }
        };

        add('~', data.home, parts.length === 0);

        parts.forEach((name, index) => {
            path += '/' + name;
            add(name, path, index === parts.length - 1);
        });
    };

    /** Hands the folder to the form and lets the existing scan fill the rest. */
    const choose = (path) => {
        const input = field();

        if (! input) return;

        input.value = path;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    };

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-pick-folder]')) {
            open();

            return;
        }

        if (! dialog()?.classList.contains('flex')) return;

        if (event.target.closest('[data-folder-cancel]')) {
            close();

            return;
        }

        if (event.target.closest('[data-folder-choose]')) {
            if (current?.path) choose(current.path);

            return;
        }

        const row = event.target.closest('[data-folder]');

        if (row) load(row.dataset.folder);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog()?.classList.contains('flex')) close();
    });
};
