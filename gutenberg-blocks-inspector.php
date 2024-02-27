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
 * Requires PHP:      8.2
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
	$blocksCount = count($allBlocks);
	$postTypes = [];
	$blocks = [];
	$urlsById = [];
	$adminUrlsByUrl = [];
	$pageNameByUrl = [];

	foreach ($allBlocks as $block) {
		$blockData = [
			'total' => 0,
			'perPage' => [],
			'postTypes' => [],
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
						'page' => $result->ID,
						'count' => $count,
						'postType' => $result->post_type,
					];

					if (!isset($postTypes[$result->post_type])) {
						$label = get_post_type_object($result->post_type)?->label;

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
			'name' => $block->name,
			'data' => $blockData,
			'icon' => $block->icon,
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
    <div class="wrap">
        <table class="wp-list-table widefat">
            <thead>
            <tr>
                <th>Icône</th>
                <th>Nom</th>
                <th>Slug</th>
                <th>Occurences</th>
                <th>Post Types</th>
            </tr>
            </thead>
            <tbody>
			<?php
			foreach ($blocks as $block) {
				?>
                <tr>
                    <td>
						<?php
						if (null !== $block['icon']) {
							if (!str_starts_with($block['icon'], '<svg')) {
								?>
                                <div class="dashicons dashicons-<?php echo $block['icon'] ?>"></div>
								<?php
							} else {
								?>
								<?php echo $block['icon'] ?>
								<?php
							}
						}

						?>
                    </td>
                    <td>
                        <strong><?php echo $block['title']; ?></strong>
                    </td>
                    <td><?php echo $block['name']; ?></td>
                    <td><?php echo $block['data']['total']; ?></td>
                    <td style="position: relative;"><?php
						$count = 0;

						foreach ($block['data']['postTypes'] as $slug => $postType) {
							if ($count > 0) {
								echo ', ';
							}

							$pages = $block['data']['urlsByPostType'][$slug];

							$urls = '<div class="post-type-links" style="display: none; position: absolute; background-color: white; box-sizing: border-box; padding: 8px 16px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1); z-index: 999; width: 350px; right: 0; top: 100%; max-height: 400px; overflow: scroll;"><ul>';
							$urls .= '<p>Pages "<strong>' . $postType . '</strong>" contenant le bloc "<strong>' . $block['title'] . '</strong>" <i>(' . count($pages) . ' occurence·s)</i></p>';

							foreach ($pages as $page) {
								$urls .= '<li><a class="post-type-link" target="_blank" href="' . $adminUrlsByUrl[$page] . '">' . $pageNameByUrl[$page] . '</a></li>';
							}

							$urls .= '</ul></div>';

							echo '<span style="position: relative; cursor: default;" class="post-type-title">' . $postType . $urls . '</span>';

							$count++;
						}
						?></td>
                </tr>
				<?php
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
    </style>

	<?php
}
