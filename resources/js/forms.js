import { toast } from './toast';

/* The small behaviours forms carry as data attributes. All delegated, none hold a node. */
export function formHelpers() {
    document.querySelectorAll('[data-autohide]').forEach((element) => {
        setTimeout(() => {
            element.style.transition = 'opacity 400ms ease, transform 400ms ease';
            element.style.opacity = '0';
            element.style.transform = 'translateY(-6px)';
            setTimeout(() => element.remove(), 420);
        }, Number(element.dataset.autohide));
    });

    document.addEventListener('change', (event) => {
        const field = event.target;

        if (field?.dataset?.autosave !== undefined && field.form) {
            field.form.requestSubmit();
        }
    });

    /* The home-office period: the range fields fold out, the day windows submit straight away. */
    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-period-toggle]');

        if (! toggle) return;

        const fields = toggle.closest('[data-period]')?.querySelector('[data-period-fields]');

        fields?.classList.toggle('hidden');
        fields?.querySelector('input[name="from"]')?.focus();
    });

    /*
     * A button carrying data-fill writes its values into the fields of its own form, by name.
     * That is "book like last time": the server knows the shape of the last booked day, the page
     * only has to put it in the fields — and the values stay editable before anything is sent.
     */
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-fill]');

        if (! trigger) return;

        event.preventDefault();

        let values = {};

        try {
            values = JSON.parse(trigger.dataset.fill);
        } catch {
            return;
        }

        const form = trigger.closest('form');

        Object.entries(values).forEach(([name, value]) => {
            // the date belongs to the day being booked, not to the day it was copied from
            if (name === 'date') return;

            const field = form?.elements.namedItem(name);

            if (field) field.value = value;
        });

        form?.querySelector('[name="work_starts_at"]')?.focus();
        toast(trigger.dataset.fillLabel || '');
    });

    /* the dialog's two modes: one selection of days, two things to do with it */
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dialog-mode] [data-mode]');

        if (! button) return;

        const wanted = button.dataset.mode;

        button.closest('[data-dialog-mode]').querySelectorAll('[data-mode]').forEach((other) => {
            other.classList.toggle('segment-active', other === button);
        });

        document.querySelectorAll('[data-mode-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.modePanel !== wanted);
        });
    });

    /* the scatter slider says what it means in minutes, not as a number nobody can picture */
    document.addEventListener('input', (event) => {
        if (! event.target.matches('[data-scatter]')) return;

        const label = event.target.closest('label')?.querySelector('[data-scatter-value]');

        if (label) label.textContent = `± ${event.target.value} min`;
    });

    /*
     * The back-dated start time belongs to both start buttons. The work form owns the field (via
     * form=), and the break form carries a mirror of it.
     */
    document.addEventListener('input', (event) => {
        if (! event.target.matches('[data-backdate]')) return;

        document.querySelectorAll('[data-backdate-mirror]').forEach((field) => {
            field.value = event.target.value;
        });
    });

    /*
     * A form marked data-busy says so while it works. Without it the slow actions on this app —
     * asking two package registries, starting an update — look like a button that did nothing, and
     * the second click starts the work twice.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-busy]');

        if (! form) return;

        const button = form.querySelector('button[type="submit"], button:not([type])');

        if (! button || button.disabled) return;

        const label = button.querySelector('[data-busy-label]');

        button.disabled = true;
        button.dataset.busy = '';

        if (label) {
            label.dataset.idle = label.textContent;
            label.textContent = label.dataset.busyLabel;
        }

        /*
         * A live form swaps its region and this button goes away with it; a plain one navigates.
         * Either way the state is released after a moment, so a refused request does not leave a
         * dead button behind.
         */
        setTimeout(() => {
            button.disabled = false;
            delete button.dataset.busy;

            if (label && label.dataset.idle) label.textContent = label.dataset.idle;
        }, 30_000);
    });
}
