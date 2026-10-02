/*
 * MARK: frame probe — ?fps=1
 *
 * Scroll smoothness cannot be guessed at from outside the app: the window runs WKWebView, the
 * numbers a Chromium tab reports do not transfer, and a hidden tab reports nothing at all. So
 * the measurement lives here, in the engine that actually stutters, behind a URL parameter that
 * costs nothing when it is absent.
 *
 * It reports the honest figure, which is not the average: a frame budget is 16.7 ms, and what is
 * felt as stutter is the worst frames, not the mean. Hence p95 and the count over budget.
 */
export function frameProbe() {
    if (! new URLSearchParams(location.search).has('fps')) return;

    const box = document.createElement('div');

    box.setAttribute('style', [
        'position: fixed', 'inset-block-start: 0.5rem', 'inset-inline-end: 0.5rem',
        'z-index: 99999', 'padding: 0.5rem 0.7rem', 'border-radius: 0.5rem',
        'background: rgb(0 0 0 / 0.82)', 'color: #fff', 'font: 500 11px/1.5 ui-monospace, monospace',
        'white-space: pre', 'pointer-events: none', 'text-align: end',
    ].join(';'));

    document.body.append(box);

    const budget = 1000 / 60;
    let frames = [];
    let last = performance.now();
    let scrolling = 0;

    // only frames produced while the view is actually moving say anything about scrolling
    addEventListener('scroll', () => { scrolling = performance.now(); }, { passive: true });

    const sample = () => {
        const now = performance.now();
        const delta = now - last;
        last = now;

        if (now - scrolling < 120) {
            frames.push(delta);
            if (frames.length > 240) frames.shift();
        }

        if (frames.length > 20) {
            const sorted = [...frames].sort((a, b) => a - b);
            const p95 = sorted[Math.floor(sorted.length * 0.95)];
            const over = frames.filter((f) => f > budget * 1.5).length;

            box.textContent = [
                `median ${sorted[Math.floor(sorted.length / 2)].toFixed(1)} ms`,
                `p95    ${p95.toFixed(1)} ms`,
                `worst  ${Math.max(...frames).toFixed(1)} ms`,
                `>25ms  ${over} / ${frames.length}`,
            ].join('\n');
        } else {
            box.textContent = 'scrolle …';
        }

        requestAnimationFrame(sample);
    };

    requestAnimationFrame(sample);

    // a double click on the badge starts a fresh window
    addEventListener('dblclick', () => { frames = []; });
}
