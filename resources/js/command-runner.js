/*
 * Running a project's make targets from the page. A click starts the target, the dialog
 * follows the output while it runs, and stopping is one button away. Polling only happens
 * while a run is open, and stops the moment it finishes.
 */
export const commandRunner = ({ toast }) => {
    const POLL = 900;

    let timer = null;
    let currentUrl = null;

    const dialog = () => document.querySelector('[data-run-dialog]');

    const headers = () => ({
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    });

    const open = () => {
        const node = dialog();

        node?.classList.remove('hidden');
        node?.classList.add('flex');
    };

    const close = () => {
        const node = dialog();

        clearTimeout(timer);
        timer = null;
        currentUrl = null;

        node?.classList.add('hidden');
        node?.classList.remove('flex');
    };

    const render = (run) => {
        const node = dialog();

        if (! node) return;

        node.querySelector('[data-run-command]').textContent = run.command;
        node.querySelector('[data-run-project]').textContent = `${run.project} · ${run.started_at}`;

        const status = node.querySelector('[data-run-status]');

        status.className = `pill shrink-0 text-[10px] ${run.classes}`;
        status.textContent = run.exit_code === null || run.running
            ? run.label
            : `${run.label} · ${run.exit_code}`;

        const output = node.querySelector('[data-run-output]');
        const atBottom = output.scrollTop + output.clientHeight >= output.scrollHeight - 24;

        output.textContent = run.output || '…';

        if (atBottom) output.scrollTop = output.scrollHeight;

        const stop = node.querySelector('[data-run-stop]');

        stop.classList.toggle('hidden', ! run.running);
        stop.dataset.stopUrl = run.stop_url;

        // a prompt can only be answered while the run is going and has a terminal
        const form = node.querySelector('[data-run-input-form]');

        form.classList.toggle('hidden', ! (run.running && run.interactive));
        form.dataset.inputUrl = run.input_url;

        currentUrl = run.url;

        clearTimeout(timer);

        if (run.running) timer = setTimeout(poll, POLL);
    };

    const poll = () => {
        if (! currentUrl) return;

        fetch(currentUrl, { headers: headers() })
            .then((response) => (response.ok ? response.json() : Promise.reject()))
            .then(render)
            .catch(() => {});
    };

    document.addEventListener('click', (event) => {
        const start = event.target.closest('[data-run]');

        if (start) {
            open();

            const output = dialog().querySelector('[data-run-output]');

            output.textContent = '…';

            fetch(start.dataset.run, {
                method: 'POST',
                headers: { ...headers(), 'Content-Type': 'application/json' },
                body: JSON.stringify({ target: start.dataset.target }),
            })
                .then((response) => response.json().then((body) => (response.ok ? body : Promise.reject(body))))
                .then(render)
                .catch((body) => {
                    close();
                    toast(body?.error ?? '');
                });

            return;
        }

        const reopen = event.target.closest('[data-open-run]');

        if (reopen) {
            open();
            currentUrl = reopen.dataset.openRun;
            poll();

            return;
        }

        if (event.target.closest('[data-run-close]')) {
            close();

            return;
        }

        const stop = event.target.closest('[data-run-stop]');


        if (stop?.dataset.stopUrl) {
            fetch(stop.dataset.stopUrl, { method: 'DELETE', headers: headers() })
                .then((response) => response.json())
                .then(render)
                .catch(() => {});
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-run-input-form]');

        if (! form) return;

        event.preventDefault();

        const field = form.querySelector('[data-run-input]');
        const line = field.value;

        field.value = '';

        fetch(form.dataset.inputUrl, {
            method: 'POST',
            headers: { ...headers(), 'Content-Type': 'application/json' },
            body: JSON.stringify({ line }),
        })
            .then((response) => response.json().then((body) => (response.ok ? body : Promise.reject(body))))
            .then(render)
            .catch((body) => toast(body?.error ?? ''));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog()?.classList.contains('flex')) close();
    });
};
