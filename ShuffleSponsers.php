<?php
/**
 * Plugin Name: Vores egen plugin - "tagged som guiderotation"
 * Description: Adds unlimited ACF-driven sponsor lists to posts and displays them with shortcodes [list id="1"], [list id="2"], etc.; deterministic server-side shuffle (keep_first only).
 * Version: 2.1.0
 * Author: Mathias Duch
 */

if (!defined('ABSPATH')) exit;

/** ---------- Requirements notice ---------- */
add_action('admin_init', function () {
    if (!function_exists('acf_add_local_field_group')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Shuffled Sponsor Lists</strong> requires <em>Advanced Custom Fields Pro</em>.</p></div>';
        });
    }
});

/** ---------- ACF field group (repeater of lists) ---------- */
add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) return;

    acf_add_local_field_group([
        'key' => 'group_smag_lists',
        'title' => 'Guide lister',
        'fields' => [
            [
                'key' => 'field_smag_lists',
                'label' => 'Lister',
                'name' => 'smag_lists',
                'type' => 'repeater',
                'layout' => 'row',
                'button_label' => 'Tilføj Liste',
                'collapsed' => '',
                'sub_fields' => [
                    [
                        'key' => 'field_list_label',
                        'label' => '',
                        'name' => 'list_label',
                        'type' => 'message',
                        'message' => '<strong>Liste</strong>',
                        'esc_html' => 0,
                        'wrapper' => ['class' => 'smag-list-label'],
                    ],
                    [
                        'key' => 'field_list_enabled',
                        'label' => 'Vis liste',
                        'name' => 'enabled',
                        'type' => 'true_false',
                        'ui' => 1,
                        'default_value' => 1,
                    ],
                    [
                        'key' => 'field_list_seed_notice',
                        'label' => 'Shuffle Info',
                        'name' => 'seed_notice',
                        'type' => 'message',
                        'message' => '🔄 Denne guide liste bliver <strong>blandet dagligt</strong>. Rækkefølgen ændrer sig én gang om dagen.',
                        'esc_html' => 0,
                    ],
                    [
                        'key' => 'field_list_keep',
                        'label' => 'Frys de første X, så de altid vises først.',
                        'name' => 'keep_first',
                        'type' => 'number',
                        'default_value' => 0,
                        'min' => 0,
                        'step' => 1,
                    ],
                    [
                        'key' => 'field_list_items',
                        'label' => 'Steder',
                        'name'  => 'items',
                        'type'  => 'repeater',
                        'layout'=> 'row',
                        'button_label' => 'Tilføj Sted',
                        'min' => 0,
                        'collapsed' => 'field_item_name',
                        'sub_fields' => [
                            [
                                'key' => 'field_item_row_label',
                                'label' => '',
                                'name'  => 'row_label',
                                'type'  => 'message',
                                'message' => '<strong>Sted</strong>',
                                'esc_html' => 0,
                                'wrapper' => ['class' => 'smag-row-label'],
                            ],
                            [
                                'key' => 'field_item_name',
                                'label' => 'Overskrift',
                                'name' => 'name',
                                'type' => 'text',
                                'required' => 1,
                            ],
                            [
                                'key' => 'field_item_heading_level',
                                'label' => 'Overskrift niveau',
                                'name' => 'heading_level',
                                'type' => 'select',
                                'choices' => [
                                    'h2' => 'H2',
                                    'h3' => 'H3',
                                    'h4' => 'H4',
                                ],
                                'default_value' => 'h3',
                                'ui' => 1,
                                'return_format' => 'value',
                            ],
                            [
                                'key' => 'field_item_img',
                                'label' => 'Billede',
                                'name' => 'image',
                                'type' => 'image',
                                'return_format' => 'id',
                                'preview_size'  => 'medium',
                            ],
                            [
                                'key' => 'field_item_caption',
                                'label' => 'Billedetekst',
                                'name'  => 'caption',
                                'type'  => 'text',
                            ],
                            [
                                'key' => 'field_item_desc',
                                'label' => 'Beskrivelse',
                                'name' => 'description',
                                'type' => 'wysiwyg',
                                'tabs' => 'visual',
                                'media_upload' => 0,
                                'delay' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'location' => [[
            ['param' => 'post_taxonomy', 'operator' => '==', 'value' => 'post_tag:guiderotation'],
        ]],
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ]);
});

/** ---------- Default metabox order ---------- */
add_filter('get_user_option_meta-box-order_post', function ($order) {
    if (!empty($order) && is_array($order)) return $order;
    $acf_box_id = 'acf-group_group_smag_lists';
    return [
        'normal'   => 'postdivrich,' . $acf_box_id,
        'advanced' => '',
        'side'     => 'submitdiv,categorydiv,tagsdiv-post_tag,postimagediv',
    ];
});

/** ---------- Admin-only UI: number "Sted" + "Liste" ---------- */
add_action('admin_enqueue_scripts', function($hook){
    if ($hook !== 'post.php' && $hook !== 'post-new.php') return;

    $js = <<<'JS'
jQuery(function($){
  var listSel = '[data-key="field_smag_lists"] > .acf-repeater > table > tbody > tr.acf-row';
  function refreshLists(){
    $(listSel).each(function(i){
      var idx = i + 1;
      $(this).find('.acf-field-message.smag-list-label .acf-input')
        .html('<p><strong>Liste ' + idx + ' - [list id="' + idx + '"]</strong></p>');
      $(this).find('[data-name="items"] tr.acf-row').each(function(j){
        var sidx = j + 1;
        $(this).find('.acf-field-message.smag-row-label .acf-input')
          .html('<p><strong>Sted ' + sidx + '</strong></p>');
      });
    });
  }
  $(document).on('click', '.acf-button[data-event="add-row"], .acf-icon.-minus.small', function(){
    setTimeout(refreshLists, 150);
  });
  if(window.acf && acf.addAction){
    acf.addAction('append', refreshLists);
    acf.addAction('show', refreshLists);
    acf.addAction('remove', refreshLists);
    acf.addAction('sortstop', refreshLists);
  }
  refreshLists();
});
JS;
    wp_register_script('smag-sted-admin', false, ['jquery'], null, true);
    wp_enqueue_script('smag-sted-admin');
    wp_add_inline_script('smag-sted-admin', $js);
});

/** ---------- Helpers ---------- */
function smag2_seed($post_id, $mode = 'daily') {
    switch ($mode) {
        case 'post':   return (int) $post_id;
        case 'random': return wp_rand();
        case 'daily':
        default:       return crc32($post_id . '|' . gmdate('Y-m-d'));
    }
}

/** ---------- Renderer ---------- */
function smag2_render_list_html($list, $post_id) {
    if (empty($list['items'])) return '';
    $seed   = smag2_seed($post_id, 'daily');
    $keepN  = max(0, (int) ($list['keep_first'] ?? 0));

    $items = array_map(function ($r) {
        return [
            'name'          => trim((string)($r['name'] ?? '')),
            'heading_level' => $r['heading_level'] ?? 'h3',
            'image_id'      => (int)($r['image'] ?? 0),
            'caption'       => trim((string)($r['caption'] ?? '')),
            'description'   => (string)($r['description'] ?? ''),
        ];
    }, $list['items']);

    $fixed   = $keepN > 0 ? array_slice($items, 0, $keepN) : [];
    $shuffle = $keepN > 0 ? array_slice($items, $keepN) : $items;

    if (count($shuffle) > 1) {
        mt_srand($seed);
        shuffle($shuffle);
        mt_srand();
    }

    $ordered = array_merge($fixed, $shuffle);

    ob_start();
    echo '<div class="smag-sponsors">';

    foreach ($ordered as $it) {
        $level = in_array($it['heading_level'], ['h2','h3','h4']) ? $it['heading_level'] : 'h3';
        echo '<' . $level . '>' . esc_html($it['name']) . '</' . $level . '>';

        if ($it['image_id']) {
            $img = wp_get_attachment_image($it['image_id'], 'large', false, [
                'loading' => 'lazy',
                'decoding'=> 'async',
                'class'   => 'wp-image-' . $it['image_id'],
            ]);
            echo '<figure class="wp-caption alignnone size-large">';
            echo $img;
            if ($it['caption'] !== '') {
                echo '<figcaption class="wp-caption-text"><small>'.esc_html($it['caption']).'</small></figcaption>';
            }
            echo '</figure>';
        }
        if ($it['description'] !== '') {
            echo wpautop( wp_kses_post($it['description']) );
        }
        echo '<hr class="wp-block-separator" style="visibility:hidden;margin:1.5rem 0 0 0" />';
    }

    echo '</div>';
    return trim(ob_get_clean());
}

/** ---------- Shortcode ---------- */
add_shortcode('list', function ($atts) {
    if (!is_singular() || is_admin()) return '';
    $post_id = get_the_ID();
    if (!$post_id || !function_exists('get_field')) return '';

    $atts = shortcode_atts(['id' => '1'], $atts, 'list');
    $id = (int) $atts['id'];

    $lists = get_field('smag_lists', $post_id);
    if (!is_array($lists) || empty($lists[$id-1])) return '';

    $list = $lists[$id-1];
    if (empty($list['enabled']) || empty($list['items'])) return '';

    return smag2_render_list_html($list + ['__idx' => $id], $post_id);
});
