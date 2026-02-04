// ==================================================
// Gutenberg Blocks Inspector - JavaScript
// ==================================================

const SELECTORS = {
    blockSearch: '#block-search',
    postTypeFilter: '#post-type-filter',
    occurrenceFilter: '#occurrence-filter',
    resetFilters: '#reset-filters',
    blocksTable: '#blocks-table',
    blocksTableRows: '#blocks-table tbody tr',
    blocksCount: '#blocks-count',
    nameCell: '.col-name .block-title',
    slugCell: '.col-slug .block-slug',
    badgeCell: '.col-occurrences .block-badge',
    postTypeCell: '.col-posttypes',
    posttypeChip: '.posttype-chip'
};

document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.querySelector(SELECTORS.blockSearch);
    const postTypeFilter = document.querySelector(SELECTORS.postTypeFilter);
    const occurrenceFilter = document.querySelector(SELECTORS.occurrenceFilter);
    const resetButton = document.querySelector(SELECTORS.resetFilters);
    const blocksCount = document.querySelector(SELECTORS.blocksCount);

    // Fonction de filtrage du tableau
    function filterBlocksTable() {
        const search = searchInput.value.toLowerCase().trim();
        const postType = postTypeFilter.value;
        const occurrence = occurrenceFilter.value;
        const rows = document.querySelectorAll(SELECTORS.blocksTableRows);

        let visibleCount = 0;

        rows.forEach(row => {
            let show = true;

            // Recherche plein texte (nom ou slug)
            if (search) {
                const name = row.querySelector(SELECTORS.nameCell)?.textContent.toLowerCase() || '';
                const slug = row.querySelector(SELECTORS.slugCell)?.textContent.toLowerCase() || '';
                if (!(name.includes(search) || slug.includes(search))) {
                    show = false;
                }
            }

            // Filtre par type de contenu
            if (show && postType) {
                const postTypesAttr = row.querySelector(SELECTORS.postTypeCell)?.getAttribute('data-posttypes') || '';
                const postTypesArr = postTypesAttr.split(',').filter(Boolean);
                if (!postTypesArr.includes(postType)) {
                    show = false;
                }
            }

            // Filtre par occurrences
            if (show && occurrence) {
                const badgeText = row.querySelector(SELECTORS.badgeCell)?.textContent.trim() || '0';
                let occValue = 0;

                if (badgeText === 'Inutilisé') {
                    occValue = 0;
                } else {
                    occValue = parseInt(badgeText, 10) || 0;
                }

                switch (occurrence) {
                    case '0':
                        if (occValue !== 0) show = false;
                        break;
                    case '1-10':
                        if (occValue < 1 || occValue > 10) show = false;
                        break;
                    case '11-50':
                        if (occValue < 11 || occValue > 50) show = false;
                        break;
                    case '>50':
                        if (occValue <= 50) show = false;
                        break;
                }
            }

            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        // Mise à jour du compteur
        if (blocksCount) {
            blocksCount.textContent = visibleCount + ' bloc' + (visibleCount > 1 ? 's' : '');
        }
    }

    // Événements des filtres
    if (searchInput) {
        searchInput.addEventListener('input', filterBlocksTable);
    }

    if (postTypeFilter) {
        postTypeFilter.addEventListener('change', filterBlocksTable);
    }

    if (occurrenceFilter) {
        occurrenceFilter.addEventListener('change', filterBlocksTable);
    }

    // Réinitialisation des filtres
    if (resetButton) {
        resetButton.addEventListener('click', function () {
            if (searchInput) searchInput.value = '';
            if (postTypeFilter) postTypeFilter.value = '';
            if (occurrenceFilter) occurrenceFilter.value = '';
            filterBlocksTable();
        });
    }

    // Animation au survol des chips de type de contenu (pour le dropdown)
    // Le dropdown est géré en CSS pur via :hover
});
