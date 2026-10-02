const pad = (value) => String(value).padStart(2, '0');

const asClock = (seconds) =>
    `${pad(Math.floor(seconds / 3600))}:${pad(Math.floor((seconds % 3600) / 60))}:${pad(seconds % 60)}`;

const asHuman = (seconds) => {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    return hours === 0 ? `${minutes}m` : `${hours}h ${pad(minutes)}m`;
};

const tick = () => {
    const now = Date.now();

    document.querySelectorAll('[data-since]').forEach((element) => {
        const since = Date.parse(element.dataset.since);

        if (Number.isNaN(since)) {
            return;
        }

        const base = Number(element.dataset.base ?? 0);
        const seconds = Math.max(0, base + Math.floor((now - since) / 1000));

        const text = element.dataset.format === 'human' ? asHuman(seconds) : asClock(seconds);

        // writing the same string still costs a layout; the human format changes once a minute
        if (element.textContent !== text) {
            element.textContent = text;
        }
    });
};

/*
 * The interval runs unconditionally: a page loaded while nothing was running has no
 * [data-since] yet, and the timer arrives later through a region swap without a reload.
 * A tick that finds no element costs nothing; a missing interval leaves the clock frozen.
 */
export function clock() {
    tick();
    setInterval(tick, 1000);
}
