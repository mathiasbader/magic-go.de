/**
 * Artist detail page — add/remove URLs for the artist. Bootstrap data
 * (artist id, artist name, CSRF token) read from window.MAGIC_ARTIST set
 * inline by artist.php.
 */
(function () {
    const M = window.MAGIC_ARTIST || {};
    const artistId = M.id;
    const artistName = M.name || '';
    const csrfToken = M.csrf || '';

    async function apiCall(action, data) {
        const resp = await fetch('/cards/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, _csrf: csrfToken, ...data })
        });
        return resp.json();
    }

    window.showUrlEdit = function () {
        const googleUrl = 'https://www.google.com/search?q=' + encodeURIComponent(artistName + ' artist');
        const addBtn = document.querySelector('#artist-url-area > .no-url:last-child');
        addBtn.outerHTML = `<div class="url-edit">
            <div class="url-edit-row">
                <input type="url" id="url-input" placeholder="Paste URL..." onkeydown="if(event.key==='Enter')saveUrl()">
                <input type="text" id="label-input" placeholder="Label (optional)" onkeydown="if(event.key==='Enter')saveUrl()">
                <button onclick="saveUrl()">Save</button>
            </div>
            <div class="url-edit-search"><a href="${googleUrl}" target="_blank" rel="noopener">Search ${artistName} on the web</a></div>
        </div>`;
        document.getElementById('url-input').focus();
    };

    window.saveUrl = async function () {
        const url = document.getElementById('url-input').value.trim();
        const label = document.getElementById('label-input').value.trim() || null;
        if (!url) return;
        await apiCall('add_artist_url', { artist_id: artistId, url, label });
        location.reload();
    };

    window.deleteUrl = async function (urlId) {
        await apiCall('delete_artist_url', { id: urlId });
        location.reload();
    };
})();
