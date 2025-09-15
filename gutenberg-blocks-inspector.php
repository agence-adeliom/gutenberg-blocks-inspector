<?php

/**
 * Adeliom - Gutenberg Blocks Inspector
 *
 * @package       Gutenberg Blocks Inspector
 * @author        Adeliom
 *
 * @wordpress-plugin
 * Plugin Name:       Gutenberg Blocks Inspector
 * Plugin URI:        https://github.com/agence-adeliom/gutenberg-blocks-inspector
 * Description:       Inspecteur de blocks Gutenberg
 * Version:           0.0.1
 * Author:            Agence Adeliom
 * Author URI:        https://adeliom.com
 * Update URI:        https://adeliom.com
 * Text Domain:       adeliom
 * Domain Path:       /lang
 * Requires PHP:      7.4
 * Requires at least: 6.3.4
 */

$metaTitle = 'Inspecteur de blocks';
$menuTitle = 'Blocks Inspector';
$slug = 'blocks-inspector';
$menuIcon = 'dashicons-editor-table';
$menuPosition = 6;

add_action('admin_menu', function () use ($metaTitle, $menuTitle, $slug, $menuIcon, $menuPosition) {
    add_menu_page($metaTitle, $menuTitle, 'manage_options', $slug, 'blocksInspectorPage', $menuIcon, $menuPosition);
});

function blocksInspectorPage()
{
    global $wpdb;

    $allBlocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
    $postTypes = [];
    $blocks = [];
    $urlsById = [];
    $adminUrlsByUrl = [];
    $pageNameByUrl = [];

    foreach ($allBlocks as $block) {
        $blockData = [
            'total'          => 0,
            'perPage'        => [],
            'postTypes'      => [],
            'urlsByPostType' => [],
        ];

        $query = <<<EOF
		SELECT {$wpdb->posts}.ID, {$wpdb->posts}.post_content, {$wpdb->posts}.post_type, {$wpdb->posts}.post_title
		FROM {$wpdb->posts}
		WHERE {$wpdb->posts}.post_content LIKE '%<!-- wp:{$block->name} %' AND {$wpdb->posts}.post_status = 'publish' AND {$wpdb->posts}.post_type != 'revision'
		EOF;

        if ($results = $wpdb->get_results($query)) {
            foreach ($results as $result) {
                $count = substr_count($result->post_content, "<!-- wp:{$block->name} ");

                if ($count > 0) {
                    $blockData['perPage'][] = [
                        'page'     => $result->ID,
                        'count'    => $count,
                        'postType' => $result->post_type,
                    ];

                    if (!isset($postTypes[$result->post_type])) {
                        $label = null;

                        if ($postType = get_post_type_object($result->post_type)) {
                            $label = $postType->label;
                        }

                        if (null === $label) {
                            $label = $result->post_type;
                        }

                        $postTypes[$result->post_type] = $label;
                    }

                    if (!isset($blockData['postTypes'][$result->post_type])) {
                        $blockData['postTypes'][$result->post_type] = $postTypes[$result->post_type];
                        $blockData['urlsByPostType'][$result->post_type] = [];
                    }

                    if (isset($blockData['urlsByPostType'][$result->post_type])) {
                        $url = null;

                        if (!isset($urlsById[$result->ID])) {
                            $urlsById[$result->ID] = get_permalink($result->ID);
                            $adminUrlsByUrl[$urlsById[$result->ID]] = get_edit_post_link($result->ID);
                            $pageNameByUrl[$urlsById[$result->ID]] = $result->post_title;
                        }

                        $url = $urlsById[$result->ID];

                        $blockData['urlsByPostType'][$result->post_type][] = $url;
                    }

                    $blockData['total'] += $count;
                }
            }
        }

        $blocks[] = [
            'title' => $block->title,
            'name'  => $block->name,
            'data'  => $blockData,
            'icon'  => $block->icon,
        ];
    }

    usort($blocks, function ($a, $b) {
        return $a['data']['total'] < $b['data']['total'];
    });

    renderTable($blocks, $adminUrlsByUrl, $pageNameByUrl);
}

function renderTable(array $blocks, array $adminUrlsByUrl, array $pageNameByUrl)
{
    ?>
    <style>
        .w-inspector {
            .odd {
                background-color: #f6f7f7;
            }

            .even {
            }
        }

        .block-badge {
            display: inline-block;
            min-width: 80px;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #222;
            text-align: center;
        }

        .badge-unused {
            background: #ffd6d6;
            color: #a00;
        }

        .badge-few {
            background: #ffe7b3;
            color: #b36b00;
        }

        .badge-many {
            background: #c6f5c6;
            color: #217a00;
        }
    </style>
    <div class="wrap">
        <h2>Filtres & Recherche</h2>
        <div id="block-filters" style="margin-bottom: 20px; display: flex; gap: 20px; align-items: flex-end;">
            <div>
                <label for="block-search">Recherche (Nom ou Slug)</label><br>
                <input type="text" id="block-search" placeholder="Rechercher..." style="min-width:200px;">
            </div>
            <div>
                <label for="post-type-filter">Type de post</label><br>
                <select id="post-type-filter">
                    <option value="">Tous</option>
                    <?php
                    // Générer la liste des post types présents dans les blocs
                    $allPostTypes = [];
                    foreach ($blocks as $block) {
                        foreach ($block['data']['postTypes'] as $slug => $label) {
                            $allPostTypes[$slug] = $label;
                        }
                    }
                    foreach ($allPostTypes as $slug => $label) {
                        echo '<option value="' . esc_attr($slug) . '">' . esc_html($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="occurrence-filter">Occurrences</label><br>
                <select id="occurrence-filter">
                    <option value="">Toutes</option>
                    <option value="0">0 (Inutilisé)</option>
                    <option value="1-10">1–10</option>
                    <option value=">100">&gt;100</option>
                </select>
            </div>

            <div style="align-self: flex-end;">
                <button type="button" id="reset-filters" class="button" style="margin-left:10px;">Réinitialiser les
                    filtres
                </button>
            </div>
        </div>
        <table class="wp-list-table widefat w-inspector" id="blocks-table">
            <thead>
            <tr>
                <th>Icône</th>
                <th>Nom</th>
                <th>Slug</th>
                <th>Occurrences</th>
                <th>Type de contenu</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $index = 0;
            foreach ($blocks as $block) {
                $rowClass = ($index % 2 === 0) ? 'even' : 'odd';
                if (!str_starts_with($block['name'], 'acf/')) {
                    continue;
                }
                // Badge logique
                $total = (int)$block['data']['total'];
                if ($total === 0) {
                    $badgeClass = 'badge-unused';
                    $badgeText = 'Inutilisé';
                } else if ($total <= 5) {
                    $badgeClass = 'badge-few';
                    $badgeText = $total . ' fois';
                } else if ($total > 50) {
                    $badgeClass = 'badge-many';
                    $badgeText = $total . ' fois';
                } else {
                    $badgeClass = 'badge-few';
                    $badgeText = $total . ' fois';
                }
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td>
                        <?php
                        if (null !== $block['icon']) {
                            if (!str_starts_with($block['icon'], '<svg')) {
                                ?>
                                <div class="dashicons dashicons-<?php echo $block['icon'] ?>"></div>
                                <?php
                            } else {
                                ?>
                                <div class="block-svg-icon-container">
                                    <?php echo $block['icon'] ?>
                                </div>
                                <?php
                            }
                        }

                        ?>
                    </td>
                    <td>
                        <strong><?php echo $block['title']; ?></strong>
                    </td>
                    <td><?php echo $block['name']; ?></td>
                    <td>
                        <span class="block-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                    </td>
                    <td style="position: relative; white-space: normal;display:flex;"
                        data-posttypes="<?php echo esc_attr(implode(',', array_keys($block['data']['postTypes']))); ?>">
                        <?php
                        $count = 0;
                        foreach ($block['data']['postTypes'] as $slug => $postType) {
                            $pages = $block['data']['urlsByPostType'][$slug];
                            $nbOcc = count($pages);
                            $urls = '<div class="post-type-links" style="display: none; position: absolute; background-color: white; box-sizing: border-box; padding: 8px 16px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); z-index: 999; width: 350px; right: 0; top: 100%; max-height: 400px; overflow: scroll;"><ul>';
                            $urls .= '<p>Pages "<strong>' . $postType . '</strong>" contenant le bloc "<strong>' . $block['title'] . '</strong>" <i>(' . number_format($nbOcc, 0, ',', ' ') . ' occurrence·s)</i></p>';
                            foreach ($pages as $page) {
                                $urls .= '<li><a class="post-type-link" target="_blank" href="' . $adminUrlsByUrl[$page] . '">' . $pageNameByUrl[$page] . '</a></li>';
                            }
                            $urls .= '</ul></div>';
                            echo '<div style="margin-right: 8px;">'
                                . ' <span class="post-type-occurrence" style="background:#eee; border-radius:8px; padding:1px 4px; font-size:12px; margin-right:4px;">' . $nbOcc . '</span>'
                                . '<span style="position: relative; cursor: default;" class="post-type-title">' . $postType . $urls . '</span>'
                                . '</div>';
                        }
                        ?>
                    </td>
                </tr>
                <?php
                $index++;
            }
            ?>
            </tbody>
        </table>
    </div>

    <script type="application/javascript">
        const titles = Array.from(document.querySelectorAll('.post-type-title'));
        let lastEnabledTooltip = null;

        titles.forEach(title => {
            title.addEventListener('mouseenter', (e) => {
                if (e.target.tagName === 'SPAN') {
                    e.target.querySelector('.post-type-links').style.display = 'block';
                    lastEnabledTooltip = e.target;
                }
            });

            title.addEventListener('mouseleave', (e) => {
                if (e.target.tagName === 'SPAN') {
                    e.target.querySelector('.post-type-links').style.display = 'none';
                }
            });
        });

        // Filtres dynamiques
        function filterBlocksTable() {
            const search = document.getElementById('block-search').value.toLowerCase();
            const postType = document.getElementById('post-type-filter').value;
            const occurrence = document.getElementById('occurrence-filter').value;
            const rows = document.querySelectorAll('#blocks-table tbody tr');
            rows.forEach(row => {
                let show = true;
                // Recherche plein texte
                const name = row.querySelector('td:nth-child(2) strong').textContent.toLowerCase();
                const slug = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                if (search && !(name.includes(search) || slug.includes(search))) {
                    show = false;
                }
                // Filtre post type
                if (postType) {
                    const postTypesAttr = row.querySelector('td:nth-child(5)').getAttribute('data-posttypes') || '';
                    const postTypesArr = postTypesAttr.split(',');
                    if (!postTypesArr.includes(postType)) {
                        show = false;
                    }
                }
                // Filtre occurrences
                const occText = row.querySelector('td:nth-child(4) .block-badge').textContent;
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

        document.getElementById('block-search').addEventListener('input', filterBlocksTable);
        document.getElementById('post-type-filter').addEventListener('change', filterBlocksTable);
        document.getElementById('occurrence-filter').addEventListener('change', filterBlocksTable);
        document.getElementById('reset-filters').addEventListener('click', function () {
            document.getElementById('block-search').value = '';
            document.getElementById('post-type-filter').value = '';
            document.getElementById('occurrence-filter').value = '';
            filterBlocksTable();
        });
    </script>

    <style>
        .post-type-title {
            text-decoration: underline;
        }

        .post-type-title:hover {
            text-decoration: none;
        }

        .post-type-link {

        }

        .post-type-link:hover {
            text-decoration: underline;
        }

        .block-svg-icon-container {
            width: 20px;
            height: 20px;
        }

        .block-svg-icon-container svg {
            width: 100%;
            height: 100%;
        }
    </style>

    <?php
}