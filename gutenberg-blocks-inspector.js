// ==================================================
// Variables globales
// ==================================================
const SELECTORS = {
    blockSearch: '#block-search',
    postTypeFilter: '#post-type-filter',
    occurrenceFilter: '#occurrence-filter',
    resetFilters: '#reset-filters',
    blocksTableRows: '#blocks-table tbody tr',
    postTypeCell: 'td:nth-child(5)',
    badgeCell: 'td:nth-child(4) .block-badge',
    nameCell: 'td:nth-child(2) strong',
    slugCell: 'td:nth-child(3)',
    blocksTable: '#blocks-table',
    blockFilters: '#block-filters',
    postTypeTitle: '.post-type-title',
    postTypeLinks: '.post-type-links',
    postTypeOccurrence: '.post-type-occurrence'
};

// Gutenberg Blocks Inspector - JS

document.addEventListener('DOMContentLoaded', function () {
    // Tooltips pour les post types
    const titles = Array.from(document.querySelectorAll(SELECTORS.postTypeTitle));
    let lastEnabledTooltip = null;
    titles.forEach(title => {
        title.addEventListener('mouseenter', (e) => {
            if (e.target.tagName === 'SPAN') {
                e.target.querySelector(SELECTORS.postTypeLinks).style.display = 'block';
                lastEnabledTooltip = e.target;
            }
        });
        title.addEventListener('mouseleave', (e) => {
            if (e.target.tagName === 'SPAN') {
                e.target.querySelector(SELECTORS.postTypeLinks).style.display = 'none';
            }
        });
    });

    // Filtres dynamiques
    function filterBlocksTable() {
        const search = document.querySelector(SELECTORS.blockSearch).value.toLowerCase();
        const postType = document.querySelector(SELECTORS.postTypeFilter).value;
        const occurrence = document.querySelector(SELECTORS.occurrenceFilter).value;
        const rows = document.querySelectorAll(SELECTORS.blocksTableRows);
        rows.forEach(row => {
            let show = true;
            // Recherche plein texte
            const name = row.querySelector(SELECTORS.nameCell).textContent.toLowerCase();
            const slug = row.querySelector(SELECTORS.slugCell).textContent.toLowerCase();
            if (search && !(name.includes(search) || slug.includes(search))) {
                show = false;
            }
            // Filtre post type
            if (postType) {
                const postTypesAttr = row.querySelector(SELECTORS.postTypeCell).getAttribute('data-posttypes') || '';
                const postTypesArr = postTypesAttr.split(',');
                if (!postTypesArr.includes(postType)) {
                    show = false;
                }
            }
            // Filtre occurrences
            const occText = row.querySelector(SELECTORS.badgeCell).textContent;
            let occValue = 0;
            if (occText === 'Inutilisé') {
                occValue = 0;
            } else {
                occValue = parseInt(occText);
            }
            if (occurrence === '0' && occValue !== 0) show = false;
            if (occurrence === '1-10' && (occValue < 1 || occValue > 10)) show = false;
            if (occurrence === '>100' && occValue <= 100) show = false;
            row.style.display = show ? '' : 'none';
        });
    }

    document.querySelector(SELECTORS.blockSearch).addEventListener('input', filterBlocksTable);
    document.querySelector(SELECTORS.postTypeFilter).addEventListener('change', filterBlocksTable);
    document.querySelector(SELECTORS.occurrenceFilter).addEventListener('change', filterBlocksTable);
    document.querySelector(SELECTORS.resetFilters).addEventListener('click', function () {
        document.querySelector(SELECTORS.blockSearch).value = '';
        document.querySelector(SELECTORS.postTypeFilter).value = '';
        document.querySelector(SELECTORS.occurrenceFilter).value = '';
        filterBlocksTable();
    });
});