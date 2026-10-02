/*
 * The confirm dialog. Nothing in here holds on to a node: the dialog is looked up when a question
 * is asked and the buttons are found through the click that lands on them, so the dialog keeps
 * working no matter what was swapped around it in the meantime.
 */
let pending = null;

const node = () => document.querySelector('[data-dialog]');

const isOpen = (dialog) => dialog !== null && ! dialog.classList.contains('hidden');

const close = (answer) => {
    const dialog = node();

    if (dialog) {
        dialog.classList.add('hidden');
        dialog.classList.remove('flex');
    }

    const resolve = pending;
    pending = null;
    resolve?.(answer);
};

export const askConfirm = (message) => new Promise((resolve) => {
    const dialog = node();

    if (! dialog) {
        resolve(window.confirm(message));

        return;
    }

    pending = resolve;
    dialog.querySelector('[data-dialog-message]').textContent = message;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    dialog.querySelector('[data-dialog-accept]')?.focus();
});

export function confirmDialog() {
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-dialog-accept]')) {
            close(true);
        } else if (event.target.closest('[data-dialog-cancel]')) {
            close(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (! isOpen(node())) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            close(false);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            close(true);
        }
    });
}
