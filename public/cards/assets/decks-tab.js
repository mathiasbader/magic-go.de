/**
 * Decks tab — Claude API key form, "Suggest decks" flow with planeswalker
 * loading screen and progress bar, deck list with favorite/delete + click-to-open.
 */
(function () {
    const M = window.MAGIC;

    /** MTG-themed taglines shown during the deck-suggestion request. */
    const LOADING_MESSAGES = [
        'Shuffling the library...', 'Tapping mana...', 'Brewing in the laboratory...',
        'Counting +1/+1 counters...', 'Consulting ancient planeswalkers...',
        'Negotiating with goblins...', 'Summoning legendary creatures...',
        'Resolving the stack...', 'Fetching dual lands...',
        'Casting Counterspell on bad ideas...', 'Untapping all permanents...',
        'Drawing seven cards...', 'Sleeving up...', 'Reading ancient tomes...',
        'Awakening sleeping dragons...', 'Sideboarding for game two...',
        'Bribing the judge...', 'Mulliganing weak hands...',
        'Asking the Elder Dragons for advice...', 'Calculating combat damage...',
        'Searching the multiverse...', 'Polishing legendary artifacts...',
        'Whispering to merfolk...', 'Forging signature spells...',
        'Cracking a booster pack...', 'Pondering during upkeep...',
        'Topdecking the answer...', 'Casting Brainstorm...',
        'Resolving triggered abilities...', 'Choosing modes for Cryptic Command...',
        'Putting the Sol Ring back in the box...', 'Tutoring for the silver bullet...',
        'Sacrificing a goblin or two...', 'Reanimating fallen creatures...',
        'Burning the opponent to the face...', 'Casting Wrath of God...',
        'Convincing Atraxa to ramp...', 'Asking Jace what to play...',
        'Trying to calm Liliana down...', 'Negotiating with Phyrexians...',
        'Hatching a dragon egg...', 'Adventuring in Eldraine...',
        'Sailing the seas of Ixalan...', 'Investigating an Innistrad clue...',
        'Brewing potions in Strixhaven...', 'Splashing a fourth color...',
        'Banishing demons...', 'Triggering ETB effects...',
        'Cracking a fetch land...', 'Discarding to hand size...',
        'Activating Lightning Bolt for 3...', 'Petting your Squirrel token...',
        'Comforting a sad Goblin...', 'Trading with vampires...',
        'Recruiting Elvish Scouts...', 'Suspending a time-walking spell...',
        'Cycling the extra lands...', 'Unearthing forgotten relics...',
        'Counting infect damage...', 'Negotiating commander tax...',
        'Hexproofing the planeswalker...', 'Setting traps for tokens...',
        'Tapping out for victory...', 'Channeling planeswalker sparks...',
        'Choosing the silver bullet...', 'Reciting Necropotence downsides...',
        'Pondering Power Nine prices...', 'Polishing the Mox collection...',
        'Tucking the commander...', 'Returning a Snapcaster Mage...',
        'Equipping Skullclamp...', 'Munching cards with a Tarmogoyf...',
        'Untapping with a smile...', 'Drafting a 7-1 deck...',
        'Casting Path to Exile on the threat...', 'Drawing power from the artifact...',
        'Stacking the library...', 'Sealing a pact with a demon...',
        'Fueling artifacts with energy...', 'Splicing onto Arcane spells...',
        'Hiring goblin solicitors...', 'Convincing Squee to stay dead...',
        'Asking Karn for guidance...', 'Sneaking into the library...',
        'Investigating mysterious clues...', 'Trying not to mill yourself out...',
        'Resolving exactly one Lightning Bolt...', 'Brewing the perfect mana base...',
        'Sleeving rares in premium sleeves...', 'Hoping you drew the basic land...',
    ];
    const LOADING_SUBSET_SIZE = 12;
    const ESTIMATED_LOADING_SECONDS = 60;

    let decksLoaded = false;
    let loadingTimer = null;
    let progressTimer = null;
    let suggestController = null;

    function renderManaPips(colors) {
        const cs = (colors || '').toUpperCase();
        if (!cs) return '<span class="mana C" title="Colorless">C</span>';
        return cs.split('').map(c => `<span class="mana ${c}" title="${c}">${c}</span>`).join('');
    }

    function renderManaCurve(curve) {
        if (!Array.isArray(curve) || !curve.length) return '';
        const max = Math.max(...curve, 1);
        const labels = ['0', '1', '2', '3', '4', '5', '6+'];
        return '<div class="deck-curve">' + curve.map((n, i) =>
            `<div class="bar" style="height:${Math.round((n / max) * 100)}%;" title="${labels[i] ?? i}: ${n}"><span>${labels[i] ?? i}</span></div>`
        ).join('') + '</div>';
    }

    function renderDeck(d) {
        const cards = (d.key_cards || []).map(c => `<span>${M.escHtml(c)}</span>`).join('');
        const missing = (d.missing_cards || []).map(c => `<span class="missing">${M.escHtml(c)}</span>`).join('');
        const meta = [];
        if (d.format) meta.push(`<b>${M.escHtml(d.format)}</b>`);
        if (d.archetype) meta.push(M.escHtml(d.archetype));
        if (d.card_count) meta.push(`${d.card_count} cards`);
        const mainImg = d.main_card_image
            ? `<img class="deck-main-img" src="${M.escHtml(M.cachedImg(d.main_card_image))}" alt="${M.escHtml(d.main_card || '')}" title="${M.escHtml(d.main_card || '')}" loading="lazy">`
            : '';
        return `<div class="deck-card" data-id="${d.id}">
            ${mainImg}
            <h3>${renderManaPips(d.colors)} <span style="flex:1;">${M.escHtml(d.name)}</span></h3>
            <div class="deck-meta">${meta.join(' &middot; ')}</div>
            ${d.main_card ? `<div class="deck-section"><div class="label">Main card</div><b>${M.escHtml(d.main_card)}</b></div>` : ''}
            ${d.strategy ? `<div class="deck-section"><div class="label">Strategy</div>${M.escHtml(d.strategy)}</div>` : ''}
            ${d.strengths ? `<div class="deck-section"><div class="label">Strengths</div>${M.escHtml(d.strengths)}</div>` : ''}
            ${d.weaknesses ? `<div class="deck-section"><div class="label">Weaknesses</div>${M.escHtml(d.weaknesses)}</div>` : ''}
            ${cards ? `<div class="deck-section"><div class="label">Key cards (yours)</div><div class="deck-cards-list">${cards}</div></div>` : ''}
            ${missing ? `<div class="deck-section"><div class="label">Would improve it</div><div class="deck-cards-list">${missing}</div></div>` : ''}
            ${d.mana_curve ? `<div class="deck-section"><div class="label">Mana curve</div>${renderManaCurve(d.mana_curve)}</div>` : ''}
            <div class="deck-actions">
                <button class="fav ${d.is_favorite ? 'on' : ''}" data-act="fav">${d.is_favorite ? '★ Favorite' : '☆ Favorite'}</button>
                <button class="del" data-act="del" style="margin-left:auto;">Delete</button>
            </div>
        </div>`;
    }

    async function loadDecks() {
        const list = document.getElementById('decks-list');
        const empty = document.getElementById('decks-empty-msg');
        const decks = await M.api('list_decks');
        if (!Array.isArray(decks)) return;
        list.innerHTML = decks.map(renderDeck).join('');
        empty.style.display = decks.length ? 'none' : '';
        decksLoaded = true;
    }

    function startLoading() {
        const loadingEl = document.getElementById('decks-loading');
        const msgEl = document.getElementById('decks-loading-msg');
        const fillEl = document.getElementById('decks-progress-fill');
        const labelEl = document.getElementById('decks-progress-label');
        document.getElementById('decks-toolbar').style.display = 'none';
        document.getElementById('decks-list').style.display = 'none';
        document.getElementById('decks-empty-msg').style.display = 'none';
        loadingEl.style.display = 'flex';

        // Pick a fresh random subset (Fisher-Yates), then alternate within it.
        const idx = LOADING_MESSAGES.map((_, i) => i);
        for (let i = idx.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [idx[i], idx[j]] = [idx[j], idx[i]];
        }
        const subset = idx.slice(0, Math.min(LOADING_SUBSET_SIZE, LOADING_MESSAGES.length))
            .map(i => LOADING_MESSAGES[i]);
        let pool = subset.slice();
        const next = () => {
            if (pool.length === 0) pool = subset.slice();
            const i = Math.floor(Math.random() * pool.length);
            const msg = pool.splice(i, 1)[0];
            msgEl.style.opacity = '0';
            setTimeout(() => { msgEl.textContent = msg; msgEl.style.opacity = '1'; }, 200);
        };
        next();
        loadingTimer = setInterval(next, 2800);

        fillEl.classList.remove('over', 'late');
        fillEl.style.width = '0%';
        const start = Date.now();
        const tick = () => {
            const elapsed = (Date.now() - start) / 1000;
            const ratio = elapsed / ESTIMATED_LOADING_SECONDS;
            if (ratio < 1) {
                fillEl.style.width = (ratio * 100).toFixed(1) + '%';
                labelEl.textContent = `${Math.floor(elapsed)}s / ~${ESTIMATED_LOADING_SECONDS}s`;
            } else {
                fillEl.style.width = '100%';
                if (ratio >= 2) {
                    fillEl.classList.add('late');
                    fillEl.classList.remove('over');
                    labelEl.textContent = `${Math.floor(elapsed)}s — Claude is taking unusually long`;
                } else {
                    fillEl.classList.add('over');
                    labelEl.textContent = `${Math.floor(elapsed)}s — longer than expected`;
                }
            }
        };
        tick();
        progressTimer = setInterval(tick, 250);
    }

    function stopLoading() {
        if (loadingTimer) { clearInterval(loadingTimer); loadingTimer = null; }
        if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
        document.getElementById('decks-loading').style.display = 'none';
        document.getElementById('decks-toolbar').style.display = '';
        document.getElementById('decks-list').style.display = '';
    }

    function wireKeyForms() {
        document.getElementById('claude-key-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('claude-key-input');
            const errEl = document.getElementById('claude-key-error');
            const value = input.value.trim();
            if (!value) return;
            errEl.style.display = 'none';
            const resp = await M.api('save_setting', { key: 'claude_api_key', value });
            if (resp && resp.ok) {
                document.getElementById('decks-api-key-form').style.display = 'none';
                document.getElementById('decks-main').style.display = '';
                input.value = '';
                if (!decksLoaded) loadDecks();
            } else {
                errEl.textContent = (resp && resp.error) || 'Could not save key';
                errEl.style.display = '';
            }
        });

        document.getElementById('claude-key-update')?.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('decks-api-key-form').style.display = '';
            document.getElementById('decks-main').style.display = 'none';
            document.getElementById('claude-key-cancel').style.display = 'inline';
        });

        document.getElementById('claude-key-cancel')?.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('decks-api-key-form').style.display = 'none';
            document.getElementById('decks-main').style.display = '';
            document.getElementById('claude-key-input').value = '';
            document.getElementById('claude-key-error').style.display = 'none';
        });
    }

    function wireDeckListClicks() {
        document.getElementById('decks-list')?.addEventListener('click', async (e) => {
            const btn = e.target.closest('button[data-act]');
            const card = e.target.closest('.deck-card');
            if (!card) return;
            if (btn) {
                e.stopPropagation();
                const id = parseInt(card.dataset.id, 10);
                if (btn.dataset.act === 'del') {
                    if (!confirm('Delete this deck?')) return;
                    const r = await M.api('delete_deck', { id });
                    if (r && r.ok) card.remove();
                } else if (btn.dataset.act === 'fav') {
                    const r = await M.api('toggle_deck_favorite', { id });
                    if (r && r.ok) {
                        btn.classList.toggle('on', r.is_favorite);
                        btn.textContent = r.is_favorite ? '★ Favorite' : '☆ Favorite';
                    }
                }
                return;
            }
            const id = parseInt(card.dataset.id, 10);
            if (id) location.href = '/decks/' + id;
        });
    }

    function wireSuggestButton() {
        document.getElementById('decks-cancel-btn')?.addEventListener('click', () => {
            if (suggestController) suggestController.abort();
        });

        document.getElementById('suggest-decks-btn')?.addEventListener('click', async () => {
            const statusEl = document.getElementById('suggest-decks-status');
            statusEl.textContent = '';
            startLoading();
            suggestController = new AbortController();
            const timeoutId = setTimeout(() => suggestController.abort(), 600000);  // 10 min hard cap
            const resp = await M.api('suggest_decks', {}, { signal: suggestController.signal });
            clearTimeout(timeoutId);
            suggestController = null;
            stopLoading();
            if (resp && resp.ok) {
                const n = (resp.saved_ids || []).length;
                statusEl.textContent = `Saved ${n} new deck${n === 1 ? '' : 's'} from ${resp.unique_cards} unique cards.`;
                statusEl.style.color = '';
                await loadDecks();
            } else if (resp && resp.cancelled) {
                statusEl.style.color = 'var(--text-muted)';
                statusEl.textContent = 'Cancelled.';
            } else {
                statusEl.style.color = 'var(--red)';
                const msg = (resp && (resp.detail || resp.error)) || 'Request failed (no response)';
                statusEl.textContent = `Failed: ${msg}`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        wireKeyForms();
        wireDeckListClicks();
        wireSuggestButton();
    });

    M.tabs.decks = {
        load: () => {
            // Only load if the API key is set and the main view is visible.
            if (!M.hasClaudeKey) return;
            if (!decksLoaded) loadDecks();
        },
    };
})();
