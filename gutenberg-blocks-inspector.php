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
 * Version:           0.0.1
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
        add_action('admin_menu', [
            $this,
            'registerMenu',
        ]);
        add_action('admin_enqueue_scripts', [
            $this,
            'enqueueAssets',
        ]);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            $this->metaTitle,
            $this->menuTitle,
            'manage_options',
            $this->slug,
            [
                $this,
                'renderPage',
            ],
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
        $this->renderTable($blocks, $adminUrlsByUrl, $pageNameByUrl);
    }

    private function renderTable(array $blocks, array $adminUrlsByUrl, array $pageNameByUrl): void
    {
        ?>
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
        <?php
    }
}

// Initialisation du plugin
new GutenbergBlocksInspector();