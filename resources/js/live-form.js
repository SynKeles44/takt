import { askConfirm } from './dialog';
import { NAV_SAFE, swapRegions } from './swap';

/*
 * Progressive enhancement: a form marked data-live posts in place and only the
 * marked regions are replaced, so an add or a delete no longer reloads the page.
 * Without JavaScript, or when the response leaves the current page, the browser
 * does the normal navigation. A form marked data-confirm asks first, either way.
 */
const send = (form, submitter) => {
    const body = new FormData(form);

    if (submitter?.name) {
        body.append(submitter.name, submitter.value);
    }

    form.dataset.busy = '';
    form.setAttribute('aria-busy', 'true');
    form.closest('[data-autohide]')?.remove();

    /*
     * A GET form carries its fields in the URL, not in a body — a fetch that puts FormData in
     * the body of a GET sends an empty request and the server answers the unfiltered page.
     * That is why the search and the filters used to reload instead of swapping.
     */
    const method = (form.method || 'post').toUpperCase();
    const reading = method === 'GET';
    const action = reading
        ? form.action + '?' + new URLSearchParams([...body].filter(([, value]) => typeof value === 'string'))
        : form.action;

    fetch(action, {
        method,
        body: reading ? undefined : body,
        headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
        redirect: 'follow',
    })
        .then((response) => Promise.all([response.text(), response.url, response.ok]))
        .then(([html, url, ok]) => {
            const target = new URL(url, window.location.origin);
            const swappable = ok
                && target.origin === window.location.origin
                && ! NAV_SAFE.includes(target.pathname);

            if (! swappable || ! swapRegions(html)) {
                window.location.href = url;

                return;
            }

            /*
             * The response was already rendered, flash included — navigating again
             * would drop the message, so the URL follows the swap instead.
             * replace, not push: the page we came from is usually gone after the action
             */
            if (target.href !== window.location.href) {
                history.replaceState({}, '', target);
            }

            // the fresh region replaced the old nodes, so look the field up again
            document.querySelector('[data-refocus]')?.focus();
        })
        .catch(() => window.location.reload())
        .finally(() => {
            delete form.dataset.busy;
            form.removeAttribute('aria-busy');
        });
};

const navigate = (form) => {
    form.dataset.confirmed = '';

    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
};

export function liveForms() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');

        if (! form) {
            return;
        }

        const message = form.dataset.confirm;
        const needsConfirm = message !== undefined && form.dataset.confirmed === undefined;
        const live = form.dataset.live !== undefined && ! form.dataset.busy;

        if (! needsConfirm && ! live) {
            delete form.dataset.confirmed;

            return;
        }

        event.preventDefault();

        const submitter = event.submitter;
        const run = () => (live ? send(form, submitter) : navigate(form));

        if (! needsConfirm) {
            run();

            return;
        }

        askConfirm(message).then((answer) => {
            if (answer) {
                run();
            }
        });
    });
}
