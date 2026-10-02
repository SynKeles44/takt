const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const post = (url, payload) => fetch(url, {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf(),
        'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify(payload),
});

/*
 * The surface the app shell talks to. The menu bar item needs the timer state and the two
 * actions — and it gets them through the page, not through a new endpoint: the forms the
 * command palette already carries bring their own CSRF token and their own live handling, so
 * the shell never has to authenticate on its own.
 */
export function shellApi() {
    window.takt = {
        state() {
            const node = document.querySelector('[data-shell-state]');

            if (! node) return { signedIn: false };

            try {
                return { signedIn: true, awayUrl: node.dataset.awayUrl, ...JSON.parse(node.textContent || '{}') };
            } catch {
                return { signedIn: true, awayUrl: node.dataset.awayUrl };
            }
        },

        start(type) {
            document.querySelector(`#palette-${type === 'break' ? 'break' : 'work'}`)?.requestSubmit?.();
        },

        stop() {
            document.querySelector('#palette-stop')?.requestSubmit?.();
        },

        /** The shell hands over what it observed; the server drops it when the trail is off. */
        reportActivity(spans) {
            const url = document.querySelector('[data-shell-state]')?.dataset.trailUrl;

            if (! url || ! Array.isArray(spans) || spans.length === 0) return Promise.resolve();

            return post(url, { spans }).catch(() => {});
        },

        /** The shell hands over the Mac's calendar for a day; the page keeps it for the widget. */
        reportCalendar(day, events) {
            const url = document.querySelector('[data-shell-state]')?.dataset.calendarUrl;

            if (! url) return Promise.resolve();

            return post(url, { day, events }).catch(() => {});
        },

        /*
         * The shell reports a lock or sleep once the Mac is back. It travels through the page so the
         * session and the CSRF token are the ones the user already has — the shell holds no
         * credentials of its own.
         */
        reportAway(from, to, url) {
            return post(url, { from, to })
                .then((response) => response.json())
                .then((answer) => {
                    // a recorded gap only shows up on the next render, so pull the page back in
                    if (answer?.recorded) window.location.reload();

                    return answer;
                })
                .catch(() => ({ recorded: false }));
        },
    };

    // a service worker from an earlier version would keep serving cached pages
    navigator.serviceWorker?.getRegistrations?.().then((registrations) => {
        registrations.forEach((registration) => registration.unregister());
    }).catch(() => {});
}
