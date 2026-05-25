<?php
/**
 * Plugin Name: CPT Menu Alphabetical Order
 * Description: Ordena alfabeticamente los CPT en el menu admin de WordPress.
 */

if (!defined('ABSPATH')) {
    exit;
}

function concepcion_is_cpt_admin_menu_item($item)
{
    if (!is_array($item) || empty($item[2])) {
        return false;
    }

    return (strpos((string) $item[2], 'edit.php?post_type=') === 0);
}

function concepcion_get_cpt_admin_menu_sort_label($item)
{
    $title = isset($item[0]) ? wp_strip_all_tags((string) $item[0]) : '';
    $slug = isset($item[2]) ? (string) $item[2] : '';
    $post_type = '';

    if ('' !== $slug) {
        $query = wp_parse_url($slug, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $query_args);
            if (!empty($query_args['post_type'])) {
                $post_type = (string) $query_args['post_type'];
            }
        }
    }

    if ('' !== $post_type) {
        $post_type_object = get_post_type_object($post_type);
        if ($post_type_object instanceof WP_Post_Type && !empty($post_type_object->labels->menu_name)) {
            $title = (string) $post_type_object->labels->menu_name;
        }
    }

    return strtolower(remove_accents(trim($title)));
}

function concepcion_sort_cpt_admin_menus()
{
    global $menu, $submenu;

    if (empty($menu) || !is_array($menu)) {
        return;
    }

    $compare = static function ($a, $b) {
        return strcmp(
            concepcion_get_cpt_admin_menu_sort_label($a),
            concepcion_get_cpt_admin_menu_sort_label($b)
        );
    };

    $cpt_items = array();
    $cpt_indexes = array();

    foreach ($menu as $idx => $item) {
        if (!concepcion_is_cpt_admin_menu_item($item)) {
            continue;
        }

        $cpt_items[] = $item;
        $cpt_indexes[] = $idx;
    }

    if (count($cpt_items) > 1) {
        usort($cpt_items, $compare);

        foreach ($cpt_indexes as $i => $idx) {
            $menu[$idx] = $cpt_items[$i];
        }
    }

    if (empty($submenu) || !is_array($submenu)) {
        return;
    }

    foreach ($submenu as $parent_slug => $items) {
        if (strpos((string) $parent_slug, 'edit.php?post_type=') !== 0) {
            continue;
        }

        if (!is_array($items) || count($items) <= 1) {
            continue;
        }

        $sorted = $items;
        usort($sorted, $compare);
        $submenu[$parent_slug] = $sorted;
    }
}

add_action('admin_menu', 'concepcion_sort_cpt_admin_menus', 99999);
add_action('admin_head', 'concepcion_sort_cpt_admin_menus', 99999);
