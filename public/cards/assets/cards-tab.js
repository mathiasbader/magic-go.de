/**
 * Cards tab — search Scryfall, list owned cards, refresh prices, filter & sort.
 */
(function () {
    const M = window.MAGIC;

    let collection = [];
    let debounceTimer = null;
    let setNameMap = {};

    let activeSetFilter = null;
    let activeLangFilter = null;
    let activeBinderFilter = null;
    let activeRarityFilter = null;
    let activeFoilFilter = null;        // null | 'normal' | 'foil'
    let sortField = 'price';
    let sortDir = 'desc';
    const rarityOrder = ['mythic', 'rare', 'uncommon', 'common'];
    const isTokenSet = code => /^t[a-z]/.test(code) && code.length > 2;

    let searchInput, searchResults, filterInput, setFilterSelect;
    let langFiltersEl, rarityFiltersEl, foilFiltersEl;
    let cardGrid, emptyState, statsEl, binderSelect;

    function getImageUris(card) {
        if (card.image_uris) return card.image_uris;
        if (card.card_faces && card.card_faces[0]?.image_uris) return card.card_faces[0].image_uris;
        return {};
    }

    async function searchScryfall(query) {
        searchResults.innerHTML = '<div class="search-hint">Searching...</div>';
        searchResults.classList.add('visible');
        try {
            const resp = await fetch(M.scryfallApi(`/cards/search?q=${encodeURIComponent(query)}&unique=prints&order=released&dir=desc`));
            if (!resp.ok) {
                searchResults.innerHTML = '<div class="search-hint">No cards found.</div>';
                return;
            }
            const data = await resp.json();
            renderSearchResults(data.data.slice(0, 20));
        } catch {
            searchResults.innerHTML = '<div class="search-hint">Search failed.</div>';
        }
    }

    function renderSearchResults(cards) {
        if (!cards.length) {
            searchResults.innerHTML = '<div class="search-hint">No cards found.</div>';
            return;
        }
        searchResults.innerHTML = cards.map(card => {
            const imgs = getImageUris(card);
            const small = imgs.small || '';
            const setName = card.set_name || card.set.toUpperCase();
            const cardData = JSON.stringify({
                scryfall_id: card.id,
                name: card.name,
                set_code: card.set,
                collector_number: card.collector_number,
                image_uri_small: imgs.small || null,
                image_uri_normal: imgs.normal || null,
                mana_cost: card.mana_cost || null,
                type_line: card.type_line || null,
                rarity: card.rarity || null,
                language: card.lang || 'en',
            }).replace(/'/g, '&#39;');
            return `<div class="search-result" data-card='${cardData}' onclick="window.location='/cards/${card.id}'">
                ${small ? `<img src="${M.cachedImg(small)}" alt="">` : '<div style="width:40px;height:56px;background:var(--bg);border-radius:3px;"></div>'}
                <div class="search-result-info">
                    <div class="search-result-name">${M.escHtml(card.name)}</div>
                    <div class="search-result-meta">${M.escHtml(setName)} #${card.collector_number} &middot; ${card.rarity}</div>
                </div>
                <button class="search-result-add" onclick="event.stopPropagation();window.MAGIC.cardsTab.addFromSearch(this.closest('.search-result'))">Add</button>
            </div>`;
        }).join('');
    }

    async function addFromSearch(el) {
        const card = JSON.parse(el.dataset.card);
        await M.api('add', { ...card, quantity: 1 });
        M.showToast(`Added ${card.name}`);
        loadCollection();
    }

    async function loadCollection() {
        collection = await M.api('list');
        if (!Array.isArray(collection)) collection = [];
        renderCollection();
        updateSetFilter();
        updateLangFilter();
        updateBinderFilter();
        updateRarityFilter();
        updateFoilFilter();
        updateStats();
        refreshPrices();
    }

    async function refreshPrices() {
        if (!collection.length) return;
        const today = new Date().toISOString().slice(0, 10);
        const needsUpdate = collection.some(c => c.market_price_date !== today);
        if (!needsUpdate) return;

        const ids = collection.map(c => ({ id: c.scryfall_id }));
        const batches = [];
        for (let i = 0; i < ids.length; i += 75) batches.push(ids.slice(i, i + 75));

        const priceMap = {};
        for (const batch of batches) {
            try {
                const resp = await fetch(M.scryfallApi('/cards/collection'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identifiers: batch }),
                });
                if (resp.ok) {
                    const data = await resp.json();
                    for (const card of data.data) priceMap[card.id] = card.prices;
                }
            } catch {}
            await new Promise(r => setTimeout(r, 100));
        }

        // For non-English cards without prices, fetch English version prices.
        const needEnglish = collection.filter(c => {
            const p = priceMap[c.scryfall_id];
            const isFoil = c.foil == 1;
            const price = p ? (isFoil ? p.eur_foil : p.eur) : null;
            return !price && (c.language || 'en') !== 'en';
        });
        if (needEnglish.length) {
            const enIds = needEnglish.map(c => ({ set: c.set_code, collector_number: c.collector_number }));
            for (let i = 0; i < enIds.length; i += 75) {
                const batch = enIds.slice(i, i + 75);
                try {
                    const resp = await fetch(M.scryfallApi('/cards/collection'), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ identifiers: batch }),
                    });
                    if (resp.ok) {
                        const data = await resp.json();
                        for (const card of data.data) {
                            const key = card.set + ':' + card.collector_number;
                            priceMap['en:' + key] = card.prices;
                        }
                    }
                } catch {}
                await new Promise(r => setTimeout(r, 100));
            }
        }

        let updated = 0;
        for (const card of collection) {
            const isFoil = card.foil == 1;
            const p = priceMap[card.scryfall_id];
            let price = p ? (isFoil ? p.eur_foil : p.eur) : null;
            let isEnglish = false;

            if (!price && (card.language || 'en') !== 'en') {
                const enKey = 'en:' + card.set_code + ':' + card.collector_number;
                const enP = priceMap[enKey];
                price = enP ? (isFoil ? enP.eur_foil : enP.eur) : null;
                if (price) isEnglish = true;
            }
            if (price) {
                card.market_price = price;
                card.market_price_date = today;
                card.market_price_is_english = (card.language || 'en') !== 'en' ? (isEnglish ? 1 : 0) : null;
                updated++;
                M.api('update_price', {
                    id: card.id,
                    market_price: parseFloat(price),
                    market_price_date: today,
                    market_price_is_english: card.market_price_is_english,
                });
            }
        }
        if (updated) renderCollection();
    }

    function renderCollection() {
        const q = filterInput.value.trim().toLowerCase();
        const setFilter = activeSetFilter;
        const filtered = collection.filter(c => {
            if (q && !c.name.toLowerCase().includes(q)) return false;
            if (setFilter === '_tokens' && !isTokenSet(c.set_code)) return false;
            if (setFilter && setFilter !== '_tokens' && c.set_code !== setFilter) return false;
            if (activeLangFilter && (c.language || 'en') !== activeLangFilter) return false;
            if (activeBinderFilter && (c.binder || '') !== activeBinderFilter) return false;
            if (activeRarityFilter && (c.rarity || '') !== activeRarityFilter) return false;
            if (activeFoilFilter === 'foil' && c.foil != 1) return false;
            if (activeFoilFilter === 'normal' && c.foil == 1) return false;
            return true;
        });

        filtered.sort((a, b) => sortBy(a, b, false));
        updateStats(filtered);

        if (collection.length === 0) {
            cardGrid.innerHTML = '';
            emptyState.style.display = '';
            return;
        }
        emptyState.style.display = 'none';
        if (filtered.length === 0) {
            cardGrid.innerHTML = '<div class="empty-state"><p>No cards match your filter.</p></div>';
            return;
        }

        // Group by scryfall_id for display (multiple physical copies → one tile).
        const grouped = {};
        for (const card of filtered) {
            const g = grouped[card.scryfall_id];
            if (!g) {
                grouped[card.scryfall_id] = { ...card, count: 1, total_price: parseFloat(card.market_price) || 0 };
            } else {
                g.count++;
                g.total_price += parseFloat(card.market_price) || 0;
            }
        }
        const display = Object.values(grouped).sort((a, b) => sortBy(a, b, true));
        cardGrid.innerHTML = display.map(renderCard).join('');
    }

    function sortBy(a, b, grouped) {
        let cmp = 0;
        if (sortField === 'name') cmp = (a.name || '').localeCompare(b.name || '');
        else if (sortField === 'price') {
            cmp = grouped
                ? ((a.total_price || 0) - (b.total_price || 0))
                : ((parseFloat(a.market_price) || 0) - (parseFloat(b.market_price) || 0));
        } else if (sortField === 'set') {
            cmp = (a.set_code || '').localeCompare(b.set_code || '') || (a.name || '').localeCompare(b.name || '');
        } else if (sortField === 'copies') {
            cmp = (a.count || 1) - (b.count || 1);
        }
        return sortDir === 'desc' ? -cmp : cmp;
    }

    function renderCard(card) {
        const price = card.total_price ? card.total_price.toFixed(2).replace('.', ',') + ' €' : '';
        const countBadge = card.count > 1 ? `<span style="color:var(--text-muted);"> (${card.count}x)</span>` : '';
        const imgLangMismatch = card.language && card.image_language && card.language !== card.image_language;
        const langBadge = card.language && card.language !== 'en'
            ? (imgLangMismatch
                ? `<div style="position:absolute;top:6px;right:6px;background:rgba(251,191,36,0.9);color:#000;font-size:0.65rem;font-weight:600;padding:2px 6px;border-radius:4px;">${(card.image_language || 'en').toUpperCase()} img &middot; ${card.language.toUpperCase()}</div>`
                : `<div style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.7);color:#fff;font-size:0.65rem;font-weight:600;padding:2px 6px;border-radius:4px;">${card.language.toUpperCase()}</div>`)
            : '';
        return `<div class="card-item" data-id="${card.id}" style="position:relative;">
            <a href="/cards/${card.id}">
            ${card.image_uri_normal
                ? `<img src="${M.cachedImg(card.image_uri_normal)}" alt="${M.escHtml(card.name)}" loading="lazy">`
                : `<div style="height:220px;background:var(--surface-alt);display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.8rem;">No image</div>`}
            ${langBadge}
            </a>
            <div class="card-item-info">
                <div class="card-item-name" title="${M.escHtml(card.name)}">${M.escHtml(card.name)}${countBadge}</div>
                ${price ? `<div class="card-item-price">${price}</div>` : ''}
            </div>
        </div>`;
    }

    async function updateSetFilter() {
        if (!Object.keys(setNameMap).length) {
            try {
                const resp = await fetch(M.scryfallApi('/sets'));
                if (resp.ok) {
                    const data = await resp.json();
                    for (const s of data.data) setNameMap[s.code] = s.name;
                }
            } catch {}
        }
        const setMap = {};
        let tokenCount = 0;
        collection.forEach(c => {
            if (isTokenSet(c.set_code)) tokenCount++;
            else setMap[c.set_code] = (setMap[c.set_code] || 0) + 1;
        });
        const sets = Object.entries(setMap).sort((a, b) => (setNameMap[a[0]] || a[0]).localeCompare(setNameMap[b[0]] || b[0]));
        const current = activeSetFilter;
        let options = '<option value="">All sets</option>';
        for (const [code, count] of sets) {
            options += `<option value="${code}" ${code === current ? 'selected' : ''}>${setNameMap[code] || code.toUpperCase()} (${count})</option>`;
        }
        if (tokenCount > 0) {
            options += `<option value="_tokens" ${'_tokens' === current ? 'selected' : ''}>Tokens (${tokenCount})</option>`;
        }
        setFilterSelect.innerHTML = options;
    }

    function updateLangFilter() {
        const langMap = {};
        collection.forEach(c => {
            const lang = c.language || 'en';
            langMap[lang] = (langMap[lang] || 0) + c.quantity;
        });
        const langs = Object.keys(langMap).sort();
        if (langs.length <= 1) { langFiltersEl.innerHTML = ''; return; }
        langFiltersEl.innerHTML = filterDivider() + langs.map(lang => {
            const active = activeLangFilter === lang;
            const dimmed = activeLangFilter && activeLangFilter !== lang;
            return `<button class="set-filter-btn${active ? ' active' : ''}${dimmed ? ' dimmed' : ''}" data-lang="${lang}">
                ${M.langNames[lang] || lang.toUpperCase()} (${langMap[lang]})
            </button>`;
        }).join('');
        langFiltersEl.querySelectorAll('.set-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const lang = btn.dataset.lang;
                activeLangFilter = activeLangFilter === lang ? null : lang;
                updateFilterStyles(langFiltersEl, 'lang', activeLangFilter);
                renderCollection();
            });
        });
    }

    function updateRarityFilter() {
        const rarityMap = {};
        collection.forEach(c => {
            const r = (c.rarity || 'common').toLowerCase();
            rarityMap[r] = (rarityMap[r] || 0) + (parseInt(c.quantity) || 1);
        });
        const rarities = rarityOrder.filter(r => rarityMap[r]);
        if (rarities.length <= 1) { rarityFiltersEl.innerHTML = ''; return; }
        rarityFiltersEl.innerHTML = filterDivider() + rarities.map(r => {
            const active = activeRarityFilter === r;
            const dimmed = activeRarityFilter && activeRarityFilter !== r;
            return `<button class="set-filter-btn${active ? ' active' : ''}${dimmed ? ' dimmed' : ''}" data-rarity="${r}" style="text-transform:capitalize;">${r} (${rarityMap[r]})</button>`;
        }).join('');
        rarityFiltersEl.querySelectorAll('.set-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const r = btn.dataset.rarity;
                activeRarityFilter = activeRarityFilter === r ? null : r;
                updateFilterStyles(rarityFiltersEl, 'rarity', activeRarityFilter);
                renderCollection();
            });
        });
    }

    function updateFoilFilter() {
        const normalCount = collection.filter(c => c.foil != 1).reduce((s, c) => s + c.quantity, 0);
        const foilCount = collection.filter(c => c.foil == 1).reduce((s, c) => s + c.quantity, 0);
        if (!foilCount || !normalCount) { foilFiltersEl.innerHTML = ''; return; }
        const items = [
            { key: 'normal', label: `Normal (${normalCount})` },
            { key: 'foil', label: `Foil (${foilCount})` },
        ];
        foilFiltersEl.innerHTML = filterDivider() + items.map(f => {
            const active = activeFoilFilter === f.key;
            const dimmed = activeFoilFilter && activeFoilFilter !== f.key;
            return `<button class="set-filter-btn${active ? ' active' : ''}${dimmed ? ' dimmed' : ''}" data-foil="${f.key}">${f.label}</button>`;
        }).join('');
        foilFiltersEl.querySelectorAll('.set-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const key = btn.dataset.foil;
                activeFoilFilter = activeFoilFilter === key ? null : key;
                updateFilterStyles(foilFiltersEl, 'foil', activeFoilFilter);
                renderCollection();
            });
        });
    }

    function filterDivider() {
        return '<span style="display:inline-block;width:1px;height:1.2em;background:var(--border);margin:0 0.5rem;vertical-align:middle;"></span>';
    }
    function updateFilterStyles(container, attr, active) {
        container.querySelectorAll('.set-filter-btn').forEach(b => {
            b.classList.toggle('active', active === b.dataset[attr]);
            b.classList.toggle('dimmed', active && active !== b.dataset[attr]);
        });
    }

    function updateBinderFilter() {
        const binderMap = {};
        collection.forEach(c => {
            const b = c.binder || '';
            if (!b) return;
            binderMap[b] = (binderMap[b] || 0) + (parseInt(c.quantity) || 1);
        });
        const binders = Object.keys(binderMap).sort();
        if (binders.length <= 1) { binderSelect.style.display = 'none'; return; }
        binderSelect.style.display = '';
        const current = activeBinderFilter;
        binderSelect.innerHTML = '<option value="">All binders</option>' +
            binders.map(b => `<option value="${M.escHtml(b)}" ${b === current ? 'selected' : ''}>${M.escHtml(b)} (${binderMap[b]})</option>`).join('');
    }

    function updateStats(filtered) {
        const cards = filtered || collection;
        const totalCards = cards.length;
        const uniqueIds = new Set(cards.map(c => c.scryfall_id)).size;
        const totalPrice = cards.reduce((sum, c) => sum + (parseFloat(c.market_price) || 0), 0);
        const priceStr = totalPrice.toFixed(2).replace('.', ',');
        const isFiltered = cards.length !== collection.length;
        statsEl.textContent = isFiltered
            ? `${totalCards} / ${collection.length} cards (${uniqueIds} unique) · ${priceStr} €`
            : `${totalCards} cards (${uniqueIds} unique) · ${priceStr} €`;
    }

    function init() {
        searchInput = document.getElementById('search-input');
        searchResults = document.getElementById('search-results');
        filterInput = document.getElementById('filter-input');
        setFilterSelect = document.getElementById('set-filter-select');
        langFiltersEl = document.getElementById('lang-filters');
        rarityFiltersEl = document.getElementById('rarity-filters');
        foilFiltersEl = document.getElementById('foil-filters');
        cardGrid = document.getElementById('card-grid');
        emptyState = document.getElementById('empty-state');
        statsEl = document.getElementById('collection-stats');
        binderSelect = document.getElementById('binder-select');

        // Read filter URL params (binder/set/lang).
        const urlParams = new URLSearchParams(location.search);
        if (urlParams.get('binder')) activeBinderFilter = urlParams.get('binder');
        if (urlParams.get('set')) activeSetFilter = urlParams.get('set');
        if (urlParams.get('lang')) activeLangFilter = urlParams.get('lang');

        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            const q = searchInput.value.trim();
            if (q.length < 2) { searchResults.classList.remove('visible'); return; }
            debounceTimer = setTimeout(() => searchScryfall(q), 350);
        });
        searchInput.addEventListener('focus', () => {
            if (searchResults.children.length > 0) searchResults.classList.add('visible');
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-section')) searchResults.classList.remove('visible');
        });

        filterInput.addEventListener('input', renderCollection);
        setFilterSelect.addEventListener('change', () => {
            activeSetFilter = setFilterSelect.value || null;
            renderCollection();
        });
        binderSelect.addEventListener('change', () => {
            activeBinderFilter = binderSelect.value || null;
            renderCollection();
        });

        const defaultDirs = { name: 'asc', price: 'desc', set: 'asc', copies: 'desc' };
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const field = btn.dataset.sort;
                if (sortField === field) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                else { sortField = field; sortDir = defaultDirs[field] || 'asc'; }
                document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                btn.querySelector('.sort-arrow').innerHTML = sortDir === 'asc' ? '&uarr;' : '&darr;';
                renderCollection();
            });
        });

        loadCollection();
    }

    M.tabs.cards = { load: init };
    M.cardsTab = { addFromSearch }; // exposed for inline onclick on dynamically rendered chips
})();
