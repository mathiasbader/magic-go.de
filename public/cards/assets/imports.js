/**
 * Imports page — drag/drop CSV upload, preview, batch import via Scryfall
 * bulk lookup, plus binder list and rename/move/language flows.
 *
 * Bootstrap data (CSRF token) read from window.MAGIC_IMPORTS set inline by
 * imports.php.
 */
(function () {
    const M = window.MAGIC_IMPORTS || {};
    const CSRF = M.csrf || '';

    function scryfallApi(path) {
        return '/api/scryfall?path=' + encodeURIComponent(path);
    }

    async function api(action, data = {}) {
        const resp = await fetch('/cards/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, _csrf: CSRF, ...data })
        });
        const text = await resp.text();
        if (!resp.ok) {
            console.error(`API ${action} failed (${resp.status}):`, text.substring(0, 500));
            return { error: resp.status, detail: text.substring(0, 500) };
        }
        try { return JSON.parse(text); } catch { return { error: 'invalid json', detail: text.substring(0, 500) }; }
    }

    function showToast(msg) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.classList.add('visible');
        setTimeout(() => t.classList.remove('visible'), 1500);
    }

    async function load() {
        const [batches, unassigned] = await Promise.all([
            api('list_batches'),
            api('count_unassigned'),
        ]);
        const el = document.getElementById('content');
        const unassignedCount = parseInt(unassigned.cnt) || 0;

        if (!batches.length && !unassignedCount) {
            el.innerHTML = '<div class="empty">No imports yet.</div>';
            return;
        }

        const unassignedRow = unassignedCount > 0 ? `
            <tr style="color:var(--text-muted);">
                <td>-</td>
                <td>Manually added</td>
                <td>-</td>
                <td>${unassignedCount}</td>
                <td><button class="delete-btn" onclick="deleteBatch('unassigned', this)">Delete</button></td>
            </tr>` : '';

        el.innerHTML = `
            <table>
                <tr>
                    <th>Date</th>
                    <th>File</th>
                    <th>Sets</th>
                    <th>Cards</th>
                    <th></th>
                </tr>
                ${batches.map(b => {
                    const binderRows = (b.binders || []).map(bi => {
                        const name = bi.binder || '';
                        const label = name || 'No binder';
                        const isNone = !name;
                        return `<span style="${isNone ? 'font-style:italic;color:var(--text-muted);' : ''}">${esc(label)} (${bi.cnt})</span>
                            <button class="binder-action" style="font-size:0.7rem;padding:0.1rem 0.4rem;" onclick="showBinderMove(this, '${esc(name).replace(/'/g, "\\'")}', ${b.id})">Move</button>`;
                    }).join(' &middot; ');
                    return `
                    <tr${binderRows ? ' class="has-binders"' : ''}>
                        <td>${b.imported_at}</td>
                        <td>${esc(b.filename || '-')}${b.format ? `<br><span style="font-size:0.75rem;color:var(--text-muted);">${esc(b.format)}</span>` : ''}</td>
                        <td style="font-size:0.8rem;max-width:250px;">${formatSets(b.sets_imported)}</td>
                        <td>${b.current_cards}</td>
                        <td><button class="delete-btn" onclick="deleteBatch(${b.id}, this)">Delete</button></td>
                    </tr>
                    ${binderRows ? `<tr><td colspan="5" style="padding:0.3rem 0.75rem;font-size:0.8rem;border-bottom:1px solid var(--border);">${binderRows}</td></tr>` : ''}`;
                }).join('')}
                ${unassignedRow}
            </table>
            <div id="delete-all-area">
                <button class="delete-all-btn" onclick="deleteAll()">Delete all cards</button>
            </div>
        `;
    }

    window.deleteBatch = function(id, btn) {
        const row = btn.closest('tr');
        const confirmRow = document.createElement('tr');
        confirmRow.innerHTML = `<td colspan="5" style="padding:0.75rem;background:rgba(248,113,113,0.08);border-bottom:1px solid var(--border);text-align:right;">
            <span style="color:var(--red);font-size:0.85rem;">Delete all cards from this import?</span>
            <button onclick="confirmDeleteBatch('${id}')" style="background:var(--red);color:#fff;border:none;border-radius:4px;padding:0.25rem 0.6rem;font-size:0.8rem;font-weight:600;cursor:pointer;font-family:inherit;margin-left:0.5rem;">Delete</button>
            <button onclick="load()" style="background:none;border:1px solid var(--border);border-radius:4px;padding:0.25rem 0.6rem;font-size:0.8rem;color:var(--text-muted);cursor:pointer;font-family:inherit;margin-left:0.3rem;">Cancel</button>
        </td>`;
        row.after(confirmRow);
        btn.disabled = true;
    };

    window.confirmDeleteBatch = async function(id) {
        if (id === 'unassigned') {
            await api('delete_unassigned', {});
            showToast('Unassigned cards deleted');
        } else {
            await api('delete_batch', { batch_id: parseInt(id) });
            showToast('Import deleted');
        }
        load();
    };

    window.deleteAll = function() {
        const area = document.getElementById('delete-all-area');
        area.innerHTML = `<div style="border:1px solid var(--red);border-radius:8px;padding:1rem 1.25rem;background:rgba(248,113,113,0.08);margin-top:1.5rem;display:flex;gap:1rem;align-items:flex-start;">
            <div style="font-size:2rem;line-height:1;">&#9888;</div>
            <div>
                <div style="font-size:0.95rem;font-weight:600;color:var(--red);margin-bottom:0.4rem;">Delete ALL cards from your collection? This cannot be undone.</div>
                <button onclick="confirmDeleteAll()" style="background:var(--red);color:#fff;border:none;border-radius:6px;padding:0.4rem 0.9rem;font-weight:600;cursor:pointer;font-family:inherit;">Delete all</button>
                <button onclick="load()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.9rem;color:var(--text-muted);cursor:pointer;font-family:inherit;margin-left:0.4rem;">Cancel</button>
            </div>
        </div>`;
    };

    window.confirmDeleteAll = async function() {
        await api('delete_all', {});
        showToast('All cards deleted');
        load();
    };

    window.load = load;

    function formatSets(setsStr) {
        if (!setsStr) return '-';
        try {
            const sets = JSON.parse(setsStr);
            sets.sort((a, b) => (b.year || '').localeCompare(a.year || ''));
            const lines = sets.map(s => {
                const url = `https://scryfall.com/sets/${s.code}`;
                const year = s.year ? `<span style="color:var(--text-muted);">${s.year}</span> ` : '';
                return `${year}<a href="${url}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;font-size:0.8rem;">${esc(s.name)}</a>`;
            });
            if (lines.length <= 3) return lines.join('<br>');
            const id = 'sets-' + Math.random().toString(36).slice(2, 8);
            const visible = lines.slice(0, 2).join('<br>');
            const rest = lines.slice(2).join('<br>');
            return `<span id="${id}">${visible}<br><a href="#" onclick="document.getElementById('${id}').querySelector('.sets-rest').style.display='';this.style.display='none';return false;" style="color:var(--text-muted);font-size:0.75rem;text-decoration:none;">[+${lines.length - 2} more]</a><span class="sets-rest" style="display:none;">${rest}<br><a href="#" onclick="this.parentElement.style.display='none';this.parentElement.previousElementSibling.style.display='';return false;" style="color:var(--text-muted);font-size:0.75rem;text-decoration:none;">Less</a></span></span>`;
        } catch {
            return esc(setsStr);
        }
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    // Drop zone + file select
    const dropZone = document.getElementById('drop-zone');
    const csvFile = document.getElementById('csv-file');

    dropZone.addEventListener('click', () => { if (!dropZone.classList.contains('importing')) { csvFile.value = ''; csvFile.click(); } });
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const file = e.dataTransfer.files[0];
        if (file) handleFile(file);
    });
    csvFile.addEventListener('change', (e) => { if (e.target.files[0]) handleFile(e.target.files[0]); });

    async function handleFile(file) {
        // Detect UTF-16 BOM so we read the file with the correct encoding.
        const header = await file.slice(0, 4).arrayBuffer();
        const bytes = new Uint8Array(header);
        let encoding = 'UTF-8';
        if (bytes[0] === 0xFF && bytes[1] === 0xFE) encoding = 'UTF-16LE';
        else if (bytes[0] === 0xFE && bytes[1] === 0xFF) encoding = 'UTF-16BE';

        const reader = new FileReader();
        const text = await new Promise(resolve => {
            reader.onload = (ev) => resolve(ev.target.result);
            reader.readAsText(file, encoding);
        });

        const rows = parseCsv(text);
        const firstLine = text.split('\n')[0] || '';
        const format = firstLine.includes('Foil/Etched') ? 'Delver MTG' : firstLine.includes('ManaBox ID') ? 'ManaBox' : 'CSV';

        if (!rows.length) {
            document.getElementById('import-info').style.display = '';
            document.getElementById('import-status').textContent = 'No cards found in file.';
            return;
        }

        // Check how many already exist
        const checkCards = rows.map(r => ({ scryfall_id: r.scryfall_id || '', set_code: r.set_code || '', collector_number: r.collector_number || '' }));
        let existingCount = 0;
        let duplicates = [];
        try {
            const resp = await api('check_existing', { cards: checkCards });
            existingCount = resp.count || 0;
            duplicates = resp.duplicates || [];
        } catch (e) {
            console.error('check_existing failed:', e);
        }

        document.getElementById('import-info').style.display = '';

        if (existingCount > 0) {
            const allExist = existingCount >= rows.length;
            const msg = allExist
                ? `All ${rows.length} cards in this file are already imported.`
                : `${existingCount} of ${rows.length} cards are already imported.`;
            const dupeHtml = duplicates.map(d =>
                `<a href="/cards/${d.id}" target="_blank" style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.4rem;text-decoration:none;color:var(--text);">
                    ${d.image ? `<img src="${esc(d.image)}" style="height:60px;border-radius:3px;">` : ''}
                    <div style="font-size:0.8rem;">${esc(d.name)} <span style="color:var(--text-muted);">&mdash; ${d.copies}x in collection</span></div>
                </a>`
            ).join('');
            const msgEl = document.getElementById('import-message');
            msgEl.style.display = '';
            msgEl.innerHTML = `<div style="border:1px solid var(--amber);border-radius:8px;padding:1.25rem 1.5rem;background:rgba(251,191,36,0.08);">
                <div style="display:flex;gap:1.25rem;align-items:flex-start;">
                    <div style="font-size:2.5rem;line-height:1;">&#9888;</div>
                    <div>
                        <div style="font-size:1rem;font-weight:600;color:var(--amber);margin-bottom:0.5rem;">Duplicate cards detected</div>
                        <div style="font-size:0.9rem;margin-bottom:0.3rem;">${esc(file.name)} (${format}) &mdash; ${rows.length} cards</div>
                        <div style="color:var(--text-muted);font-size:0.85rem;margin-bottom:0.75rem;">${msg} Importing again will create additional copies.</div>
                        <button onclick="doImport()" style="background:var(--accent);color:var(--bg);border:none;border-radius:6px;padding:0.4rem 0.9rem;font-weight:600;cursor:pointer;font-family:inherit;">Import anyway</button>
                        <button onclick="cancelImport()" style="background:none;border:1px solid var(--border);border-radius:6px;padding:0.4rem 0.9rem;color:var(--text-muted);cursor:pointer;font-family:inherit;margin-left:0.4rem;">Cancel</button>
                    </div>
                </div>
                ${dupeHtml ? `<div style="margin-top:1rem;max-height:300px;overflow-y:auto;">${dupeHtml}</div>` : ''}
            </div>`;
            document.getElementById('import-info').style.display = 'none';
            window._pendingImport = { rows, filename: file.name, format };
            return;
        }

        document.getElementById('import-message').style.display = 'none';
        document.getElementById('import-title').textContent = `Importing ${rows.length} cards`;
        document.getElementById('import-status').textContent = `${file.name} (${format})`;
        document.getElementById('import-progress-wrap').style.display = '';
        document.getElementById('import-progress-bar').style.width = '0%';
        document.getElementById('import-done-top').style.display = 'none';
        document.getElementById('import-done-bottom').style.display = 'none';
        document.getElementById('import-preview-cards').innerHTML = '';
        dropZone.classList.add('importing');

        startImport(rows, file.name, format);
    }

    let csvDelimiter = ',';

    function parseCSVRows(text) {
        const rows = [];
        let row = [];
        let current = '';
        let inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const ch = text[i];
            if (inQuotes) {
                if (ch === '"' && text[i + 1] === '"') {
                    current += '"'; i++;
                } else if (ch === '"') {
                    inQuotes = false;
                } else {
                    current += ch;
                }
            } else {
                if (ch === '"') {
                    inQuotes = true;
                } else if (ch === csvDelimiter) {
                    row.push(current.trim());
                    current = '';
                } else if (ch === '\n' || (ch === '\r' && text[i + 1] === '\n')) {
                    if (ch === '\r') i++;
                    row.push(current.trim());
                    if (row.some(c => c !== '')) rows.push(row);
                    row = [];
                    current = '';
                } else if (ch === '\r') {
                    row.push(current.trim());
                    if (row.some(c => c !== '')) rows.push(row);
                    row = [];
                    current = '';
                } else {
                    current += ch;
                }
            }
        }
        row.push(current.trim());
        if (row.some(c => c !== '')) rows.push(row);
        return rows;
    }

    function parseCsv(text) {
        try {
        text = text.replace(/^﻿/, '').replace(/\0/g, '');
        const firstLine = text.split('\n')[0] || '';
        csvDelimiter = firstLine.includes('\t') ? '\t' : ',';
        const allRows = parseCSVRows(text);
        if (allRows.length < 2) return [];
        const headers = allRows[0];
        if (!headers || !headers.length) return [];
        console.log('CSV headers:', headers);
        console.log('CSV total rows (excl header):', allRows.length - 1);
        const nameIdx = findCol(headers, ['Name', 'name', 'Card Name', 'card_name']);
        const setIdx = findCol(headers, ['Set code', 'Set Code', 'set_code']);
        const numIdx = findCol(headers, ['Collector Number', 'collector_number', 'Card Number', 'Number']);
        const qtyIdx = findCol(headers, ['Quantity', 'quantity', 'Count', 'Qty']);
        const scryfallIdx = findCol(headers, ['Scryfall ID', 'scryfall_id', 'Scryfall Id']);
        const rarityIdx = findCol(headers, ['Rarity', 'rarity']);
        const foilIdx = findCol(headers, ['Foil', 'foil', 'Foil/Etched']);
        const langIdx = findCol(headers, ['Language', 'language', 'Lang']);
        const condIdx = findCol(headers, ['Condition', 'condition']);
        const priceIdx = findCol(headers, ['Purchase price', 'Purchase Price', 'purchase_price', 'Unit Price']);
        const currencyIdx = findCol(headers, ['Purchase price currency', 'Purchase Price Currency', 'purchase_price_currency']);
        const binderIdx = findCol(headers, ['Binder Name', 'Binder', 'binder_name']);
        const artistIdx = findCol(headers, ['Artist', 'artist']);
        const rarityMap = {'C':'common','U':'uncommon','R':'rare','M':'mythic','S':'special','B':'bonus'};
        if (nameIdx === -1) return [];
        const rows = [];
        for (let i = 1; i < allRows.length; i++) {
            const cols = allRows[i];
            if (!cols[nameIdx]) continue;
            const foil = foilIdx !== -1 ? cols[foilIdx] : '';
            const isFoil = foil && foil !== '' && foil !== 'normal' && foil !== 'No' && foil !== 'Regular';
            let price = null;
            if (priceIdx !== -1 && cols[priceIdx]) {
                price = parseFloat(cols[priceIdx].replace(/[^0-9.,\-]/g, '').replace(',', '.')) || null;
            }
            let rarity = (rarityIdx !== -1 ? cols[rarityIdx] : '') || '';
            if (rarity.length === 1) rarity = rarityMap[rarity.toUpperCase()] || rarity;
            rows.push({
                name: cols[nameIdx],
                set_code: setIdx !== -1 ? cols[setIdx]?.toLowerCase() : '',
                collector_number: numIdx !== -1 ? cols[numIdx] : '',
                quantity: qtyIdx !== -1 ? parseInt(cols[qtyIdx]) || 1 : 1,
                scryfall_id: scryfallIdx !== -1 ? cols[scryfallIdx] : '',
                rarity, language: langIdx !== -1 ? cols[langIdx] || 'en' : 'en',
                foil: isFoil,
                condition: condIdx !== -1 ? cols[condIdx] || 'near_mint' : 'near_mint',
                purchase_price: price,
                purchase_currency: currencyIdx !== -1 ? cols[currencyIdx] || null : null,
                binder: binderIdx !== -1 ? cols[binderIdx] || null : null,
                artist_csv: artistIdx !== -1 ? cols[artistIdx] || null : null,
            });
        }
        return rows;
        } catch (e) { console.error('CSV parse error:', e); return []; }
    }

    function findCol(headers, candidates) {
        if (!headers || !candidates) return -1;
        for (const c of candidates) {
            const idx = headers.findIndex(h => (h || '').trim().toLowerCase() === c.toLowerCase());
            if (idx !== -1) return idx;
        }
        return -1;
    }

    window.doImport = function() {
        const p = window._pendingImport;
        if (!p) return;
        document.getElementById('import-message').style.display = 'none';
        document.getElementById('import-info').style.display = '';
        document.getElementById('import-title').textContent = `Importing ${p.rows.length} cards`;
        document.getElementById('import-status').textContent = `${p.filename} (${p.format})`;
        document.getElementById('import-done-top').style.display = 'none';
        document.getElementById('import-done-bottom').style.display = 'none';
        document.getElementById('import-preview-cards').innerHTML = '';
        document.getElementById('import-progress-wrap').style.display = '';
        document.getElementById('import-progress-bar').style.width = '0%';
        dropZone.classList.add('importing');
        startImport(p.rows, p.filename, p.format);
        window._pendingImport = null;
    };

    window.cancelImport = function() {
        window._pendingImport = null;
        document.getElementById('import-message').style.display = 'none';
        csvFile.value = '';
    };

    async function startImport(csvRows, filename, format) {
        if (!csvRows.length) return;
        document.getElementById('import-info').style.display = '';
        document.getElementById('import-title').textContent = `Importing ${csvRows.length} cards`;
        document.getElementById('import-status').textContent = `${filename} (${format})`;
        document.getElementById('import-progress-wrap').style.display = '';
        document.getElementById('import-progress-bar').style.width = '0%';
        document.getElementById('import-done-top').style.display = 'none';
        document.getElementById('import-done-bottom').style.display = 'none';
        document.getElementById('import-preview-cards').innerHTML = '';
        let done = 0;
        const total = csvRows.length;

        const batchResp = await api('create_batch', { filename, format, card_count: total });
        console.log('create_batch response:', batchResp);
        const batchId = batchResp.batch_id;
        if (!batchId) {
            document.getElementById('import-title').textContent = 'Import failed: could not create batch';
            document.getElementById('import-status').innerHTML = `<pre style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:0.75rem;margin-top:0.5rem;font-size:0.75rem;color:var(--red);overflow-x:auto;white-space:pre-wrap;">create_batch response: ${esc(JSON.stringify(batchResp, null, 2))}\n\nCSV rows parsed: ${total}\nFirst row: ${esc(JSON.stringify(csvRows[0], null, 2))}</pre>`;
            dropZone.classList.remove('importing');
            return;
        }

        const cardDataMap = {};

        // Build identifiers for bulk lookup — both ID and set+number for resilience
        const seenIds = new Set();
        const seenSets = new Set();
        const idLookups = [];
        const setLookups = [];
        csvRows.forEach(row => {
            const sid = (row.scryfall_id || '').trim();
            if (sid && sid.length === 36 && !seenIds.has(sid)) {
                seenIds.add(sid);
                idLookups.push({ id: sid });
            }
            const set = (row.set_code || '').trim();
            const num = (row.collector_number || '').trim();
            const setKey = set + ':' + num;
            if (set && num && num.length <= 10 && !seenSets.has(setKey)) {
                seenSets.add(setKey);
                setLookups.push({ set, collector_number: num });
            }
        });
        const identifiers = [...idLookups, ...setLookups];

        let batchErrors = 0;
        console.log('Identifiers:', identifiers.length, '(ids:', idLookups.length, 'sets:', setLookups.length, ') sample:', identifiers.slice(0, 3));
        for (let i = 0; i < identifiers.length; i += 75) {
            try {
                const batch = identifiers.slice(i, i + 75);
                const resp = await fetch(scryfallApi('/cards/collection'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ identifiers: batch })
                });
                const data = await resp.json();
                console.log(`Batch ${i}: ${resp.status}, found: ${data.data?.length || 0}, not_found: ${data.not_found?.length || 0}`, resp.ok ? '' : data);
                if (resp.ok && data.data) {
                    for (const card of data.data) {
                        cardDataMap[card.id] = card;
                        cardDataMap[card.set + ':' + card.collector_number] = card;
                    }
                } else {
                    batchErrors++;
                }
            } catch (e) {
                batchErrors++;
                console.error('Batch fetch error:', e);
            }
            await new Promise(r => setTimeout(r, 100));
        }
        const mapSize = Object.keys(cardDataMap).length;
        console.log('CardDataMap size:', mapSize, 'batch errors:', batchErrors);
        if (mapSize === 0 && identifiers.length > 0) {
            document.getElementById('import-status').innerHTML = '<span style="color:var(--amber);">Warning: Scryfall API returned no cards. Check browser console (F12) for details. Retrying with individual lookups...</span>';
            for (let i = 0; i < csvRows.length; i++) {
                const row = csvRows[i];
                const set = (row.set_code || '').trim();
                const num = (row.collector_number || '').trim();
                const sid = (row.scryfall_id || '').trim();
                if (!set || !num) continue;
                try {
                    const url = sid && sid.length === 36
                        ? scryfallApi(`/cards/${sid}`)
                        : scryfallApi(`/cards/${set}/${num}`);
                    const resp = await fetch(url);
                    if (resp.ok) {
                        const card = await resp.json();
                        cardDataMap[card.id] = card;
                        cardDataMap[card.set + ':' + card.collector_number] = card;
                    }
                } catch (e) { console.error(`Individual fetch failed for ${set}/${num}:`, e); }
                if (i % 10 === 9) await new Promise(r => setTimeout(r, 100));
            }
            console.log('CardDataMap size after fallback:', Object.keys(cardDataMap).length);
        }

        const nonEnglish = csvRows.filter(r => r.language && r.language !== 'en' && r.set_code && r.collector_number);
        if (nonEnglish.length) {
            document.getElementById('import-status').textContent += ` (fetching ${nonEnglish.length} localized cards...)`;
            for (let i = 0; i < nonEnglish.length; i += 5) {
                const batch = nonEnglish.slice(i, i + 5);
                await Promise.all(batch.map(async r => {
                    try {
                        const resp = await fetch(scryfallApi(`/cards/${r.set_code}/${r.collector_number}/${r.language}`));
                        if (resp.ok) {
                            const card = await resp.json();
                            cardDataMap[r.set_code + ':' + r.collector_number + ':' + r.language] = card;
                        }
                    } catch {}
                }));
                await new Promise(r => setTimeout(r, 100));
            }
        }

        const importedSets = new Map();
        let successCount = 0;
        let successCards = 0;
        let skipCount = 0;
        let errorCount = 0;
        const errors = [];
        for (let i = 0; i < csvRows.length; i++) {
            const row = csvRows[i];
            const key = row.set_code + ':' + row.collector_number;
            const langKey = key + ':' + row.language;
            const isNonEnglish = row.language && row.language !== 'en';
            let cardData = (isNonEnglish ? cardDataMap[langKey] : null) || cardDataMap[key] || (row.scryfall_id ? cardDataMap[row.scryfall_id] : null);
            const isFallbackImage = isNonEnglish && !cardDataMap[langKey] && !!cardData;

            if (cardData) {
                const imgs = cardData.image_uris || cardData.card_faces?.[0]?.image_uris || {};
                try {
                    const addResult = await api('add', {
                        scryfall_id: cardData.id, name: cardData.printed_name || cardData.name,
                        set_code: cardData.set, collector_number: cardData.collector_number,
                        quantity: row.quantity, image_uri_small: imgs.small || null, image_uri_normal: imgs.normal || null,
                        mana_cost: cardData.mana_cost || null, type_line: cardData.type_line || null,
                        rarity: cardData.rarity || null, language: row.language || cardData.lang || 'en',
                        foil: row.foil ? 1 : 0, condition: row.condition || 'near_mint',
                        purchase_price: row.purchase_price, purchase_currency: row.purchase_currency,
                        binder: row.binder, artist: cardData.artist || row.artist_csv || null, batch_id: batchId,
                        image_is_fallback: isFallbackImage ? 1 : 0,
                        image_language: cardData.lang || 'en',
                    });
                    if (addResult.error) {
                        errorCount++;
                        errors.push(`Row ${i+1} "${row.name}" [${row.set_code}/${row.collector_number}]: API error: ${JSON.stringify(addResult)}`);
                    } else {
                        successCount++;
                        successCards += row.quantity || 1;
                    }
                } catch (e) {
                    errorCount++;
                    errors.push(`Row ${i+1} "${row.name}" [${row.set_code}/${row.collector_number}]: Exception: ${e.message || e}`);
                }
                if (!/^t[a-z]/.test(cardData.set) || cardData.set.length <= 2) {
                    importedSets.set(cardData.set, { name: cardData.set_name || cardData.set.toUpperCase(), year: (cardData.released_at || '').substring(0, 4) });
                }
                if (imgs.small) {
                    const preview = document.getElementById('import-preview-cards');
                    const img = document.createElement('img');
                    img.src = imgs.small;
                    img.style.cssText = 'width:100%;border-radius:5px;';
                    img.alt = '';
                    preview.appendChild(img);
                }
            } else {
                skipCount++;
                errors.push(`Row ${i+1} "${row.name}" [${row.set_code}/${row.collector_number}] scryfall_id="${row.scryfall_id}": Not found in Scryfall bulk data`);
            }
            done++;
            document.getElementById('import-progress-bar').style.width = Math.round(done / total * 100) + '%';
            document.getElementById('import-status').textContent = `${done} / ${total} processed (${successCards} cards added, ${skipCount} not found, ${errorCount} errors)`;
        }

        let summary = `Done! ${successCards} cards added (${successCount} unique), ${skipCount} not found on Scryfall, ${errorCount} API errors.`;
        document.getElementById('import-title').textContent = summary;
        let statusHtml = '';
        if (errors.length) {
            statusHtml = `<details style="margin-top:0.75rem;"><summary style="cursor:pointer;color:var(--red);font-size:0.85rem;">${errors.length} issue(s) — click to expand</summary><pre style="background:var(--bg);border:1px solid var(--border);border-radius:6px;padding:0.75rem;margin-top:0.5rem;font-size:0.75rem;color:var(--text-muted);overflow-x:auto;white-space:pre-wrap;max-height:400px;overflow-y:auto;">${esc(errors.join('\n'))}</pre></details>`;
        }
        document.getElementById('import-status').innerHTML = statusHtml;
        document.getElementById('import-done-top').style.display = '';
        document.getElementById('import-done-bottom').style.display = '';
        if (successCount > 0) {
            const setsData = [...importedSets.entries()]
                .map(([code, info]) => ({ code, name: info.name, year: info.year }))
                .sort((a, b) => (b.year || '').localeCompare(a.year || ''));
            api('update_batch_count', { batch_id: batchId, card_count: successCards, sets: JSON.stringify(setsData) });
        } else {
            api('delete_batch', { batch_id: batchId });
        }
        dropZone.classList.remove('importing');
        showToast(`Imported ${successCards} cards`);
        load();
    }

    load();
    loadBinders();

    async function loadBinders() {
        const binders = await api('list_binders');
        const el = document.getElementById('binder-content');
        if (!binders.length) {
            el.innerHTML = '<div class="empty">No cards yet.</div>';
            return;
        }
        el.innerHTML = binders.map(b => {
            const name = b.binder || '';
            const label = name || 'No binder';
            const isNone = !name;
            const total = parseFloat(b.total_price) || 0;
            const priceStr = total.toFixed(2).replace('.', ',') + ' &euro;';
            return `<div class="binder-row">
                <div class="binder-name" data-binder="${esc(name)}" style="${isNone ? 'color:var(--text-muted);font-style:italic;' : ''}"><a href="/cards/?binder=${encodeURIComponent(name)}" style="color:var(--text);text-decoration:none;${isNone ? 'color:var(--text-muted);font-style:italic;' : ''}">${esc(label)}</a>${isNone ? '' : '<button class="binder-edit" onclick="editBinderName(this)" title="Rename binder">&#9998;</button>'}</div>
                <div class="binder-price">${priceStr}</div>
                <div class="binder-count">${b.card_count} cards</div>
                <button class="binder-action" onclick="showBinderMove(this, '${esc(name).replace(/'/g, "\\'")}')">Move</button>
                <button class="binder-action" onclick="showBinderLanguage(this, '${esc(name).replace(/'/g, "\\'")}', '${esc(b.common_language || '')}')">${b.common_language ? esc(langLabel(b.common_language)) : 'Language'}</button>
            </div>`;
        }).join('');
    }
    window.loadBinders = loadBinders;

    window.editBinderName = function(btn) {
        const nameDiv = btn.closest('.binder-name');
        const currentName = nameDiv.dataset.binder;
        nameDiv.dataset.original = nameDiv.innerHTML;
        nameDiv.innerHTML = `
            <div class="binder-edit-form">
                <input type="text" onkeydown="if(event.key==='Enter')this.nextElementSibling.click();else if(event.key==='Escape')this.nextElementSibling.nextElementSibling.click();">
                <button onclick="saveBinderName(this)">Save</button>
                <button onclick="cancelBinderEdit(this)">Cancel</button>
            </div>`;
        const input = nameDiv.querySelector('input');
        input.value = currentName;
        input.focus();
        input.select();
    };

    window.cancelBinderEdit = function(btn) {
        const nameDiv = btn.closest('.binder-name');
        if (nameDiv.dataset.original !== undefined) {
            nameDiv.innerHTML = nameDiv.dataset.original;
            delete nameDiv.dataset.original;
        }
    };

    window.saveBinderName = async function(btn) {
        const nameDiv = btn.closest('.binder-name');
        const oldName = nameDiv.dataset.binder;
        const input = nameDiv.querySelector('input');
        const newName = input.value.trim();
        if (!newName || newName === oldName) {
            window.cancelBinderEdit(btn);
            return;
        }
        const binders = await api('list_binders');
        const exists = binders.some(b => (b.binder || '') === newName);
        if (exists) {
            if (!confirm(`A binder named "${newName}" already exists. Merge "${oldName}" into "${newName}"?`)) {
                return;
            }
        }
        await api('move_binder', { from: oldName, to: newName });
        showToast('Binder renamed');
        load();
        loadBinders();
    };

    window.showBinderMove = function(btn, fromBinder, batchId) {
        const container = btn.closest('tr') || btn.closest('.binder-row');
        const existing = container.querySelector('.binder-move');
        if (existing) { existing.remove(); btn.style.display = ''; return; }
        btn.style.display = 'none';

        api('list_binders').then(binders => {
            const options = binders
                .map(b => b.binder || '')
                .filter(b => b !== fromBinder)
                .map(b => `<option value="${esc(b)}">${esc(b || 'No binder')}</option>`)
                .join('');

            const moveDiv = document.createElement('div');
            moveDiv.className = 'binder-move';
            moveDiv.style.marginTop = '0.3rem';
            moveDiv.innerHTML = `
                <select onchange="const inp=this.nextElementSibling;if(this.value==='__new__'){inp.style.display='';inp.focus();}else{inp.style.display='none';inp.value='';}">
                    <option value="" disabled selected>Move to...</option>
                    ${options}
                    <option value="__new__">+ New binder</option>
                </select>
                <input type="text" placeholder="New binder name" style="display:none;" onkeydown="if(event.key==='Enter')this.parentElement.querySelector('button').click()">
                <button onclick="doMoveBinder('${esc(fromBinder).replace(/'/g, "\\'")}', this, ${batchId || 'null'})">Move</button>
                <button onclick="this.closest('.binder-move').remove();this.closest('tr,div').querySelector('.binder-action').style.display=''" style="background:none;border:1px solid var(--border);color:var(--text-muted);">Cancel</button>
            `;
            container.querySelector('td') ? container.querySelector('td').appendChild(moveDiv) : container.appendChild(moveDiv);
        });
    };

    const LANGUAGES = [
        {code:'en', name:'English'}, {code:'de', name:'German'}, {code:'fr', name:'French'},
        {code:'es', name:'Spanish'}, {code:'it', name:'Italian'}, {code:'pt', name:'Portuguese'},
        {code:'ja', name:'Japanese'}, {code:'ko', name:'Korean'}, {code:'zhs', name:'Chinese (S)'},
        {code:'zht', name:'Chinese (T)'}, {code:'ru', name:'Russian'}, {code:'ph', name:'Phyrexian'},
    ];
    const langLabel = (c) => (LANGUAGES.find(l => l.code === c) || {}).name || c;

    window.showBinderLanguage = function(btn, binder, currentLang) {
        const container = btn.closest('.binder-row');
        const existing = container.querySelector('.binder-language');
        if (existing) { existing.remove(); btn.style.display = ''; return; }
        btn.style.display = 'none';
        const langDiv = document.createElement('div');
        langDiv.className = 'binder-language binder-move';
        langDiv.style.marginTop = '0.3rem';
        langDiv.innerHTML = `
            <select>
                <option value="" disabled${!currentLang ? ' selected' : ''}>Change to...</option>
                ${LANGUAGES.map(l => `<option value="${l.code}"${l.code === currentLang ? ' selected' : ''}>${esc(l.name)}</option>`).join('')}
            </select>
            <button onclick="doChangeLanguage('${esc(binder).replace(/'/g, "\\'")}', this)">Change</button>
            <button onclick="const row=this.closest('.binder-row');this.closest('.binder-language').remove();[...row.querySelectorAll('.binder-action')].forEach(b=>b.style.display='');" style="background:none;border:1px solid var(--border);color:var(--text-muted);">Cancel</button>
            <span class="lang-status" style="font-size:0.8rem;color:var(--text-muted);margin-left:0.5rem;"></span>
        `;
        container.appendChild(langDiv);
    };

    window.doChangeLanguage = async function(binder, btn) {
        const langDiv = btn.closest('.binder-language');
        const select = langDiv.querySelector('select');
        const status = langDiv.querySelector('.lang-status');
        const lang = select.value;
        if (!lang) return;
        btn.disabled = true;
        select.disabled = true;
        const cards = await api('list_binder_cards', { binder });
        if (!cards.length) { showToast('No cards in binder'); langDiv.remove(); return; }
        let done = 0, fallbackCount = 0, failCount = 0, updatedCount = 0;
        status.textContent = `0 / ${cards.length}`;
        for (const card of cards) {
            const set = card.set_code, num = card.collector_number;
            if (!set || !num) { failCount++; done++; status.textContent = `${done} / ${cards.length}`; continue; }
            let cardData = null, isFallback = false;
            if (lang !== 'en') {
                try {
                    const resp = await fetch(scryfallApi(`/cards/${set}/${num}/${lang}`));
                    if (resp.ok) cardData = await resp.json();
                } catch {}
            }
            if (!cardData) {
                try {
                    const resp = await fetch(scryfallApi(`/cards/${set}/${num}`));
                    if (resp.ok) cardData = await resp.json();
                } catch {}
                if (cardData && lang !== 'en') isFallback = true;
            }
            if (cardData) {
                const imgs = cardData.image_uris || cardData.card_faces?.[0]?.image_uris || {};
                await api('update_card_language', {
                    id: card.id,
                    scryfall_id: cardData.id,
                    language: lang,
                    name: cardData.printed_name || cardData.name,
                    image_uri_small: imgs.small || null,
                    image_uri_normal: imgs.normal || null,
                    image_language: cardData.lang || 'en',
                    image_is_fallback: isFallback ? 1 : 0,
                });
                updatedCount++;
                if (isFallback) fallbackCount++;
            } else {
                failCount++;
            }
            done++;
            status.textContent = `${done} / ${cards.length}`;
            await new Promise(r => setTimeout(r, 100));
        }
        const parts = [`${updatedCount} updated`];
        if (fallbackCount) parts.push(`${fallbackCount} English fallback`);
        if (failCount) parts.push(`${failCount} failed`);
        showToast(parts.join(', '));
        loadBinders();
        load();
    };

    window.doMoveBinder = async function(fromBinder, btn, batchId) {
        const moveDiv = btn.closest('.binder-move');
        const select = moveDiv.querySelector('select');
        const input = moveDiv.querySelector('input');
        let toBinder;
        if (select.value === '__new__') {
            toBinder = input.value.trim();
            if (!toBinder) return;
        } else {
            toBinder = select.value;
            if (toBinder === '') return;
        }
        await api('move_binder', { from: fromBinder, to: toBinder, batch_id: batchId });
        showToast('Cards moved');
        load();
        loadBinders();
    };
})();
