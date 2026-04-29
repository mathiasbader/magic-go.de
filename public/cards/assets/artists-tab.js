/**
 * Artists tab — list owned artists with country/language filters and sorting.
 * Sort preference is persisted in localStorage so it survives reloads.
 */
(function () {
    const M = window.MAGIC;
    let artists = [];
    let sortField = localStorage.getItem('artistSortField') || 'name';
    let sortDir = localStorage.getItem('artistSortDir') || (sortField === 'designs' ? 'desc' : 'asc');

    async function load() {
        artists = await M.api('list_artists');
        if (!Array.isArray(artists)) artists = [];

        const countryCounts = {};
        artists.forEach(a => { if (a.country) countryCounts[a.country] = (countryCounts[a.country] || 0) + 1; });
        const countries = Object.keys(countryCounts).sort((a, b) => (M.countryNames[a] || a).localeCompare(M.countryNames[b] || b));
        const countryEl = document.getElementById('artist-country-filter');
        countryEl.innerHTML = '<option value="">All countries</option>'
            + countries.map(c => `<option value="${c}">${M.countryNames[c] || c} (${countryCounts[c]})</option>`).join('');

        const langCounts = {};
        artists.forEach(a => { if (a.lang) langCounts[a.lang] = (langCounts[a.lang] || 0) + 1; });
        const langs = Object.keys(langCounts).sort((a, b) => (M.langNames[a] || a).localeCompare(M.langNames[b] || b));
        const langEl = document.getElementById('artist-lang-filter');
        langEl.innerHTML = '<option value="">All languages</option>'
            + langs.map(l => `<option value="${l}">${M.langNames[l] || l} (${langCounts[l]})</option>`).join('');

        render();

        const orphanResp = await M.api('count_orphan_artists');
        const orphanEl = document.getElementById('artist-orphans');
        const cnt = parseInt(orphanResp.cnt) || 0;
        if (cnt > 0) {
            orphanEl.style.display = '';
            orphanEl.innerHTML = `${cnt} artist${cnt !== 1 ? 's' : ''} without cards in collection. <a href="#" onclick="window.MAGIC.artistsTab.deleteOrphans();return false;" style="color:var(--red);border-bottom:1px dotted var(--red);text-decoration:none;">Delete</a>`;
        } else {
            orphanEl.style.display = 'none';
        }
    }

    function render() {
        const listEl = document.getElementById('artist-list');
        const q = (document.getElementById('artist-filter-input').value || '').trim().toLowerCase();
        const countryFilter = document.getElementById('artist-country-filter').value;
        const langFilter = document.getElementById('artist-lang-filter').value;

        if (!artists.length) {
            listEl.innerHTML = '<div class="empty-state"><p>No artists yet. Import cards to populate.</p></div>';
            return;
        }
        let filtered = [...artists];
        if (q) filtered = filtered.filter(a => a.name.toLowerCase().includes(q));
        if (countryFilter) filtered = filtered.filter(a => a.country === countryFilter);
        if (langFilter) filtered = filtered.filter(a => a.lang === langFilter);
        if (!filtered.length) {
            listEl.innerHTML = '<div class="empty-state"><p>No artists match your filter.</p></div>';
            return;
        }

        filtered.sort((a, b) => {
            let cmp = 0;
            if (sortField === 'name') cmp = (a.name || '').localeCompare(b.name || '');
            else if (sortField === 'designs') cmp = (a.card_count || 0) - (b.card_count || 0);
            return sortDir === 'desc' ? -cmp : cmp;
        });

        listEl.innerHTML = filtered.map(a => {
            const urls = (a.urls || []).map(u => {
                if (u.label) return M.escHtml(u.label);
                try {
                    const p = new URL(u.url);
                    const path = p.pathname.replace(/\/$/, '');
                    return M.escHtml(p.hostname + (path.length > 5 ? path.substring(0, 5) + '...' : path));
                } catch { return M.escHtml(u.url); }
            }).join(', ');
            const meta = [];
            if (a.country) meta.push(M.countryNames[a.country] || a.country);
            if (a.birth_year) meta.push('b. ' + a.birth_year);
            return `<a href="/cards/artists/${a.id}" class="artist-row">
                <div class="artist-row-info">
                    <div class="artist-row-name">${q ? M.highlightMatch(a.name, q) : M.escHtml(a.name)}</div>
                    <div class="artist-row-count">${a.card_count} design${a.card_count != 1 ? 's' : ''}${meta.length ? ' · ' + meta.join(', ') : ''}</div>
                    ${urls ? `<div class="artist-row-url">${urls}</div>` : ''}
                </div>
                <div class="artist-row-thumb">
                    ${a.sample_image ? `<img src="${M.cachedImg(a.sample_image.replace('/normal/', '/art_crop/'))}" alt="" loading="lazy">` : ''}
                </div>
            </a>`;
        }).join('');
    }

    async function deleteOrphans() {
        const el = document.getElementById('artist-orphans');
        el.innerHTML = 'Deleting...';
        const resp = await M.api('delete_orphan_artists');
        el.innerHTML = `<span style="color:var(--green);">Deleted ${resp.deleted || 0} orphan artists.</span>`;
        setTimeout(() => { el.style.display = 'none'; }, 2000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-artist-sort]').forEach(b => {
            b.classList.toggle('active', b.dataset.artistSort === sortField);
            if (b.dataset.artistSort === sortField) {
                b.querySelector('.sort-arrow').innerHTML = sortDir === 'asc' ? '&uarr;' : '&darr;';
            }
            b.addEventListener('click', () => {
                const field = b.dataset.artistSort;
                if (sortField === field) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                else { sortField = field; sortDir = field === 'designs' ? 'desc' : 'asc'; }
                localStorage.setItem('artistSortField', sortField);
                localStorage.setItem('artistSortDir', sortDir);
                document.querySelectorAll('[data-artist-sort]').forEach(x => x.classList.remove('active'));
                b.classList.add('active');
                b.querySelector('.sort-arrow').innerHTML = sortDir === 'asc' ? '&uarr;' : '&darr;';
                render();
            });
        });
        document.getElementById('artist-filter-input')?.addEventListener('input', render);
        document.getElementById('artist-country-filter')?.addEventListener('change', render);
        document.getElementById('artist-lang-filter')?.addEventListener('change', render);
    });

    M.tabs.artists = { load };
    M.artistsTab = { deleteOrphans };
})();
