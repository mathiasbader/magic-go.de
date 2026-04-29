/**
 * Magic — shared client-side core.
 *
 * Exposes a single `window.MAGIC` namespace with:
 *  - CSRF + server bootstrap data set inline by index.php
 *  - api(action, data, opts) — JSON POST with abort + error handling
 *  - cachedImg / scryfallApi / escHtml / showToast utilities
 *  - tab registration: each tab module registers an onActivate hook in
 *    MAGIC.tabs[name]; core.js wires the click handlers and lazy-loads.
 *  - shared lookup tables (language / country names) used in multiple tabs
 */
(function () {
    if (!window.MAGIC) window.MAGIC = {};
    const M = window.MAGIC;

    M.scryfallApi = function (path) {
        return '/api/scryfall?path=' + encodeURIComponent(path);
    };

    M.cachedImg = function (url) {
        if (!url) return url;
        if (url.includes('cards.scryfall.io') || url.includes('svgs.scryfall.io')) {
            return '/img/cache?url=' + encodeURIComponent(url);
        }
        return url;
    };

    M.escHtml = function (s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    };

    M.highlightMatch = function (name, query) {
        const i = (name || '').toLowerCase().indexOf(query);
        if (i === -1) return M.escHtml(name);
        return M.escHtml(name.substring(0, i))
            + '<mark style="background:var(--accent);color:var(--bg);border-radius:2px;padding:0 1px;">'
            + M.escHtml(name.substring(i, i + query.length))
            + '</mark>'
            + M.escHtml(name.substring(i + query.length));
    };

    M.api = async function (action, data = {}, opts = {}) {
        try {
            const resp = await fetch('/cards/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, _csrf: M.csrf, ...data }),
                signal: opts.signal,
            });
            const text = await resp.text();
            try {
                return JSON.parse(text);
            } catch {
                const snippet = text.replace(/\s+/g, ' ').trim().slice(0, 500) || '(empty body)';
                return {
                    error: 'Invalid server response',
                    status: resp.status,
                    detail: `HTTP ${resp.status} returned non-JSON: ${snippet}`,
                };
            }
        } catch (e) {
            if (e.name === 'AbortError') return { error: 'Cancelled', cancelled: true };
            return { error: 'Network error', detail: String(e.message || e) };
        }
    };

    let toastEl = null;
    M.showToast = function (msg) {
        if (!toastEl) toastEl = document.getElementById('toast');
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.classList.add('visible');
        setTimeout(() => toastEl.classList.remove('visible'), 1500);
    };

    M.langNames = {
        en: 'English', de: 'German', fr: 'French', es: 'Spanish', it: 'Italian',
        pt: 'Portuguese', ja: 'Japanese', ko: 'Korean', zhs: 'Chinese (S)', zht: 'Chinese (T)',
        zh: 'Chinese', ru: 'Russian', ph: 'Phyrexian', pl: 'Polish', nl: 'Dutch',
        hr: 'Croatian', hu: 'Hungarian', bg: 'Bulgarian', el: 'Greek', id: 'Indonesian',
        ar: 'Arabic', vi: 'Vietnamese', sv: 'Swedish',
    };
    M.countryNames = {
        US: 'United States', GB: 'United Kingdom', DE: 'Germany', FR: 'France', IT: 'Italy',
        ES: 'Spain', PL: 'Poland', NL: 'Netherlands', BE: 'Belgium', AT: 'Austria',
        SE: 'Sweden', HR: 'Croatia', HU: 'Hungary', BG: 'Bulgaria', RU: 'Russia',
        GR: 'Greece', JP: 'Japan', CN: 'China', KR: 'South Korea', PH: 'Philippines',
        ID: 'Indonesia', AU: 'Australia', NZ: 'New Zealand', CA: 'Canada', BR: 'Brazil',
        MX: 'Mexico', CU: 'Cuba', JO: 'Jordan', VN: 'Vietnam',
    };

    // Tab registry — each tab module sets `MAGIC.tabs.<name> = { load: fn }`
    // The wired click handler invokes `load()` when the tab is activated, but
    // only the first time (load-once behaviour). The 'cards' tab is always
    // loaded eagerly because it backs the home view.
    M.tabs = M.tabs || {};
    const loaded = new Set();

    M.activateTab = function (name) {
        document.querySelectorAll('.page-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('tab-' + name);
        if (panel) panel.classList.add('active');
        const urls = {
            cards: '/cards/',
            sets: '/sets/',
            artists: '/artists/',
            decks: '/decks/',
        };
        history.replaceState(null, '', urls[name] || ('#' + name));
        const tab = M.tabs[name];
        if (tab && tab.load && !loaded.has(name)) {
            loaded.add(name);
            tab.load();
        } else if (tab && tab.activate) {
            tab.activate();
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.page-tab').forEach(tab => {
            tab.addEventListener('click', () => M.activateTab(tab.dataset.tab));
        });

        // Initial-tab load. Cards loads eagerly; others lazy-load when tab opens.
        if (M.tabs.cards && M.tabs.cards.load) {
            loaded.add('cards');
            M.tabs.cards.load();
        }
        if (M.initialTab && M.initialTab !== 'cards') {
            // Mark cards as already-loaded so it doesn't fire again on first switch.
            const tab = M.tabs[M.initialTab];
            if (tab && tab.load && !loaded.has(M.initialTab)) {
                loaded.add(M.initialTab);
                tab.load();
            }
        }

        // Hash-based deep link to artists tab (legacy).
        if (location.hash === '#artists') {
            M.activateTab('artists');
        }
    });
})();
