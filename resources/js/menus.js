/*
 * Small pop-outs: the row menus behind data-menu-toggle, the (i) hints next to fields, and the
 * account menu at the foot of the sidebar. All delegated, because every one of their triggers
 * sits inside a region that live forms replace.
 */
const closeMenus = (except = null) => {
    document.querySelectorAll('[data-menu]').forEach((menu) => {
        if (menu === except) return;

        menu.classList.add('hidden');
        menu.closest('[data-menu-wrap]')?.querySelector('[data-menu-toggle]')?.setAttribute('aria-expanded', 'false');
    });
};

const closeHints = (except = null) => {
    document.querySelectorAll('.hint[data-open]').forEach((hint) => {
        if (hint !== except) delete hint.dataset.open;
    });
};

const accountMenu = () => document.querySelector('[data-account-menu]');

const placeAccount = (menu) => {
    const toggle = document.querySelector('[data-account-toggle]');

    if (! toggle) return;

    const anchor = toggle.getBoundingClientRect();
    const box = menu.getBoundingClientRect();
    const gap = 8;
    const desktop = window.matchMedia('(min-width: 64rem)').matches;

    const left = desktop
        ? Math.min(anchor.left, window.innerWidth - box.width - gap)
        : Math.max(gap, anchor.right - box.width);

    const top = desktop
        ? Math.max(gap, anchor.top - box.height - gap)
        : Math.min(anchor.bottom + gap, window.innerHeight - box.height - gap);

    menu.style.left = `${Math.max(gap, left)}px`;
    menu.style.top = `${top}px`;
};

const setAccount = (open) => {
    const menu = accountMenu();

    /*
     * Leaving early when nothing changes is not a micro-optimisation here: this runs from a
     * scroll listener, so without it every scrolled frame wrote aria-expanded again and
     * invalidated style recalculation for the header — while the menu was already closed.
     */
    if (! menu || open === ! menu.classList.contains('hidden')) {
        return;
    }

    menu.classList.toggle('hidden', ! open);
    document.querySelectorAll('[data-account-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    if (open) {
        placeAccount(menu);
    }
};

export function menus() {
    document.addEventListener('click', (event) => {
        const accountToggle = event.target.closest('[data-account-toggle]');

        if (accountToggle) {
            setAccount(accountMenu()?.classList.contains('hidden') === true);
        } else if (! accountMenu()?.contains(event.target)) {
            setAccount(false);
        }

        const toggle = event.target.closest('[data-menu-toggle]');
        const insideMenu = event.target.closest('[data-menu]');
        const own = toggle?.closest('[data-menu-wrap]')?.querySelector('[data-menu]');

        closeMenus(own ?? insideMenu);

        if (toggle && own) {
            const open = own.classList.contains('hidden');
            own.classList.toggle('hidden', ! open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        /*
         * The (i) next to a field: hovering shows its help, clicking pins it open so the links in it
         * can actually be used. A click outside or escape closes it again.
         */
        if (event.target.closest('.hint-panel')) return;

        const hintToggle = event.target.closest('.hint-toggle');
        const hint = hintToggle?.closest('.hint');

        closeHints(hint ?? null);

        if (hint) {
            if (hint.dataset.open === undefined) {
                hint.dataset.open = '';
            } else {
                delete hint.dataset.open;
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;

        closeMenus();
        closeHints();
        setAccount(false);
    });

    window.addEventListener('resize', () => setAccount(false));
    window.addEventListener('scroll', () => setAccount(false), { passive: true });
}

export function navToggle() {
    document.addEventListener('click', (event) => {
        if (! event.target.closest('[data-nav-toggle]')) return;

        const collapsed = document.documentElement.dataset.nav === 'collapsed';

        if (collapsed) {
            delete document.documentElement.dataset.nav;
            localStorage.removeItem('takt.nav');
        } else {
            document.documentElement.dataset.nav = 'collapsed';
            localStorage.setItem('takt.nav', 'collapsed');
        }

        document.querySelectorAll('[data-nav-toggle]').forEach((trigger) => {
            trigger.setAttribute('aria-expanded', collapsed ? 'true' : 'false');
        });
    });
}
