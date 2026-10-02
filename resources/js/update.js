/*
 * The update notice of the downloaded app. The shell decides that there is a newer release and
 * does the work; this only shows the notice and hands the click back. Every element is looked up
 * when it is needed — the notice lives outside the regions, but nothing here assumes that.
 */
const DISMISSED = 'takt.update.dismissed';

const node = () => document.querySelector('[data-update]');

const fill = (template, values) => Object.entries(values).reduce((text, [key, value]) => text.replace(`:${key}`, value), template ?? '');

const send = (action) => window.webkit?.messageHandlers?.update?.postMessage({ action });

const remembered = () => {
    try {
        return localStorage.getItem(DISMISSED);
    } catch {
        return null;
    }
};

export const updateAvailable = ({ version, current }) => {
    const notice = node();

    // "Later" holds until the next version, not for ever
    if (! notice || remembered() === version) return;

    const title = notice.querySelector('[data-update-title]');
    const text = notice.querySelector('[data-update-text]');

    title.textContent = fill(title.dataset.template, { version });
    text.textContent = fill(text.dataset.idle, { current });
    notice.dataset.version = version;
    delete notice.dataset.state;
    notice.querySelector('[data-update-actions]').hidden = false;
    notice.hidden = false;
};

export const updateStatus = (state, detail = '') => {
    const notice = node();

    if (! notice) return;

    const text = notice.querySelector('[data-update-text]');
    const busy = state !== 'failed';

    notice.hidden = false;
    notice.dataset.state = state;
    text.textContent = fill(text.dataset[state] ?? '', { reason: detail });

    // while it works there is nothing to click; a failure brings the buttons back for a retry
    notice.querySelector('[data-update-actions]').hidden = busy;
};

export function updateNotice() {
    document.addEventListener('click', (event) => {
        const notice = event.target.closest('[data-update]');

        if (! notice) return;

        if (event.target.closest('[data-update-install]')) {
            updateStatus('download');
            send('install');
        } else if (event.target.closest('[data-update-notes]')) {
            send('notes');
        } else if (event.target.closest('[data-update-later]')) {
            try {
                localStorage.setItem(DISMISSED, notice.dataset.version ?? '');
            } catch {
                // without storage "later" lasts until the next page
            }

            notice.hidden = true;
        }
    });
}
