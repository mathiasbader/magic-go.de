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
})();
