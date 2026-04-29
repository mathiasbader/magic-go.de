/**
 * Sets tab — year tabs and per-year filter buttons (original/UB/released/upcoming).
 * The set cards themselves are rendered server-side; this only handles UI state.
 */
(function () {
    const M = window.MAGIC;
    let initialized = false;

    function init() {
        if (initialized) return;
        initialized = true;

        const yearTabs = document.querySelectorAll('.year-tab');
        const yearPanels = document.querySelectorAll('.year-panel');

        function switchToYear(year) {
            yearTabs.forEach(t => t.classList.toggle('active', t.dataset.year === year));
            yearPanels.forEach(p => {
                const isActive = p.dataset.year === year;
                p.classList.toggle('active', isActive);
                if (isActive) resetFilters(p);
            });
        }

        function resetFilters(panel) {
            panel.querySelectorAll('.sets-filter-btn').forEach(b => b.classList.remove('active', 'dimmed'));
            panel.querySelectorAll('.set-card').forEach(c => c.classList.remove('hidden'));
            panel._activeFilter = null;
        }

        yearPanels.forEach(panel => {
            panel.querySelectorAll('.sets-filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const filter = btn.dataset.filter;
                    if (panel._activeFilter === filter) {
                        resetFilters(panel);
                        return;
                    }
                    panel._activeFilter = filter;
                    panel.querySelectorAll('.sets-filter-btn').forEach(b => {
                        b.classList.toggle('active', b.dataset.filter === filter);
                        b.classList.toggle('dimmed', b.dataset.filter !== filter);
                    });
                    panel.querySelectorAll('.set-card').forEach(card => {
                        const match = card.dataset.universe === filter || card.dataset.status === filter;
                        card.classList.toggle('hidden', !match);
                    });
                });
            });
        });

        yearTabs.forEach(tab => {
            tab.addEventListener('click', () => switchToYear(tab.dataset.year));
        });

        switchToYear(M.setsLatestYear);
    }

    M.tabs.sets = { load: init };
})();
