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
    posttypeChip: '.posttype-chip',
    formSearch: '#form-search',
    formsTable: '#forms-table',
    formsTableRows: '#forms-table tbody tr',
    formsCount: '#forms-count'
};

const ACTIVE_TAB = new URLSearchParams(window.location.search).get('tab') || 'blocks';

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

    // ===== Onglet Formulaires =====
    if (ACTIVE_TAB === 'forms') {
        const formSearchInput = document.querySelector(SELECTORS.formSearch);
        const formsCount = document.querySelector(SELECTORS.formsCount);

        function filterFormsTable() {
            const search = formSearchInput.value.toLowerCase().trim();
            const rows = document.querySelectorAll(SELECTORS.formsTableRows);
            let visibleCount = 0;

            rows.forEach(row => {
                const id = row.querySelector('.col-form-id')?.textContent.toLowerCase() || '';
                const title = row.querySelector('.col-form-title')?.textContent.toLowerCase() || '';
                const show = !search || id.includes(search) || title.includes(search);
                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            if (formsCount) {
                formsCount.textContent = visibleCount + ' formulaire' + (visibleCount > 1 ? 's' : '');
            }
        }

        if (formSearchInput) {
            formSearchInput.addEventListener('input', filterFormsTable);
        }

        // Tri des colonnes du tableau des formulaires
        const formsTable = document.querySelector(SELECTORS.formsTable);

        if (formsTable) {
            const tbody = formsTable.querySelector('tbody');
            const headers = Array.from(formsTable.querySelectorAll('th[data-sort-key]'));
            let currentKey = null;
            let currentDirection = null;

            headers.forEach(header => {
                const button = header.querySelector('.th-sort');
                if (!button) return;

                button.addEventListener('click', function () {
                    const key = header.getAttribute('data-sort-key');
                    const type = header.getAttribute('data-sort-type') || 'text';

                    // Premier clic : décroissant sur les chiffres, croissant sur le texte
                    const defaultDirection = type === 'number' ? 'desc' : 'asc';
                    const direction = currentKey === key
                        ? (currentDirection === 'asc' ? 'desc' : 'asc')
                        : defaultDirection;

                    currentKey = key;
                    currentDirection = direction;

                    headers.forEach(other => other.setAttribute('aria-sort', 'none'));
                    header.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');

                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    rows.sort((a, b) => {
                        const aValue = a.getAttribute('data-sort-' + key) || '';
                        const bValue = b.getAttribute('data-sort-' + key) || '';
                        const comparison = type === 'number'
                            ? (parseFloat(aValue) || 0) - (parseFloat(bValue) || 0)
                            : aValue.localeCompare(bValue, 'fr', { sensitivity: 'base' });

                        return direction === 'asc' ? comparison : -comparison;
                    });

                    rows.forEach(row => tbody.appendChild(row));
                });
            });
        }
    }
});
