/*
 * The two watches read their data from the page every minute instead of once at boot: both
 * JSON nodes describe the state the server last rendered, and a region swap can bring newer
 * to-dos or a newer timer without a reload.
 */
const seenSet = (key) => new Set(JSON.parse(localStorage.getItem(key) || '[]'));

const granted = () => 'Notification' in window && Notification.permission === 'granted';

const checkDue = () => {
    const node = document.querySelector('[data-due-watch]');

    if (! node || ! granted()) {
        return;
    }

    const seenKey = 'takt.notified';
    const seen = seenSet(seenKey);
    const now = Date.now();

    JSON.parse(node.textContent || '[]').forEach((todo) => {
        const due = Date.parse(todo.due);
        const lead = Math.max(todo.lead, 0) * 60_000;
        const key = `${todo.id}:${todo.due}`;

        if (Number.isNaN(due) || seen.has(key) || now < due - lead) {
            return;
        }

        const label = now >= due ? 'Überfällig' : 'Bald fällig';

        new Notification(`${label}: ${todo.title}`, {
            body: new Date(due).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }),
            icon: '/icons/icon-192.png',
            tag: key,
        });

        seen.add(key);
        localStorage.setItem(seenKey, JSON.stringify([...seen].slice(-200)));
    });
};

const checkWork = () => {
    const node = document.querySelector('[data-work-watch]');

    if (! node || ! granted()) {
        return;
    }

    const watch = JSON.parse(node.textContent || '{}');
    const labels = node.dataset;
    const seenKey = `takt.worknotified.${watch.day}`;
    const seen = seenSet(seenKey);

    const since = watch.since ? Date.parse(watch.since) : null;
    const running = since ? Math.max(0, (Date.now() - since) / 1000) : 0;
    const work = watch.work + running;

    const fire = (key, title, body) => {
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        localStorage.setItem(seenKey, JSON.stringify([...seen]));

        new Notification(title, { body, icon: '/icons/icon-192.png', tag: `${watch.day}:${key}` });
    };

    if (watch.target > 0 && work >= watch.target) {
        fire('target', labels.labelTarget, labels.bodyTarget);
    }

    if (work > 21_600 && watch.break < 1_800) {
        fire('break', labels.labelBreak, labels.bodyBreak);
    }

    if (work >= 34_200) {
        fire('max', labels.labelMax, labels.bodyMax);
    }
};

const paint = () => {
    const status = document.querySelector('[data-notify-status]');

    if (! status) {
        return;
    }

    const state = 'Notification' in window
        ? Notification.permission.charAt(0).toUpperCase() + Notification.permission.slice(1)
        : 'Unsupported';

    status.textContent = status.dataset[`state${state}`] ?? status.dataset.stateDefault ?? '';
};

export function notifications() {
    if ('Notification' in window) {
        checkDue();
        checkWork();
        setInterval(() => { checkDue(); checkWork(); }, 60_000);
    }

    paint();
    document.addEventListener('takt:swapped', paint);

    document.addEventListener('click', (event) => {
        if (! event.target.closest('[data-notify-request]') || ! ('Notification' in window)) {
            return;
        }

        Notification.requestPermission().then(paint);
    });
}
