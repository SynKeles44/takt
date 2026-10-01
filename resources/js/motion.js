import autoAnimate from '@formkit/auto-animate';
import { animate } from 'motion/mini';

/**
 * The motion layer.
 *
 * Two small libraries rather than one large one, and both framework-free because this app has no
 * framework: `motion/mini` is the Web Animations API with a spring and a stagger on top — the
 * browser runs the animation off the main thread — and `auto-animate` handles the case that is
 * genuinely hard by hand, a list whose items appear, vanish or reorder.
 *
 * Everything here is an enhancement. With reduced motion on, or with the import failed, every
 * element is in its final state and the page works — nothing waits for an animation to finish.
 */

const calm = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 * inView and stagger live in the full `motion` bundle; animate alone is in `motion/mini`. Pulling
 * the full bundle for an IntersectionObserver wrapper and a multiplication would cost more than
 * both helpers are worth, so they are the fifteen lines below and the import stays mini.
 */
const inView = (element, run, options = {}) => {
    if (! ('IntersectionObserver' in window)) {
        run();

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (! entry.isIntersecting) return;

            observer.unobserve(entry.target);
            run(entry.target);
        });
    }, { rootMargin: options.margin ?? '0px', threshold: 0 });

    observer.observe(element);
};

/** The project's motion tokens, read from CSS so there is one source for duration and easing. */
const token = (name, fallback) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();

    return value === '' ? fallback : value;
};

const seconds = (name, fallback) => {
    const value = token(name, '');

    if (value.endsWith('ms')) return parseFloat(value) / 1000;
    if (value.endsWith('s')) return parseFloat(value);

    return fallback;
};

export function motionLayer() {
    if (calm()) return;

    const duration = seconds('--dur', 0.28);
    const ease = [0.22, 1, 0.36, 1];

    /*
     * Cards and rows arrive when they are scrolled to, not when the page loads: animating what
     * nobody has looked at yet spends frames on nothing. inView fires once per element.
     */
    document.querySelectorAll('[data-reveal]').forEach((element) => {
        element.style.opacity = '0';

        inView(element, () => {
            animate(
                element,
                { opacity: [0, 1], transform: ['translateY(8px)', 'translateY(0px)'] },
                { duration, easing: ease },
            );

            // clear the inline styles so nothing keeps a compositing layer alive afterwards
            setTimeout(() => {
                element.style.opacity = '';
                element.style.transform = '';
            }, duration * 1000 + 60);
        }, { margin: '0px 0px -10% 0px' });
    });

    /* A list that changes — filtered, sorted, a row removed — moves instead of jumping. */
    document.querySelectorAll('[data-auto-animate]').forEach((list) => {
        autoAnimate(list, { duration: duration * 1000, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' });
    });

    /*
     * A number that counts up is readable while it moves only if it is short. Anything above
     * four digits is set directly — a counter racing through six digits is decoration, not
     * information.
     */
    document.querySelectorAll('[data-count]').forEach((element) => {
        const target = Number(element.dataset.count);

        if (! Number.isFinite(target) || Math.abs(target) > 9999) return;

        inView(element, () => {
            animate(0, target, {
                duration: Math.min(0.9, 0.25 + Math.abs(target) / 200),
                easing: ease,
                onUpdate: (value) => { element.textContent = Math.round(value).toString(); },
            });
        });
    });

    /* Staggered entrance for a group that arrives together, capped so a long list stays cheap. */
    document.querySelectorAll('[data-stagger]').forEach((group) => {
        const children = [...group.children].slice(0, 12);

        if (children.length === 0) return;

        inView(group, () => {
            children.forEach((child, index) => {
                animate(
                    child,
                    { opacity: [0, 1], transform: ['translateY(6px)', 'translateY(0px)'] },
                    { duration, delay: index * 0.04, easing: ease },
                );
            });
        });
    });
}
