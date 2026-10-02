export const toast = (text) => {
    let host = document.querySelector('[data-toast-host]');

    if (! host) {
        host = document.createElement('div');
        host.dataset.toastHost = '';
        host.className = 'pointer-events-none fixed inset-x-4 bottom-4 z-50 flex justify-center sm:inset-x-auto sm:bottom-6 sm:right-6 sm:justify-end';
        document.body.append(host);
    }

    host.replaceChildren();

    const box = document.createElement('div');
    box.className = 'toast pointer-events-auto';
    box.setAttribute('role', 'status');
    box.textContent = text;
    host.append(box);

    setTimeout(() => {
        box.style.transition = 'opacity .22s ease, transform .22s ease';
        box.style.opacity = '0';
        box.style.transform = 'translateY(0.4rem)';
        setTimeout(() => box.remove(), 240);
    }, 2600);
};
