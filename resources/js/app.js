import { createBoard } from './board';
import { dayRange } from './day-range';
import { commandRunner } from './command-runner';
import { docker } from './docker';
import { folderPicker } from './folder-picker';

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
tick();
setInterval(tick, 1000);

const dialog = document.querySelector('[data-dialog]');
const dialogMessage = dialog?.querySelector('[data-dialog-message]');
const dialogAccept = dialog?.querySelector('[data-dialog-accept]');
let dialogResolve = null;

const closeDialog = (answer) => {
    if (! dialog) {
        return;
    }

    dialog.classList.add('hidden');
    dialog.classList.remove('flex');

    const resolve = dialogResolve;
    dialogResolve = null;
    resolve?.(answer);
};

const askConfirm = (message) => new Promise((resolve) => {
    if (! dialog) {
        resolve(window.confirm(message));

        return;
    }

    dialogResolve = resolve;
    dialogMessage.textContent = message;
    dialog.classList.remove('hidden');
    dialog.classList.add('flex');
    dialogAccept.focus();
});

if (dialog) {
    dialog.querySelectorAll('[data-dialog-cancel]').forEach((trigger) => {
        trigger.addEventListener('click', () => closeDialog(false));
    });

    dialogAccept.addEventListener('click', () => closeDialog(true));

    document.addEventListener('keydown', (event) => {
        if (dialog.classList.contains('hidden')) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog(false);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            closeDialog(true);
        }
    });
}

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

/*
 * Flip-through picker, used for the design style and the colour scheme alike: the
 * slides are all in the page, blättern only swaps which one is visible and keeps the
 * URL, the labels and the confirm button in step with it.
 */
document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-slide]')];
    const steps = [...carousel.querySelectorAll('[data-step]')];
    const name = carousel.querySelector('[data-slide-name]');
    const text = carousel.querySelector('[data-slide-text]');
    const index = carousel.querySelector('[data-slide-index]');
    const form = carousel.querySelector('[data-slide-form]');
    const activeBadge = carousel.querySelector('[data-slide-active]');
    // never `input[type=hidden]`: that is the CSRF token, and overwriting it broke the submit
    const field = form?.querySelector('[data-slide-value]');
    const param = carousel.dataset.param;

    let current = Math.max(0, slides.findIndex((slide) => ! slide.classList.contains('is-off')));

    const show = (target) => {
        const previous = current;
        current = (target + slides.length) % slides.length;

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

        if (name) name.textContent = slide.dataset.label;
        if (text) text.textContent = slide.dataset.description;
        if (index) index.textContent = slide.dataset.position;
        if (field) field.value = value;

        const isActive = value === carousel.dataset.active;
        form?.classList.toggle('hidden', isActive);
        activeBadge?.classList.toggle('hidden', ! isActive);

        steps.forEach((step) => {
            const offset = Number(step.dataset.step);
            const neighbour = slides[(current + offset + slides.length) % slides.length];
            step.href = step.href.replace(new RegExp(`${param}=[^&]*`), `${param}=${neighbour.dataset.slide}`);
        });

        const url = new URL(window.location.href);
        url.searchParams.set(param, value);
        window.history.replaceState({}, '', url);
    };

    steps.forEach((step) => {
        step.addEventListener('click', (event) => {
            event.preventDefault();
            show(current + Number(step.dataset.step));
        });
    });
});

const palette = document.querySelector('[data-palette]');

if (palette) {
    const input = palette.querySelector('[data-palette-input]');
    const staticItems = [...palette.querySelectorAll('[data-palette-item]')];
    const results = palette.querySelector('[data-palette-results]');
    const template = palette.querySelector('[data-palette-template]');
    const scroll = palette.querySelector('[data-palette-scroll]');
    const empty = palette.querySelector('[data-palette-empty]');
    const endpoint = scroll?.dataset.paletteSearch;
    let active = 0;
    let lookup;
    let request = 0;

    const items = () => [...palette.querySelectorAll('[data-palette-item]')];
    const visible = () => items().filter((item) => ! item.classList.contains('hidden'));

    const mark = () => {
        const shown = visible();
        active = Math.max(0, Math.min(active, shown.length - 1));

        items().forEach((item) => item.querySelector('a, button')?.removeAttribute('data-active'));

        const target = shown[active]?.querySelector('a, button');
        target?.setAttribute('data-active', '');
        target?.scrollIntoView({ block: 'nearest' });
    };

    const render = (rows) => {
        results.replaceChildren();

        rows.forEach((row) => {
            const node = template.content.firstElementChild.cloneNode(true);
            node.dataset.label = row.label.toLowerCase();

            const link = node.querySelector('a');

            if (row.copy) {
                link.href = '#';
                link.dataset.copy = row.copy;

                if (row.ping) {
                    link.dataset.copyPing = row.ping;
                }
            } else {
                link.href = row.url;
            }
            node.querySelector('[data-slot="label"]').textContent = row.label;
            node.querySelector('[data-slot="hint"]').textContent = row.hint ?? '';
            node.querySelector('[data-slot="group"]').textContent = row.group;
            results.append(node);
        });

        results.classList.toggle('hidden', rows.length === 0);
        empty?.classList.toggle('hidden', visible().length > 0);
        mark();
    };

    const search = (needle) => {
        if (! endpoint || needle.length < 2) {
            render([]);

            return;
        }

        const ticket = ++request;

        fetch(`${endpoint}?q=${encodeURIComponent(needle)}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : { results: [] }))
            .then((payload) => {
                if (ticket === request) {
                    render(payload.results ?? []);
                }
            })
            .catch(() => {});
    };

    const filter = () => {
        const needle = input.value.trim().toLowerCase();

        staticItems.forEach((item) => {
            item.classList.toggle('hidden', needle !== '' && ! item.dataset.label.includes(needle));
        });

        empty?.classList.toggle('hidden', visible().length > 0);
        active = 0;
        mark();

        clearTimeout(lookup);
        lookup = setTimeout(() => search(needle), 180);
    };

    const open = () => {
        palette.classList.remove('hidden');
        palette.classList.add('flex');
        input.value = '';
        render([]);
        filter();
        input.focus();
    };

    const close = () => {
        palette.classList.add('hidden');
        palette.classList.remove('flex');
    };

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            palette.classList.contains('hidden') ? open() : close();

            return;
        }

        if (palette.classList.contains('hidden')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            active = (active + 1) % Math.max(1, visible().length);
            mark();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            const shown = Math.max(1, visible().length);
            active = (active - 1 + shown) % shown;
            mark();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            visible()[active]?.querySelector('a, button')?.click();
        }
    });

    input.addEventListener('input', filter);
    palette.querySelectorAll('[data-palette-close]').forEach((trigger) => trigger.addEventListener('click', close));
    document.querySelectorAll('[data-palette-open]').forEach((trigger) => trigger.addEventListener('click', open));
}


const dueWatchNode = document.querySelector('[data-due-watch]');

if (dueWatchNode && 'Notification' in window) {
    const watched = JSON.parse(dueWatchNode.textContent || '[]');
    const seenKey = 'takt.notified';
    const seen = new Set(JSON.parse(localStorage.getItem(seenKey) || '[]'));

    const remember = (key) => {
        seen.add(key);
        localStorage.setItem(seenKey, JSON.stringify([...seen].slice(-200)));
    };

    const check = () => {
        if (Notification.permission !== 'granted') {
            return;
        }

        const now = Date.now();

        watched.forEach((todo) => {
            const due = Date.parse(todo.due);
            const window = Math.max(todo.lead, 0) * 60_000;
            const key = `${todo.id}:${todo.due}`;

            if (Number.isNaN(due) || seen.has(key) || now < due - window) {
                return;
            }

            const label = now >= due ? 'Überfällig' : 'Bald fällig';

            new Notification(`${label}: ${todo.title}`, {
                body: new Date(due).toLocaleString('de-DE', { dateStyle: 'medium', timeStyle: 'short' }),
                icon: '/icons/icon-192.png',
                tag: key,
            });

            remember(key);
        });
    };

    check();
    setInterval(check, 60_000);
}

/*
 * The surface the app shell talks to. The menu bar item needs the timer state and the two
 * actions — and it gets them through the page, not through a new endpoint: the forms the
 * command palette already carries bring their own CSRF token and their own live handling, so
 * the shell never has to authenticate on its own.
 */
window.takt = {
    state() {
        const node = document.querySelector('[data-shell-state]');

        if (! node) return { signedIn: false };

        try {
            return { signedIn: true, awayUrl: node.dataset.awayUrl, ...JSON.parse(node.textContent || '{}') };
        } catch {
            return { signedIn: true, awayUrl: node.dataset.awayUrl };
        }
    },

    start(type) {
        document.querySelector(`#palette-${type === 'break' ? 'break' : 'work'}`)?.requestSubmit?.();
    },

    stop() {
        document.querySelector('#palette-stop')?.requestSubmit?.();
    },

    /** The shell hands over what it observed; the server drops it when the trail is off. */
    reportActivity(spans) {
        const url = document.querySelector('[data-shell-state]')?.dataset.trailUrl;

        if (! url || ! Array.isArray(spans) || spans.length === 0) return Promise.resolve();

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ spans }),
        }).catch(() => {});
    },

    /** The shell hands over the Mac's calendar for a day; the page keeps it for the widget. */
    reportCalendar(day, events) {
        const url = document.querySelector('[data-shell-state]')?.dataset.calendarUrl;

        if (! url) return Promise.resolve();

        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ day, events }),
        }).catch(() => {});
    },

    /*
     * The shell reports a lock or sleep once the Mac is back. It travels through the page so the
     * session and the CSRF token are the ones the user already has — the shell holds no
     * credentials of its own.
     */
    reportAway(from, to, url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ from, to }),
        })
            .then((response) => response.json())
            .then((answer) => {
                // a recorded gap only shows up on the next render, so pull the page back in
                if (answer?.recorded) window.location.reload();

                return answer;
            })
            .catch(() => ({ recorded: false }));
    },
};

const workWatchNode = document.querySelector('[data-work-watch]');

if (workWatchNode && 'Notification' in window) {
    const watch = JSON.parse(workWatchNode.textContent || '{}');
    const labels = workWatchNode.dataset;
    const seenKey = `takt.worknotified.${watch.day}`;
    const seen = new Set(JSON.parse(localStorage.getItem(seenKey) || '[]'));

    const worked = () => {
        const since = watch.since ? Date.parse(watch.since) : null;
        const running = since ? Math.max(0, (Date.now() - since) / 1000) : 0;

        return watch.work + running;
    };

    const fire = (key, title, body) => {
        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        localStorage.setItem(seenKey, JSON.stringify([...seen]));

        new Notification(title, { body, icon: '/icons/icon-192.png', tag: `${watch.day}:${key}` });
    };

    const check = () => {
        if (Notification.permission !== 'granted') {
            return;
        }

        const work = worked();

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

    check();
    setInterval(check, 60_000);
}

document.querySelectorAll('[data-notify-request]').forEach((button) => {
    const status = document.querySelector('[data-notify-status]');

    const paint = () => {
        if (! status) {
            return;
        }

        status.textContent = status.dataset[`state${'Notification' in window ? Notification.permission.charAt(0).toUpperCase() + Notification.permission.slice(1) : 'Unsupported'}`]
            ?? status.dataset.stateDefault
            ?? '';
    };

    paint();

    button.addEventListener('click', () => {
        if (! ('Notification' in window)) {
            return;
        }

        Notification.requestPermission().then(paint);
    });
});

// a service worker from an earlier version would keep serving cached pages
navigator.serviceWorker?.getRegistrations?.().then((registrations) => {
    registrations.forEach((registration) => registration.unregister());
}).catch(() => {});

const NAV_SAFE = ['/login', '/registrieren', '/logout'];

/**
 * Swaps the marked regions of the current page for the ones in `html`. With `only` given,
 * exactly those regions are replaced — that is how paging through the days leaves the
 * reviews alone instead of fetching them from GitHub again.
 */
const calmed = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/*
 * A view transition makes a region swap read as one movement instead of a jump. It is opt-in by
 * capability and by preference: without startViewTransition, or with reduced motion asked for,
 * the swap happens exactly as it did before. The names are set per region, so only the parts
 * that actually changed animate — the rest of the page stays still.
 */
const withTransition = (mutate) => {
    if (! document.startViewTransition || calmed()) {
        mutate();

        return;
    }

    document.querySelectorAll('[data-region]').forEach((node) => {
        node.style.viewTransitionName = 'region-' + node.dataset.region;
    });

    const transition = document.startViewTransition(mutate);

    transition.finished
        .catch(() => {})
        .finally(() => {
            document.querySelectorAll('[data-region]').forEach((node) => {
                node.style.viewTransitionName = '';
            });

            delete document.documentElement.dataset.swapDirection;
        });
};

const swapRegions = (html, only = null) => {
    const doc = new DOMParser().parseFromString(html, 'text/html');

    // decided before anything moves, so the caller still gets a synchronous yes or no
    const pairs = [...document.querySelectorAll('[data-region]')]
        .filter((node) => only === null || only.includes(node.dataset.region))
        .map((node) => [node, doc.querySelector(`[data-region="${node.dataset.region}"]`)])
        .filter(([, fresh]) => fresh !== null);

    const swapped = pairs.length > 0;

    if (swapped) {
        // what actually changed, read before the swap so it can be pointed at afterwards
        const changed = pairs.flatMap(([node, fresh]) => {
            const before = [...node.querySelectorAll('.metric')].map((m) => m.textContent.trim());

            return [...fresh.querySelectorAll('.metric')]
                .map((metric, index) => (metric.textContent.trim() === before[index] ? null : metric))
                .filter(Boolean);
        });

        withTransition(() => {
            pairs.forEach(([node, fresh]) => {
                node.replaceWith(fresh);

                // the entrance animation belongs to a page load; an in-place update only fades
                fresh.querySelectorAll(':scope > *').forEach((child) => {
                    child.style.animation = 'none';
                });

                fresh.dataset.swapped = '';
                requestAnimationFrame(() => requestAnimationFrame(() => delete fresh.dataset.swapped));
            });
        });

        // a swapped-in region brings new collapsible blocks with it
        rememberBlocks();

        /*
         * A number that changed says so once. Without this a live update is silent: the value is
         * simply different afterwards and nothing tells the eye where to look.
         */
        if (! calmed()) {
            changed.forEach((metric) => {
                metric.dataset.changed = '';
                metric.addEventListener('animationend', () => delete metric.dataset.changed, { once: true });
            });
        }
    }

    const title = doc.querySelector('title')?.textContent;

    if (title) {
        document.title = title;
    }

    const openPalette = document.querySelector('[data-palette]:not(.hidden)');

    if (openPalette) {
        openPalette.classList.add('hidden');
        openPalette.classList.remove('flex');
    }

    const flash = doc.querySelector('[data-flash]')?.textContent?.trim();

    if (flash) {
        toast(flash);
    }

    return swapped;
};

const toast = (text) => {
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

const bump = (element, delta) => {
    if (element) {
        element.textContent = String(Math.max(0, Number(element.textContent) + delta));
    }
};

const dropItem = (item) => {
    const group = item.closest('[data-group]');

    item.setAttribute('data-leaving', '');
    item.style.maxHeight = `${item.offsetHeight}px`;

    setTimeout(() => {
        item.style.maxHeight = '0px';
        item.style.marginTop = '0px';
        item.style.overflow = 'hidden';
        item.style.transition = 'max-height .22s ease, margin .22s ease';
    }, 60);

    setTimeout(() => {
        item.remove();

        if (group && group.querySelectorAll('[data-item]').length === 0) {
            group.remove();
        }
    }, 300);

    bump(group?.querySelector('[data-group-count]'), -1);
};

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-async]');

    if (! form) {
        return;
    }

    event.preventDefault();

    const item = form.closest('[data-item]');
    const list = form.closest('[data-todo-list]');
    const wasDone = item?.dataset.done === '1';

    if (item) {
        item.dataset.done = wasDone ? '0' : '1';
    }

    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then((response) => (response.ok ? response.json() : Promise.reject(response)))
        .then((payload) => {
            if (payload.reload) {
                window.location.reload();

                return;
            }

            if (item) {
                item.dataset.done = payload.done ? '1' : '0';
            }

            if (payload.status) {
                toast(payload.status);
            }

            if (! item || item.hasAttribute('data-stay') || ! list) {
                return;
            }

            const filter = list.dataset.filter ?? 'open';

            bump(list.querySelector('[data-count="open"]'), payload.done ? -1 : 1);
            bump(list.querySelector('[data-count="done"]'), payload.done ? 1 : -1);

            if ((filter === 'open' && payload.done) || (filter === 'done' && ! payload.done)) {
                dropItem(item);
            }
        })
        .catch(() => window.location.reload());
});

document.querySelectorAll('[data-nav-toggle]').forEach((trigger) => {
    trigger.addEventListener('click', () => {
        const collapsed = document.documentElement.dataset.nav === 'collapsed';

        if (collapsed) {
            delete document.documentElement.dataset.nav;
            localStorage.removeItem('takt.nav');
        } else {
            document.documentElement.dataset.nav = 'collapsed';
            localStorage.setItem('takt.nav', 'collapsed');
        }

        trigger.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
    });
});

const accountMenu = document.querySelector('[data-account-menu]');
const accountToggle = document.querySelector('[data-account-toggle]');

if (accountMenu && accountToggle) {
    const place = () => {
        const anchor = accountToggle.getBoundingClientRect();
        const menu = accountMenu.getBoundingClientRect();
        const gap = 8;
        const desktop = window.matchMedia('(min-width: 64rem)').matches;

        const left = desktop
            ? Math.min(anchor.left, window.innerWidth - menu.width - gap)
            : Math.max(gap, anchor.right - menu.width);

        const top = desktop
            ? Math.max(gap, anchor.top - menu.height - gap)
            : Math.min(anchor.bottom + gap, window.innerHeight - menu.height - gap);

        accountMenu.style.left = `${Math.max(gap, left)}px`;
        accountMenu.style.top = `${top}px`;
    };

    const setOpen = (open) => {
        /*
         * Leaving early when nothing changes is not a micro-optimisation here: this runs from a
         * scroll listener, so without it every scrolled frame wrote aria-expanded again and
         * invalidated style recalculation for the header — while the menu was already closed.
         */
        if (open === ! accountMenu.classList.contains('hidden')) {
            return;
        }

        accountMenu.classList.toggle('hidden', ! open);
        accountToggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            place();
        }
    };

    accountToggle.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpen(accountMenu.classList.contains('hidden'));
    });

    document.addEventListener('click', (event) => {
        if (! accountMenu.contains(event.target)) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    window.addEventListener('resize', () => setOpen(false));
    window.addEventListener('scroll', () => setOpen(false), { passive: true });
}

/*
 * Progressive enhancement: a form marked data-live posts in place and only the
 * marked regions are replaced, so an add or a delete no longer reloads the page.
 * Without JavaScript, or when the response leaves the current page, the browser
 * does the normal navigation.
 */
document.addEventListener('submit', (event) => {
    const form = event.target.closest('form');

    if (! form) {
        return;
    }

    const message = form.dataset.confirm;
    const needsConfirm = message !== undefined && form.dataset.confirmed === undefined;
    const live = form.dataset.live !== undefined && ! form.dataset.busy;

    if (! needsConfirm && ! live) {
        delete form.dataset.confirmed;

        return;
    }

    event.preventDefault();

    const run = () => {
        if (! live) {
            form.dataset.confirmed = '';

            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }

            return;
        }

        const body = new FormData(form);
        const submitter = event.submitter;

        if (submitter?.name) {
            body.append(submitter.name, submitter.value);
        }

        form.dataset.busy = '';
        form.setAttribute('aria-busy', 'true');
        form.closest('[data-autohide]')?.remove();

        fetch(form.action, {
            method: (form.method || 'post').toUpperCase(),
            body,
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            redirect: 'follow',
        })
            .then((response) => Promise.all([response.text(), response.url, response.ok]))
            .then(([html, url, ok]) => {
                const target = new URL(url, window.location.origin);
                const swappable = ok
                    && target.origin === window.location.origin
                    && ! NAV_SAFE.includes(target.pathname);

                if (! swappable || ! swapRegions(html)) {
                    window.location.href = url;

                    return;
                }

                /*
                 * The response was already rendered, flash included — navigating again
                 * would drop the message, so the URL follows the swap instead.
                 */
                // replace, not push: the page we came from is usually gone after the action
                if (target.href !== window.location.href) {
                    history.replaceState({}, '', target);
                }

                // the fresh region replaced the old nodes, so look the field up again
                document.querySelector('[data-refocus]')?.focus();
            })
            .catch(() => window.location.reload())
            .finally(() => {
                delete form.dataset.busy;
                form.removeAttribute('aria-busy');
            });
    };

    if (needsConfirm) {
        askConfirm(message).then((answer) => {
            if (answer) {
                run();
            }
        });

        return;
    }

    run();
});

// delegated so the menus keep working inside regions that were swapped in place
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-menu-toggle]');
    const insideMenu = event.target.closest('[data-menu]');
    const own = toggle?.closest('[data-menu-wrap]')?.querySelector('[data-menu]');

    document.querySelectorAll('[data-menu]').forEach((menu) => {
        if (menu !== own && menu !== insideMenu) {
            menu.classList.add('hidden');
            menu.closest('[data-menu-wrap]')?.querySelector('[data-menu-toggle]')?.setAttribute('aria-expanded', 'false');
        }
    });

    if (! toggle || ! own) {
        return;
    }

    const open = own.classList.contains('hidden');
    own.classList.toggle('hidden', ! open);
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    document.querySelectorAll('[data-menu]').forEach((menu) => menu.classList.add('hidden'));
});

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

/* The home-office period: the range fields fold out, the day windows submit straight away. */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-period-toggle]');

    if (! toggle) {
        return;
    }

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

    if (! trigger) {
        return;
    }

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

// anything carrying data-copy puts its content on the clipboard, delegated so it
// keeps working inside regions that were swapped in place
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

function fallback(text, done) {
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.cssText = 'position:fixed;left:-9999px';
    document.body.append(field);
    field.select();

    try {
        document.execCommand('copy');
        done();
    } catch (error) {
        // nothing we can do without clipboard access
    }

    field.remove();
}

// the dashboard's edit mode; it re-reads the DOM after every region swap
const boardApi = createBoard({ swapRegions, toast });

boardApi.apply();

// marking a range of days in the calendar
dayRange();

// picking a project folder without leaving the page
folderPicker({ toast });

// running a project's make targets from the page
commandRunner({ toast });

// the container list, its actions and its logs
docker({ swapRegions, toast });

/*
 * The review sections arrive after the page: fetching them from GitHub costs more than a
 * second, and the rest of the development page has no reason to wait for it.
 */
const loadReviews = () => {
    const slot = document.querySelector('[data-reviews-slot]:not([data-loaded])');

    if (! slot || slot.querySelector('[data-review-sections]')) return;

    fetch(slot.dataset.reviewsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => (response.ok ? response.text() : Promise.reject()))
        .then((html) => {
            slot.innerHTML = html;
            slot.dataset.loaded = '';
            rememberBlocks(slot);
        })
        .catch(() => {});
};

loadReviews();

/* Collapsible blocks start closed and remember what was opened, per key. */
const rememberBlocks = (root = document) => {
    root.querySelectorAll('details[data-remember]:not([data-wired])').forEach((block) => {
        const key = 'takt.open.' + block.dataset.remember;

        block.dataset.wired = '';
        block.open = localStorage.getItem(key) === '1';

        block.addEventListener('toggle', () => localStorage.setItem(key, block.open ? '1' : '0'));
    });
};

rememberBlocks();

/*
 * Filtering the make targets. Typing opens every project that still has a match and closes
 * the ones that have none, so the list reads as one flat result while a filter is active.
 */
const commandFilter = document.querySelector('[data-command-filter]');

if (commandFilter) {
    const projects = [...document.querySelectorAll('[data-command-project]')];

    commandFilter.addEventListener('input', () => {
        const term = commandFilter.value.trim().toLowerCase();

        projects.forEach((card) => {
            const block = card.querySelector('details');
            const targets = [...card.querySelectorAll('[data-search]')];
            let hits = 0;

            targets.forEach((target) => {
                const match = term === '' || target.dataset.search.includes(term);

                target.classList.toggle('hidden', ! match);

                if (match) hits += 1;
            });

            const nameMatches = term !== '' && card.dataset.name.includes(term);

            card.classList.toggle('hidden', term !== '' && hits === 0 && ! nameMatches);
            card.querySelector('[data-command-empty]')?.classList.toggle('hidden', hits > 0 || term === '');

            if (block && term !== '') {
                block.open = hits > 0 || nameMatches;
            } else if (block) {
                block.open = localStorage.getItem('takt.open.' + block.dataset.remember) === '1';
            }
        });
    });
}

/*
 * Links that only change part of the page: the day navigation in the development section
 * replaces the header and the commits, and leaves everything else — above all the reviews,
 * which come from GitHub — untouched.
 */
const partialLink = (url, regions, push = true) =>
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => (response.ok ? response.text() : Promise.reject()))
        .then((html) => {
            if (! swapRegions(html, regions)) return Promise.reject();

            if (push) window.history.pushState({ regions }, '', url);

            return true;
        });

/*
 * Which way the content should travel. A link that carries a date or a week says where it goes,
 * so paging forward slides the new content in from the right and back from the left — the same
 * reading direction the dates have.
 */
const direction = (href) => {
    const now = new URL(window.location.href).searchParams;
    const next = new URL(href, window.location.origin).searchParams;

    for (const key of ['tag', 'from', 'woche', 'monat', 'jahr']) {
        const a = now.get(key);
        const b = next.get(key);

        if (a && b && a !== b) return b > a ? 'forward' : 'back';
    }

    return null;
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-partial]');

    if (! link || event.metaKey || event.ctrlKey || event.shiftKey || link.target === '_blank') return;

    event.preventDefault();

    const way = direction(link.href);

    if (way) document.documentElement.dataset.swapDirection = way;

    partialLink(link.href, link.dataset.partial.split(' ')).catch(() => {
        window.location.href = link.href;
    });
});

window.addEventListener('popstate', (event) => {
    const regions = event.state?.regions;

    if (! Array.isArray(regions)) return;

    partialLink(window.location.href, regions, false).catch(() => window.location.reload());
});

/*
 * The (i) next to a field: hovering shows its help, clicking pins it open so the links in it
 * can actually be used. A click outside or escape closes it again.
 */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.hint-toggle');
    const inside = event.target.closest('.hint-panel');

    if (inside) return;

    document.querySelectorAll('.hint[data-open]').forEach((hint) => {
        if (! toggle || hint !== toggle.closest('.hint')) delete hint.dataset.open;
    });

    if (! toggle) return;

    const hint = toggle.closest('.hint');

    if (hint.dataset.open === undefined) {
        hint.dataset.open = '';
    } else {
        delete hint.dataset.open;
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;

    document.querySelectorAll('.hint[data-open]').forEach((hint) => delete hint.dataset.open);
});

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
if (new URLSearchParams(location.search).has('fps')) {
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
