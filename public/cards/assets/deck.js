/**
 * Deck detail page — wires up the favorite/delete buttons. Bootstrap data
 * (deck id, CSRF token) read from window.MAGIC_DECK set inline by deck.php.
 */
(function () {
    const M = window.MAGIC_DECK || {};
    const deckId = M.id;
    const csrfToken = M.csrf || '';

    async function api(action, data = {}) {
        const r = await fetch('/cards/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, _csrf: csrfToken, ...data }),
        });
        return r.json();
    }

    document.querySelectorAll('button[data-act]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (btn.dataset.act === 'del') {
                if (!confirm('Delete this deck?')) return;
                const r = await api('delete_deck', { id: deckId });
                if (r && r.ok) location.href = '/decks/';
            } else if (btn.dataset.act === 'fav') {
                const r = await api('toggle_deck_favorite', { id: deckId });
                if (r && r.ok) {
                    btn.classList.toggle('on', r.is_favorite);
                    btn.textContent = r.is_favorite ? '★ Favorite' : '☆ Favorite';
                }
            }
        });
    });

    // Enrich the "Cards that would improve the deck" tiles with the Scryfall
    // image + a click-through to the card detail page (which can render any
    // Scryfall ID, even for cards not in the user's collection).
    document.querySelectorAll('.missing-card').forEach(async tile => {
        const name = tile.dataset.name;
        if (!name) return;
        try {
            const path = '/cards/named?fuzzy=' + encodeURIComponent(name);
            const r = await fetch('/api/scryfall?path=' + encodeURIComponent(path));
            if (!r.ok) return;
            const card = await r.json();
            const img = card.image_uris?.normal
                || card.image_uris?.small
                || card.card_faces?.[0]?.image_uris?.normal
                || card.card_faces?.[0]?.image_uris?.small;
            if (img) {
                const wrap = tile.querySelector('.card-tile-wrap');
                wrap.innerHTML = `<img src="/img/cache?url=${encodeURIComponent(img)}" alt="${card.name}" loading="lazy">`;
            }
            if (card.id) {
                tile.setAttribute('href', '/cards/' + card.id);
            }
        } catch {}
    });
})();
