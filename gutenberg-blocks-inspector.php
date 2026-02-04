<?php

/**
 * Adeliom - Gutenberg Blocks Inspector (OOP)
 *
 * @package       Gutenberg Blocks Inspector
 * @author        Adeliom
 *
 * @wordpress-plugin
 * Plugin Name:       Gutenberg Blocks Inspector
 * Plugin URI:        https://github.com/agence-adeliom/gutenberg-blocks-inspector
 * Description:       Inspecteur de blocks Gutenberg
 * Version:           0.0.2
 * Author:            Agence Adeliom
 * Author URI:        https://adeliom.com
 * Update URI:        https://adeliom.com
 * Text Domain:       adeliom
 * Domain Path:       /lang
 * Requires PHP:      7.4
 * Requires at least: 6.3.4
 */

class GutenbergBlocksInspector
{
    // =========================
    // Variables de configuration
    // =========================
    private string $metaTitle = 'Inspecteur de blocks';
    private string $menuTitle = 'Blocks Inspector';
    private string $slug = 'blocks-inspector';
    private string $menuIcon = 'dashicons-editor-table';
    private int $menuPosition = 6;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            $this->metaTitle,
            $this->menuTitle,
            'manage_options',
            $this->slug,
            [$this, 'renderPage'],
            $this->menuIcon,
            $this->menuPosition
        );
    }

    public function enqueueAssets($hook): void
    {
        if (isset($_GET['page']) && $_GET['page'] === $this->slug) {
            $base_url = plugin_dir_url(__FILE__);
            wp_enqueue_style('gutenberg-blocks-inspector', $base_url . 'gutenberg-blocks-inspector.css');
            wp_enqueue_script('gutenberg-blocks-inspector', $base_url . 'gutenberg-blocks-inspector.js', [], false, true);
        }
    }

    public function renderPage(): void
    {
        global $wpdb;
        $allBlocks = WP_Block_Type_Registry::get_instance()->get_all_registered();
        $postTypes = [];
        $blocks = [];
        $urlsById = [];
        $adminUrlsByUrl = [];
        $pageNameByUrl = [];

        // Statistiques globales
        $stats = [
            'totalBlocks'      => 0,
            'unusedBlocks'     => 0,
            'totalOccurrences' => 0,
            'topBlocks'        => [],
            'postTypesCount'   => 0,
            'totalPosts'       => 0,
            'byPostType'       => [],
        ];

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

        // Calcul des statistiques globales (uniquement pour les blocs ACF)
        $allPostTypesForStats = [];
        $allPostsIds = [];
        $blocksForTop = [];

        foreach ($blocks as $block) {
            if (!str_starts_with($block['name'], 'acf/')) {
                continue;
            }
            $stats['totalBlocks']++;
            $stats['totalOccurrences'] += $block['data']['total'];

            if ($block['data']['total'] === 0) {
                $stats['unusedBlocks']++;
            }

            // Collecter pour le top 3
            if ($block['data']['total'] > 0) {
                $blocksForTop[] = [
                    'name'  => $block['name'],
                    'title' => $block['title'],
                    'count' => $block['data']['total'],
                    'icon'  => $block['icon'],
                ];
            }

            // Stats par type de contenu
            foreach ($block['data']['postTypes'] as $slug => $label) {
                $allPostTypesForStats[$slug] = $label;
                if (!isset($stats['byPostType'][$slug])) {
                    $stats['byPostType'][$slug] = [
                        'label'       => $label,
                        'blocksCount' => 0,
                        'occurrences' => 0,
                    ];
                }
                $stats['byPostType'][$slug]['blocksCount']++;
                $stats['byPostType'][$slug]['occurrences'] += count($block['data']['urlsByPostType'][$slug] ?? []);
            }

            foreach ($block['data']['perPage'] as $pageData) {
                $allPostsIds[$pageData['page']] = true;
            }
        }

        // Trier et garder le top 3
        usort($blocksForTop, fn($a, $b) => $b['count'] - $a['count']);
        $stats['topBlocks'] = array_slice($blocksForTop, 0, 3);

        // Trier les types de contenu par occurrences
        uasort($stats['byPostType'], fn($a, $b) => $b['occurrences'] - $a['occurrences']);

        $stats['postTypesCount'] = count($allPostTypesForStats);
        $stats['totalPosts'] = count($allPostsIds);

        $this->renderTable($blocks, $adminUrlsByUrl, $pageNameByUrl, $stats);
    }

    private function renderTable(array $blocks, array $adminUrlsByUrl, array $pageNameByUrl, array $stats): void
    {
        ?>
        <div class="wrap blocks-inspector-wrap">
            <h1><?php echo esc_html($this->metaTitle); ?></h1>

            <!-- Section Statistiques -->
            <div class="blocks-dashboard">
                <!-- Colonne gauche : Stats générales -->
                <div class="blocks-stats-main">
                    <div class="blocks-stats-row">
                        <div class="blocks-stat-card stat-default">
                            <div class="stat-icon-wrap">
                                <span class="dashicons dashicons-screenoptions"></span>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo number_format($stats['totalBlocks'], 0, ',', ' '); ?></span>
                                <span class="stat-label">Blocs ACF</span>
                            </div>
                        </div>

                        <div class="blocks-stat-card stat-danger">
                            <div class="stat-icon-wrap">
                                <span class="dashicons dashicons-dismiss"></span>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo number_format($stats['unusedBlocks'], 0, ',', ' '); ?></span>
                                <span class="stat-label">Inutilisés</span>
                            </div>
                            <?php if ($stats['totalBlocks'] > 0): ?>
                            <div class="stat-percentage"><?php echo round(($stats['unusedBlocks'] / $stats['totalBlocks']) * 100); ?>%</div>
                            <?php endif; ?>
                        </div>

                        <div class="blocks-stat-card stat-success">
                            <div class="stat-icon-wrap">
                                <span class="dashicons dashicons-chart-bar"></span>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo number_format($stats['totalOccurrences'], 0, ',', ' '); ?></span>
                                <span class="stat-label">Occurrences</span>
                            </div>
                        </div>

                        <div class="blocks-stat-card stat-purple">
                            <div class="stat-icon-wrap">
                                <span class="dashicons dashicons-admin-page"></span>
                            </div>
                            <div class="stat-content">
                                <span class="stat-value"><?php echo number_format($stats['totalPosts'], 0, ',', ' '); ?></span>
                                <span class="stat-label">Pages</span>
                            </div>
                        </div>
                    </div>

                    <!-- Top 3 des blocs -->
                    <div class="blocks-top-section">
                        <h3 class="section-title">
                            <span class="dashicons dashicons-trophy"></span>
                            Top 3 des blocs
                        </h3>
                        <div class="blocks-top-list">
                            <?php
                            $medals = ['🥇', '🥈', '🥉'];
                            foreach ($stats['topBlocks'] as $index => $topBlock):
                            ?>
                            <div class="top-block-item">
                                <span class="top-block-rank"><?php echo $medals[$index]; ?></span>
                                <div class="top-block-icon">
                                    <?php if (!empty($topBlock['icon']) && str_starts_with($topBlock['icon'], '<svg')): ?>
                                        <div class="block-svg-icon-container"><?php echo $topBlock['icon']; ?></div>
                                    <?php elseif (!empty($topBlock['icon'])): ?>
                                        <span class="dashicons dashicons-<?php echo esc_attr($topBlock['icon']); ?>"></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-block-default"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="top-block-info">
                                    <span class="top-block-title"><?php echo esc_html($topBlock['title']); ?></span>
                                    <span class="top-block-slug"><?php echo esc_html($topBlock['name']); ?></span>
                                </div>
                                <span class="top-block-count"><?php echo number_format($topBlock['count'], 0, ',', ' '); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Colonne droite : Usage par type de contenu -->
                <div class="blocks-stats-sidebar">
                    <h3 class="section-title">
                        <span class="dashicons dashicons-category"></span>
                        Usage par type de contenu
                    </h3>
                    <div class="posttype-stats-list">
                        <?php foreach ($stats['byPostType'] as $slug => $ptStats): ?>
                        <div class="posttype-stat-item">
                            <div class="posttype-stat-header">
                                <span class="posttype-stat-label"><?php echo esc_html($ptStats['label']); ?></span>
                                <span class="posttype-stat-count"><?php echo number_format($ptStats['occurrences'], 0, ',', ' '); ?> occ.</span>
                            </div>
                            <div class="posttype-stat-bar-wrap">
                                <?php
                                $maxOcc = max(array_column($stats['byPostType'], 'occurrences'));
                                $percentage = $maxOcc > 0 ? ($ptStats['occurrences'] / $maxOcc) * 100 : 0;
                                ?>
                                <div class="posttype-stat-bar" style="width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <span class="posttype-stat-blocks"><?php echo $ptStats['blocksCount']; ?> bloc<?php echo $ptStats['blocksCount'] > 1 ? 's' : ''; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Filtres modernisés -->
            <div class="blocks-filters-section">
                <h2 class="section-title">
                    <span class="dashicons dashicons-filter"></span>
                    Filtres & Recherche
                </h2>
                <div class="blocks-filters-bar">
                    <div class="filter-group">
                        <label for="block-search">Recherche</label>
                        <div class="filter-input-wrap">
                            <span class="dashicons dashicons-search"></span>
                            <input type="text" id="block-search" placeholder="Nom ou slug du bloc...">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label for="post-type-filter">Type de contenu</label>
                        <select id="post-type-filter">
                            <option value="">Tous les types</option>
                            <?php
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
                    <div class="filter-group">
                        <label for="occurrence-filter">Occurrences</label>
                        <select id="occurrence-filter">
                            <option value="">Toutes</option>
                            <option value="0">0 (Inutilisé)</option>
                            <option value="1-10">1 – 10</option>
                            <option value="11-50">11 – 50</option>
                            <option value=">50">> 50</option>
                        </select>
                    </div>
                    <button type="button" id="reset-filters" class="filter-reset-btn">
                        <span class="dashicons dashicons-update"></span>
                        Réinitialiser
                    </button>
                </div>
            </div>

            <!-- Tableau modernisé -->
            <div class="blocks-table-section">
                <div class="blocks-table-header">
                    <h2 class="section-title">
                        <span class="dashicons dashicons-editor-table"></span>
                        Liste des blocs
                    </h2>
                    <span class="blocks-table-count" id="blocks-count"><?php echo $stats['totalBlocks']; ?> blocs</span>
                </div>
                <div class="blocks-table-wrapper">
                    <table class="blocks-table" id="blocks-table">
                        <thead>
                        <tr>
                            <th class="col-icon">Icône</th>
                            <th class="col-name">Nom</th>
                            <th class="col-slug">Slug</th>
                            <th class="col-occurrences">Occurrences</th>
                            <th class="col-posttypes">Types de contenu</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($blocks as $block) {
                            if (!str_starts_with($block['name'], 'acf/')) {
                                continue;
                            }
                            $total = (int)$block['data']['total'];
                            if ($total === 0) {
                                $badgeClass = 'badge-unused';
                                $badgeText = 'Inutilisé';
                            } elseif ($total <= 10) {
                                $badgeClass = 'badge-few';
                                $badgeText = $total;
                            } elseif ($total <= 50) {
                                $badgeClass = 'badge-medium';
                                $badgeText = $total;
                            } else {
                                $badgeClass = 'badge-many';
                                $badgeText = $total;
                            }
                            ?>
                            <tr data-total="<?php echo $total; ?>">
                                <td class="col-icon">
                                    <div class="block-icon-cell">
                                        <?php if (!empty($block['icon']) && str_starts_with($block['icon'], '<svg')): ?>
                                            <div class="block-svg-icon-container"><?php echo $block['icon']; ?></div>
                                        <?php elseif (!empty($block['icon'])): ?>
                                            <span class="dashicons dashicons-<?php echo esc_attr($block['icon']); ?>"></span>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-block-default"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-name">
                                    <span class="block-title"><?php echo esc_html($block['title']); ?></span>
                                </td>
                                <td class="col-slug">
                                    <code class="block-slug"><?php echo esc_html($block['name']); ?></code>
                                </td>
                                <td class="col-occurrences">
                                    <span class="block-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                </td>
                                <td class="col-posttypes" data-posttypes="<?php echo esc_attr(implode(',', array_keys($block['data']['postTypes']))); ?>">
                                    <div class="posttypes-list">
                                        <?php foreach ($block['data']['postTypes'] as $slug => $postType):
                                            $pages = $block['data']['urlsByPostType'][$slug];
                                            $nbOcc = count($pages);
                                        ?>
                                        <div class="posttype-chip">
                                            <span class="posttype-chip-count"><?php echo $nbOcc; ?></span>
                                            <span class="posttype-chip-label"><?php echo esc_html($postType); ?></span>
                                            <div class="posttype-dropdown">
                                                <div class="posttype-dropdown-header">
                                                    <strong><?php echo esc_html($postType); ?></strong>
                                                    <span><?php echo $nbOcc; ?> page<?php echo $nbOcc > 1 ? 's' : ''; ?></span>
                                                </div>
                                                <ul class="posttype-dropdown-list">
                                                    <?php foreach ($pages as $page): ?>
                                                    <li>
                                                        <a href="<?php echo esc_url($adminUrlsByUrl[$page] ?? '#'); ?>" target="_blank">
                                                            <?php echo esc_html($pageNameByUrl[$page] ?? 'Sans titre'); ?>
                                                            <span class="dashicons dashicons-external"></span>
                                                        </a>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

// Initialisation du plugin
new GutenbergBlocksInspector();
