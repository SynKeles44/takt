/*
 * Flip-through picker, used for the design style and the colour scheme alike: the slides are all
 * in the page, blättern only swaps which one is visible and keeps the URL, the labels and the
 * confirm button in step with it. Which slide is current is read off the page, so the picker
 * has no state of its own to lose when the settings page is swapped around it.
 */
const show = (carousel, target) => {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const param = carousel.dataset.param;
    const previous = Math.max(0, slides.findIndex((slide) => ! slide.classList.contains('is-off')));
    const current = (target + slides.length) % slides.length;

    slides.forEach((slide, position) => {
        const off = position !== current;

        slide.classList.toggle('is-off', off);
        slide.toggleAttribute('aria-hidden', off);
    });

    const slide = slides[current];
    const value = slide.dataset.slide;
    const forward = (current - previous + slides.length) % slides.length === 1;

    slide.classList.remove('slide-in-left', 'slide-in-right');
    void slide.offsetWidth;
    slide.classList.add(forward ? 'slide-in-right' : 'slide-in-left');

    const name = carousel.querySelector('[data-slide-name]');
    const text = carousel.querySelector('[data-slide-text]');
    const index = carousel.querySelector('[data-slide-index]');
    const form = carousel.querySelector('[data-slide-form]');
    // never `input[type=hidden]`: that is the CSRF token, and overwriting it broke the submit
    const field = form?.querySelector('[data-slide-value]');

    if (name) name.textContent = slide.dataset.label;
    if (text) text.textContent = slide.dataset.description;
    if (index) index.textContent = slide.dataset.position;
    if (field) field.value = value;

    const isActive = value === carousel.dataset.active;
    form?.classList.toggle('hidden', isActive);
    carousel.querySelector('[data-slide-active]')?.classList.toggle('hidden', ! isActive);

    carousel.querySelectorAll('[data-step]').forEach((step) => {
        const offset = Number(step.dataset.step);
        const neighbour = slides[(current + offset + slides.length) % slides.length];
        step.href = step.href.replace(new RegExp(`${param}=[^&]*`), `${param}=${neighbour.dataset.slide}`);
    });

    const url = new URL(window.location.href);
    url.searchParams.set(param, value);
    window.history.replaceState({}, '', url);
};

export function carousel() {
    document.addEventListener('click', (event) => {
        const step = event.target.closest('[data-carousel] [data-step]');

        if (! step) return;

        event.preventDefault();

        const host = step.closest('[data-carousel]');
        const slides = [...host.querySelectorAll('[data-slide]')];
        const current = Math.max(0, slides.findIndex((slide) => ! slide.classList.contains('is-off')));

        show(host, current + Number(step.dataset.step));
    });
}
