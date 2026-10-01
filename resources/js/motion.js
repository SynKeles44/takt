import autoAnimate from '@formkit/auto-animate';
import { animate } from 'motion/mini';

import { rememberMarkerPosition, takeMarkerHandover } from './marker';

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

/**
 * The sliding marker behind a segmented control. It is one element that moves between the
 * segments rather than a border on each — so switching reads as the same marker travelling,
 * which is what makes a selection feel chosen instead of redrawn.
 */
export function segmentedIndicator() {
    document.querySelectorAll('.segmented').forEach((group) => {
        if (group.dataset.indicator !== undefined) return;

        group.dataset.indicator = '';

        const marker = document.createElement('span');

        marker.className = 'segment-marker';
        marker.setAttribute('aria-hidden', 'true');
        group.prepend(marker);

        const place = (animated) => {
            const active = group.querySelector('.segment-active');

            if (! active) {
                marker.style.opacity = '0';

                return;
            }

            const box = active.getBoundingClientRect();
            const host = group.getBoundingClientRect();

            const next = {
                opacity: 1,
                transform: `translateX(${box.left - host.left}px)`,
                width: `${box.width}px`,
                height: `${box.height}px`,
            };

            if (animated && ! calm()) {
                animate(marker, next, { type: 'spring', stiffness: 420, damping: 32 });
            } else {
                Object.assign(marker.style, {
                    opacity: '1',
                    transform: next.transform,
                    width: next.width,
                    height: next.height,
                });
            }
        };

        place(false);

        /*
         * A segmented control whose segments are links navigates, and then the animation started
         * on the press is thrown away with the old document — the same failure the navigation rows
         * had. A keyed group hands its position to the next page instead, through the same storage
         * the row markers use, and arrives travelling rather than already in place.
         */
        const key = group.dataset.markerKey ? `seg:${group.dataset.markerKey}` : null;
        const from = key ? takeMarkerHandover(key) : null;

        if (from && ! calm()) {
            const now = marker.getBoundingClientRect();
            const shift = from.left - now.left;

            if (shift !== 0) {
                marker.animate(
                    [
                        { transform: `${marker.style.transform} translateX(${shift}px)`, width: `${from.width}px` },
                        { transform: marker.style.transform, width: marker.style.width },
                    ],
                    { duration: 380, easing: 'cubic-bezier(0.34, 1.4, 0.64, 1)', fill: 'none' },
                );
            }
        }

        group.addEventListener('pointerdown', (event) => {
            const segment = event.target.closest('.segment');

            if (! segment || segment === group.querySelector('.segment-active')) return;

            if (key) {
                rememberMarkerPosition(key, marker.getBoundingClientRect());
            }

            // move the marker on the press, not on the reload the click may trigger
            group.querySelectorAll('.segment-active').forEach((other) => other.classList.remove('segment-active'));
            segment.classList.add('segment-active');
            place(true);
        });

        // the marker is positioned, so it has to follow a resize
        window.addEventListener('resize', () => place(false), { passive: true });
    });
}
