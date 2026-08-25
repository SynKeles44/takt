/*
 * The container list: actions on a container, its logs in a window, and a list that keeps
 * itself current while the page is in front. Polling stops when the tab goes to the back —
 * `docker ps` is cheap, but not free, and nobody watches a hidden tab.
 */
export const docker = ({ swapRegions, toast }) => {
    const REFRESH = 6000;

    let timer = null;

    const root = () => document.querySelector('[data-docker]');
    const dialog = () => document.querySelector('[data-docker-dialog]');

    const headers = () => ({
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    });

    const refresh = () => {
        const node = root();

        if (! node) return Promise.resolve();

        return fetch(node.dataset.listUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then((response) => (response.ok ? response.text() : Promise.reject()))
            .then((html) => swapRegions(html, ['docker-list']))
            .catch(() => {});
    };

    const schedule = () => {
        clearTimeout(timer);

        if (! root() || document.hidden) return;

        timer = setTimeout(() => refresh().then(schedule), REFRESH);
    };

    const act = (id, action, button) => {
        button.disabled = true;

        fetch(root().dataset.actUrl, {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, action }),
        })
            .then((response) => response.json().then((body) => (response.ok ? body : Promise.reject(body))))
            .then((body) => {
                toast(body.message ?? '');

                return refresh();
            })
            .catch((body) => toast(body?.error ?? ''))
            .finally(() => {
                button.disabled = false;
                schedule();
            });
    };

    const logs = (id) => {
        const node = dialog();
        const url = new URL(root().dataset.logsUrl, window.location.origin);

        url.searchParams.set('id', id);

        node.classList.remove('hidden');
        node.classList.add('flex');
        node.querySelector('[data-docker-output]').textContent = '…';

        fetch(url, { headers: headers() })
            .then((response) => response.json().then((body) => (response.ok ? body : Promise.reject(body))))
            .then((body) => {
                node.querySelector('[data-docker-title]').textContent = body.title;
                node.querySelector('[data-docker-name]').textContent = body.name;

                const output = node.querySelector('[data-docker-output]');

                output.textContent = body.output;
                output.scrollTop = output.scrollHeight;
            })
            .catch((body) => {
                close();
                toast(body?.error ?? '');
            });
    };

    const close = () => {
        const node = dialog();

        node?.classList.add('hidden');
        node?.classList.remove('flex');
    };

    document.addEventListener('click', (event) => {
        if (! root()) return;

        const action = event.target.closest('[data-docker-action]');

        if (action) {
            act(action.dataset.dockerId, action.dataset.dockerAction, action);

            return;
        }

        const log = event.target.closest('[data-docker-logs]');

        if (log) {
            logs(log.dataset.dockerLogs);

            return;
        }

        if (event.target.closest('[data-docker-refresh]')) {
            refresh().then(schedule);

            return;
        }

        if (event.target.closest('[data-docker-close]')) close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog()?.classList.contains('flex')) close();
    });

    document.addEventListener('visibilitychange', () => (document.hidden ? clearTimeout(timer) : schedule()));

    schedule();
};
