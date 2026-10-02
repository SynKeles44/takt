import { toast } from './toast';

const bump = (element, delta) => {
    if (element) {
        element.textContent = String(Math.max(0, Number(element.textContent) + delta));
    }
};

const dropItem = (item) => {
    const group = item.closest('[data-group]');

    item.setAttribute('data-leaving', '');
    item.style.maxHeight = `${item.offsetHeight}px`;

    setTimeout(() => {
        item.style.maxHeight = '0px';
        item.style.marginTop = '0px';
        item.style.overflow = 'hidden';
        item.style.transition = 'max-height .22s ease, margin .22s ease';
    }, 60);

    setTimeout(() => {
        item.remove();

        if (group && group.querySelectorAll('[data-item]').length === 0) {
            group.remove();
        }
    }, 300);

    bump(group?.querySelector('[data-group-count]'), -1);
};

/* The to-do toggles: optimistic, answered as JSON, and the row leaves the list when the filter says so. */
export function asyncForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-async]');

        if (! form) {
            return;
        }

        event.preventDefault();

        const item = form.closest('[data-item]');
        const list = form.closest('[data-todo-list]');
        const wasDone = item?.dataset.done === '1';

        if (item) {
            item.dataset.done = wasDone ? '0' : '1';
        }

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(response)))
            .then((payload) => {
                if (payload.reload) {
                    window.location.reload();

                    return;
                }

                if (item) {
                    item.dataset.done = payload.done ? '1' : '0';
                }

                if (payload.status) {
                    toast(payload.status);
                }

                if (! item || item.hasAttribute('data-stay') || ! list) {
                    return;
                }

                const filter = list.dataset.filter ?? 'open';

                bump(list.querySelector('[data-count="open"]'), payload.done ? -1 : 1);
                bump(list.querySelector('[data-count="done"]'), payload.done ? 1 : -1);

                if ((filter === 'open' && payload.done) || (filter === 'done' && ! payload.done)) {
                    dropItem(item);
                }
            })
            .catch(() => window.location.reload());
    });
}
