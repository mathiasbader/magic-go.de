/**
 * Card detail page. Reads bootstrap data (scryfallId, owned copy, all copies,
 * CSRF token) from window.MAGIC_CARD set inline by card.php, then fetches
 * the live Scryfall card and renders the detail view.
 */
(function () {
    const M = window.MAGIC_CARD || {};
    const scryfallId = M.scryfallId || '';
    const owned = M.owned || null;
    const allCopies = M.allCopies || [];
    const csrfToken = M.csrf || '';

    const langNames = {en:'English',de:'German',fr:'French',es:'Spanish',it:'Italian',pt:'Portuguese',ja:'Japanese',ko:'Korean',zhs:'Chinese (S)',zht:'Chinese (T)',ru:'Russian',ph:'Phyrexian'};
    let currentCard = null;

    function cachedImg(url) {
        if (!url) return url;
        if (url.includes('cards.scryfall.io') || url.includes('svgs.scryfall.io')) {
            return '/img/cache?url=' + encodeURIComponent(url);
        }
        return url;
    }

    function scryfallApi(path) {
        return '/api/scryfall?path=' + encodeURIComponent(path);
    }

    async function apiCall(action, data) {
        const resp = await fetch('/cards/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, _csrf: csrfToken, ...data })
        });
        return resp.json();
    }

    fetch(scryfallApi(`/cards/${scryfallId}`))
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(async card => {
            const isFoil = owned && owned.foil == 1;
            let price = isFoil ? card.prices?.eur_foil : card.prices?.eur;
            let priceIsEnglish = false;

            if (!price && card.lang !== 'en') {
                try {
                    const enResp = await fetch(scryfallApi(`/cards/${card.set}/${card.collector_number}/en`));
                    if (enResp.ok) {
                        const enCard = await enResp.json();
                        price = isFoil ? enCard.prices?.eur_foil : enCard.prices?.eur;
                        if (price) priceIsEnglish = true;
                    }
                } catch {}
            }

            if (price && owned) {
                const today = new Date().toISOString().slice(0, 10);
                apiCall('update_price', {
                    id: owned.id,
                    market_price: parseFloat(price),
                    market_price_date: today,
                    market_price_is_english: card.lang !== 'en' ? (priceIsEnglish ? 1 : 0) : null,
                });
            }

            try {
                render(card, price, priceIsEnglish);
            } catch (e) {
                console.error('Render error:', e);
                if (owned) renderFallback();
            }
        })
        .catch((e) => {
            console.error('Scryfall fetch error:', e);
            if (owned) {
                renderFallback();
            } else {
                document.getElementById('content').innerHTML = '<p>Card not found.</p>';
            }
        });

    function renderFallback() {
        document.getElementById('content').classList.remove('loading');
        const artCropUrl = owned.image_uri_normal ? owned.image_uri_normal.replace('/normal/', '/art_crop/') : '';
        const pt = owned.power && owned.toughness ? `${owned.power}/${owned.toughness}` : '';
        document.getElementById('content').innerHTML = `
            <div class="card-detail">
                <div class="card-image">
                    ${owned.image_uri_normal ? `<img src="${cachedImg(owned.image_uri_normal)}" alt="${esc(owned.name)}" style="cursor:pointer;" onclick="showArt(this)" data-art="${cachedImg(artCropUrl)}">` : ''}
                </div>
                <div class="card-info">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.25rem;">
                        <h1>${esc(owned.name)}</h1>
                        ${owned.mana_cost ? `<div class="mana-cost" style="margin:0;white-space:nowrap;">${mana(owned.mana_cost)}</div>` : ''}
                    </div>
                    <div class="type-line">${esc(owned.type_line || '')}</div>
                    ${renderOwnedBadge()}
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Set</div><div class="info-value"><a href="/cards/?set=${owned.set_code}" style="color:var(--accent);text-decoration:none;">${owned.set_code.toUpperCase()}</a></div></div>
                        <div class="info-item"><div class="info-label">Number</div><div class="info-value">#${owned.collector_number}</div></div>
                        <div class="info-item"><div class="info-label">Rarity</div><div class="info-value" style="text-transform:capitalize;">${owned.rarity || '?'}</div></div>
                        <div class="info-item"><div class="info-label">Artist</div><div class="info-value">${renderArtist(owned.artist)}</div></div>
                        ${owned.binder ? `<div class="info-item"><div class="info-label">Binder</div><div class="info-value"><a href="/cards/?binder=${encodeURIComponent(owned.binder)}" style="color:var(--accent);text-decoration:none;">${esc(owned.binder)}</a></div></div>` : ''}
                        ${pt ? `<div class="info-item"><div class="info-label">Power / Toughness</div><div class="info-value">${pt}</div></div>` : ''}
                        <div class="info-item"><div class="info-label">Mana Value</div><div class="info-value">${owned.cmc ?? ''}</div></div>
                        <div class="info-item"><div class="info-label">Language</div><div class="info-value"><a href="/cards/?lang=${owned.language}" style="color:var(--accent);text-decoration:none;">${langNames[owned.language] || owned.language}</a></div></div>
                    </div>
                    ${owned.oracle_text ? `<div class="oracle-text">${mana(esc(owned.oracle_text))}</div>` : ''}
                    ${owned.market_price ? `
                        <div class="info-label" style="margin-bottom:0.25rem;">Price <span style="opacity:0.5">(${owned.market_price_date})</span></div>
                        <div class="prices">
                            <div class="price-tag">${parseFloat(owned.market_price).toFixed(2).replace('.', ',')} &euro;${owned.market_price_is_english == 1 ? ' <span style="opacity:0.5">(en)</span>' : ''}</div>
                        </div>
                    ` : ''}
                    ${owned ? `<div id="delete-area" style="margin-top:1.5rem;">
                        <a href="#" onclick="showDeleteConfirm();return false;" style="color:var(--red);font-size:0.85rem;text-decoration:none;border-bottom:1px dotted var(--red);">Delete from collection</a>
                    </div>` : ''}
                    <p style="color:var(--text-muted);font-size:0.8rem;margin-top:1rem;">Scryfall unavailable. Showing stored data.</p>
                </div>
            </div>`;
        document.title = owned.name + ' - Magic: The Gathering';
        if (owned.image_uri_small) setFavicon(cachedImg(owned.image_uri_small));
        if (artCropUrl) { const p = new Image(); p.src = cachedImg(artCropUrl); }
    }

    function render(card, resolvedPrice, priceIsEnglish) {
        currentCard = card;
        document.getElementById('content').classList.remove('loading');
        const imgs = card.image_uris || card.card_faces?.[0]?.image_uris || {};
        const largeImg = imgs.large || imgs.normal || '';
        const pngImg = imgs.png || largeImg;
        const artCropImg = imgs.art_crop || '';
        const displayName = card.printed_name || card.name;
        const displayType = card.printed_type_line || card.type_line || '';
        const oracle = card.printed_text || card.oracle_text || card.card_faces?.map(f => (f.printed_name || f.name) + '\n' + (f.printed_text || f.oracle_text)).join('\n\n---\n\n') || '';
        const flavor = card.flavor_text || '';
        const pt = card.power && card.toughness ? `${card.power}/${card.toughness}` : '';
        const loyalty = card.loyalty || '';
        const cmc = card.cmc ?? '';
        const manaCost = card.mana_cost || card.card_faces?.map(f => f.mana_cost).filter(Boolean).join(' // ') || '';

        const legalFmts = ['standard', 'pioneer', 'modern', 'legacy', 'vintage', 'commander', 'pauper'];
        const legalities = legalFmts.map(f => {
            const status = card.legalities?.[f] || 'not_legal';
            return `<span class="legality-tag legality-${status}">${f}: ${status.replace('_', ' ')}</span>`;
        }).join('');

        const isFoil = owned && owned.foil == 1;
        let priceHtml = '';
        if (resolvedPrice) {
            const val = parseFloat(resolvedPrice).toFixed(2).replace('.', ',');
            const enTag = priceIsEnglish ? ' <span style="opacity:0.5">(en)</span>' : '';
            const foilTag = isFoil ? ' <span>Foil</span>' : '';
            priceHtml = `<div class="price-tag">${val} &euro;${foilTag}${enTag}</div>`;
        } else if (owned?.market_price) {
            const val = parseFloat(owned.market_price).toFixed(2).replace('.', ',');
            const enTag = owned.market_price_is_english == 1 ? ' <span style="opacity:0.5">(en)</span>' : '';
            const foilTag = isFoil ? ' <span>Foil</span>' : '';
            priceHtml = `<div class="price-tag">${val} &euro;${foilTag}${enTag} <span style="opacity:0.5">(${owned.market_price_date})</span></div>`;
        }

        const setName = card.set_name || card.set.toUpperCase();

        let secondFace = '';
        if (card.card_faces && card.card_faces.length > 1 && card.card_faces[1]?.image_uris) {
            secondFace = `<img src="${cachedImg(card.card_faces[1].image_uris.large || card.card_faces[1].image_uris.normal)}" alt="${esc(card.card_faces[1].name)}" style="margin-top:1rem;">`;
        }

        document.getElementById('content').innerHTML = `
            <div class="card-detail">
                <div class="card-image">
                    ${owned?.image_language && owned.language !== owned.image_language ? `<div style="background:var(--amber);color:#000;text-align:center;padding:0.4rem;font-size:0.85rem;font-weight:600;border-radius:8px 8px 0 0;">Image is ${langNames[owned.image_language] || owned.image_language} &middot; card is ${langNames[owned.language] || owned.language}</div>` : ''}
                    <img src="${cachedImg(pngImg)}" alt="${esc(displayName)}" style="cursor:pointer;${owned?.image_language && owned.language !== owned.image_language ? 'border-radius:0 0 12px 12px;' : ''}" onclick="showArt(this)" data-art="${cachedImg(artCropImg)}">
                    ${secondFace}
                </div>
                <div class="card-info">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5rem;margin-bottom:0.25rem;">
                        <h1>${esc(displayName)}</h1>
                        ${manaCost ? `<div class="mana-cost" style="margin:0;white-space:nowrap;">${mana(manaCost)}</div>` : ''}
                    </div>
                    ${card.printed_name && card.printed_name !== card.name ? `<div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.25rem;">${esc(card.name)}</div>` : ''}
                    <div class="type-line">${esc(displayType)}</div>
                    ${owned ? renderOwnedBadge() : `<div id="add-to-collection-area" style="margin-bottom:0.75rem;">${notInCollectionBadge()}</div>`}

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Set</div>
                            <div class="info-value"><a href="/cards/?set=${card.set}" style="color:var(--accent);text-decoration:none;">${esc(setName)}</a></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Number</div>
                            <div class="info-value">#${card.collector_number}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Rarity</div>
                            <div class="info-value" style="text-transform:capitalize;">${card.rarity}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Artist</div>
                            <div class="info-value" id="artist-cell">${renderArtist(card.artist)}</div>
                        </div>
                        ${owned?.binder ? `<div class="info-item"><div class="info-label">Binder</div><div class="info-value"><a href="/cards/?binder=${encodeURIComponent(owned.binder)}" style="color:var(--accent);text-decoration:none;">${esc(owned.binder)}</a></div></div>` : ''}
                        ${pt ? `<div class="info-item"><div class="info-label">Power / Toughness</div><div class="info-value">${pt}</div></div>` : ''}
                        ${loyalty ? `<div class="info-item"><div class="info-label">Loyalty</div><div class="info-value">${loyalty}</div></div>` : ''}
                        <div class="info-item">
                            <div class="info-label">Mana Value</div>
                            <div class="info-value">${cmc}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Released</div>
                            <div class="info-value">${card.released_at || '?'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Language</div>
                            <div class="info-value"><a href="/cards/?lang=${owned?.language || card.lang}" style="color:var(--accent);text-decoration:none;">${langNames[owned?.language || card.lang] || owned?.language || card.lang}</a></div>
                        </div>
                    </div>

                    ${oracle ? `<div class="oracle-text">${mana(esc(oracle))}</div>` : ''}
                    ${flavor ? `<div class="flavor-text">${esc(flavor)}</div>` : ''}

                    ${priceHtml ? `<div class="info-label" style="margin-bottom:0.25rem;">Price</div><div class="prices">${priceHtml}</div>` : ''}

                    <div class="info-label" style="margin-top:1rem;margin-bottom:0.25rem;">Format Legality</div>
                    <div class="legalities">${legalities}</div>

                    <div class="card-links">
                        <a href="${esc(card.scryfall_uri)}" target="_blank" rel="noopener">Scryfall</a>
                        ${card.related_uris?.gatherer ? `<a href="${esc(card.related_uris.gatherer)}" target="_blank" rel="noopener">Gatherer</a>` : ''}
                        ${card.related_uris?.edhrec ? `<a href="${esc(card.related_uris.edhrec)}" target="_blank" rel="noopener">EDHREC</a>` : ''}
                        ${card.purchase_uris?.tcgplayer ? `<a href="${esc(card.purchase_uris.tcgplayer)}" target="_blank" rel="noopener">TCGplayer</a>` : ''}
                        ${card.purchase_uris?.cardmarket ? `<a href="${esc(card.purchase_uris.cardmarket)}" target="_blank" rel="noopener">Cardmarket</a>` : ''}
                    </div>
                    ${owned ? `<div id="delete-area" style="margin-top:1.5rem;">
                        <a href="#" onclick="showDeleteConfirm();return false;" style="color:var(--red);font-size:0.85rem;text-decoration:none;border-bottom:1px dotted var(--red);">Delete from collection</a>
                    </div>` : ''}
                </div>
            </div>
        `;

        document.title = displayName + ' - Magic: The Gathering';
        const smallImg = card.image_uris?.small || card.card_faces?.[0]?.image_uris?.small || '';
        if (smallImg) setFavicon(cachedImg(smallImg));
        if (artCropImg) { const p = new Image(); p.src = cachedImg(artCropImg); }
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function setFavicon(url) {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function() {
            try {
                const size = 64;
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const ctx = canvas.getContext('2d');
                const scale = Math.min(size / img.width, size / img.height);
                const w = img.width * scale;
                const h = img.height * scale;
                ctx.drawImage(img, (size - w) / 2, (size - h) / 2, w, h);
                applyFavicon(canvas.toDataURL('image/png'));
            } catch (e) {
                applyFavicon(url);
            }
        };
        img.onerror = function() { applyFavicon(url); };
        img.src = url;
    }

    function applyFavicon(href) {
        let link = document.querySelector('link[rel="icon"]');
        if (!link) { link = document.createElement('link'); link.rel = 'icon'; document.head.appendChild(link); }
        link.href = href;
    }

    function mana(s) {
        if (!s) return '';
        return s.replace(/\{([^}]+)\}/g, (_, sym) => {
            const code = sym.replace('/', '').toUpperCase();
            return `<img src="${cachedImg('https://svgs.scryfall.io/card-symbols/' + encodeURIComponent(code) + '.svg')}" alt="{${sym}}" style="width:1.1em;height:1.1em;vertical-align:-0.15em;margin-right:5px;">`;
        });
    }

    window.showArt = function(img) {
        const artSrc = img.dataset.art;
        if (!artSrc) return;
        const overlay = document.createElement('div');
        overlay.className = 'art-overlay';
        const artImg = document.createElement('img');
        artImg.src = artSrc;
        artImg.alt = img.alt;
        overlay.appendChild(artImg);
        overlay.addEventListener('click', () => overlay.remove());
        document.addEventListener('keydown', function handler(e) {
            if (e.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', handler); }
        });
        document.body.appendChild(overlay);
    };

    window.showDeleteConfirm = function() {
        document.getElementById('delete-area').innerHTML = `
            <div style="border:1px solid var(--red);border-radius:8px;padding:0.75rem 1rem;background:rgba(248,113,113,0.08);">
                <div style="font-size:0.9rem;color:var(--red);margin-bottom:0.5rem;">Remove this copy from your collection?${allCopies.length > 1 ? ' (' + (allCopies.length - 1) + ' other cop' + (allCopies.length > 2 ? 'ies' : 'y') + ' will remain)' : ''}</div>
                <button onclick="deleteCard()" style="background:var(--red);color:#fff;border:none;border-radius:6px;padding:0.35rem 0.8rem;font-weight:600;cursor:pointer;font-family:inherit;font-size:0.85rem;">Delete</button>
                <button onclick="cancelDelete()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:0.35rem 0.8rem;color:var(--text-muted);cursor:pointer;font-family:inherit;font-size:0.85rem;margin-left:0.3rem;">Cancel</button>
            </div>`;
    };

    window.cancelDelete = function() {
        document.getElementById('delete-area').innerHTML = `
            <a href="#" onclick="showDeleteConfirm();return false;" style="color:var(--red);font-size:0.85rem;text-decoration:none;border-bottom:1px dotted var(--red);">Delete from collection</a>`;
    };

    window.deleteCard = async function() {
        await apiCall('delete', { id: owned.id });
        window.location.href = '/cards/';
    };

    function renderOwnedBadge() {
        const count = allCopies.length;
        if (count <= 1) {
            return `<div class="owned-badge">In collection</div>`;
        }
        const copies = allCopies.map((c, i) => {
            const label = `Copy ${i + 1}`;
            const details = [c.foil == 1 ? 'Foil' : null, c.condition ? c.condition.replace('_', ' ') : null, c.binder || null].filter(Boolean).join(', ');
            const isCurrent = c.id == owned.id;
            if (isCurrent) {
                return `<span style="font-weight:600;">${label}</span>${details ? ` <span style="color:var(--text-muted);font-size:0.8rem;">(${details})</span>` : ''}`;
            }
            return `<a href="/cards/${c.id}" style="color:var(--accent);text-decoration:none;">${label}</a>${details ? ` <span style="color:var(--text-muted);font-size:0.8rem;">(${details})</span>` : ''}`;
        }).join('<br>');
        return `<div class="owned-badge">In collection: ${count}x</div>
            <div style="font-size:0.85rem;margin-bottom:0.75rem;line-height:1.8;">${copies}</div>`;
    }

    function notInCollectionBadge() {
        return `<div onclick="showAddToCollection()" title="Click to add to your collection" style="background:rgba(148,163,184,0.15);color:var(--text-muted);font-size:0.8rem;font-weight:600;padding:0.25rem 0.6rem;border-radius:6px;display:inline-block;cursor:pointer;">Not in collection</div>`;
    }

    window.showAddToCollection = async function() {
        const area = document.getElementById('add-to-collection-area');
        if (!area || !currentCard) return;
        const binders = await apiCall('list_binders');
        const names = (Array.isArray(binders) ? binders : []).map(b => b.binder || '');
        const distinct = [];
        if (!names.includes('')) distinct.push('');
        names.forEach(n => { if (!distinct.includes(n)) distinct.push(n); });
        const options = distinct.map(b => `<option value="${esc(b)}">${esc(b || '(no binder)')}</option>`).join('');
        area.innerHTML = `
            <div style="border:1px solid var(--border);border-radius:8px;padding:0.75rem 1rem;background:var(--surface);display:inline-block;">
                <div style="font-size:0.85rem;color:var(--text-muted);margin-bottom:0.5rem;">Add to collection</div>
                <select id="add-binder-select" style="background:var(--bg);border:1px solid var(--border);color:var(--text);border-radius:4px;padding:0.3rem 0.5rem;font-family:inherit;font-size:0.85rem;margin-right:0.4rem;">
                    ${options}
                </select>
                <button onclick="doAddToCollection(this)" style="background:var(--accent);color:#000;border:none;border-radius:6px;padding:0.35rem 0.8rem;font-weight:600;cursor:pointer;font-family:inherit;font-size:0.85rem;">Add to collection</button>
                <button onclick="cancelAddToCollection()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:0.35rem 0.8rem;color:var(--text-muted);cursor:pointer;font-family:inherit;font-size:0.85rem;margin-left:0.3rem;">Cancel</button>
            </div>`;
    };

    window.cancelAddToCollection = function() {
        const area = document.getElementById('add-to-collection-area');
        if (area) area.innerHTML = notInCollectionBadge();
    };

    window.doAddToCollection = async function(btn) {
        if (!currentCard) return;
        if (btn) { btn.disabled = true; btn.textContent = 'Adding...'; }
        const binder = document.getElementById('add-binder-select').value;
        const imgs = currentCard.image_uris || currentCard.card_faces?.[0]?.image_uris || {};
        const result = await apiCall('add', {
            scryfall_id: currentCard.id,
            name: currentCard.printed_name || currentCard.name,
            set_code: currentCard.set,
            collector_number: currentCard.collector_number,
            image_uri_small: imgs.small || null,
            image_uri_normal: imgs.normal || null,
            mana_cost: currentCard.mana_cost || null,
            type_line: currentCard.type_line || null,
            rarity: currentCard.rarity || null,
            language: currentCard.lang || 'en',
            image_language: currentCard.lang || 'en',
            artist: currentCard.artist || null,
            binder: binder || null,
            quantity: 1,
        });
        if (result && result.ok) {
            window.location.reload();
        } else {
            alert('Failed to add card: ' + (result && result.error ? result.error : 'unknown error'));
            if (btn) { btn.disabled = false; btn.textContent = 'Add to collection'; }
        }
    };

    function renderArtist(artistName) {
        if (!artistName) return 'Unknown';
        const artistId = owned?.artist_id;
        if (artistId) {
            return `<a href="/cards/artists/${artistId}" class="artist-link">${esc(artistName)}</a>`;
        }
        return esc(artistName);
    }
})();
