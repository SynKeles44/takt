import { toast } from './toast';

/*
 * The text of a group of pull requests, built from the ticked boxes: one heading per group,
 * its links below it, a blank line between groups, and a group nobody ticked left out. The
 * server renders the same shape into data-copy for the full set — this narrows it to the
 * selection at the moment of the click.
 */
const withTitles = () => document.querySelector('[data-copy-titles]')?.checked === true;

// with titles a pull request takes two lines, and the pairs are spaced apart
const pullLine = (url, title) => (withTitles() && title ? `${title}\n${url}` : url);

const selectionText = (root) => {
    const groups = root.matches('[data-pull-group]') ? [root] : [...root.querySelectorAll('[data-pull-group]')];
    const separator = withTitles() ? '\n\n' : '\n';

    return groups
        .map((group) => {
            const picked = [...group.querySelectorAll('[data-pull-pick]')]
                .filter((box) => box.checked)
                .map((box) => pullLine(box.value, box.dataset.title));

            return picked.length === 0 ? null : `${group.dataset.copyHeading ?? ''}:\n${picked.join(separator)}`;
        })
        .filter(Boolean)
        .join('\n\n');
};

const fallback = (text, done) => {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;left:-9999px';
    document.body.append(field);
    field.select();

    try {
        document.execCommand('copy');
        done();
    } catch {
        // nothing we can do without clipboard access
    }

    field.remove();
};

// anything carrying data-copy puts its content on the clipboard
export function copyButtons() {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-copy]');

        if (! trigger) {
            return;
        }

        event.preventDefault();

        const scope = trigger.dataset.copyScope;
        let text = trigger.dataset.copy ?? '';

        if (scope === 'pull') {
            text = pullLine(text, trigger.dataset.copyTitle);
        } else if (scope) {
            const root = scope === 'group' ? trigger.closest('[data-pull-group]') : trigger.closest('.surface');

            text = selectionText(root ?? document);

            if (text === '') {
                toast(trigger.dataset.copyEmpty || '');

                return;
            }
        }

        const done = () => {
            toast(trigger.dataset.copyLabel || 'Kopiert.');

            const ping = trigger.dataset.copyPing;

            if (ping) {
                fetch(ping, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                }).catch(() => {});
            }
        };

        if (navigator.clipboard?.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(() => fallback(text, done));

            return;
        }

        fallback(text, done);
    });
}
