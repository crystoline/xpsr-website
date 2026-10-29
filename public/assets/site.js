// XPSR website: theme switch, documentation menu, copy buttons, "On this page", and search.
// Pages work without it; this only adds conveniences.
(() => {
    'use strict';

    const root = document.documentElement;
    const body = document.body;

    // Theme: follow the system until the visitor chooses.
    document.querySelector('[data-theme-toggle]')?.addEventListener('click', () => {
        const dark = root.dataset.theme
            ? root.dataset.theme === 'dark'
            : matchMedia('(prefers-color-scheme: dark)').matches;
        root.dataset.theme = dark ? 'light' : 'dark';
        try { localStorage.setItem('xpsr-theme', root.dataset.theme); } catch (e) { /* private mode */ }
    });

    // Documentation menu on narrow screens.
    const navToggle = document.querySelector('.nav-toggle');
    const setNav = (open) => {
        body.classList.toggle('nav-open', open);
        navToggle?.setAttribute('aria-expanded', String(open));
    };
    navToggle?.addEventListener('click', () => setNav(!body.classList.contains('nav-open')));
    document.addEventListener('click', (event) => {
        if (body.classList.contains('nav-open') && !event.target.closest('.sidebar, .nav-toggle')) {
            setNav(false);
        }
        for (const menu of document.querySelectorAll('details.version-menu[open]')) {
            if (!menu.contains(event.target)) menu.open = false;
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        setNav(false);
        for (const menu of document.querySelectorAll('details.version-menu[open]')) menu.open = false;
    });

    // Copy buttons: on every code block, and on elements with data-copy.
    const copyIcon = '<svg class="icon icon-copy" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4.5A1.5 1.5 0 0 1 3 13.5v-9A1.5 1.5 0 0 1 4.5 3h9A1.5 1.5 0 0 1 15 4.5V5"/></svg>'
        + '<svg class="icon icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>';
    for (const block of document.querySelectorAll('.prose .code-block')) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'copy-button';
        button.setAttribute('aria-label', 'Copy the code');
        button.innerHTML = copyIcon;
        block.append(button);
    }
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.copy-button');
        if (!button) return;
        const text = button.dataset.copy ?? button.closest('.code-block')?.querySelector('pre')?.innerText ?? '';
        try {
            await navigator.clipboard.writeText(text.replace(/\n$/, ''));
            button.classList.add('copied');
            setTimeout(() => button.classList.remove('copied'), 1600);
        } catch (e) { /* clipboard unavailable */ }
    });

    // "On this page": highlight the section being read.
    const tocLinks = [...document.querySelectorAll('.toc a')];
    if (tocLinks.length > 0) {
        const targets = tocLinks
            .map((link) => [document.getElementById(decodeURIComponent(link.hash.slice(1))), link])
            .filter(([heading]) => heading !== null);
        let scheduled = false;
        const update = () => {
            scheduled = false;
            const offset = parseInt(getComputedStyle(root).getPropertyValue('--header-h'), 10) + 32;
            let current = targets[0];
            for (const target of targets) {
                if (target[0].getBoundingClientRect().top - offset > 0) break;
                current = target;
            }
            for (const [, link] of targets) link.classList.toggle('active', link === current?.[1]);
        };
        addEventListener('scroll', () => {
            if (!scheduled) { scheduled = true; requestAnimationFrame(update); }
        }, { passive: true });
        update();
    }

    // Search, with the Pagefind index that the build adds in /pagefind/.
    const dialog = document.getElementById('search');
    if (!dialog) return;
    const input = dialog.querySelector('input');
    const results = dialog.querySelector('.search-results');
    const version = body.dataset.searchVersion;
    let pagefind = null;
    let unavailable = false;
    let timer = 0;
    let latest = 0;

    const load = async () => {
        if (pagefind || unavailable) return pagefind;
        try {
            pagefind = await import('/pagefind/pagefind.js');
            await pagefind.options({ excerptLength: 22 });
            pagefind.init();
        } catch (e) {
            unavailable = true;
        }
        return pagefind;
    };
    const open = () => {
        if (dialog.open) return;
        setNav(false);
        dialog.showModal();
        input.select();
        load();
    };
    const escape = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const items = () => [...results.querySelectorAll('.search-result')];
    const select = (index) => {
        const list = items();
        list.forEach((item, i) => item.setAttribute('aria-selected', String(i === index)));
        list[index]?.scrollIntoView({ block: 'nearest' });
    };

    const search = async () => {
        const query = input.value.trim();
        const request = ++latest;
        if (query === '') {
            results.innerHTML = '';
            return;
        }
        const index = await load();
        if (request !== latest) return;
        if (!index) {
            results.innerHTML = '<p class="search-empty">Search is added when the site is built: run <code>composer build</code> and open the result.</p>';
            return;
        }
        const response = await index.search(query, version ? { filters: { version } } : {});
        const pages = await Promise.all(response.results.slice(0, 8).map((result) => result.data()));
        if (request !== latest) return;
        if (pages.length === 0) {
            results.innerHTML = `<p class="search-empty">Nothing found for “${escape(query)}”.</p>`;
            return;
        }
        results.innerHTML = pages.map((page) => {
            const sections = (page.sub_results ?? [])
                .filter((sub) => sub.url !== page.url)
                .slice(0, 3)
                .map((sub) => `<a class="search-result search-sub" role="option" href="${escape(sub.url)}"><strong>${escape(sub.title)}</strong><span>${sub.excerpt}</span></a>`)
                .join('');
            return `<a class="search-result" role="option" href="${escape(page.url)}"><strong>${escape(page.meta?.title ?? page.url)}</strong><span>${page.excerpt}</span></a>${sections}`;
        }).join('');
        select(0);
    };

    for (const button of document.querySelectorAll('[data-search-open]')) button.addEventListener('click', open);
    dialog.querySelector('[data-search-close]')?.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => { input.value = ''; results.innerHTML = ''; });
    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 120); });
    input.addEventListener('keydown', (event) => {
        const list = items();
        const current = list.findIndex((item) => item.getAttribute('aria-selected') === 'true');
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            select(Math.max(0, Math.min(list.length - 1, current + step)));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const target = list[current] ?? list[0];
            if (target) location.href = target.href;
        }
    });
    document.addEventListener('keydown', (event) => {
        const typing = event.target.closest?.('input, textarea, select, [contenteditable="true"]');
        if ((event.key === 'k' && (event.metaKey || event.ctrlKey)) || (event.key === '/' && !typing)) {
            event.preventDefault();
            open();
        }
    });
})();
