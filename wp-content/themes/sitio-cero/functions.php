<?php

if (!defined('ABSPATH')) {
    exit;
}

// Orden alfabético de menús CPT en admin (solo edit.php?post_type=...).
function sitio_cero_is_cpt_admin_menu_item($item)
{
    if (!is_array($item) || empty($item[2])) {
        return false;
    }

    return (strpos((string) $item[2], 'edit.php?post_type=') === 0);
}

function sitio_cero_get_cpt_admin_menu_sort_label($item)
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

add_action('admin_menu', function () {
    sitio_cero_sort_cpt_admin_menus();
}, 99999);

function sitio_cero_sort_cpt_admin_menus()
{
    global $menu, $submenu;

    if (empty($menu) || !is_array($menu)) {
        return;
    }

    $compare = static function ($a, $b) {
        return strcmp(
            sitio_cero_get_cpt_admin_menu_sort_label($a),
            sitio_cero_get_cpt_admin_menu_sort_label($b)
        );
    };

    // 1) Ordenar items de menú de CPT.
    $cpt_items = array();
    $cpt_indexes = array();

    foreach ($menu as $idx => $item) {
        if (!sitio_cero_is_cpt_admin_menu_item($item)) {
            continue;
        }

        $cpt_items[] = $item;
        $cpt_indexes[] = $idx;
    }

    if (count($cpt_items) > 1) {
        usort($cpt_items, $compare);

        // Reinsertar en las mismas posiciones que ocupaban los CPTs.
        foreach ($cpt_indexes as $i => $idx) {
            $menu[$idx] = $cpt_items[$i];
        }
    }

    // 2) Ordenar submenús de cada CPT.
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

add_action('admin_head', 'sitio_cero_sort_cpt_admin_menus', 99999);

// Orden alfabético por título en listados admin de todos los CPT.
function sitio_cero_sort_cpt_admin_listings($query)
{
    if (!is_admin() || !($query instanceof WP_Query) || !$query->is_main_query()) {
        return;
    }

    global $pagenow;
    if ('edit.php' !== $pagenow) {
        return;
    }

    $post_type = (string) $query->get('post_type');
    if ('' === $post_type || 'any' === $post_type || !post_type_exists($post_type)) {
        return;
    }

    $post_type_object = get_post_type_object($post_type);
    if (!($post_type_object instanceof WP_Post_Type) || !empty($post_type_object->_builtin)) {
        return;
    }

    if ('' !== (string) $query->get('orderby')) {
        return;
    }

    $query->set('orderby', 'title');
    $query->set('order', 'ASC');
}
add_action('pre_get_posts', 'sitio_cero_sort_cpt_admin_listings', 100);

function sitio_cero_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support(
        'custom-logo',
        array(
            'height'      => 80,
            'width'       => 320,
            'flex-height' => true,
            'flex-width'  => true,
        )
    );
    add_theme_support('html5', array('search-form', 'gallery', 'caption', 'style', 'script'));

    register_nav_menus(
        array(
            'primary'          => __('Menú principal', 'sitio-cero'),
            'hero_info'        => __('Menú Quiero información', 'sitio-cero'),
            'temas_ciudadanos' => __('Menú Temas ciudadanos', 'sitio-cero'),
            'tramites_hero'    => __('Menu Trámites - Hero', 'sitio-cero'),
            'tramites_grid'    => __('Menu Trámites - Grilla', 'sitio-cero'),
        )
    );
}
add_action('after_setup_theme', 'sitio_cero_setup');

function sitio_cero_assets()
{
    $version = wp_get_theme()->get('Version');

    wp_enqueue_style('dashicons');
    wp_enqueue_style(
        'sitio-cero-material-symbols',
        sitio_cero_get_material_symbols_stylesheet_url(),
        array(),
        null
    );

    wp_enqueue_style(
        'sitio-cero-main',
        get_template_directory_uri() . '/assets/css/main.css',
        array(),
        $version
    );

    $logo_height_desktop = sitio_cero_sanitize_logo_height(get_theme_mod('sitio_cero_brand_logo_height_desktop', 102));
    $logo_height_mobile = sitio_cero_sanitize_logo_height(get_theme_mod('sitio_cero_brand_logo_height_mobile', 72));
    $inline_css = ':root{--sitio-cero-brand-logo-max-height:' . $logo_height_desktop . 'px;--sitio-cero-brand-logo-max-height-mobile:' . $logo_height_mobile . 'px;}';
    wp_add_inline_style('sitio-cero-main', $inline_css);

    wp_enqueue_script(
        'sitio-cero-main',
        get_template_directory_uri() . '/assets/js/main.js',
        array(),
        $version,
        true
    );
}
add_action('wp_enqueue_scripts', 'sitio_cero_assets');

function sitio_cero_should_render_breadcrumbs()
{
    if (is_admin()) {
        return false;
    }

    if (is_front_page() || is_home()) {
        return false;
    }

    return true;
}

function sitio_cero_get_term_breadcrumb_items($term)
{
    $items = array();
    if (!$term instanceof WP_Term) {
        return $items;
    }

    $ancestors = array_reverse(get_ancestors((int) $term->term_id, (string) $term->taxonomy, 'taxonomy'));
    foreach ($ancestors as $ancestor_id) {
        $ancestor = get_term((int) $ancestor_id, (string) $term->taxonomy);
        if (!$ancestor instanceof WP_Term || is_wp_error($ancestor)) {
            continue;
        }

        $ancestor_link = get_term_link($ancestor);
        $items[] = array(
            'label' => (string) $ancestor->name,
            'url'   => !is_wp_error($ancestor_link) ? (string) $ancestor_link : '',
        );
    }

    $term_link = get_term_link($term);
    $items[] = array(
        'label' => (string) $term->name,
        'url'   => !is_wp_error($term_link) ? (string) $term_link : '',
    );

    return $items;
}

function sitio_cero_get_breadcrumb_items()
{
    $items = array(
        array(
            'label' => __('Inicio', 'sitio-cero'),
            'url'   => home_url('/'),
        ),
    );

    if (is_search()) {
        $items[] = array(
            'label' => sprintf(__('Búsqueda: %s', 'sitio-cero'), get_search_query()),
            'url'   => '',
        );
        return $items;
    }

    if (is_404()) {
        $items[] = array(
            'label' => __('Página no encontrada', 'sitio-cero'),
            'url'   => '',
        );
        return $items;
    }

    if (is_post_type_archive()) {
        $post_type = get_query_var('post_type');
        if (is_array($post_type)) {
            $post_type = reset($post_type);
        }
        $post_type_obj = is_string($post_type) ? get_post_type_object($post_type) : null;
        $items[] = array(
            'label' => $post_type_obj ? (string) $post_type_obj->labels->name : __('Archivo', 'sitio-cero'),
            'url'   => '',
        );
        return $items;
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $term_items = sitio_cero_get_term_breadcrumb_items($term);
            if (!empty($term_items)) {
                $last_index = count($term_items) - 1;
                foreach ($term_items as $index => $term_item) {
                    if (!is_array($term_item)) {
                        continue;
                    }
                    if ($index === $last_index) {
                        $term_item['url'] = '';
                    }
                    $items[] = $term_item;
                }
            }
        }
        return $items;
    }

    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id <= 0) {
            return $items;
        }

        $post_type = (string) get_post_type($post_id);

        if ('page' === $post_type) {
            $ancestors = array_reverse(get_post_ancestors($post_id));
            foreach ($ancestors as $ancestor_id) {
                $items[] = array(
                    'label' => get_the_title((int) $ancestor_id),
                    'url'   => get_permalink((int) $ancestor_id),
                );
            }

            $items[] = array(
                'label' => get_the_title($post_id),
                'url'   => '',
            );
            return $items;
        }

        if ('post' === $post_type) {
            $page_for_posts = (int) get_option('page_for_posts');
            if ($page_for_posts > 0) {
                $items[] = array(
                    'label' => get_the_title($page_for_posts),
                    'url'   => get_permalink($page_for_posts),
                );
            }

            $categories = get_the_category($post_id);
            if (is_array($categories) && !empty($categories) && $categories[0] instanceof WP_Term) {
                $term_items = sitio_cero_get_term_breadcrumb_items($categories[0]);
                if (!empty($term_items)) {
                    foreach ($term_items as $term_item) {
                        if (!is_array($term_item)) {
                            continue;
                        }
                        $items[] = $term_item;
                    }
                }
            }
        } else {
            $archive_url = get_post_type_archive_link($post_type);
            $post_type_obj = get_post_type_object($post_type);
            if (is_string($archive_url) && '' !== trim($archive_url) && $post_type_obj) {
                $items[] = array(
                    'label' => (string) $post_type_obj->labels->name,
                    'url'   => $archive_url,
                );
            }
        }

        $items[] = array(
            'label' => get_the_title($post_id),
            'url'   => '',
        );
        return $items;
    }

    if (is_author()) {
        $author = get_queried_object();
        $items[] = array(
            'label' => $author instanceof WP_User ? (string) $author->display_name : __('Autor', 'sitio-cero'),
            'url'   => '',
        );
        return $items;
    }

    if (is_date()) {
        $items[] = array(
            'label' => wp_strip_all_tags(get_the_archive_title()),
            'url'   => '',
        );
        return $items;
    }

    $title = wp_strip_all_tags(get_the_archive_title());
    if ('' !== trim($title)) {
        $items[] = array(
            'label' => $title,
            'url'   => '',
        );
    }

    return $items;
}

function sitio_cero_render_breadcrumbs()
{
    if (!sitio_cero_should_render_breadcrumbs()) {
        return;
    }

    $items = sitio_cero_get_breadcrumb_items();
    if (!is_array($items) || count($items) < 2) {
        return;
    }

    echo '<div class="site-breadcrumbs" aria-label="' . esc_attr__('Miga de pan', 'sitio-cero') . '">';
    echo '<div class="container">';
    echo '<nav class="breadcrumbs" aria-label="' . esc_attr__('Miga de pan', 'sitio-cero') . '">';
    echo '<ol class="breadcrumbs__list">';

    $last_index = count($items) - 1;
    foreach ($items as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? trim((string) $item['label']) : '';
        $url = isset($item['url']) ? trim((string) $item['url']) : '';
        if ('' === $label) {
            continue;
        }

        $is_current = $index === $last_index;
        echo '<li class="breadcrumbs__item">';
        if (!$is_current && '' !== $url) {
            echo '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        } else {
            echo '<span aria-current="page">' . esc_html($label) . '</span>';
        }
        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';
    echo '</div>';
    echo '</div>';
}

function sitio_cero_register_topbar_item_post_type()
{
    $labels = array(
        'name'               => __('Topbar', 'sitio-cero'),
        'singular_name'      => __('Item Topbar', 'sitio-cero'),
        'menu_name'          => __('Topbar', 'sitio-cero'),
        'name_admin_bar'     => __('Item Topbar', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar item Topbar', 'sitio-cero'),
        'new_item'           => __('Nuevo item Topbar', 'sitio-cero'),
        'edit_item'          => __('Editar item Topbar', 'sitio-cero'),
        'view_item'          => __('Ver item Topbar', 'sitio-cero'),
        'all_items'          => __('Todos los items Topbar', 'sitio-cero'),
        'search_items'       => __('Buscar items Topbar', 'sitio-cero'),
        'not_found'          => __('No se encontraron items.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay items en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'topbar_item',
        array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => true,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'hierarchical'       => true,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-editor-ul',
            'supports'           => array('title', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_topbar_item_post_type');

function sitio_cero_add_topbar_item_metaboxes()
{
    add_meta_box(
        'sitio_cero_topbar_item_details',
        __('Configuracion del item', 'sitio-cero'),
        'sitio_cero_render_topbar_item_metabox',
        'topbar_item',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_topbar_item_metaboxes');

function sitio_cero_render_topbar_item_metabox($post)
{
    wp_nonce_field('sitio_cero_save_topbar_item_meta', 'sitio_cero_topbar_item_meta_nonce');

    $type = get_post_meta($post->ID, 'sitio_cero_topbar_item_type', true);
    $subtitle = get_post_meta($post->ID, 'sitio_cero_topbar_item_subtitle', true);
    $url = get_post_meta($post->ID, 'sitio_cero_topbar_item_url', true);
    $icon = get_post_meta($post->ID, 'sitio_cero_topbar_item_icon', true);
    $target_blank = get_post_meta($post->ID, 'sitio_cero_topbar_item_target_blank', true);

    if (!is_string($type) || '' === trim($type)) {
        $type = 'info';
    }
    if (!is_string($subtitle)) {
        $subtitle = '';
    }
    if (!is_string($url)) {
        $url = '';
    }
    if (!is_string($icon)) {
        $icon = '';
    }

    ?>
    <p>
        <label for="sitio_cero_topbar_item_type"><strong><?php esc_html_e('Tipo de item', 'sitio-cero'); ?></strong></label><br>
        <select id="sitio_cero_topbar_item_type" name="sitio_cero_topbar_item_type">
            <option value="info" <?php selected($type, 'info'); ?>><?php esc_html_e('Informacion', 'sitio-cero'); ?></option>
            <option value="cta" <?php selected($type, 'cta'); ?>><?php esc_html_e('Boton destacado', 'sitio-cero'); ?></option>
            <option value="link" <?php selected($type, 'link'); ?>><?php esc_html_e('Link simple', 'sitio-cero'); ?></option>
        </select>
    </p>
    <p>
        <label for="sitio_cero_topbar_item_subtitle"><strong><?php esc_html_e('Subtitulo / valor', 'sitio-cero'); ?></strong></label><br>
        <input type="text" class="widefat" id="sitio_cero_topbar_item_subtitle" name="sitio_cero_topbar_item_subtitle" value="<?php echo esc_attr($subtitle); ?>" placeholder="<?php esc_attr_e('Ejemplo: Ley de Transparencia', 'sitio-cero'); ?>">
    </p>
    <p>
        <label for="sitio_cero_topbar_item_url"><strong><?php esc_html_e('URL (opcional)', 'sitio-cero'); ?></strong></label><br>
        <input type="url" class="widefat" id="sitio_cero_topbar_item_url" name="sitio_cero_topbar_item_url" value="<?php echo esc_attr($url); ?>" placeholder="https://">
    </p>
    <p>
        <label for="sitio_cero_topbar_item_icon"><strong><?php esc_html_e('Icono Material Symbols (opcional)', 'sitio-cero'); ?></strong></label><br>
        <input type="text" class="widefat" id="sitio_cero_topbar_item_icon" name="sitio_cero_topbar_item_icon" value="<?php echo esc_attr($icon); ?>" placeholder="<?php esc_attr_e('Ejemplo: info', 'sitio-cero'); ?>">
    </p>
    <p>
        <label>
            <input type="checkbox" name="sitio_cero_topbar_item_target_blank" value="1" <?php checked('1', (string) $target_blank); ?>>
            <?php esc_html_e('Abrir enlace en nueva pestaña', 'sitio-cero'); ?>
        </label>
    </p>
    <p><em><?php esc_html_e('Ordena los items con "Atributos de pagina > Orden" para simular un menu.', 'sitio-cero'); ?></em></p>
    <?php
}

function sitio_cero_save_topbar_item_meta($post_id)
{
    if (!isset($_POST['sitio_cero_topbar_item_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_topbar_item_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_topbar_item_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $allowed_types = array('info', 'cta', 'link');
    $type = isset($_POST['sitio_cero_topbar_item_type']) ? sanitize_key(wp_unslash($_POST['sitio_cero_topbar_item_type'])) : 'info';
    if (!in_array($type, $allowed_types, true)) {
        $type = 'info';
    }
    update_post_meta($post_id, 'sitio_cero_topbar_item_type', $type);

    $subtitle = isset($_POST['sitio_cero_topbar_item_subtitle']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_topbar_item_subtitle'])) : '';
    update_post_meta($post_id, 'sitio_cero_topbar_item_subtitle', $subtitle);

    $url = isset($_POST['sitio_cero_topbar_item_url']) ? esc_url_raw(wp_unslash($_POST['sitio_cero_topbar_item_url'])) : '';
    update_post_meta($post_id, 'sitio_cero_topbar_item_url', $url);

    $icon = isset($_POST['sitio_cero_topbar_item_icon']) ? sanitize_key(wp_unslash($_POST['sitio_cero_topbar_item_icon'])) : '';
    update_post_meta($post_id, 'sitio_cero_topbar_item_icon', $icon);

    $target_blank = isset($_POST['sitio_cero_topbar_item_target_blank']) ? '1' : '0';
    update_post_meta($post_id, 'sitio_cero_topbar_item_target_blank', $target_blank);
}
add_action('save_post_topbar_item', 'sitio_cero_save_topbar_item_meta');

function sitio_cero_get_topbar_items()
{
    $items = array();

    $posts = get_posts(
        array(
            'post_type'      => 'topbar_item',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'date'       => 'ASC',
                'ID'         => 'ASC',
            ),
            'no_found_rows'  => true,
        )
    );

    if (!is_array($posts) || empty($posts)) {
        return $items;
    }

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $type = get_post_meta($post->ID, 'sitio_cero_topbar_item_type', true);
        $subtitle = get_post_meta($post->ID, 'sitio_cero_topbar_item_subtitle', true);
        $url = get_post_meta($post->ID, 'sitio_cero_topbar_item_url', true);
        $icon = get_post_meta($post->ID, 'sitio_cero_topbar_item_icon', true);
        $target_blank = get_post_meta($post->ID, 'sitio_cero_topbar_item_target_blank', true);

        $type = is_string($type) ? sanitize_key($type) : 'info';
        if (!in_array($type, array('info', 'cta', 'link'), true)) {
            $type = 'info';
        }

        $items[] = array(
            'type'         => $type,
            'title'        => sanitize_text_field((string) $post->post_title),
            'subtitle'     => is_string($subtitle) ? sanitize_text_field($subtitle) : '',
            'url'          => is_string($url) ? esc_url($url) : '',
            'icon'         => is_string($icon) ? sanitize_key($icon) : '',
            'target_blank' => '1' === (string) $target_blank,
        );
    }

    return $items;
}

function sitio_cero_seed_default_topbar_items()
{
    if (!post_type_exists('topbar_item')) {
        return;
    }

    $seed_version = '2';
    $seed_option = 'sitio_cero_topbar_items_seeded_version';
    if ((string) get_option($seed_option, '') === $seed_version) {
        return;
    }

    $existing = get_posts(
        array(
            'post_type'      => 'topbar_item',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );
    if (!empty($existing)) {
        update_option($seed_option, $seed_version);
        return;
    }

    $defaults = array(
        array(
            'title'    => __('Fono central', 'sitio-cero'),
            'subtitle' => '+56 2 3386 8000',
            'type'     => 'info',
        ),
        array(
            'title'    => __('Emergencias', 'sitio-cero'),
            'subtitle' => '1414',
            'type'     => 'info',
        ),
        array(
            'title'        => __('Solicitud de información', 'sitio-cero'),
            'subtitle'     => __('Ley de Transparencia', 'sitio-cero'),
            'type'         => 'cta',
            'url'          => 'https://www.portaltransparencia.cl/PortalPdT/ingreso-sai-v2',
            'icon'         => 'info',
            'target_blank' => '1',
        ),
        array(
            'title'        => __('Transparencia activa', 'sitio-cero'),
            'subtitle'     => __('Ley de Transparencia', 'sitio-cero'),
            'type'         => 'cta',
            'url'          => 'https://www.portaltransparencia.cl/PortalPdT/directorio-de-organismos-regulados',
            'icon'         => 'folder_open',
            'target_blank' => '1',
        ),
        array(
            'title'    => __('Canales de atención', 'sitio-cero'),
            'type'     => 'link',
            'url'      => home_url('/#canales'),
        ),
    );

    foreach ($defaults as $index => $item) {
        $post_id = wp_insert_post(
            array(
                'post_type'   => 'topbar_item',
                'post_status' => 'publish',
                'post_title'  => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
                'menu_order'  => (int) $index,
            ),
            true
        );

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta($post_id, 'sitio_cero_topbar_item_type', isset($item['type']) ? sanitize_key((string) $item['type']) : 'info');
        update_post_meta($post_id, 'sitio_cero_topbar_item_subtitle', isset($item['subtitle']) ? sanitize_text_field((string) $item['subtitle']) : '');
        update_post_meta($post_id, 'sitio_cero_topbar_item_url', isset($item['url']) ? esc_url_raw((string) $item['url']) : '');
        update_post_meta($post_id, 'sitio_cero_topbar_item_icon', isset($item['icon']) ? sanitize_key((string) $item['icon']) : '');
        update_post_meta($post_id, 'sitio_cero_topbar_item_target_blank', isset($item['target_blank']) ? '1' : '0');
    }

    update_option($seed_option, $seed_version);
}
add_action('init', 'sitio_cero_seed_default_topbar_items', 46);

function sitio_cero_normalize_topbar_items_spelling()
{
    if (!post_type_exists('topbar_item')) {
        return;
    }

    $version = '1';
    $option_key = 'sitio_cero_topbar_spelling_normalized_version';
    if ((string) get_option($option_key, '') === $version) {
        return;
    }

    $title_map = array(
        'Solicitud de informacion' => 'Solicitud de información',
        'Canales de atencion'      => 'Canales de atención',
    );
    $subtitle_map = array(
        'Ley de Transparencia' => 'Ley de Transparencia',
    );

    $posts = get_posts(
        array(
            'post_type'      => 'topbar_item',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        $current_title = get_the_title($post_id);
        if (isset($title_map[$current_title])) {
            wp_update_post(
                array(
                    'ID'         => $post_id,
                    'post_title' => $title_map[$current_title],
                )
            );
        }

        $current_subtitle = get_post_meta($post_id, 'sitio_cero_topbar_item_subtitle', true);
        if (is_string($current_subtitle) && isset($subtitle_map[$current_subtitle])) {
            update_post_meta($post_id, 'sitio_cero_topbar_item_subtitle', $subtitle_map[$current_subtitle]);
        }
    }

    update_option($option_key, $version);
}
add_action('init', 'sitio_cero_normalize_topbar_items_spelling', 47);

function sitio_cero_enqueue_topbar_sort_admin_assets($hook_suffix)
{
    if ('edit.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'topbar_item' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'sitio-cero-admin-topbar-sort',
        get_template_directory_uri() . '/assets/css/admin-topbar-sort.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-topbar-sort',
        get_template_directory_uri() . '/assets/js/admin-topbar-sort.js',
        array('jquery', 'jquery-ui-sortable'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-topbar-sort',
        'sitioCeroTopbarSort',
        array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('sitio_cero_topbar_sort'),
            'savingText' => __('Guardando orden...', 'sitio-cero'),
            'savedText'  => __('Orden guardado.', 'sitio-cero'),
            'errorText'  => __('No fue posible guardar el orden.', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_topbar_sort_admin_assets');

function sitio_cero_order_topbar_admin_list($query)
{
    if (!is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
        return;
    }

    global $pagenow;
    if ('edit.php' !== $pagenow) {
        return;
    }

    $post_type = $query->get('post_type');
    if ('topbar_item' !== $post_type) {
        return;
    }

    if ((string) $query->get('orderby') !== '') {
        return;
    }

    $query->set('orderby', 'menu_order title');
    $query->set('order', 'ASC');
}
add_action('pre_get_posts', 'sitio_cero_order_topbar_admin_list');

function sitio_cero_ajax_sort_topbar_items()
{
    check_ajax_referer('sitio_cero_topbar_sort', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(
            array('message' => __('Permisos insuficientes.', 'sitio-cero')),
            403
        );
    }

    $raw_order = isset($_POST['order']) ? wp_unslash($_POST['order']) : array();
    if (!is_array($raw_order)) {
        wp_send_json_error(
            array('message' => __('Orden invalido.', 'sitio-cero')),
            400
        );
    }

    $order = array_values(array_filter(array_map('absint', $raw_order)));
    if (empty($order)) {
        wp_send_json_error(
            array('message' => __('No se recibieron items.', 'sitio-cero')),
            400
        );
    }

    $position = 0;
    foreach ($order as $post_id) {
        if ('topbar_item' !== get_post_type($post_id)) {
            continue;
        }
        if (!current_user_can('edit_post', $post_id)) {
            continue;
        }

        wp_update_post(
            array(
                'ID'         => $post_id,
                'menu_order' => $position,
            )
        );
        $position++;
    }

    wp_send_json_success(
        array(
            'updated' => $position,
        )
    );
}
add_action('wp_ajax_sitio_cero_sort_topbar_items', 'sitio_cero_ajax_sort_topbar_items');

function sitio_cero_enqueue_lamina_sort_admin_assets($hook_suffix)
{
    if ('edit.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'lamina_hero' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'sitio-cero-admin-lamina-sort',
        get_template_directory_uri() . '/assets/css/admin-topbar-sort.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-lamina-sort',
        get_template_directory_uri() . '/assets/js/admin-lamina-sort.js',
        array('jquery', 'jquery-ui-sortable'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-lamina-sort',
        'sitioCeroLaminaSort',
        array(
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('sitio_cero_lamina_sort'),
            'savingText' => __('Guardando orden...', 'sitio-cero'),
            'savedText'  => __('Orden guardado.', 'sitio-cero'),
            'errorText'  => __('No fue posible guardar el orden.', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_lamina_sort_admin_assets');

function sitio_cero_order_lamina_admin_list($query)
{
    if (!is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
        return;
    }

    global $pagenow;
    if ('edit.php' !== $pagenow) {
        return;
    }

    $post_type = $query->get('post_type');
    if ('lamina_hero' !== $post_type) {
        return;
    }

    if ((string) $query->get('orderby') !== '') {
        return;
    }

    $query->set('orderby', 'menu_order title');
    $query->set('order', 'ASC');
}
add_action('pre_get_posts', 'sitio_cero_order_lamina_admin_list');

function sitio_cero_ajax_sort_lamina_items()
{
    check_ajax_referer('sitio_cero_lamina_sort', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error(
            array('message' => __('Permisos insuficientes.', 'sitio-cero')),
            403
        );
    }

    $raw_order = isset($_POST['order']) ? wp_unslash($_POST['order']) : array();
    if (!is_array($raw_order)) {
        wp_send_json_error(
            array('message' => __('Orden invalido.', 'sitio-cero')),
            400
        );
    }

    $order = array_values(array_filter(array_map('absint', $raw_order)));
    if (empty($order)) {
        wp_send_json_error(
            array('message' => __('No se recibieron items.', 'sitio-cero')),
            400
        );
    }

    $position = 0;
    foreach ($order as $post_id) {
        if ('lamina_hero' !== get_post_type($post_id)) {
            continue;
        }
        if (!current_user_can('edit_post', $post_id)) {
            continue;
        }

        wp_update_post(
            array(
                'ID'         => $post_id,
                'menu_order' => $position,
            )
        );
        $position++;
    }

    wp_send_json_success(
        array(
            'updated' => $position,
        )
    );
}
add_action('wp_ajax_sitio_cero_sort_lamina_items', 'sitio_cero_ajax_sort_lamina_items');

function sitio_cero_enqueue_direccion_custom_css()
{
    if (!is_singular('direccion_municipal')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $custom_css = get_post_meta($post_id, 'sitio_cero_direccion_custom_css', true);
    if (!is_string($custom_css) || '' === trim($custom_css)) {
        return;
    }

    $clean_css = function_exists('sitio_cero_sanitize_tramite_custom_css')
        ? sitio_cero_sanitize_tramite_custom_css($custom_css)
        : trim((string) wp_kses((string) $custom_css, array()));

    if ('' === $clean_css) {
        return;
    }

    $selector = '#direccion-municipal-' . $post_id;
    $custom_css_output = false !== strpos($clean_css, '{{selector}}')
        ? str_replace('{{selector}}', $selector, $clean_css)
        : $selector . ' { ' . $clean_css . ' }';

    wp_add_inline_style('sitio-cero-main', $custom_css_output);
}
add_action('wp_enqueue_scripts', 'sitio_cero_enqueue_direccion_custom_css', 20);

function sitio_cero_get_material_symbols_stylesheet_url()
{
    return 'https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@20..48,400,0,0';
}

function sitio_cero_admin_assets($hook_suffix)
{
    if ('nav-menus.php' !== $hook_suffix) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'sitio-cero-material-symbols',
        sitio_cero_get_material_symbols_stylesheet_url(),
        array(),
        null
    );

    wp_enqueue_style(
        'sitio-cero-admin-menu-icons',
        get_template_directory_uri() . '/assets/css/admin-menu-icons.css',
        array('sitio-cero-material-symbols'),
        $version
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_admin_assets');

function sitio_cero_enqueue_aviso_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'aviso' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_style(
        'sitio-cero-admin-aviso-files',
        get_template_directory_uri() . '/assets/css/admin-aviso-files.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-aviso-files',
        get_template_directory_uri() . '/assets/js/admin-aviso-files.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-aviso-files',
        'sitioCeroAvisoFiles',
        array(
            'frameTitle'  => __('Selecciona archivos', 'sitio-cero'),
            'frameButton' => __('Agregar archivos', 'sitio-cero'),
            'addItemText' => __('Agregar item manual', 'sitio-cero'),
            'removeText'  => __('Quitar', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_aviso_admin_assets');

function sitio_cero_enqueue_aviso_grilla_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'aviso_grilla' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_script(
        'sitio-cero-admin-aviso-grilla',
        get_template_directory_uri() . '/assets/js/admin-aviso-grilla.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-aviso-grilla',
        'sitioCeroAvisoGrilla',
        array(
            'frameTitle'  => __('Selecciona una imagen', 'sitio-cero'),
            'frameButton' => __('Usar imagen', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_aviso_grilla_admin_assets');

function sitio_cero_enqueue_municipalidad_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'municipalidad' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_script(
        'sitio-cero-admin-aviso-grilla',
        get_template_directory_uri() . '/assets/js/admin-aviso-grilla.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-aviso-grilla',
        'sitioCeroAvisoGrilla',
        array(
            'frameTitle'  => __('Selecciona una imagen horizontal', 'sitio-cero'),
            'frameButton' => __('Usar imagen', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_municipalidad_admin_assets');

function sitio_cero_enqueue_noticia_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'noticia' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_style(
        'sitio-cero-admin-noticia-gallery',
        get_template_directory_uri() . '/assets/css/admin-noticia-gallery.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-noticia-gallery',
        get_template_directory_uri() . '/assets/js/admin-noticia-gallery.js',
        array('jquery', 'jquery-ui-sortable'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-noticia-gallery',
        'sitioCeroNoticiaGallery',
        array(
            'frameTitle'  => __('Selecciona imágenes para la galeria', 'sitio-cero'),
            'frameButton' => __('Usar imágenes', 'sitio-cero'),
            'emptyText'   => __('No hay imágenes seleccionadas. Haz clic en "Seleccionar imágenes".', 'sitio-cero'),
            'removeText'  => __('Quitar', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_noticia_admin_assets');

function sitio_cero_enqueue_direccion_municipal_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'direccion_municipal' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_style(
        'sitio-cero-admin-aviso-files',
        get_template_directory_uri() . '/assets/css/admin-aviso-files.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-aviso-files',
        get_template_directory_uri() . '/assets/js/admin-aviso-files.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-aviso-files',
        'sitioCeroAvisoFiles',
        array(
            'frameTitle'  => __('Selecciona archivos', 'sitio-cero'),
            'frameButton' => __('Agregar archivos', 'sitio-cero'),
            'addItemText' => __('Agregar item manual', 'sitio-cero'),
            'removeText'  => __('Quitar', 'sitio-cero'),
        )
    );

    wp_enqueue_style(
        'sitio-cero-admin-direccion-municipal',
        get_template_directory_uri() . '/assets/css/admin-direccion-municipal.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-direccion-municipal',
        get_template_directory_uri() . '/assets/js/admin-direccion-municipal.js',
        array('jquery'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-direccion-municipal',
        'sitioCeroDireccionMunicipal',
        array(
            'phoneLabel'             => __('Telefono', 'sitio-cero'),
            'resourceBlockLabel'     => __('Bloque', 'sitio-cero'),
            'sectionLabel'           => __('Sección', 'sitio-cero'),
            'removeText'             => __('Quitar', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_direccion_municipal_admin_assets');

function sitio_cero_sanitize_attachment_id($value)
{
    return absint($value);
}

function sitio_cero_sanitize_logo_height($value)
{
    $value = absint($value);
    if ($value < 36) {
        return 36;
    }
    if ($value > 280) {
        return 280;
    }

    return $value;
}

function sitio_cero_get_brand_logo_data()
{
    $fallback = array(
        'url'    => get_template_directory_uri() . '/assets/images/logo-concepcion-2025.png',
        'width'  => 1241,
        'height' => 739,
    );

    $logo_id = absint(get_theme_mod('sitio_cero_brand_logo_id', 0));
    if ($logo_id > 0) {
        $logo_src = wp_get_attachment_image_src($logo_id, 'full');
        if (is_array($logo_src) && !empty($logo_src[0])) {
            $width = !empty($logo_src[1]) ? (int) $logo_src[1] : $fallback['width'];
            $height = !empty($logo_src[2]) ? (int) $logo_src[2] : $fallback['height'];
            return array(
                'url'    => $logo_src[0],
                'width'  => $width,
                'height' => $height,
            );
        }
    }

    $custom_logo_id = absint(get_theme_mod('custom_logo', 0));
    if ($custom_logo_id > 0) {
        $logo_src = wp_get_attachment_image_src($custom_logo_id, 'full');
        if (is_array($logo_src) && !empty($logo_src[0])) {
            $width = !empty($logo_src[1]) ? (int) $logo_src[1] : $fallback['width'];
            $height = !empty($logo_src[2]) ? (int) $logo_src[2] : $fallback['height'];
            return array(
                'url'    => $logo_src[0],
                'width'  => $width,
                'height' => $height,
            );
        }
    }

    return $fallback;
}

function sitio_cero_customize_register($wp_customize)
{
    $wp_customize->add_section(
        'sitio_cero_header_brand',
        array(
            'title'       => __('Cabecera: Logo superior', 'sitio-cero'),
            'priority'    => 35,
            'description' => __('Configura el archivo y tamaño del logo ubicado entre el topbar y el menu principal.', 'sitio-cero'),
        )
    );

    $wp_customize->add_setting(
        'sitio_cero_brand_logo_id',
        array(
            'default'           => 0,
            'sanitize_callback' => 'sitio_cero_sanitize_attachment_id',
        )
    );

    $wp_customize->add_control(
        new WP_Customize_Media_Control(
            $wp_customize,
            'sitio_cero_brand_logo_id',
            array(
                'label'       => __('Archivo de logo', 'sitio-cero'),
                'section'     => 'sitio_cero_header_brand',
                'mime_type'   => 'image',
                'description' => __('Si no seleccionas uno, se usara el logo de Sitio (Identidad del sitio) o el logo por defecto del tema.', 'sitio-cero'),
            )
        )
    );

    $wp_customize->add_setting(
        'sitio_cero_brand_logo_height_desktop',
        array(
            'default'           => 102,
            'sanitize_callback' => 'sitio_cero_sanitize_logo_height',
        )
    );

    $wp_customize->add_control(
        'sitio_cero_brand_logo_height_desktop',
        array(
            'label'       => __('Altura logo desktop (px)', 'sitio-cero'),
            'section'     => 'sitio_cero_header_brand',
            'type'        => 'range',
            'input_attrs' => array(
                'min'  => 36,
                'max'  => 280,
                'step' => 1,
            ),
        )
    );

    $wp_customize->add_setting(
        'sitio_cero_brand_logo_height_mobile',
        array(
            'default'           => 72,
            'sanitize_callback' => 'sitio_cero_sanitize_logo_height',
        )
    );

    $wp_customize->add_control(
        'sitio_cero_brand_logo_height_mobile',
        array(
            'label'       => __('Altura logo movil (px)', 'sitio-cero'),
            'section'     => 'sitio_cero_header_brand',
            'type'        => 'range',
            'input_attrs' => array(
                'min'  => 36,
                'max'  => 220,
                'step' => 1,
            ),
        )
    );
}
add_action('customize_register', 'sitio_cero_customize_register');

function sitio_cero_menu_fallback()
{
    $home = esc_url(home_url('/'));
    $direcciones_url = get_post_type_archive_link('direccion_municipal');
    if (!is_string($direcciones_url) || '' === trim($direcciones_url)) {
        $direcciones_url = home_url('/?post_type=direccion_municipal');
    }
    $direcciones_url = esc_url($direcciones_url);
    $direcciones_label = sitio_cero_get_direccion_menu_label();
    $direcciones_posts = sitio_cero_get_direccion_menu_posts();

    echo '<ul class="site-nav__list">';
    echo '<li><a href="' . $home . '">' . esc_html__('Inicio', 'sitio-cero') . '</a></li>';
    echo '<li><a href="' . $home . '#avisos">' . esc_html__('Avisos', 'sitio-cero') . '</a></li>';
    echo '<li><a href="' . $home . '#noticias">' . esc_html__('Noticias', 'sitio-cero') . '</a></li>';

    if (!empty($direcciones_posts)) {
        echo '<li class="menu-item-has-children">';
        echo '<a href="' . $direcciones_url . '">' . esc_html($direcciones_label) . '</a>';
        echo '<ul class="sub-menu">';
        foreach ($direcciones_posts as $direccion_post) {
            if (!$direccion_post instanceof WP_Post) {
                continue;
            }

            $direccion_title = sanitize_text_field((string) $direccion_post->post_title);
            $direccion_url = get_permalink((int) $direccion_post->ID);
            if (!is_string($direccion_url) || '' === trim($direccion_url)) {
                continue;
            }

            echo '<li><a href="' . esc_url($direccion_url) . '">' . esc_html($direccion_title) . '</a></li>';
        }
        echo '</ul>';
        echo '</li>';
    } else {
        echo '<li><a href="' . $direcciones_url . '">' . esc_html($direcciones_label) . '</a></li>';
    }

    echo '<li><a href="' . $home . '#agenda">' . esc_html__('Agenda', 'sitio-cero') . '</a></li>';
    echo '</ul>';
}

function sitio_cero_get_direccion_menu_label()
{
    $post_type = get_post_type_object('direccion_municipal');
    if ($post_type && isset($post_type->labels->name) && is_string($post_type->labels->name) && '' !== trim($post_type->labels->name)) {
        return $post_type->labels->name;
    }

    return __('Direcciones municipales', 'sitio-cero');
}

function sitio_cero_get_direccion_menu_posts()
{
    if (!post_type_exists('direccion_municipal')) {
        return array();
    }

    $posts = get_posts(
        array(
            'post_type'      => 'direccion_municipal',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'title'      => 'ASC',
                'date'       => 'ASC',
            ),
            'no_found_rows'  => true,
        )
    );

    return is_array($posts) ? $posts : array();
}

function sitio_cero_is_direcciones_menu_item($menu_item)
{
    if (!$menu_item instanceof WP_Post) {
        return false;
    }

    $item_type = isset($menu_item->type) ? (string) $menu_item->type : '';
    $item_object = isset($menu_item->object) ? (string) $menu_item->object : '';
    if (
        ('post_type_archive' === $item_type || 'post_type' === $item_type)
        && 'direccion_municipal' === $item_object
    ) {
        return true;
    }

    $item_url = isset($menu_item->url) ? strtolower((string) $menu_item->url) : '';
    if (
        '' !== $item_url
        && (
            false !== strpos($item_url, 'post_type=direccion_municipal')
            || false !== strpos($item_url, '/direcciones-municipales')
        )
    ) {
        return true;
    }

    return false;
}

function sitio_cero_get_default_primary_menu_items()
{
    return array(
        array(
            'title' => __('Inicio', 'sitio-cero'),
            'url'   => home_url('/'),
        ),
        array(
            'title' => __('Avisos', 'sitio-cero'),
            'url'   => home_url('/#avisos'),
        ),
        array(
            'title' => __('Noticias', 'sitio-cero'),
            'url'   => home_url('/#noticias'),
        ),
        array(
            'title' => sitio_cero_get_direccion_menu_label(),
            'url'   => home_url('/?post_type=direccion_municipal'),
        ),
        array(
            'title' => __('Agenda', 'sitio-cero'),
            'url'   => home_url('/#agenda'),
        ),
    );
}

function sitio_cero_seed_primary_menu_once()
{
    $seed_option = 'sitio_cero_primary_menu_seeded';
    if ('1' === (string) get_option($seed_option, '0')) {
        return;
    }

    if (!function_exists('has_nav_menu') || !function_exists('wp_create_nav_menu')) {
        return;
    }

    if (has_nav_menu('primary')) {
        update_option($seed_option, '1');
        return;
    }

    $menu_name = __('Menu principal', 'sitio-cero');
    $menu_object = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu_object && isset($menu_object->term_id) ? (int) $menu_object->term_id : 0;

    if ($menu_id <= 0) {
        $created_menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($created_menu_id) || (int) $created_menu_id <= 0) {
            return;
        }
        $menu_id = (int) $created_menu_id;
    }

    $existing_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (empty($existing_items)) {
        $default_items = sitio_cero_get_default_primary_menu_items();
        foreach ($default_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
            $item_url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ('' === $item_title || '' === $item_url) {
                continue;
            }

            wp_update_nav_menu_item(
                $menu_id,
                0,
                array(
                    'menu-item-title'  => $item_title,
                    'menu-item-type'   => 'custom',
                    'menu-item-url'    => $item_url,
                    'menu-item-status' => 'publish',
                )
            );
        }
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = array();
    }
    $locations['primary'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    update_option($seed_option, '1');
}
add_action('init', 'sitio_cero_seed_primary_menu_once', 21);

function sitio_cero_seed_primary_direcciones_mega_once()
{
    $seed_option = 'sitio_cero_primary_menu_direcciones_mega_seeded';
    $seed_version = '2';
    if ($seed_version === (string) get_option($seed_option, '')) {
        return;
    }

    if (!post_type_exists('direccion_municipal')) {
        return;
    }

    $locations = get_theme_mod('nav_menu_locations');
    $menu_id = (is_array($locations) && isset($locations['primary'])) ? absint($locations['primary']) : 0;
    if ($menu_id <= 0) {
        return;
    }

    $menu_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (!is_array($menu_items)) {
        $menu_items = array();
    }

    $direcciones_parent_id = 0;
    foreach ($menu_items as $menu_item) {
        if (!$menu_item instanceof WP_Post) {
            continue;
        }

        if ((int) $menu_item->menu_item_parent !== 0) {
            continue;
        }

        if (sitio_cero_is_direcciones_menu_item($menu_item)) {
            $direcciones_parent_id = (int) $menu_item->ID;
            break;
        }
    }

    $direcciones_archive_url = get_post_type_archive_link('direccion_municipal');
    if (!is_string($direcciones_archive_url) || '' === trim($direcciones_archive_url)) {
        $direcciones_archive_url = home_url('/?post_type=direccion_municipal');
    }

    if ($direcciones_parent_id <= 0) {
        $parent_result = wp_update_nav_menu_item(
            $menu_id,
            0,
            array(
                'menu-item-title'  => sitio_cero_get_direccion_menu_label(),
                'menu-item-type'   => 'custom',
                'menu-item-url'    => esc_url_raw($direcciones_archive_url),
                'menu-item-status' => 'publish',
            )
        );
        if (is_wp_error($parent_result) || (int) $parent_result <= 0) {
            return;
        }
        $direcciones_parent_id = (int) $parent_result;
    }

    $menu_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (!is_array($menu_items)) {
        $menu_items = array();
    }

    $existing_child_object_ids = array();
    foreach ($menu_items as $menu_item) {
        if (
            !$menu_item instanceof WP_Post
            || (int) $menu_item->menu_item_parent !== $direcciones_parent_id
        ) {
            continue;
        }

        if ('post_type' === (string) $menu_item->type && 'direccion_municipal' === (string) $menu_item->object) {
            $existing_child_object_ids[(int) $menu_item->object_id] = (int) $menu_item->ID;
        }
    }

    $direcciones_posts = sitio_cero_get_direccion_menu_posts();
    foreach ($direcciones_posts as $direccion_post) {
        if (!$direccion_post instanceof WP_Post) {
            continue;
        }

        $direccion_id = (int) $direccion_post->ID;
        if ($direccion_id <= 0) {
            continue;
        }

        if (isset($existing_child_object_ids[$direccion_id])) {
            $existing_child_menu_id = (int) $existing_child_object_ids[$direccion_id];
            if ('' === trim((string) get_post_meta($existing_child_menu_id, '_sitio_cero_menu_icon', true))) {
                update_post_meta($existing_child_menu_id, '_sitio_cero_menu_icon', 'google:apartment');
            }
            continue;
        }

        $child_result = wp_update_nav_menu_item(
            $menu_id,
            0,
            array(
                'menu-item-title'     => sanitize_text_field((string) $direccion_post->post_title),
                'menu-item-object-id' => $direccion_id,
                'menu-item-object'    => 'direccion_municipal',
                'menu-item-parent-id' => $direcciones_parent_id,
                'menu-item-type'      => 'post_type',
                'menu-item-status'    => 'publish',
            )
        );

        if (!is_wp_error($child_result) && (int) $child_result > 0) {
            update_post_meta((int) $child_result, '_sitio_cero_menu_icon', 'google:apartment');
        }
    }

    update_option($seed_option, $seed_version);
}
add_action('init', 'sitio_cero_seed_primary_direcciones_mega_once', 70);

function sitio_cero_get_temas_ciudadanos_default_items()
{
    return array(
        array(
            'label' => __('Aseo y ornato', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Seguridad pública', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Tránsito y movilidad', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Cultura y deporte', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Salud comunal', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Educación municipal', 'sitio-cero'),
            'url'   => '#',
        ),
    );
}

function sitio_cero_temas_ciudadanos_menu_fallback()
{
    $default_items = sitio_cero_get_temas_ciudadanos_default_items();

    echo '<ul id="menu-temas-ciudadanos" class="topic-grid">';
    foreach ($default_items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $item_label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
        $item_url = isset($item['url']) ? esc_url((string) $item['url']) : '#';
        if ('' === $item_label) {
            continue;
        }

        echo '<li class="menu-item">';
        echo '<a href="' . $item_url . '">' . esc_html($item_label) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

function sitio_cero_seed_temas_ciudadanos_menu_once()
{
    $seed_option = 'sitio_cero_temas_ciudadanos_menu_seeded';
    if ('1' === (string) get_option($seed_option, '0')) {
        return;
    }

    if (!function_exists('has_nav_menu') || !function_exists('wp_create_nav_menu')) {
        return;
    }

    if (has_nav_menu('temas_ciudadanos')) {
        update_option($seed_option, '1');
        return;
    }

    $menu_name = __('Temas ciudadanos', 'sitio-cero');
    $menu_object = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu_object && isset($menu_object->term_id) ? (int) $menu_object->term_id : 0;

    if ($menu_id <= 0) {
        $created_menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($created_menu_id) || (int) $created_menu_id <= 0) {
            return;
        }
        $menu_id = (int) $created_menu_id;
    }

    $existing_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (empty($existing_items)) {
        $default_items = sitio_cero_get_temas_ciudadanos_default_items();
        foreach ($default_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $item_url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ('' === $item_label || '' === $item_url) {
                continue;
            }

            wp_update_nav_menu_item(
                $menu_id,
                0,
                array(
                    'menu-item-title'  => $item_label,
                    'menu-item-type'   => 'custom',
                    'menu-item-url'    => $item_url,
                    'menu-item-status' => 'publish',
                )
            );
        }
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = array();
    }
    $locations['temas_ciudadanos'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    update_option($seed_option, '1');
}
add_action('init', 'sitio_cero_seed_temas_ciudadanos_menu_once', 22);

function sitio_cero_register_footer_columna_post_type()
{
    $labels = array(
        'name'               => __('Columnas footer', 'sitio-cero'),
        'singular_name'      => __('Columna footer', 'sitio-cero'),
        'menu_name'          => __('Footer', 'sitio-cero'),
        'name_admin_bar'     => __('Columna footer', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar columna footer', 'sitio-cero'),
        'new_item'           => __('Nueva columna footer', 'sitio-cero'),
        'edit_item'          => __('Editar columna footer', 'sitio-cero'),
        'view_item'          => __('Ver columna footer', 'sitio-cero'),
        'all_items'          => __('Todas las columnas', 'sitio-cero'),
        'search_items'       => __('Buscar columnas', 'sitio-cero'),
        'not_found'          => __('No se encontraron columnas.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay columnas en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'footer_columna',
        array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => true,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-columns',
            'supports'           => array('title', 'editor', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_footer_columna_post_type');

function sitio_cero_get_default_footer_columns()
{
    return array(
        array(
            'title'   => __('La municipalidad', 'sitio-cero'),
            'content' => '<ul><li><a href="#">Alcaldía</a></li><li><a href="#">Concejo municipal</a></li><li><a href="#">Direcciones</a></li><li><a href="#">Cuenta pública</a></li></ul>',
        ),
        array(
            'title'   => __('Servicios', 'sitio-cero'),
            'content' => '<ul><li><a href="#avisos">Avisos</a></li><li><a href="#">Patentes</a></li><li><a href="#">Permisos de circulación</a></li><li><a href="#">Pagos municipales</a></li></ul>',
        ),
        array(
            'title'   => __('Transparencia', 'sitio-cero'),
            'content' => '<ul><li><a href="#">Ley de transparencia</a></li><li><a href="#">Compras públicas</a></li><li><a href="#">Datos abiertos</a></li><li><a href="#">Solicitudes de información</a></li></ul>',
        ),
        array(
            'title'   => __('Contacto', 'sitio-cero'),
            'content' => '<ul><li>Av. Principal 100, Concepción</li><li>+56 2 3386 8000</li><li>contacto@municipio.cl</li><li>Lun a Vie: 08:30 - 14:00</li></ul>',
        ),
    );
}

function sitio_cero_get_footer_columns($limit = 4)
{
    $limit = absint($limit);
    if ($limit <= 0) {
        return array();
    }

    $posts = get_posts(
        array(
            'post_type'      => 'footer_columna',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'date'       => 'ASC',
                'ID'         => 'ASC',
            ),
            'no_found_rows'  => true,
        )
    );

    $columns = array();

    if (is_array($posts)) {
        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                continue;
            }

            $title = sanitize_text_field((string) $post->post_title);
            $raw_content = is_string($post->post_content) ? $post->post_content : '';

            $columns[] = array(
                'title'   => $title,
                'content' => apply_filters('the_content', $raw_content),
            );
        }
    }

    if (empty($columns)) {
        $defaults = sitio_cero_get_default_footer_columns();
        foreach ($defaults as $item) {
            if (count($columns) >= $limit) {
                break;
            }

            $columns[] = array(
                'title'   => isset($item['title']) ? sanitize_text_field((string) $item['title']) : '',
                'content' => isset($item['content']) ? (string) $item['content'] : '',
            );
        }
    }

    return array_slice($columns, 0, $limit);
}

function sitio_cero_seed_default_footer_columns()
{
    if (!post_type_exists('footer_columna')) {
        return;
    }

    $seed_version = '3';
    $already_seeded_version = (string) get_option('sitio_cero_default_footer_columns_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $existing_items = get_posts(
        array(
            'post_type'      => 'footer_columna',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing_items)) {
        update_option('sitio_cero_default_footer_columns_seeded_version', $seed_version);
        return;
    }

    $defaults = sitio_cero_get_default_footer_columns();

    foreach ($defaults as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
        $content = isset($item['content']) ? (string) $item['content'] : '';
        if ('' === $title && '' === trim(wp_strip_all_tags($content))) {
            continue;
        }

        $post_id = wp_insert_post(
            array(
                'post_type'    => 'footer_columna',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $content,
                'menu_order'   => (int) $index,
            ),
            true
        );

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta($post_id, '_sitio_cero_footer_columna_default', '1');
    }

    update_option('sitio_cero_default_footer_columns_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_footer_columns', 45);

function sitio_cero_register_direccion_municipal_post_type()
{
    $labels = array(
        'name'               => __('Direcciones municipales', 'sitio-cero'),
        'singular_name'      => __('Dirección municipal', 'sitio-cero'),
        'menu_name'          => __('Direcciones', 'sitio-cero'),
        'name_admin_bar'     => __('Dirección municipal', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar dirección municipal', 'sitio-cero'),
        'new_item'           => __('Nueva dirección municipal', 'sitio-cero'),
        'edit_item'          => __('Editar dirección municipal', 'sitio-cero'),
        'view_item'          => __('Ver dirección municipal', 'sitio-cero'),
        'all_items'          => __('Todas las direcciones', 'sitio-cero'),
        'search_items'       => __('Buscar direcciones', 'sitio-cero'),
        'not_found'          => __('No se encontraron direcciones.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay direcciones en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'direccion_municipal',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'has_archive'       => true,
            'rewrite'           => array('slug' => 'direcciones-municipales'),
            'menu_position'     => 24,
            'menu_icon'         => 'dashicons-building',
            'supports'          => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions'),
        )
    );
}
add_action('init', 'sitio_cero_register_direccion_municipal_post_type');

function sitio_cero_register_municipalidad_post_type()
{
    $labels = array(
        'name'               => __('Municipalidad', 'sitio-cero'),
        'singular_name'      => __('Página municipalidad', 'sitio-cero'),
        'menu_name'          => __('Municipalidad', 'sitio-cero'),
        'name_admin_bar'     => __('Página municipalidad', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar página municipalidad', 'sitio-cero'),
        'new_item'           => __('Nueva página municipalidad', 'sitio-cero'),
        'edit_item'          => __('Editar página municipalidad', 'sitio-cero'),
        'view_item'          => __('Ver página municipalidad', 'sitio-cero'),
        'all_items'          => __('Páginas municipalidad', 'sitio-cero'),
        'search_items'       => __('Buscar páginas municipalidad', 'sitio-cero'),
        'not_found'          => __('No se encontraron páginas municipalidad.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay páginas municipalidad en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'municipalidad',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'has_archive'       => true,
            'rewrite'           => array('slug' => 'municipalidad'),
            'menu_position'     => 25,
            'menu_icon'         => 'dashicons-admin-site-alt3',
            'supports'          => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions'),
        )
    );
}
add_action('init', 'sitio_cero_register_municipalidad_post_type');

function sitio_cero_register_participacion_ciudadana_post_type()
{
    $labels = array(
        'name'               => __('Participación C', 'sitio-cero'),
        'singular_name'      => __('Participación C', 'sitio-cero'),
        'menu_name'          => __('Participación C', 'sitio-cero'),
        'name_admin_bar'     => __('Participación C', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar participación C', 'sitio-cero'),
        'new_item'           => __('Nueva participación C', 'sitio-cero'),
        'edit_item'          => __('Editar participación C', 'sitio-cero'),
        'view_item'          => __('Ver participación C', 'sitio-cero'),
        'all_items'          => __('Participación C', 'sitio-cero'),
        'search_items'       => __('Buscar participación C', 'sitio-cero'),
        'not_found'          => __('No se encontraron registros.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay registros en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'participacion_c',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => false,
            'has_archive'       => false,
            'rewrite'           => array('slug' => 'participacion-ciudadana'),
            'menu_position'     => 26,
            'menu_icon'         => 'dashicons-groups',
            'supports'          => array('title', 'editor', 'revisions', 'thumbnail'),
        )
    );
}
add_action('init', 'sitio_cero_register_participacion_ciudadana_post_type');

function sitio_cero_get_participacion_ciudadana_content()
{
    $content_file = get_template_directory() . '/template-parts/participacion-ciudadana-content.html';
    if (!file_exists($content_file)) {
        return '';
    }

    $content = file_get_contents($content_file);
    if (!is_string($content)) {
        return '';
    }

    return $content;
}

function sitio_cero_seed_default_participacion_ciudadana()
{
    if (!post_type_exists('participacion_c')) {
        return;
    }

    $seed_version = '2';
    $seed_option = 'sitio_cero_default_participacion_ciudadana_seeded_version';
    if ($seed_version === (string) get_option($seed_option, '')) {
        return;
    }

    $existing_items = get_posts(
        array(
            'post_type'      => 'participacion_c',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing_items)) {
        $post_id = (int) $existing_items[0];
        if ($post_id > 0 && !sitio_cero_participacion_ciudadana_has_meta($post_id)) {
            sitio_cero_set_participacion_ciudadana_defaults($post_id);
        }
        update_option($seed_option, $seed_version);
        return;
    }

    $post_id = wp_insert_post(
        array(
            'post_type'    => 'participacion_c',
            'post_status'  => 'publish',
            'post_title'   => __('Participacion C', 'sitio-cero'),
            'post_name'    => 'participacion-ciudadana',
            'post_content' => '',
        ),
        true
    );

    if (is_wp_error($post_id) || !$post_id) {
        return;
    }

    sitio_cero_set_participacion_ciudadana_defaults((int) $post_id);
    update_option($seed_option, $seed_version);
}
add_action('init', 'sitio_cero_seed_default_participacion_ciudadana', 49);

function sitio_cero_migrate_participacion_ciudadana_post_type()
{
    $migrate_version = '1';
    $migrate_option = 'sitio_cero_participacion_ciudadana_post_type_migrated';
    if ($migrate_version === (string) get_option($migrate_option, '')) {
        return;
    }

    global $wpdb;
    if (!isset($wpdb->posts)) {
        return;
    }

    $updated = $wpdb->update(
        $wpdb->posts,
        array('post_type' => 'participacion_c'),
        array('post_type' => 'participacion_ciudadana'),
        array('%s'),
        array('%s')
    );

    if (false === $updated) {
        return;
    }

    update_option($migrate_option, $migrate_version);
}
add_action('init', 'sitio_cero_migrate_participacion_ciudadana_post_type', 48);

function sitio_cero_migrate_participacion_ciudadana_title()
{
    if (!post_type_exists('participacion_c')) {
        return;
    }

    $migrate_version = '1';
    $migrate_option = 'sitio_cero_participacion_ciudadana_title_migrated';
    if ($migrate_version === (string) get_option($migrate_option, '')) {
        return;
    }

    $posts = get_posts(
        array(
            'post_type'      => 'participacion_c',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 5,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        )
    );

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $title = (string) $post->post_title;
        $slug = (string) $post->post_name;
        if ('participacion-ciudadana' !== $slug && 'participacion-c' !== $slug) {
            continue;
        }

        if ('Participación Ciudadana' === $title || 'Participacion ciudadana' === $title || 'Participación ciudadana' === $title) {
            wp_update_post(
                array(
                    'ID'         => (int) $post->ID,
                    'post_title' => __('Participacion C', 'sitio-cero'),
                )
            );
        }
    }

    update_option($migrate_option, $migrate_version);
}
add_action('init', 'sitio_cero_migrate_participacion_ciudadana_title', 50);

function sitio_cero_migrate_participacion_ciudadana_slug()
{
    if (!post_type_exists('participacion_c')) {
        return;
    }

    $migrate_version = '2';
    $migrate_option = 'sitio_cero_participacion_ciudadana_slug_migrated';
    if ($migrate_version === (string) get_option($migrate_option, '')) {
        return;
    }

    $posts = get_posts(
        array(
            'post_type'      => 'participacion_c',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 5,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        )
    );

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        if ('participacion-c' !== (string) $post->post_name) {
            continue;
        }

        wp_update_post(
            array(
                'ID'        => (int) $post->ID,
                'post_name' => 'participacion-ciudadana',
            )
        );
    }

    update_option($migrate_option, $migrate_version);
}
add_action('init', 'sitio_cero_migrate_participacion_ciudadana_slug', 51);

function sitio_cero_remove_participacion_ciudadana_editor_support()
{
    if (!post_type_exists('participacion_c')) {
        return;
    }

    remove_post_type_support('participacion_c', 'editor');
}
add_action('init', 'sitio_cero_remove_participacion_ciudadana_editor_support', 52);

function sitio_cero_get_default_participacion_ciudadana_meta()
{
    return array(
        'hero' => array(
            'eyebrow' => 'Municipalidad de Concepción',
            'title'   => 'Participación Ciudadana',
            'lead'    => 'Información, documentos y mecanismos para fortalecer la organización social, la transparencia y la participación activa en la comuna.',
            'actions' => array(
                array(
                    'label'  => 'Proyecto de Habilitación Normativa: Pedro del Río Zañartu',
                    'url'    => 'https://concepcion.cl/proyecto-de-habilitacion-normativa-prz/',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Ordenanza de Participación Ciudadana',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/02/Ordenanza-de-Participación-Ciudadana.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Solicitud Ministro de fe',
                    'url'    => 'https://concepcion.cl/formulario-de-solicitud-de-designacion-de-funcionario-municipal-como-ministro-de-fe/',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Servicios en Línea',
                    'url'    => '#online',
                    'target' => '',
                ),
                array(
                    'label'  => 'ODS y Sociedad Civil',
                    'url'    => 'https://concepcion.cl/ods/',
                    'target' => '_blank',
                ),
            ),
        ),
        'subnav' => array(
            array(
                'label' => 'Audiencias Públicas',
                'url'   => '#audiencias',
            ),
            array(
                'label' => 'Kit Elecciones',
                'url'   => '#kit',
            ),
            array(
                'label' => 'Servicios en línea',
                'url'   => '#online',
            ),
            array(
                'label' => 'Recursos y documentos',
                'url'   => '#recursos',
            ),
            array(
                'label' => 'Mecanismos de participación',
                'url'   => '#mecanismos',
            ),
            array(
                'label' => 'CCOSOC',
                'url'   => '#ccosoc',
            ),
        ),
        'audiencias' => array(
            'kicker' => 'Participación',
            'title'  => 'Audiencias Públicas',
            'body'   => '<p><strong>¿Qué son las Audiencias Públicas?</strong></p>
<p>Las audiencias públicas representarán un medio por el cual el alcalde y el concejo municipal conocerán desde la perspectiva de los propios ciudadanos de las materias que estimen de interés comunal.</p>
<p>Las audiencias públicas podrán ser convocadas por el alcalde o a requerimiento de a lo menos, un tercio del mismo concejo municipal. También podrán ser solicitadas por no menos de cien ciudadanos inscritos en los registros electorales de la comuna.</p>
<p><strong>Nota:</strong> Conoce sobre el procedimiento de solicitud de Audiencia Pública en la Ordenanza de Participación Ciudadana de la comuna de Concepción.</p>',
            'links'  => array(
                array(
                    'label'  => 'ACTA AUDIENCIA PÚBLICA Nº 1',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/01/ACTA-AUDIENCIA-PÚBLICA-Nº-1-.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'ACTA AUDIENCIA PUBLICA Nº 2',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/01/ACTA-AUDIENCIA-PUBLICA-Nº-2-2022.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'ACTA AUDIENCIA PUBLICA Nº 3',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/01/ACTA-AUDIENCIA-PUBLICA-Nº-3-2022.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'ACTA AUDIENCIA PÚBLICA Nº 4',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/01/ACTA-AUDIENCIA-PÚBLICA-Nº-4-2022.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Ordenanza de Participación Ciudadana',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/01/Ordenanza-de-Participación-Ciudadana.pdf',
                    'target' => '_blank',
                ),
            ),
        ),
        'kit' => array(
            'kicker' => 'Descargas',
            'title'  => 'Kit Elecciones',
            'items'  => array(
                array(
                    'label'  => 'Comunicación de directorio',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2024/05/Comunicación-de-Directorio-Ley-21.146.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Guía proceso eleccionario',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2025/04/02-Actualización-de-Directorio-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Funciones comisión electoral',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2022/05/Comisioìn-Electoral-.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Comunicación fecha de la elección',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2023/09/comunicación-fecha-de-elección.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Ley 19.418, sobre juntas de vecinos y demás organizaciones comunitarias',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2021/09/Ley-19.418-ultima-versión.pdf',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Ley 21.146, simplifica el procedimiento de calificación de las elecciones',
                    'url'    => 'https://concepcion.cl/wp-content/uploads/2021/09/Ley-21146_27-FEB-2019-2.pdf',
                    'target' => '_blank',
                ),
            ),
        ),
        'online' => array(
            'kicker' => 'Atención digital',
            'title'  => 'Servicios en línea',
            'items'  => array(
                array(
                    'label'  => 'Registro Público de Organizaciones Sociales (Elecciones, reclamos, fallos TER), Ley 21.146',
                    'url'    => 'https://appl2.smc.cl/orgcomunwebdoc/home/index/concepcion',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Ingreso Digital Oficina Partes',
                    'url'    => 'https://concepcion.cl/formulario-oficina-de-partes/',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Busca tu inscripción en el Registro Civil',
                    'url'    => 'https://www.portaltransparencia.cl/PortalPdT/pdtta/-/ta/AK002/OA/PJSFL',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Sistema de atención al vecino',
                    'url'    => 'http://vecino.concepcion.cl',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Consulta Ingresos Oficina Partes',
                    'url'    => 'http://sgd.smc.cl/mesa/concepcion.aspx',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Formulario Solicitud de copia de estatutos',
                    'url'    => 'https://concepcion.cl/formulario-solicitud-de-estatutos/',
                    'target' => '_blank',
                ),
                array(
                    'label'  => 'Solicitud de designación ministro de fe',
                    'url'    => 'https://concepcion.cl/formulario-de-solicitud-de-designacion-de-funcionario-municipal-como-ministro-de-fe/',
                    'target' => '_blank',
                ),
            ),
        ),
        'recursos' => array(
            'kicker' => 'Biblioteca',
            'title'  => 'Recursos y documentos',
            'items'  => array(
                array(
                    'title'   => 'Kit Digital Dirigente Social',
                    'anchor'  => '',
                    'content' => '<a class="pc-link" href="https://concepcion.cl/wp-content/uploads/2019/09/Kit-Digital.zip" target="_blank" rel="noopener">Descarga aquí tu Kit Digital</a>',
                ),
                array(
                    'title'   => 'Audiencia Pública',
                    'anchor'  => '',
                    'content' => '<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2023/01/Decreto-0008.pdf" target="_blank" rel="noopener">Audiencia n°6</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2023/01/audiencia-5.pdf" target="_blank" rel="noopener">Audiencia n°5</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/09/audiencia-n°4.pdf" target="_blank" rel="noopener">Audiencia n°4</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/09/Dec-949-AUDIENCIA-Nº-1.pdf" target="_blank" rel="noopener">Dec 949 AUDIENCIA Nº 1</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/09/Dec-Nº-632-CONVOCA-AUDIENCIA-PUBLICA-Nº3.pdf" target="_blank" rel="noopener">Dec Nº 632 CONVOCA AUDIENCIA PUBLICA Nº3</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/09/Dec-Nº-845-CONVOCA-A-AUDIENCIA-Nº-4.pdf" target="_blank" rel="noopener">Dec Nº 845 CONVOCA A AUDIENCIA Nº 4</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/09/Dec-Nº442-Audiencia-Pùblica-Nº2.pdf" target="_blank" rel="noopener">Dec Nº442 Audiencia Pública Nº2</a></li>
</ul>',
                ),
                array(
                    'title'   => 'Guía de trámites personalidad jurídica',
                    'anchor'  => '',
                    'content' => '<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/01__Constitución_de_Personas_Jurídicas_Ley_19_418_Juntas_de_Vecinos_y_Demás_Organizaciones_Comunitarias.pdf" target="_blank" rel="noopener">Pasos para constituir juntas de vecinos y organizaciones comunitarias</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/03-Modificación-de-Estatutos-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Modificación Estatuto Ley 19.418 (juntas de vecinos y organizaciones comunitarias)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/06-De-la-Disolución-de-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Disolución de Organización Ley 19.418 (juntas de vecinos y organizaciones comunitarias)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/04-Modificación-de-Directorio-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Modificación del Directorio Ley 19.418 (juntas de vecinos y organizaciones comunitarias)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/05-De-la-Constitución-de-Uniones-Comunales-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Constitución Uniones Comunales Ley 19.418</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/07-De-la-Censura-de-Dirigentes-Sociales-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Censura dirigentes sociales Ley 19.418</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/04/08-Otro-Trámites-Personas-Jurídicas-Ley-19.418-Juntas-de-Vecinos-y-Demás-Organizaciones-Comunitarias.pdf" target="_blank" rel="noopener">Otros trámites Ley 19.418</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/08/Modificación-Estatuto-Corpporación-ONG-Asociación-y-Fundación.pdf" target="_blank" rel="noopener">Modificación Estatuto (Corporación ONG, Asociación y Fundación)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/08/Constitucion-de-Organizaciones-regidas-bajo-el-Codigo-Civil.pdf" target="_blank" rel="noopener">Constitución Asociación, Corporaciones, Fundaciones y ONG</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/08/Tenencia-responsable-de-mascotas-1.pdf" target="_blank" rel="noopener">Registro organizaciones promotoras de tenencia responsable de mascotas (Ley Nº 21020)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/08/TRAMITES-LEY-DE-COPROPIEDAD-21.442.pdf" target="_blank" rel="noopener">Trámites de la Ley de Copropiedad N°21.442</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/08/REGLAMENTO-DE-COPROPIEDAD.pdf" target="_blank" rel="noopener">Reglamento de Copropiedad</a></li>
</ul>
<h3>Formato macro tipo, instructivos de participación ciudadana</h3>
<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2024/12/Instructivo-Comunicación-de-Directorio.-ley-21.146.docx" target="_blank" rel="noopener">Instructivo Comunicación de Directorio Ley 21.146</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/COMUNICACIÓN-DE-COMITÉ-DE-ADMINISTRACION-LEY-DE-COPROPIEDAD-INMOBILIARIA3.docx" target="_blank" rel="noopener">Comunicación de comité de administración Ley de Copropiedad Inmobiliaria</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/DOCUMENTOS-PARA-REGISTRO-PJ-PROMOTORAS-DE-TENENCIA-RESPONSABLE-DE-MASCOTAS2.docx" target="_blank" rel="noopener">Documento para registro PJ promotoras de tenencia responsable de mascotas</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/Instructivo-Constitución-de-Corporaciones-y-Fundaciones1.docx" target="_blank" rel="noopener">Instructivo Constitución de Corporaciones y Fundaciones</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2024/12/Instructivo-Modificacion-Directorio-Corporaciones.docx" target="_blank" rel="noopener">Instructivo Modificación Directorio Corporaciones</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2024/12/Instructivo-Modificacion-Estatutos-Ley-Nº-19418.docx" target="_blank" rel="noopener">Instructivo Modificación Estatutos Ley Nº 19.418</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2024/12/Instructivo-Obtención-Personalidad-Jurídica-OO-CC.docx" target="_blank" rel="noopener">Instructivo Obtención Personalidad Jurídica OO CC</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/Instructivo-Otros-Tramites1.docx" target="_blank" rel="noopener">Instructivo Otros Trámites</a></li>
</ul>',
                ),
                array(
                    'title'   => 'Cartas Ciudadanas',
                    'anchor'  => '',
                    'content' => '<a class="pc-link" href="https://concepcion.cl/wp-content/uploads/2019/09/carta-ciudadanal-laguna-redonda.pdf" target="_blank" rel="noopener">Carta Ciudadana Laguna Redonda</a>',
                ),
                array(
                    'title'   => 'Estatutos Tipo',
                    'anchor'  => '',
                    'content' => '<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/1-ESTATUTOS-JUNTA-DE-VECINOS-.docx" target="_blank" rel="noopener">Estatuto tipo Junta de Vecinos</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/Estatutos_Organización_Comunitaria_3_0.docx" target="_blank" rel="noopener">Estatuto tipo Organización Comunitaria</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/Estatutos_Organización_Comunitaria_CLUB_DE_ADULTO_MAYOR_3_0.docx" target="_blank" rel="noopener">Estatuto tipo Club de Adulto Mayor</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/Estatutos_Organización_Comunitaria_COMITÉ_DE_VIVIENDA_3_0.docx" target="_blank" rel="noopener">Estatuto tipo Comité de Vivienda</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/Estatutos_Organización_Comunitaria_COMITÉ_DE_SEGURIDAD_3_0.docx" target="_blank" rel="noopener">Estatuto tipo Comité de Seguridad</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2025/03/6-Modelo_estatutos_asociacion.doc" target="_blank" rel="noopener">Modelo estatutos asociación (Código Civil)</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2022/06/Modelo_estatutos_centro_de_padres.pdf" target="_blank" rel="noopener">Modelo estatutos Centro de Padres</a></li>
</ul>',
                ),
                array(
                    'title'   => 'Capacitaciones',
                    'anchor'  => '',
                    'content' => '<ul class="pc-list">
<li><a href="https://www.concepcion.cl/infobanner/capacitacion-ley-20-500-sobre-asociaciones-y-participacion-ciudadana-en-la-gestion-publica/" target="_blank" rel="noopener">Capacitación Ley 20.500 sobre asociaciones y participación ciudadana en la gestión pública</a></li>
<li><a href="https://www.concepcion.cl/infobanner/capacitacion-codes-16-05-2018/" target="_blank" rel="noopener">Capacitación CODES (16-05-2018)</a></li>
</ul>',
                ),
                array(
                    'title'   => 'CCOSOC',
                    'anchor'  => 'ccosoc',
                    'content' => '<p><strong>Fallo Tribunal Electoral Regional Elección Consejeros(as) Consejo Comunal de Organizaciones de la Sociedad Civil Comuna de Concepción.</strong></p>
<p><a class="pc-link" href="https://concepcion.cl/wp-content/uploads/2023/01/8_175-2022_Sentencia.pdf" target="_blank" rel="noopener">Documento</a></p>
<h3>Reglamentos</h3>
<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/REGLAMENTO_01-2014.pdf" target="_blank" rel="noopener">Reglamento Comisiones y Sesiones</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/REGLAMENTO_01-2014.pdf" target="_blank" rel="noopener">Reglamento 01-2014</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/REGLAMENTO_01-2014.pdf" target="_blank" rel="noopener">Reglamento CCOSOC 2018</a></li>
</ul>
<h3>Constitución</h3>
<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/DECRETO-Constitución-CCOSOC-2014-2018.pdf" target="_blank" rel="noopener">DECRETO Constitución CCOSOC 2014-2018</a></li>
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/DECRETO-Constitución-CCOSOC-2018-2022.pdf" target="_blank" rel="noopener">DECRETO Constitución CCOSOC 2018-2022</a></li>
</ul>
<h3>Actas</h3>
<h4>Sesiones 2014-2018</h4>
<h5>Sesiones Extraordinarias</h5>
<ul class="pc-list">
<li><a href="http://ventatuning.webkonce.cl/wp-content/uploads/2019/09/actaextra_01-2016.pdf" target="_blank" rel="noopener">actaextra_01-2016</a></li>
<li><a href="http://ventatuning.webkonce.cl/wp-content/uploads/2019/09/actaextra_01-2017.pdf" target="_blank" rel="noopener">actaextra_01-2017</a></li>
<li><a href="http://ventatuning.webkonce.cl/wp-content/uploads/2019/09/actaextra_02-2017.pdf" target="_blank" rel="noopener">actaextra_02-2017</a></li>
</ul>
<h5>Sesiones Ordinarias</h5>
<ul class="pc-list">
<li><a href="https://concepcion.cl/wp-content/uploads/2019/09/Acta-ord.-constitucion-ccosoc.pdf" target="_blank" rel="noopener">Acta ordinaria constitución CCOSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_ccosoc_01-2015.pdf" target="_blank" rel="noopener">Acta CCOSOC 01-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_ccosoc_02-2015.pdf" target="_blank" rel="noopener">Acta CCOSOC 02-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta-ord.-03-2015.pdf" target="_blank" rel="noopener">Acta ordinaria 03-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta-sesi%C3%B3n-ordinaria-n%C2%BA-04-2015.pdf" target="_blank" rel="noopener">Acta sesión ordinaria Nº 04-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta-sesi%C3%B3-n%C2%BA-05-2015.pdf" target="_blank" rel="noopener">Acta sesión Nº 05-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta.ordinaria-n%C2%BA-06-2015.pdf" target="_blank" rel="noopener">Acta ordinaria Nº 06-2015</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_07-2016.pdf" target="_blank" rel="noopener">Acta 07-2016</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_08-2016.pdf" target="_blank" rel="noopener">Acta 08-2016</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_9-2016.pdf" target="_blank" rel="noopener">Acta 9-2016</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta-09-2016.pdf" target="_blank" rel="noopener">Acta 09-2016</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_10-2016.pdf" target="_blank" rel="noopener">Acta 10-2016</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/actaextra_01-20171.pdf" target="_blank" rel="noopener">Acta extra ordinaria 01-2017</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/acta_12-2017.pdf" target="_blank" rel="noopener">Acta 12-2017</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/actaccosoc_13-2017.pdf" target="_blank" rel="noopener">Acta CCOSOC 13-2017</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/actacosoc_14-2017.pdf" target="_blank" rel="noopener">Acta CCOSOC 14-2017</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/actaextracosoc_03-2017.pdf" target="_blank" rel="noopener">Acta extra CCOSOC 03-2017</a></li>
</ul>
<h3>Comisiones</h3>
<h4>CCOSOC 2014-2018</h4>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/COMISIONES-COSOC.pdf" target="_blank" rel="noopener">Comisiones COSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Comisiones-Ccosoc.pdf" target="_blank" rel="noopener">Comisiones CCOSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/FORMATO-DE-ACTAS.pdf" target="_blank" rel="noopener">Formato de actas</a></li>
</ul>
<h3>Documentos</h3>
<h4>Decretos de Convocatoria 2014-2018</h4>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_403-2014-SEGUNDA-CONVOCATORIA-A-ELECCIONES-DE-ORGANIZACIONES-INTERES-PUBLICO-Y-ASOCIACIONES...pdf" target="_blank" rel="noopener">Decreto Alcaldicio 403-2014 Segunda Convocatoria a elecciones de organizaciones interés público y asociaciones</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_183-2014-convocatoria-elecciones-consejo-de-la-sociedad-civil.pdf" target="_blank" rel="noopener">DA 183-2014 Convocatoria elecciones CCOSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_146-2014.pdf" target="_blank" rel="noopener">Decreto Alcaldicio 146-2014</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_26-2014-convocatoria-elecciones.pdf" target="_blank" rel="noopener">Decreto Alcaldicio 26-2014 convocatoria elecciones</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Decreto-385-paro-del-Registro-Civil.pdf" target="_blank" rel="noopener">Decreto 385 paro del Registro-Civil</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Decreto-1era-convocatoria.pdf" target="_blank" rel="noopener">Decreto 1era convocatoria</a></li>
</ul>
<h4>Decreto tablas de materias</h4>
<h5>Sesión Extraordinaria</h5>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_604-2014-CONVOCATORIA-SESI%C3%93N-EXTRAORDINARIA-N%C2%B01.pdf" target="_blank" rel="noopener">DA 604-2014 Convocatoria sesión extraordinaria Nº1</a></li>
</ul>
<h5>Sesión Ordinaria</h5>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/181204160626_0001.pdf" target="_blank" rel="noopener">Decreto de convocatoria sesión Nº1 CCOSOC</a></li>
</ul>
<h4>Elecciones CCOSOC 2018-2022</h4>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/ORG.-RELEVANTES-PARA-EL-DESARROLLO-ECONOMICO-SOCIAL-Y-CULTURAL-DE-LA-CIUDAD.pdf" target="_blank" rel="noopener">Organizaciones relevantes para el desarrollo económico social y cultural de la ciudad</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/DA_1006-2018.pdf" target="_blank" rel="noopener">Decreto Alcaldicio 1006-2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/CRONOGRAMA-ELECCIONES-CCOSOC-2018.pdf" target="_blank" rel="noopener">Cronograma Elecciones CCOSOC 2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LISTADO-FUNCIONALES-CCOSOC-20181.pdf" target="_blank" rel="noopener">Listado Fundido CCOSOC 2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LISTADO-ORG.-INTERES-PUBLICO-CCOSOC-2018.pdf" target="_blank" rel="noopener">Listado Org Interés Público CCOSOC 2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LISTADO-TERRITORIALES-CCOSOC-2018.pdf" target="_blank" rel="noopener">Listado Territoriales CCOSOC 2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LISTADO-ASOC.-SINDICALES-CCOSOC-2018.pdf" target="_blank" rel="noopener">Listado Asociaciones Sindicales CCOSOC 2018</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LISTADO-CCOSOC-2018.xlsx" target="_blank" rel="noopener">Listado CCOSOC 2018</a></li>
</ul>
<h4>Capacitaciones</h4>
<h5>CCOSOC 2014-2018</h5>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Ley-20.500-y-CCOSOSC.pdf" target="_blank" rel="noopener">Ley 20.500 y CCOSOSC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/PROYECTOS-FNDRcosoc.pdf" target="_blank" rel="noopener">Proyectos FNDR CCOSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Reglamento-del-Consejo-Comunal-de-Organizaciones-de-la-Sociedad-Civil.pdf" target="_blank" rel="noopener">Reglamento del CCOSOC</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LEY-N%C2%B0-20.500.pptx" target="_blank" rel="noopener">LEY N° 20.500</a></li>
</ul>
<h5>CCOSOC 2018-2022</h5>
<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/Presentaci%C3%B3n-Reglamento-COSOC.ppt" target="_blank" rel="noopener">Presentación Reglamento COSOC</a></li>
</ul>',
                ),
                array(
                    'title'   => 'Mecanismos de Participación',
                    'anchor'  => 'mecanismos',
                    'content' => '<h3>Consejo de la Sociedad Civil</h3>
<p>Descripción: Será un órgano asesor y colaborador de la municipalidad, el cual tendrá por objetivo asegurar la participación de las organizaciones comunitarias de carácter territorial y funcional y de las organizaciones de interés público de la comuna.</p>
<h4>Requisitos</h4>
<p>a) Tener 18 años de edad, con excepción de los representantes de organizaciones señaladas en la ley Nº 19.418 sobre juntas de vecinos y demás organizaciones comunitarias;</p>
<p>b) Tener un año de afiliación a una organización del estamento, en caso que corresponda, en el momento de la elección;</p>
<p>c) Ser chileno o extranjero avecindado en el país, durante 3 años.</p>
<p>d) No haber sido condenado por delito que merezca pena aflictiva.</p>
<p>Para más información del CCOSOC AQUÍ</p>
<h3>Cabildos Comunales</h3>
<p>Descripción: Instrumento de participación ciudadana, consultiva, convocada por la municipalidad, que tendrá por objeto requerir la opinión de la comunidad en temas de interés local, a los que se podrá invitar a actores relevantes en los temas que se debatirán.</p>
<p>La participación de la comunidad local en los cabildos comunales es voluntaria y sus conclusiones no serán vinculantes para la Municipalidad de Concepción.</p>
<p>Requisitos: Los cabildos comunales serán convocados por el alcalde o por requerimiento de un tercio del concejo municipal. El alcalde iniciará el procedimiento del cabildo, en cualquier época, mediante convocatoria que deberá expedir a lo menos 15 días corridos, antes de la fecha de realización del mismo.</p>
<p>La convocatoria se hará a través de medios escritos de circulación comunal, publicación en la web municipal y/o mediante la disposición de informativos en puntos relevante de la comuna.</p>
<p>Enlace: <a href="https://concepcion.cl/wp-content/uploads/2023/01/Ordenanza-de-Participación-Ciudadana.pdf" target="_blank" rel="noopener">Ordenanza de Participación Ciudadana</a></p>
<h3>Audiencias Públicas</h3>
<p>Descripción: Mecanismo por el cual el alcalde y el concejo municipal conocerán desde la perspectiva de los propios ciudadanos de las materias que estimen de interés comunal.</p>
<p>Las audiencias públicas podrán ser convocadas por el alcalde o a requerimiento de a lo menos, un tercio del mismo concejo municipal.</p>
<p>También podrán ser solicitadas por no menos de cien ciudadanos inscritos en los registros electorales de la comuna.</p>
<p>Requisitos: Para solicitar una audiencia pública se requerirá:</p>
<p>a) Presentar una solicitud escrita identificando, a lo más, a cinco personas que representarán a los requirentes, indicando nombre completo, número de cédula de nacional de identidad, domicilio y firma;</p>
<p>b) Señalar y fundamentar la materia que se someterá a conocimiento del concejo municipal, y</p>
<p>c) Nómina de, a lo menos, cien ciudadanos inscritos en los registros electorales de la comuna, indicando nombre completo, número de cédula de nacional de identidad, domicilio y firma.</p>
<p>Enlace: <a href="https://concepcion.cl/wp-content/uploads/2023/01/Ordenanza-de-Participación-Ciudadana.pdf" target="_blank" rel="noopener">Ordenanza de Participación Ciudadana</a></p>
<h3>Encuestas de opinión, consultas o sondeos</h3>
<p>Descripción: Mecanismo que tiene por objeto explorar y conocer las percepciones, sentimientos y pensamientos de la comunidad local respecto de la gestión municipal y de materias de interés comunal.</p>
<p>No se podrá efectuar ninguna encuesta de opinión, consulta o sondeo dentro de los seis meses anteriores a cualquier elección, ya sea presidencial, parlamentaria o municipal.</p>
<p>Requisitos: La consulta vecinal será convocada, en cualquier época, por la municipalidad. En dicha convocatoria se expresará el objeto de la consulta, así como la fecha y el lugar de su realización por lo menos siete días antes de la fecha establecida.</p>
<p>La convocatoria impresa se colocará en lugares de mayor afluencia y se difundirá en los medios masivos de comunicación local. Las encuestas, consultas o sondeos serán dispuestas mediante un decreto alcaldicio.</p>
<p>Enlace: <a href="https://concepcion.cl/wp-content/uploads/2023/01/Ordenanza-de-Participación-Ciudadana.pdf" target="_blank" rel="noopener">Ordenanza de Participación Ciudadana</a></p>
<h3>Plebiscitos Comunales</h3>
<p>Descripción: Acto mediante el cual se manifiesta la voluntad soberana de la ciudadanía local, mediante su opinión en relación a materias determinadas de interés comunal, que le son consultadas.</p>
<p>Requisitos: Ciudadanos inscritos en los registros electorales de la comuna.</p>
<p>Enlace: <a href="https://concepcion.cl/wp-content/uploads/2023/01/Ordenanza-de-Participación-Ciudadana.pdf" target="_blank" rel="noopener">Ordenanza de Participación Ciudadana</a></p>
<h3>Fondo de Desarrollo Vecinal</h3>
<p>Descripción: Fondo municipal que debe destinarse a brindar apoyo financiero a proyectos específicos de desarrollo comunitario, presentados por las juntas de vecinos y organizaciones funcionales.</p>
<p>Requisitos: Indirecta a través de juntas de vecinos.</p>
<h3>Constitución de Organizaciones Comunitarias como Mecanismos de Participación</h3>
<p>Descripción: Las Organizaciones Comunitarias son entidades de participación de los habitantes de la comuna, a través de ellas, los vecinos pueden hacer llegar a las autoridades distintos proyectos, priorizaciones de intereses comunales, influir en las decisiones de las autoridades, gestionar y/o ejecutar obras y/o proyectos de incidencia en la unidad vecinal o en la comuna entre otras.</p>
<p>Requisitos: La Comunidad local podrá formar, en cualquier época, organizaciones de intereses público, entendiéndose por el solo ministerio de la ley, como este tipo de organizaciones, las de carácter funcional, territorial, e indígenas, asegurándose en general la participación de todo tipo de asociación que tenga por finalidad la agrupación de personas en torno a objetos de intereses común a los asociados, o de afectación de bienes a un fin determinado.</p>
<p>Enlace:</p>
<p><a href="https://concepcion.cl/wp-content/uploads/2021/09/Ley-19.418-ultima-versión.pdf" target="_blank" rel="noopener">Ley 19.418 de Juntas de Vecinos y demás organizaciones Comunitarias</a></p>
<p><a href="https://www.concepcion.cl/wp-content/uploads/2019/02/LEY-N%C2%B0-20.500.pptx" target="_blank" rel="noopener">Ley 20.500 sobre asociaciones y participación ciudadana en la gestión pública</a></p>',
                ),
                array(
                    'title'   => 'Reglamento FONDEVE',
                    'anchor'  => '',
                    'content' => '<ul class="pc-list">
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/04/Reglamento-6-del-7-sept.-2001.-FONDEVE.pdf" target="_blank" rel="noopener">Reglamento Nº 6 – 7 sept. 2001</a></li>
<li><a href="https://www.concepcion.cl/wp-content/uploads/2019/04/Reglamento-6Modificacion-FONDEVE-16-Oct-2009.pdf" target="_blank" rel="noopener">Reglamento Nº 4 Modificación – 16 Oct 2009</a></li>
</ul>',
                ),
                array(
                    'title'   => 'Credencial del Dirigente Social',
                    'anchor'  => '',
                    'content' => '<a class="pc-link" href="https://www.concepcion.cl/wp-content/uploads/2019/05/convenio-dirigente-nov.2018.pdf" target="_blank" rel="noopener">Convenio</a>',
                ),
            ),
        ),
    );
}

function sitio_cero_participacion_ciudadana_has_meta($post_id)
{
    $keys = array(
        'sitio_cero_pc_hero_title',
        'sitio_cero_pc_hero_actions',
        'sitio_cero_pc_subnav_items',
        'sitio_cero_pc_audiencias_title',
        'sitio_cero_pc_audiencias_links',
        'sitio_cero_pc_kit_title',
        'sitio_cero_pc_kit_items',
        'sitio_cero_pc_online_title',
        'sitio_cero_pc_online_items',
        'sitio_cero_pc_recursos_title',
        'sitio_cero_pc_recursos_items',
    );

    foreach ($keys as $key) {
        if (metadata_exists('post', $post_id, $key)) {
            return true;
        }
    }

    return false;
}

function sitio_cero_set_participacion_ciudadana_defaults($post_id)
{
    $defaults = sitio_cero_get_default_participacion_ciudadana_meta();

    update_post_meta($post_id, 'sitio_cero_pc_hero_eyebrow', $defaults['hero']['eyebrow']);
    update_post_meta($post_id, 'sitio_cero_pc_hero_title', $defaults['hero']['title']);
    update_post_meta($post_id, 'sitio_cero_pc_hero_lead', $defaults['hero']['lead']);
    update_post_meta($post_id, 'sitio_cero_pc_hero_actions', $defaults['hero']['actions']);

    update_post_meta($post_id, 'sitio_cero_pc_subnav_items', $defaults['subnav']);

    update_post_meta($post_id, 'sitio_cero_pc_audiencias_kicker', $defaults['audiencias']['kicker']);
    update_post_meta($post_id, 'sitio_cero_pc_audiencias_title', $defaults['audiencias']['title']);
    update_post_meta($post_id, 'sitio_cero_pc_audiencias_body', $defaults['audiencias']['body']);
    update_post_meta($post_id, 'sitio_cero_pc_audiencias_links', $defaults['audiencias']['links']);

    update_post_meta($post_id, 'sitio_cero_pc_kit_kicker', $defaults['kit']['kicker']);
    update_post_meta($post_id, 'sitio_cero_pc_kit_title', $defaults['kit']['title']);
    update_post_meta($post_id, 'sitio_cero_pc_kit_items', $defaults['kit']['items']);

    update_post_meta($post_id, 'sitio_cero_pc_online_kicker', $defaults['online']['kicker']);
    update_post_meta($post_id, 'sitio_cero_pc_online_title', $defaults['online']['title']);
    update_post_meta($post_id, 'sitio_cero_pc_online_items', $defaults['online']['items']);

    update_post_meta($post_id, 'sitio_cero_pc_recursos_kicker', $defaults['recursos']['kicker']);
    update_post_meta($post_id, 'sitio_cero_pc_recursos_title', $defaults['recursos']['title']);
    update_post_meta($post_id, 'sitio_cero_pc_recursos_items', $defaults['recursos']['items']);
}

function sitio_cero_get_participacion_ciudadana_meta($post_id)
{
    $defaults = sitio_cero_get_default_participacion_ciudadana_meta();

    $hero_eyebrow = get_post_meta($post_id, 'sitio_cero_pc_hero_eyebrow', true);
    $hero_title = get_post_meta($post_id, 'sitio_cero_pc_hero_title', true);
    $hero_lead = get_post_meta($post_id, 'sitio_cero_pc_hero_lead', true);
    $hero_actions = get_post_meta($post_id, 'sitio_cero_pc_hero_actions', true);
    $subnav_items = get_post_meta($post_id, 'sitio_cero_pc_subnav_items', true);
    $audiencias_kicker = get_post_meta($post_id, 'sitio_cero_pc_audiencias_kicker', true);
    $audiencias_title = get_post_meta($post_id, 'sitio_cero_pc_audiencias_title', true);
    $audiencias_body = get_post_meta($post_id, 'sitio_cero_pc_audiencias_body', true);
    $audiencias_links = get_post_meta($post_id, 'sitio_cero_pc_audiencias_links', true);
    $kit_kicker = get_post_meta($post_id, 'sitio_cero_pc_kit_kicker', true);
    $kit_title = get_post_meta($post_id, 'sitio_cero_pc_kit_title', true);
    $kit_items = get_post_meta($post_id, 'sitio_cero_pc_kit_items', true);
    $online_kicker = get_post_meta($post_id, 'sitio_cero_pc_online_kicker', true);
    $online_title = get_post_meta($post_id, 'sitio_cero_pc_online_title', true);
    $online_items = get_post_meta($post_id, 'sitio_cero_pc_online_items', true);
    $recursos_kicker = get_post_meta($post_id, 'sitio_cero_pc_recursos_kicker', true);
    $recursos_title = get_post_meta($post_id, 'sitio_cero_pc_recursos_title', true);
    $recursos_items = get_post_meta($post_id, 'sitio_cero_pc_recursos_items', true);

    $hero_actions = is_array($hero_actions) ? $hero_actions : $defaults['hero']['actions'];
    $subnav_items = is_array($subnav_items) ? $subnav_items : $defaults['subnav'];
    $audiencias_links = is_array($audiencias_links) ? $audiencias_links : $defaults['audiencias']['links'];
    $kit_items = is_array($kit_items) ? $kit_items : $defaults['kit']['items'];
    $online_items = is_array($online_items) ? $online_items : $defaults['online']['items'];
    $recursos_items = is_array($recursos_items) ? $recursos_items : $defaults['recursos']['items'];

    return array(
        'hero' => array(
            'eyebrow' => is_string($hero_eyebrow) && '' !== trim($hero_eyebrow) ? $hero_eyebrow : $defaults['hero']['eyebrow'],
            'title'   => is_string($hero_title) && '' !== trim($hero_title) ? $hero_title : $defaults['hero']['title'],
            'lead'    => is_string($hero_lead) && '' !== trim($hero_lead) ? $hero_lead : $defaults['hero']['lead'],
            'actions' => $hero_actions,
        ),
        'subnav' => $subnav_items,
        'audiencias' => array(
            'kicker' => is_string($audiencias_kicker) && '' !== trim($audiencias_kicker) ? $audiencias_kicker : $defaults['audiencias']['kicker'],
            'title'  => is_string($audiencias_title) && '' !== trim($audiencias_title) ? $audiencias_title : $defaults['audiencias']['title'],
            'body'   => is_string($audiencias_body) && '' !== trim($audiencias_body) ? $audiencias_body : $defaults['audiencias']['body'],
            'links'  => $audiencias_links,
        ),
        'kit' => array(
            'kicker' => is_string($kit_kicker) && '' !== trim($kit_kicker) ? $kit_kicker : $defaults['kit']['kicker'],
            'title'  => is_string($kit_title) && '' !== trim($kit_title) ? $kit_title : $defaults['kit']['title'],
            'items'  => $kit_items,
        ),
        'online' => array(
            'kicker' => is_string($online_kicker) && '' !== trim($online_kicker) ? $online_kicker : $defaults['online']['kicker'],
            'title'  => is_string($online_title) && '' !== trim($online_title) ? $online_title : $defaults['online']['title'],
            'items'  => $online_items,
        ),
        'recursos' => array(
            'kicker' => is_string($recursos_kicker) && '' !== trim($recursos_kicker) ? $recursos_kicker : $defaults['recursos']['kicker'],
            'title'  => is_string($recursos_title) && '' !== trim($recursos_title) ? $recursos_title : $defaults['recursos']['title'],
            'items'  => $recursos_items,
        ),
    );
}

function sitio_cero_render_participacion_ciudadana($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return;
    }

    $data = sitio_cero_get_participacion_ciudadana_meta($post_id);

    ?>
    <div class="pc-page">
        <section class="pc-hero">
            <div class="pc-hero__inner">
                <p class="pc-hero__eyebrow"><?php echo esc_html($data['hero']['eyebrow']); ?></p>
                <h1 class="pc-hero__title"><?php echo esc_html($data['hero']['title']); ?></h1>
                <p class="pc-hero__lead"><?php echo esc_html($data['hero']['lead']); ?></p>
                <?php if (!empty($data['hero']['actions'])) : ?>
                    <div class="pc-hero__actions" aria-label="<?php esc_attr_e('Accesos directos', 'sitio-cero'); ?>">
                        <?php foreach ($data['hero']['actions'] as $action) : ?>
                            <?php
                            $label = isset($action['label']) ? sanitize_text_field((string) $action['label']) : '';
                            $url = isset($action['url']) ? esc_url((string) $action['url']) : '';
                            $target = isset($action['target']) && '_blank' === $action['target'] ? '_blank' : '';
                            if ('' === $label || '' === $url) {
                                continue;
                            }
                            ?>
                            <a class="pc-pill" href="<?php echo esc_url($url); ?>"<?php echo '' !== $target ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($data['subnav'])) : ?>
            <section class="pc-subnav" aria-label="<?php esc_attr_e('Indice de contenidos', 'sitio-cero'); ?>">
                <div class="pc-subnav__inner">
                    <?php foreach ($data['subnav'] as $item) : ?>
                        <?php
                        $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                        $url = isset($item['url']) ? esc_url((string) $item['url']) : '';
                        if ('' === $label || '' === $url) {
                            continue;
                        }
                        ?>
                        <a class="pc-subnav__link" href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section id="audiencias" class="pc-section pc-section--soft">
            <div class="pc-section__inner">
                <header class="pc-section__header">
                    <p class="pc-kicker"><?php echo esc_html($data['audiencias']['kicker']); ?></p>
                    <h2><?php echo esc_html($data['audiencias']['title']); ?></h2>
                </header>
                <div class="pc-columns">
                    <div class="pc-flow">
                        <?php echo wpautop(wp_kses((string) $data['audiencias']['body'], sitio_cero_get_direccion_allowed_html())); ?>
                    </div>
                    <?php if (!empty($data['audiencias']['links'])) : ?>
                        <div class="pc-linklist" aria-label="<?php esc_attr_e('Documentos de audiencias públicas', 'sitio-cero'); ?>">
                            <?php foreach ($data['audiencias']['links'] as $link) : ?>
                                <?php
                                $label = isset($link['label']) ? sanitize_text_field((string) $link['label']) : '';
                                $url = isset($link['url']) ? esc_url((string) $link['url']) : '';
                                $target = isset($link['target']) && '_blank' === $link['target'] ? '_blank' : '';
                                if ('' === $label || '' === $url) {
                                    continue;
                                }
                                ?>
                                <a class="pc-link" href="<?php echo esc_url($url); ?>"<?php echo '' !== $target ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($label); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="kit" class="pc-section pc-section--paper">
            <div class="pc-section__inner">
                <header class="pc-section__header">
                    <p class="pc-kicker"><?php echo esc_html($data['kit']['kicker']); ?></p>
                    <h2><?php echo esc_html($data['kit']['title']); ?></h2>
                </header>
                <?php if (!empty($data['kit']['items'])) : ?>
                    <div class="pc-grid" aria-label="<?php esc_attr_e('Kit elecciones', 'sitio-cero'); ?>">
                        <?php foreach ($data['kit']['items'] as $item) : ?>
                            <?php
                            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                            $url = isset($item['url']) ? esc_url((string) $item['url']) : '';
                            $target = isset($item['target']) && '_blank' === $item['target'] ? '_blank' : '';
                            if ('' === $label || '' === $url) {
                                continue;
                            }
                            ?>
                            <a class="pc-card" href="<?php echo esc_url($url); ?>"<?php echo '' !== $target ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="online" class="pc-section pc-section--dark">
            <div class="pc-section__inner">
                <header class="pc-section__header">
                    <p class="pc-kicker"><?php echo esc_html($data['online']['kicker']); ?></p>
                    <h2><?php echo esc_html($data['online']['title']); ?></h2>
                </header>
                <?php if (!empty($data['online']['items'])) : ?>
                    <div class="pc-grid" aria-label="<?php esc_attr_e('Servicios en línea', 'sitio-cero'); ?>">
                        <?php foreach ($data['online']['items'] as $item) : ?>
                            <?php
                            $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                            $url = isset($item['url']) ? esc_url((string) $item['url']) : '';
                            $target = isset($item['target']) && '_blank' === $item['target'] ? '_blank' : '';
                            if ('' === $label || '' === $url) {
                                continue;
                            }
                            ?>
                            <a class="pc-card pc-card--dark" href="<?php echo esc_url($url); ?>"<?php echo '' !== $target ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html($label); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="recursos" class="pc-section">
            <div class="pc-section__inner">
                <header class="pc-section__header">
                    <p class="pc-kicker"><?php echo esc_html($data['recursos']['kicker']); ?></p>
                    <h2><?php echo esc_html($data['recursos']['title']); ?></h2>
                </header>
                <?php if (!empty($data['recursos']['items'])) : ?>
                    <div class="pc-accordion" aria-label="<?php esc_attr_e('Recursos y documentos', 'sitio-cero'); ?>">
                        <?php foreach ($data['recursos']['items'] as $item) : ?>
                            <?php
                            $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
                            $anchor = isset($item['anchor']) ? sanitize_title((string) $item['anchor']) : '';
                            $content = isset($item['content']) ? (string) $item['content'] : '';
                            if ('' === $title && '' === trim($content)) {
                                continue;
                            }
                            ?>
                            <details<?php echo '' !== $anchor ? ' id="' . esc_attr($anchor) . '"' : ''; ?>>
                                <summary><?php echo esc_html($title); ?></summary>
                                <div class="pc-accordion__body pc-flow">
                                    <?php echo do_shortcode(wp_kses((string) $content, sitio_cero_get_direccion_allowed_html())); ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>
    <?php
}

function sitio_cero_add_participacion_ciudadana_metaboxes()
{
    add_meta_box(
        'sitio_cero_participacion_ciudadana',
        __('Contenido Participación Ciudadana', 'sitio-cero'),
        'sitio_cero_render_participacion_ciudadana_metabox',
        'participacion_c',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_participacion_ciudadana_metaboxes');

function sitio_cero_render_participacion_ciudadana_metabox($post)
{
    wp_nonce_field('sitio_cero_save_participacion_ciudadana_meta', 'sitio_cero_participacion_ciudadana_meta_nonce');

    $data = sitio_cero_get_participacion_ciudadana_meta($post->ID);
    $hero_actions = !empty($data['hero']['actions']) ? $data['hero']['actions'] : array(array('label' => '', 'url' => '', 'target' => ''));
    $subnav_items = !empty($data['subnav']) ? $data['subnav'] : array(array('label' => '', 'url' => ''));
    $audiencias_links = !empty($data['audiencias']['links']) ? $data['audiencias']['links'] : array(array('label' => '', 'url' => '', 'target' => ''));
    $kit_items = !empty($data['kit']['items']) ? $data['kit']['items'] : array(array('label' => '', 'url' => '', 'target' => ''));
    $online_items = !empty($data['online']['items']) ? $data['online']['items'] : array(array('label' => '', 'url' => '', 'target' => ''));
    $recursos_items = !empty($data['recursos']['items']) ? $data['recursos']['items'] : array(array('title' => '', 'anchor' => '', 'content' => ''));

    ?>
    <div class="sitio-cero-pc-metabox">
        <h3><?php esc_html_e('Hero', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-grid">
            <p>
                <label for="sitio_cero_pc_hero_eyebrow"><strong><?php esc_html_e('Eyebrow', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_hero_eyebrow" name="sitio_cero_pc_hero_eyebrow" type="text" class="widefat" value="<?php echo esc_attr($data['hero']['eyebrow']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_hero_title"><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_hero_title" name="sitio_cero_pc_hero_title" type="text" class="widefat" value="<?php echo esc_attr($data['hero']['title']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_hero_lead"><strong><?php esc_html_e('Bajada', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_hero_lead" name="sitio_cero_pc_hero_lead" type="text" class="widefat" value="<?php echo esc_attr($data['hero']['lead']); ?>">
            </p>
        </div>

        <h4><?php esc_html_e('Accesos directos (píldoras)', 'sitio-cero'); ?></h4>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($hero_actions as $action) : ?>
                    <div class="sitio-cero-pc-row" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_hero_action_label[]" value="<?php echo esc_attr((string) ($action['label'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                            <input type="url" class="widefat" name="sitio_cero_pc_hero_action_url[]" value="<?php echo esc_url((string) ($action['url'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_pc_hero_action_target[]">
                                <option value=""<?php selected('', $action['target'] ?? ''); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                <option value="_blank"<?php selected('_blank', $action['target'] ?? ''); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar acceso', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_hero_action_label[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                        <input type="url" class="widefat" name="sitio_cero_pc_hero_action_url[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                        <select class="widefat" name="sitio_cero_pc_hero_action_target[]">
                            <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                            <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                        </select>
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Subnav', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($subnav_items as $item) : ?>
                    <div class="sitio-cero-pc-row" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_subnav_label[]" value="<?php echo esc_attr((string) ($item['label'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Enlace', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_subnav_url[]" value="<?php echo esc_attr((string) ($item['url'] ?? '')); ?>" placeholder="#audiencias">
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar enlace', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_subnav_label[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Enlace', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_subnav_url[]" value="" placeholder="#audiencias">
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Audiencias Públicas', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-grid">
            <p>
                <label for="sitio_cero_pc_audiencias_kicker"><strong><?php esc_html_e('Kicker', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_audiencias_kicker" name="sitio_cero_pc_audiencias_kicker" type="text" class="widefat" value="<?php echo esc_attr($data['audiencias']['kicker']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_audiencias_title"><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_audiencias_title" name="sitio_cero_pc_audiencias_title" type="text" class="widefat" value="<?php echo esc_attr($data['audiencias']['title']); ?>">
            </p>
        </div>
        <p>
            <label for="sitio_cero_pc_audiencias_body"><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
            <textarea id="sitio_cero_pc_audiencias_body" name="sitio_cero_pc_audiencias_body" class="widefat" rows="6"><?php echo esc_textarea($data['audiencias']['body']); ?></textarea>
            <small><?php esc_html_e('Puedes usar HTML básico (p, strong, ul, li, a).', 'sitio-cero'); ?></small>
        </p>

        <h4><?php esc_html_e('Documentos de Audiencias', 'sitio-cero'); ?></h4>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($audiencias_links as $link) : ?>
                    <div class="sitio-cero-pc-row" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_audiencias_link_label[]" value="<?php echo esc_attr((string) ($link['label'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                            <input type="url" class="widefat" name="sitio_cero_pc_audiencias_link_url[]" value="<?php echo esc_url((string) ($link['url'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_pc_audiencias_link_target[]">
                                <option value=""<?php selected('', $link['target'] ?? ''); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                <option value="_blank"<?php selected('_blank', $link['target'] ?? ''); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar documento', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_audiencias_link_label[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                        <input type="url" class="widefat" name="sitio_cero_pc_audiencias_link_url[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                        <select class="widefat" name="sitio_cero_pc_audiencias_link_target[]">
                            <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                            <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                        </select>
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Kit Elecciones', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-grid">
            <p>
                <label for="sitio_cero_pc_kit_kicker"><strong><?php esc_html_e('Kicker', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_kit_kicker" name="sitio_cero_pc_kit_kicker" type="text" class="widefat" value="<?php echo esc_attr($data['kit']['kicker']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_kit_title"><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_kit_title" name="sitio_cero_pc_kit_title" type="text" class="widefat" value="<?php echo esc_attr($data['kit']['title']); ?>">
            </p>
        </div>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($kit_items as $item) : ?>
                    <div class="sitio-cero-pc-row" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_kit_item_label[]" value="<?php echo esc_attr((string) ($item['label'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                            <input type="url" class="widefat" name="sitio_cero_pc_kit_item_url[]" value="<?php echo esc_url((string) ($item['url'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_pc_kit_item_target[]">
                                <option value=""<?php selected('', $item['target'] ?? ''); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                <option value="_blank"<?php selected('_blank', $item['target'] ?? ''); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar archivo', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_kit_item_label[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                        <input type="url" class="widefat" name="sitio_cero_pc_kit_item_url[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                        <select class="widefat" name="sitio_cero_pc_kit_item_target[]">
                            <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                            <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                        </select>
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Servicios en línea', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-grid">
            <p>
                <label for="sitio_cero_pc_online_kicker"><strong><?php esc_html_e('Kicker', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_online_kicker" name="sitio_cero_pc_online_kicker" type="text" class="widefat" value="<?php echo esc_attr($data['online']['kicker']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_online_title"><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_online_title" name="sitio_cero_pc_online_title" type="text" class="widefat" value="<?php echo esc_attr($data['online']['title']); ?>">
            </p>
        </div>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($online_items as $item) : ?>
                    <div class="sitio-cero-pc-row" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_online_item_label[]" value="<?php echo esc_attr((string) ($item['label'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                            <input type="url" class="widefat" name="sitio_cero_pc_online_item_url[]" value="<?php echo esc_url((string) ($item['url'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_pc_online_item_target[]">
                                <option value=""<?php selected('', $item['target'] ?? ''); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                <option value="_blank"<?php selected('_blank', $item['target'] ?? ''); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                            </select>
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar servicio', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_online_item_label[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                        <input type="url" class="widefat" name="sitio_cero_pc_online_item_url[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                        <select class="widefat" name="sitio_cero_pc_online_item_target[]">
                            <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                            <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                        </select>
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Recursos y documentos', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-pc-grid">
            <p>
                <label for="sitio_cero_pc_recursos_kicker"><strong><?php esc_html_e('Kicker', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_recursos_kicker" name="sitio_cero_pc_recursos_kicker" type="text" class="widefat" value="<?php echo esc_attr($data['recursos']['kicker']); ?>">
            </p>
            <p>
                <label for="sitio_cero_pc_recursos_title"><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_pc_recursos_title" name="sitio_cero_pc_recursos_title" type="text" class="widefat" value="<?php echo esc_attr($data['recursos']['title']); ?>">
            </p>
        </div>
        <p class="description"><?php esc_html_e('Cada item es un acordeón. Puedes usar HTML (ul, li, a, h3, p).', 'sitio-cero'); ?></p>
        <div class="sitio-cero-pc-repeater" data-pc-repeater>
            <div class="sitio-cero-pc-list" data-pc-list>
                <?php foreach ($recursos_items as $item) : ?>
                    <div class="sitio-cero-pc-row sitio-cero-pc-row--stack" data-pc-row>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Título del acordeón', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_recursos_item_title[]" value="<?php echo esc_attr((string) ($item['title'] ?? '')); ?>">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Anchor (opcional)', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_pc_recursos_item_anchor[]" value="<?php echo esc_attr((string) ($item['anchor'] ?? '')); ?>" placeholder="ccosoc">
                        </div>
                        <div class="sitio-cero-pc-field">
                            <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                            <textarea class="widefat" rows="8" name="sitio_cero_pc_recursos_item_content[]"><?php echo esc_textarea((string) ($item['content'] ?? '')); ?></textarea>
                        </div>
                        <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-pc-add><?php esc_html_e('Agregar acordeón', 'sitio-cero'); ?></button>
            <template data-pc-template>
                <div class="sitio-cero-pc-row sitio-cero-pc-row--stack" data-pc-row>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Título del acordeón', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_recursos_item_title[]" value="">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Anchor (opcional)', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_pc_recursos_item_anchor[]" value="" placeholder="ccosoc">
                    </div>
                    <div class="sitio-cero-pc-field">
                        <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                        <textarea class="widefat" rows="8" name="sitio_cero_pc_recursos_item_content[]"></textarea>
                    </div>
                    <button type="button" class="button-link-delete" data-pc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>
    </div>
    <?php
}

function sitio_cero_collect_participacion_link_items($labels, $urls, $targets)
{
    $items = array();
    $labels = is_array($labels) ? $labels : array();
    $urls = is_array($urls) ? $urls : array();
    $targets = is_array($targets) ? $targets : array();
    $count = max(count($labels), count($urls), count($targets));

    for ($i = 0; $i < $count; $i++) {
        $label = isset($labels[$i]) ? sanitize_text_field((string) $labels[$i]) : '';
        $url = isset($urls[$i]) ? esc_url_raw((string) $urls[$i]) : '';
        $target = isset($targets[$i]) && '_blank' === $targets[$i] ? '_blank' : '';

        if ('' === $label && '' === $url) {
            continue;
        }

        $items[] = array(
            'label'  => $label,
            'url'    => $url,
            'target' => $target,
        );
    }

    return $items;
}

function sitio_cero_collect_participacion_nav_items($labels, $urls)
{
    $items = array();
    $labels = is_array($labels) ? $labels : array();
    $urls = is_array($urls) ? $urls : array();
    $count = max(count($labels), count($urls));

    for ($i = 0; $i < $count; $i++) {
        $label = isset($labels[$i]) ? sanitize_text_field((string) $labels[$i]) : '';
        $url = isset($urls[$i]) ? esc_url_raw((string) $urls[$i]) : '';

        if ('' === $label && '' === $url) {
            continue;
        }

        $items[] = array(
            'label' => $label,
            'url'   => $url,
        );
    }

    return $items;
}

function sitio_cero_collect_participacion_recursos_items($titles, $anchors, $contents)
{
    $items = array();
    $titles = is_array($titles) ? $titles : array();
    $anchors = is_array($anchors) ? $anchors : array();
    $contents = is_array($contents) ? $contents : array();
    $count = max(count($titles), count($anchors), count($contents));

    for ($i = 0; $i < $count; $i++) {
        $title = isset($titles[$i]) ? sanitize_text_field((string) $titles[$i]) : '';
        $anchor = isset($anchors[$i]) ? sanitize_title(ltrim((string) $anchors[$i], '#')) : '';
        $content = isset($contents[$i]) ? sitio_cero_sanitize_direccion_html((string) $contents[$i]) : '';

        if ('' === $title && '' === trim((string) wp_strip_all_tags($content))) {
            continue;
        }

        $items[] = array(
            'title'   => $title,
            'anchor'  => $anchor,
            'content' => $content,
        );
    }

    return $items;
}

function sitio_cero_save_participacion_ciudadana_meta($post_id)
{
    if (!isset($_POST['sitio_cero_participacion_ciudadana_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_participacion_ciudadana_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_participacion_ciudadana_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $hero_eyebrow = isset($_POST['sitio_cero_pc_hero_eyebrow']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_hero_eyebrow'])) : '';
    $hero_title = isset($_POST['sitio_cero_pc_hero_title']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_hero_title'])) : '';
    $hero_lead = isset($_POST['sitio_cero_pc_hero_lead']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_hero_lead'])) : '';

    update_post_meta($post_id, 'sitio_cero_pc_hero_eyebrow', $hero_eyebrow);
    update_post_meta($post_id, 'sitio_cero_pc_hero_title', $hero_title);
    update_post_meta($post_id, 'sitio_cero_pc_hero_lead', $hero_lead);

    $hero_actions = sitio_cero_collect_participacion_link_items(
        isset($_POST['sitio_cero_pc_hero_action_label']) ? wp_unslash($_POST['sitio_cero_pc_hero_action_label']) : array(),
        isset($_POST['sitio_cero_pc_hero_action_url']) ? wp_unslash($_POST['sitio_cero_pc_hero_action_url']) : array(),
        isset($_POST['sitio_cero_pc_hero_action_target']) ? wp_unslash($_POST['sitio_cero_pc_hero_action_target']) : array()
    );
    if (!empty($hero_actions)) {
        update_post_meta($post_id, 'sitio_cero_pc_hero_actions', $hero_actions);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_hero_actions');
    }

    $subnav_items = sitio_cero_collect_participacion_nav_items(
        isset($_POST['sitio_cero_pc_subnav_label']) ? wp_unslash($_POST['sitio_cero_pc_subnav_label']) : array(),
        isset($_POST['sitio_cero_pc_subnav_url']) ? wp_unslash($_POST['sitio_cero_pc_subnav_url']) : array()
    );
    if (!empty($subnav_items)) {
        update_post_meta($post_id, 'sitio_cero_pc_subnav_items', $subnav_items);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_subnav_items');
    }

    $audiencias_kicker = isset($_POST['sitio_cero_pc_audiencias_kicker']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_audiencias_kicker'])) : '';
    $audiencias_title = isset($_POST['sitio_cero_pc_audiencias_title']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_audiencias_title'])) : '';
    $audiencias_body = isset($_POST['sitio_cero_pc_audiencias_body']) ? sitio_cero_sanitize_direccion_html(wp_unslash($_POST['sitio_cero_pc_audiencias_body'])) : '';

    update_post_meta($post_id, 'sitio_cero_pc_audiencias_kicker', $audiencias_kicker);
    update_post_meta($post_id, 'sitio_cero_pc_audiencias_title', $audiencias_title);
    update_post_meta($post_id, 'sitio_cero_pc_audiencias_body', $audiencias_body);

    $audiencias_links = sitio_cero_collect_participacion_link_items(
        isset($_POST['sitio_cero_pc_audiencias_link_label']) ? wp_unslash($_POST['sitio_cero_pc_audiencias_link_label']) : array(),
        isset($_POST['sitio_cero_pc_audiencias_link_url']) ? wp_unslash($_POST['sitio_cero_pc_audiencias_link_url']) : array(),
        isset($_POST['sitio_cero_pc_audiencias_link_target']) ? wp_unslash($_POST['sitio_cero_pc_audiencias_link_target']) : array()
    );
    if (!empty($audiencias_links)) {
        update_post_meta($post_id, 'sitio_cero_pc_audiencias_links', $audiencias_links);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_audiencias_links');
    }

    $kit_kicker = isset($_POST['sitio_cero_pc_kit_kicker']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_kit_kicker'])) : '';
    $kit_title = isset($_POST['sitio_cero_pc_kit_title']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_kit_title'])) : '';
    update_post_meta($post_id, 'sitio_cero_pc_kit_kicker', $kit_kicker);
    update_post_meta($post_id, 'sitio_cero_pc_kit_title', $kit_title);

    $kit_items = sitio_cero_collect_participacion_link_items(
        isset($_POST['sitio_cero_pc_kit_item_label']) ? wp_unslash($_POST['sitio_cero_pc_kit_item_label']) : array(),
        isset($_POST['sitio_cero_pc_kit_item_url']) ? wp_unslash($_POST['sitio_cero_pc_kit_item_url']) : array(),
        isset($_POST['sitio_cero_pc_kit_item_target']) ? wp_unslash($_POST['sitio_cero_pc_kit_item_target']) : array()
    );
    if (!empty($kit_items)) {
        update_post_meta($post_id, 'sitio_cero_pc_kit_items', $kit_items);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_kit_items');
    }

    $online_kicker = isset($_POST['sitio_cero_pc_online_kicker']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_online_kicker'])) : '';
    $online_title = isset($_POST['sitio_cero_pc_online_title']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_online_title'])) : '';
    update_post_meta($post_id, 'sitio_cero_pc_online_kicker', $online_kicker);
    update_post_meta($post_id, 'sitio_cero_pc_online_title', $online_title);

    $online_items = sitio_cero_collect_participacion_link_items(
        isset($_POST['sitio_cero_pc_online_item_label']) ? wp_unslash($_POST['sitio_cero_pc_online_item_label']) : array(),
        isset($_POST['sitio_cero_pc_online_item_url']) ? wp_unslash($_POST['sitio_cero_pc_online_item_url']) : array(),
        isset($_POST['sitio_cero_pc_online_item_target']) ? wp_unslash($_POST['sitio_cero_pc_online_item_target']) : array()
    );
    if (!empty($online_items)) {
        update_post_meta($post_id, 'sitio_cero_pc_online_items', $online_items);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_online_items');
    }

    $recursos_kicker = isset($_POST['sitio_cero_pc_recursos_kicker']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_recursos_kicker'])) : '';
    $recursos_title = isset($_POST['sitio_cero_pc_recursos_title']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_pc_recursos_title'])) : '';
    update_post_meta($post_id, 'sitio_cero_pc_recursos_kicker', $recursos_kicker);
    update_post_meta($post_id, 'sitio_cero_pc_recursos_title', $recursos_title);

    $recursos_items = sitio_cero_collect_participacion_recursos_items(
        isset($_POST['sitio_cero_pc_recursos_item_title']) ? wp_unslash($_POST['sitio_cero_pc_recursos_item_title']) : array(),
        isset($_POST['sitio_cero_pc_recursos_item_anchor']) ? wp_unslash($_POST['sitio_cero_pc_recursos_item_anchor']) : array(),
        isset($_POST['sitio_cero_pc_recursos_item_content']) ? wp_unslash($_POST['sitio_cero_pc_recursos_item_content']) : array()
    );
    if (!empty($recursos_items)) {
        update_post_meta($post_id, 'sitio_cero_pc_recursos_items', $recursos_items);
    } else {
        delete_post_meta($post_id, 'sitio_cero_pc_recursos_items');
    }
}
add_action('save_post_participacion_c', 'sitio_cero_save_participacion_ciudadana_meta');

function sitio_cero_enqueue_participacion_ciudadana_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'participacion_c' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'sitio-cero-admin-participacion-ciudadana',
        get_template_directory_uri() . '/assets/css/admin-participacion-ciudadana.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-participacion-ciudadana',
        get_template_directory_uri() . '/assets/js/admin-participacion-ciudadana.js',
        array('jquery'),
        $version,
        true
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_participacion_ciudadana_admin_assets');

function sitio_cero_get_default_municipalidad_pages()
{
    return array(
        array(
            'title'      => __('Alcalde', 'sitio-cero'),
            'slug'       => 'alcalde',
            'menu_order' => 1,
            'content'    => __('Ingresa aqui la informacion del alcalde, su mensaje y lineamientos de gestion municipal.', 'sitio-cero'),
        ),
        array(
            'title'      => __('Concejo Municipal', 'sitio-cero'),
            'slug'       => 'concejo-municipal',
            'menu_order' => 2,
            'content'    => __('Ingresa aqui la informacion del concejo municipal, integrantes, comisiones y actas.', 'sitio-cero'),
        ),
    );
}

function sitio_cero_get_default_concejo_members()
{
    $base_url = trailingslashit(get_template_directory_uri()) . 'assets/images/concejo';
    $default_images = array(
        $base_url . '/concejo-01.svg',
        $base_url . '/concejo-02.svg',
        $base_url . '/concejo-03.svg',
        $base_url . '/concejo-04.svg',
        $base_url . '/concejo-05.svg',
        $base_url . '/concejo-06.svg',
        $base_url . '/concejo-07.svg',
        $base_url . '/concejo-08.svg',
        $base_url . '/concejo-09.svg',
        $base_url . '/concejo-10.svg',
    );

    $names = array(
        'José Eduardo Piña Faúndez (Republicano)',
        'Oscar Iván Ramírez Romero (PDC)',
        'Daniel Pacheco Ponce (PSC)',
        'Claudia Arriagada Parra (Igualdad)',
        'Olimpia Fernanda Riveros Ravelo (PCCH)',
        'Andrea del Pilar Estrada Arteaga (Republicano)',
        'Christian Paulsen Garbarino (UDI)',
        'Francisca Collipal Lagos (PSC)',
        'Eric Alexis Riquelme Sanhueza (FA)',
        'Miguel Ángel Berríos Garate (RN)',
    );

    $image_overrides = array(
        1 => $default_images[0],
        2 => 'https://www.pdc.cl/wp-content/uploads/2024/12/OSCAR-RAMIREZ-ROMERO-CONCEJAL-CONCEPCION.png',
        3 => 'https://socialcristiano.cl/wp-content/uploads/2025/03/images.jpg',
        4 => 'https://i1.sndcdn.com/artworks-000059564033-kgmnlg-t1080x1080.jpg',
        5 => 'https://assets.diarioconcepcion.cl/2024/04/pag-5-Olimpia-Riveros-candidata-a-alcaldesa-de-Concepcion-foto-carolina-e1712215694190-850x500.jpg',
        6 => $default_images[5],
        7 => 'https://static.wixstatic.com/media/e9676d_313b71a28e2b4835a7a9f8fd13ede236~mv2.jpg/v1/crop/x_0%2Cy_6%2Cw_718%2Ch_707/fill/w_195%2Ch_192%2Cal_c%2Cq_80%2Cusm_0.66_1.00_0.01%2Cenc_avif%2Cquality_auto/CPaulsenfoto_edited.jpg',
        8 => 'https://socialcristiano.cl/wp-content/uploads/2025/03/Francisca-Collipal-e1742306962434.jpeg',
        9 => $default_images[8],
        10 => $default_images[9],
    );

    $members = array();
    foreach ($names as $index => $name) {
        $position = $index + 1;
        $image_url = isset($image_overrides[$position]) ? $image_overrides[$position] : $default_images[$index];
        $members[] = array(
            'name'      => $name,
            'email'     => 'concejal' . $position . '@municipio.cl',
            'image_url' => esc_url_raw($image_url),
            'facebook'  => '',
            'instagram' => '',
            'x'         => '',
            'whatsapp'  => '',
        );
    }

    return $members;
}

function sitio_cero_seed_default_municipalidad_pages()
{
    if (!post_type_exists('municipalidad')) {
        return;
    }

    $seed_version = '4';
    $already_seeded_version = (string) get_option('sitio_cero_default_municipalidad_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $defaults = sitio_cero_get_default_municipalidad_pages();
    foreach ($defaults as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = isset($item['title']) ? trim((string) $item['title']) : '';
        if ('' === $title) {
            continue;
        }

        $slug_source = isset($item['slug']) ? (string) $item['slug'] : $title;
        $slug = sanitize_title($slug_source);
        if ('' === $slug) {
            $slug = sanitize_title($title);
        }

        $existing = get_posts(
            array(
                'post_type'      => 'municipalidad',
                'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
                'name'           => $slug,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        if (!empty($existing)) {
            $post_id = (int) $existing[0];
        } else {
            $post_id = wp_insert_post(
                array(
                    'post_type'    => 'municipalidad',
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => isset($item['content']) ? (string) $item['content'] : '',
                    'menu_order'   => isset($item['menu_order']) ? (int) $item['menu_order'] : 0,
                ),
                true
            );
        }

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta((int) $post_id, '_sitio_cero_demo_municipalidad', '1');

        if ('alcalde' === $slug) {
            $bio_enabled = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_bio_enabled', true);
            if ('' === $bio_enabled) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_bio_enabled', '1');
            }

            $bio_title = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_bio_title', true);
            if ('' === trim($bio_title)) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_bio_title', __('Mensaje del alcalde', 'sitio-cero'));
            }

            $bio_text = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_bio_text', true);
            if ('' === trim($bio_text)) {
                update_post_meta(
                    (int) $post_id,
                    'sitio_cero_municipalidad_bio_text',
                    __('Este espacio esta pensado para publicar una biografia politica e institucional del alcalde, destacando trayectoria, prioridades y compromiso con la comuna.', 'sitio-cero')
                );
            }

            $signer_name = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_signer_name', true);
            if ('' === trim($signer_name)) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_signer_name', __('Nombre del alcalde', 'sitio-cero'));
            }

            $signer_role = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_signer_role', true);
            if ('' === trim($signer_role)) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_signer_role', __('Alcalde', 'sitio-cero'));
            }
        } elseif ('concejo-municipal' === $slug) {
            $concejo_enabled = (string) get_post_meta((int) $post_id, 'sitio_cero_municipalidad_concejo_enabled', true);
            if ('' === $concejo_enabled) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_concejo_enabled', '1');
            }

            $concejo_members = get_post_meta((int) $post_id, 'sitio_cero_municipalidad_concejo_members', true);
            $needs_refresh = false;
            if (!is_array($concejo_members) || empty($concejo_members)) {
                $needs_refresh = true;
            } else {
                $placeholder_count = 0;
                foreach ($concejo_members as $member) {
                    $member_name = is_array($member) && isset($member['name']) ? trim((string) $member['name']) : '';
                    if ('' === $member_name || preg_match('/^Concejal\\s+\\d+$/iu', $member_name)) {
                        $placeholder_count++;
                    }
                }
                if ($placeholder_count === count($concejo_members)) {
                    $needs_refresh = true;
                }
            }

            if ($needs_refresh) {
                update_post_meta((int) $post_id, 'sitio_cero_municipalidad_concejo_members', sitio_cero_get_default_concejo_members());
            } else {
                $has_any_image = false;
                foreach ($concejo_members as $member) {
                    if (is_array($member) && !empty($member['image_url'])) {
                        $has_any_image = true;
                        break;
                    }
                }

                if (!$has_any_image) {
                    $defaults = sitio_cero_get_default_concejo_members();
                    $updated_members = array();
                    foreach ($concejo_members as $index => $member) {
                        if (!is_array($member)) {
                            continue;
                        }

                        $image_url = isset($defaults[$index]['image_url']) ? (string) $defaults[$index]['image_url'] : '';
                        $member['image_url'] = '' === trim((string) ($member['image_url'] ?? '')) ? $image_url : $member['image_url'];
                        $updated_members[] = $member;
                    }

                    if (!empty($updated_members)) {
                        update_post_meta((int) $post_id, 'sitio_cero_municipalidad_concejo_members', $updated_members);
                    }
                }
            }
        }
    }

    update_option('sitio_cero_default_municipalidad_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_municipalidad_pages', 47);

function sitio_cero_add_municipalidad_metaboxes($post_type, $post)
{
    if ('municipalidad' !== (string) $post_type) {
        return;
    }

    $post_slug = '';
    if ($post instanceof WP_Post) {
        $post_slug = (string) $post->post_name;
    }

    $concejo_slugs = array('concejo-municipal', 'concejales');
    $is_concejo_page = in_array($post_slug, $concejo_slugs, true);

    if (!$is_concejo_page) {
        add_meta_box(
            'sitio_cero_municipalidad_bio',
            __('Bloque biografia de autoridad', 'sitio-cero'),
            'sitio_cero_render_municipalidad_bio_metabox',
            'municipalidad',
            'normal',
            'high'
        );
    }

    add_meta_box(
        'sitio_cero_municipalidad_concejo',
        __('Grilla Concejo Municipal (5x2)', 'sitio-cero'),
        'sitio_cero_render_municipalidad_concejo_metabox',
        'municipalidad',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_municipalidad_metaboxes', 10, 2);

function sitio_cero_render_municipalidad_bio_metabox($post)
{
    wp_nonce_field('sitio_cero_save_municipalidad_bio_meta', 'sitio_cero_municipalidad_bio_meta_nonce');

    $is_alcalde = 'alcalde' === (string) $post->post_name;
    $bio_enabled_value = (string) get_post_meta($post->ID, 'sitio_cero_municipalidad_bio_enabled', true);
    $bio_enabled = '1' === $bio_enabled_value || ($is_alcalde && '' === $bio_enabled_value);
    $bio_title = get_post_meta($post->ID, 'sitio_cero_municipalidad_bio_title', true);
    $bio_text = get_post_meta($post->ID, 'sitio_cero_municipalidad_bio_text', true);
    $signer_name = get_post_meta($post->ID, 'sitio_cero_municipalidad_signer_name', true);
    $signer_role = get_post_meta($post->ID, 'sitio_cero_municipalidad_signer_role', true);

    if (!is_string($bio_title)) {
        $bio_title = '';
    }
    if (!is_string($bio_text)) {
        $bio_text = '';
    }
    if (!is_string($signer_name)) {
        $signer_name = '';
    }
    if (!is_string($signer_role)) {
        $signer_role = '';
    }
    ?>
    <?php if (!$is_alcalde) : ?>
        <p class="description"><?php esc_html_e('Este bloque fue pensado para la pagina con slug "alcalde". Puedes dejarlo vacio en otras paginas.', 'sitio-cero'); ?></p>
    <?php endif; ?>

    <p>
        <label>
            <input type="checkbox" name="sitio_cero_municipalidad_bio_enabled" value="1" <?php checked($bio_enabled); ?>>
            <?php esc_html_e('Activar bloque biografia en dos columnas', 'sitio-cero'); ?>
        </label>
    </p>

    <p class="description">
        <?php esc_html_e('La imagen de la columna izquierda usa la Imagen destacada y se muestra en formato vertical.', 'sitio-cero'); ?>
    </p>

    <p>
        <label for="sitio_cero_municipalidad_bio_title"><strong><?php esc_html_e('Titulo del bloque', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_municipalidad_bio_title" type="text" name="sitio_cero_municipalidad_bio_title" class="widefat" value="<?php echo esc_attr($bio_title); ?>">
    </p>

    <p>
        <label for="sitio_cero_municipalidad_bio_text"><strong><?php esc_html_e('Cuerpo del texto', 'sitio-cero'); ?></strong></label>
        <textarea id="sitio_cero_municipalidad_bio_text" name="sitio_cero_municipalidad_bio_text" class="widefat" rows="8"><?php echo esc_textarea($bio_text); ?></textarea>
    </p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <p style="margin:0;">
            <label for="sitio_cero_municipalidad_signer_name"><strong><?php esc_html_e('Nombre firmante', 'sitio-cero'); ?></strong></label>
            <input id="sitio_cero_municipalidad_signer_name" type="text" name="sitio_cero_municipalidad_signer_name" class="widefat" value="<?php echo esc_attr($signer_name); ?>">
        </p>
        <p style="margin:0;">
            <label for="sitio_cero_municipalidad_signer_role"><strong><?php esc_html_e('Cargo firmante', 'sitio-cero'); ?></strong></label>
            <input id="sitio_cero_municipalidad_signer_role" type="text" name="sitio_cero_municipalidad_signer_role" class="widefat" value="<?php echo esc_attr($signer_role); ?>">
        </p>
    </div>
    <?php
}

function sitio_cero_render_municipalidad_concejo_metabox($post)
{
    wp_nonce_field('sitio_cero_save_municipalidad_bio_meta', 'sitio_cero_municipalidad_bio_meta_nonce');

    $is_concejo_page = 'concejo-municipal' === (string) $post->post_name;
    $concejo_enabled_value = (string) get_post_meta($post->ID, 'sitio_cero_municipalidad_concejo_enabled', true);
    $concejo_enabled = '1' === $concejo_enabled_value || ($is_concejo_page && '' === $concejo_enabled_value);
    $members = get_post_meta($post->ID, 'sitio_cero_municipalidad_concejo_members', true);

    if (!is_array($members) || empty($members)) {
        $members = sitio_cero_get_default_concejo_members();
    }

    $members = array_slice(array_values($members), 0, 10);
    while (count($members) < 10) {
        $members[] = array(
            'name'      => '',
            'email'     => '',
            'image_url' => '',
        );
    }
    ?>
    <?php if (!$is_concejo_page) : ?>
        <p class="description"><?php esc_html_e('Este bloque fue pensado para la pagina con slug "concejo-municipal".', 'sitio-cero'); ?></p>
    <?php endif; ?>

    <p>
        <label>
            <input type="checkbox" name="sitio_cero_municipalidad_concejo_enabled" value="1" <?php checked($concejo_enabled); ?>>
            <?php esc_html_e('Activar grilla de equipo (5 columnas x 2 filas)', 'sitio-cero'); ?>
        </label>
    </p>

    <p class="description"><?php esc_html_e('Edita nombre, correo, redes sociales, WhatsApp e imagen vertical de cada integrante. La imagen acepta URL (por ejemplo desde Biblioteca de Medios).', 'sitio-cero'); ?></p>

    <style>
        .sitio-cero-concejo-image-preview { margin-top:8px; border:1px dashed #ccd0d4; border-radius:6px; background:#f6f7f7; min-height:120px; display:flex; align-items:center; justify-content:center; padding:6px; }
        .sitio-cero-concejo-image-preview__img { max-height:160px; max-width:100%; height:auto; width:auto; display:block; }
        .sitio-cero-concejo-image-placeholder { font-size:12px; color:#646970; }
    </style>

    <table class="widefat striped" style="max-width:100%;">
        <thead>
            <tr>
                <th style="width:26%;"><?php esc_html_e('Nombre', 'sitio-cero'); ?></th>
                <th style="width:20%;"><?php esc_html_e('Correo electronico', 'sitio-cero'); ?></th>
                <th style="width:30%;"><?php esc_html_e('Redes y WhatsApp', 'sitio-cero'); ?></th>
                <th style="width:24%;"><?php esc_html_e('URL imagen (vertical)', 'sitio-cero'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($members as $index => $member) : ?>
                <?php
                $member_name = is_array($member) && isset($member['name']) ? (string) $member['name'] : '';
                $member_email = is_array($member) && isset($member['email']) ? (string) $member['email'] : '';
                $member_image_url = is_array($member) && isset($member['image_url']) ? (string) $member['image_url'] : '';
                $member_facebook = is_array($member) && isset($member['facebook']) ? (string) $member['facebook'] : '';
                $member_instagram = is_array($member) && isset($member['instagram']) ? (string) $member['instagram'] : '';
                $member_x = is_array($member) && isset($member['x']) ? (string) $member['x'] : '';
                $member_whatsapp = is_array($member) && isset($member['whatsapp']) ? (string) $member['whatsapp'] : '';
                $preview_alt = '' !== $member_name
                    ? sprintf(__('Imagen de %s', 'sitio-cero'), $member_name)
                    : __('Previsualizacion de imagen', 'sitio-cero');
                ?>
                <tr>
                    <td>
                        <label for="sitio_cero_municipalidad_concejo_member_name_<?php echo esc_attr((string) $index); ?>" class="screen-reader-text"><?php esc_html_e('Nombre', 'sitio-cero'); ?></label>
                        <input
                            id="sitio_cero_municipalidad_concejo_member_name_<?php echo esc_attr((string) $index); ?>"
                            type="text"
                            name="sitio_cero_municipalidad_concejo_member_name[]"
                            class="widefat"
                            value="<?php echo esc_attr($member_name); ?>"
                            placeholder="<?php echo esc_attr(sprintf(__('Concejal %d', 'sitio-cero'), $index + 1)); ?>"
                        >
                    </td>
                    <td>
                        <label for="sitio_cero_municipalidad_concejo_member_email_<?php echo esc_attr((string) $index); ?>" class="screen-reader-text"><?php esc_html_e('Correo electronico', 'sitio-cero'); ?></label>
                        <input
                            id="sitio_cero_municipalidad_concejo_member_email_<?php echo esc_attr((string) $index); ?>"
                            type="email"
                            name="sitio_cero_municipalidad_concejo_member_email[]"
                            class="widefat"
                            value="<?php echo esc_attr($member_email); ?>"
                            placeholder="<?php echo esc_attr('concejal' . ($index + 1) . '@municipio.cl'); ?>"
                        >
                    </td>
                    <td>
                        <div style="display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:6px;">
                            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;">
                                <?php esc_html_e('Facebook', 'sitio-cero'); ?>
                                <input
                                    type="url"
                                    name="sitio_cero_municipalidad_concejo_member_facebook[]"
                                    class="widefat"
                                    value="<?php echo esc_attr(esc_url_raw($member_facebook)); ?>"
                                    placeholder="https://facebook.com/usuario"
                                >
                            </label>
                            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;">
                                <?php esc_html_e('Instagram', 'sitio-cero'); ?>
                                <input
                                    type="url"
                                    name="sitio_cero_municipalidad_concejo_member_instagram[]"
                                    class="widefat"
                                    value="<?php echo esc_attr(esc_url_raw($member_instagram)); ?>"
                                    placeholder="https://instagram.com/usuario"
                                >
                            </label>
                            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;">
                                <?php esc_html_e('X / Twitter', 'sitio-cero'); ?>
                                <input
                                    type="url"
                                    name="sitio_cero_municipalidad_concejo_member_x[]"
                                    class="widefat"
                                    value="<?php echo esc_attr(esc_url_raw($member_x)); ?>"
                                    placeholder="https://x.com/usuario"
                                >
                            </label>
                            <label style="display:flex;flex-direction:column;gap:4px;font-size:12px;">
                                <?php esc_html_e('WhatsApp', 'sitio-cero'); ?>
                                <input
                                    type="text"
                                    name="sitio_cero_municipalidad_concejo_member_whatsapp[]"
                                    class="widefat"
                                    value="<?php echo esc_attr($member_whatsapp); ?>"
                                    placeholder="https://wa.me/569..."
                                >
                            </label>
                        </div>
                    </td>
                    <td>
                        <label for="sitio_cero_municipalidad_concejo_member_image_<?php echo esc_attr((string) $index); ?>" class="screen-reader-text"><?php esc_html_e('URL imagen', 'sitio-cero'); ?></label>
                        <input
                            id="sitio_cero_municipalidad_concejo_member_image_<?php echo esc_attr((string) $index); ?>"
                            type="url"
                            name="sitio_cero_municipalidad_concejo_member_image[]"
                            class="widefat sitio-cero-concejo-image-url"
                            value="<?php echo esc_attr(esc_url_raw($member_image_url)); ?>"
                            placeholder="https://..."
                        >
                        <button type="button" class="button button-secondary sitio-cero-media-picker" data-target="#sitio_cero_municipalidad_concejo_member_image_<?php echo esc_attr((string) $index); ?>">
                            <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
                        </button>
                        <div class="sitio-cero-concejo-image-preview">
                            <img
                                class="sitio-cero-concejo-image-preview__img"
                                src="<?php echo esc_url($member_image_url); ?>"
                                alt="<?php echo esc_attr($preview_alt); ?>"
                                <?php echo '' === $member_image_url ? 'style="display:none;"' : ''; ?>
                            >
                            <span class="sitio-cero-concejo-image-placeholder" <?php echo '' !== $member_image_url ? 'style="display:none;"' : ''; ?>>
                                <?php esc_html_e('Sin imagen', 'sitio-cero'); ?>
                            </span>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function sitio_cero_normalize_whatsapp_url($raw)
{
    $raw = trim((string) $raw);
    if ('' === $raw) {
        return '';
    }

    if (wp_http_validate_url($raw)) {
        return esc_url_raw($raw);
    }

    $digits = preg_replace('/\\D+/', '', $raw);
    if ('' === $digits) {
        return '';
    }

    return 'https://wa.me/' . $digits;
}

function sitio_cero_save_municipalidad_bio_meta($post_id)
{
    if (!isset($_POST['sitio_cero_municipalidad_bio_meta_nonce'])) {
        return;
    }

    $nonce = wp_unslash($_POST['sitio_cero_municipalidad_bio_meta_nonce']);
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_municipalidad_bio_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if ('municipalidad' !== get_post_type($post_id)) {
        return;
    }

    $bio_enabled = isset($_POST['sitio_cero_municipalidad_bio_enabled']) ? '1' : '0';
    update_post_meta($post_id, 'sitio_cero_municipalidad_bio_enabled', $bio_enabled);

    $bio_title = isset($_POST['sitio_cero_municipalidad_bio_title'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_municipalidad_bio_title']))
        : '';
    if ('' !== trim($bio_title)) {
        update_post_meta($post_id, 'sitio_cero_municipalidad_bio_title', $bio_title);
    } else {
        delete_post_meta($post_id, 'sitio_cero_municipalidad_bio_title');
    }

    $bio_text = isset($_POST['sitio_cero_municipalidad_bio_text'])
        ? wp_kses_post(wp_unslash($_POST['sitio_cero_municipalidad_bio_text']))
        : '';
    if ('' !== trim(wp_strip_all_tags($bio_text))) {
        update_post_meta($post_id, 'sitio_cero_municipalidad_bio_text', $bio_text);
    } else {
        delete_post_meta($post_id, 'sitio_cero_municipalidad_bio_text');
    }

    $signer_name = isset($_POST['sitio_cero_municipalidad_signer_name'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_municipalidad_signer_name']))
        : '';
    if ('' !== trim($signer_name)) {
        update_post_meta($post_id, 'sitio_cero_municipalidad_signer_name', $signer_name);
    } else {
        delete_post_meta($post_id, 'sitio_cero_municipalidad_signer_name');
    }

    $signer_role = isset($_POST['sitio_cero_municipalidad_signer_role'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_municipalidad_signer_role']))
        : '';
    if ('' !== trim($signer_role)) {
        update_post_meta($post_id, 'sitio_cero_municipalidad_signer_role', $signer_role);
    } else {
        delete_post_meta($post_id, 'sitio_cero_municipalidad_signer_role');
    }

    $concejo_enabled = isset($_POST['sitio_cero_municipalidad_concejo_enabled']) ? '1' : '0';
    update_post_meta($post_id, 'sitio_cero_municipalidad_concejo_enabled', $concejo_enabled);

    $member_names = isset($_POST['sitio_cero_municipalidad_concejo_member_name'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_name'])
        : array();
    $member_emails = isset($_POST['sitio_cero_municipalidad_concejo_member_email'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_email'])
        : array();
    $member_images = isset($_POST['sitio_cero_municipalidad_concejo_member_image'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_image'])
        : array();
    $member_facebooks = isset($_POST['sitio_cero_municipalidad_concejo_member_facebook'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_facebook'])
        : array();
    $member_instagrams = isset($_POST['sitio_cero_municipalidad_concejo_member_instagram'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_instagram'])
        : array();
    $member_x_urls = isset($_POST['sitio_cero_municipalidad_concejo_member_x'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_x'])
        : array();
    $member_whatsapps = isset($_POST['sitio_cero_municipalidad_concejo_member_whatsapp'])
        ? wp_unslash($_POST['sitio_cero_municipalidad_concejo_member_whatsapp'])
        : array();

    if (!is_array($member_names)) {
        $member_names = array();
    }
    if (!is_array($member_emails)) {
        $member_emails = array();
    }
    if (!is_array($member_images)) {
        $member_images = array();
    }
    if (!is_array($member_facebooks)) {
        $member_facebooks = array();
    }
    if (!is_array($member_instagrams)) {
        $member_instagrams = array();
    }
    if (!is_array($member_x_urls)) {
        $member_x_urls = array();
    }
    if (!is_array($member_whatsapps)) {
        $member_whatsapps = array();
    }

    $members = array();
    $max_members = 10;
    for ($index = 0; $index < $max_members; $index++) {
        $name_raw = isset($member_names[$index]) ? (string) $member_names[$index] : '';
        $email_raw = isset($member_emails[$index]) ? (string) $member_emails[$index] : '';
        $image_raw = isset($member_images[$index]) ? (string) $member_images[$index] : '';
        $facebook_raw = isset($member_facebooks[$index]) ? (string) $member_facebooks[$index] : '';
        $instagram_raw = isset($member_instagrams[$index]) ? (string) $member_instagrams[$index] : '';
        $x_raw = isset($member_x_urls[$index]) ? (string) $member_x_urls[$index] : '';
        $whatsapp_raw = isset($member_whatsapps[$index]) ? (string) $member_whatsapps[$index] : '';

        $name = sanitize_text_field($name_raw);
        $email = sanitize_email($email_raw);
        $image_url = esc_url_raw($image_raw);
        $facebook = esc_url_raw($facebook_raw);
        $instagram = esc_url_raw($instagram_raw);
        $x_url = esc_url_raw($x_raw);
        $whatsapp = sitio_cero_normalize_whatsapp_url($whatsapp_raw);

        if ('' === trim($name) && '' === trim($email) && '' === trim($image_url) && '' === trim($facebook) && '' === trim($instagram) && '' === trim($x_url) && '' === trim($whatsapp)) {
            continue;
        }

        $members[] = array(
            'name'      => $name,
            'email'     => $email,
            'image_url' => $image_url,
            'facebook'  => $facebook,
            'instagram' => $instagram,
            'x'         => $x_url,
            'whatsapp'  => $whatsapp,
        );
    }

    if (!empty($members)) {
        update_post_meta($post_id, 'sitio_cero_municipalidad_concejo_members', $members);
    } else {
        delete_post_meta($post_id, 'sitio_cero_municipalidad_concejo_members');
    }
}
add_action('save_post_municipalidad', 'sitio_cero_save_municipalidad_bio_meta');

function sitio_cero_flush_municipalidad_rewrite_rules_once()
{
    if (!post_type_exists('municipalidad')) {
        return;
    }

    $flush_version = '1';
    $stored_version = (string) get_option('sitio_cero_municipalidad_rewrite_flushed_version', '');
    if ($flush_version === $stored_version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('sitio_cero_municipalidad_rewrite_flushed_version', $flush_version);
}
add_action('init', 'sitio_cero_flush_municipalidad_rewrite_rules_once', 99);

function sitio_cero_register_canal_ciudadano_post_type()
{
    $labels = array(
        'name'               => __('Canales ciudadanos', 'sitio-cero'),
        'singular_name'      => __('Canal ciudadano', 'sitio-cero'),
        'menu_name'          => __('Canal reportes', 'sitio-cero'),
        'name_admin_bar'     => __('Canal ciudadano', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar canal ciudadano', 'sitio-cero'),
        'new_item'           => __('Nuevo canal ciudadano', 'sitio-cero'),
        'edit_item'          => __('Editar canal ciudadano', 'sitio-cero'),
        'view_item'          => __('Ver canal ciudadano', 'sitio-cero'),
        'all_items'          => __('Todos los canales', 'sitio-cero'),
        'search_items'       => __('Buscar canales', 'sitio-cero'),
        'not_found'          => __('No se encontraron canales.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay canales en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'canal_ciudadano',
        array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => true,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-warning',
            'supports'           => array('title', 'editor', 'page-attributes', 'revisions'),
        )
    );
}
add_action('init', 'sitio_cero_register_canal_ciudadano_post_type');

function sitio_cero_add_canal_ciudadano_metaboxes()
{
    add_meta_box(
        'sitio_cero_canal_ciudadano_settings',
        __('Configuracion de portada', 'sitio-cero'),
        'sitio_cero_render_canal_ciudadano_metabox',
        'canal_ciudadano',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_canal_ciudadano_metaboxes');

function sitio_cero_render_canal_ciudadano_metabox($post)
{
    wp_nonce_field('sitio_cero_save_canal_ciudadano_meta', 'sitio_cero_canal_ciudadano_meta_nonce');

    $visible_value = get_post_meta($post->ID, 'sitio_cero_canal_visible', true);
    $visible = '' === (string) $visible_value || '1' === (string) $visible_value;
    $button_label = get_post_meta($post->ID, 'sitio_cero_canal_button_label', true);
    $button_url = get_post_meta($post->ID, 'sitio_cero_canal_button_url', true);

    if (!is_string($button_label)) {
        $button_label = '';
    }
    if (!is_string($button_url)) {
        $button_url = '';
    }
    ?>
    <p>
        <label>
            <input type="checkbox" name="sitio_cero_canal_visible" value="1"<?php checked($visible); ?>>
            <?php esc_html_e('Mostrar este bloque en el front page', 'sitio-cero'); ?>
        </label>
    </p>
    <p>
        <label for="sitio_cero_canal_button_label"><strong><?php esc_html_e('Texto del boton (opcional)', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_canal_button_label" type="text" name="sitio_cero_canal_button_label" class="widefat" value="<?php echo esc_attr($button_label); ?>" placeholder="<?php esc_attr_e('Ejemplo: Ir a canales de atencion', 'sitio-cero'); ?>">
    </p>
    <p>
        <label for="sitio_cero_canal_button_url"><strong><?php esc_html_e('URL del boton (opcional)', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_canal_button_url" type="url" name="sitio_cero_canal_button_url" class="widefat" value="<?php echo esc_attr(esc_url_raw($button_url)); ?>" placeholder="#canales">
    </p>
    <p class="description">
        <?php esc_html_e('Si creas varios canales visibles, se mostrara el primero segun orden de menu.', 'sitio-cero'); ?>
    </p>
    <?php
}

function sitio_cero_save_canal_ciudadano_meta($post_id)
{
    if (!isset($_POST['sitio_cero_canal_ciudadano_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_canal_ciudadano_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_canal_ciudadano_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_visible = isset($_POST['sitio_cero_canal_visible']) && '1' === (string) wp_unslash($_POST['sitio_cero_canal_visible']);
    update_post_meta($post_id, 'sitio_cero_canal_visible', $is_visible ? '1' : '0');

    $button_label = isset($_POST['sitio_cero_canal_button_label'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_canal_button_label']))
        : '';
    if ('' !== $button_label) {
        update_post_meta($post_id, 'sitio_cero_canal_button_label', $button_label);
    } else {
        delete_post_meta($post_id, 'sitio_cero_canal_button_label');
    }

    $button_url = isset($_POST['sitio_cero_canal_button_url'])
        ? esc_url_raw(wp_unslash($_POST['sitio_cero_canal_button_url']))
        : '';
    if ('' !== $button_url) {
        update_post_meta($post_id, 'sitio_cero_canal_button_url', $button_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_canal_button_url');
    }
}
add_action('save_post_canal_ciudadano', 'sitio_cero_save_canal_ciudadano_meta');

function sitio_cero_seed_default_canal_ciudadano()
{
    if (!post_type_exists('canal_ciudadano')) {
        return;
    }

    $seed_version = '1';
    $already_seeded_version = (string) get_option('sitio_cero_default_canal_ciudadano_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $title = __('Canal de reportes y emergencias urbanas', 'sitio-cero');
    $slug = sanitize_title($title);

    $existing = get_posts(
        array(
            'post_type'      => 'canal_ciudadano',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'name'           => $slug,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing)) {
        $post_id = (int) $existing[0];
    } else {
        $post_id = wp_insert_post(
            array(
                'post_type'    => 'canal_ciudadano',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_content' => __('Si detectas luminarias apagadas, semáforos con falla o situación de riesgo vial, ingresa tu reporte en línea y recibe seguimiento.', 'sitio-cero'),
                'menu_order'   => 1,
            ),
            true
        );
    }

    if (is_wp_error($post_id) || !$post_id) {
        return;
    }

    update_post_meta((int) $post_id, '_sitio_cero_demo_canal_ciudadano', '1');
    update_post_meta((int) $post_id, 'sitio_cero_canal_visible', '1');
    update_post_meta((int) $post_id, 'sitio_cero_canal_button_label', __('Ir a canales de atención', 'sitio-cero'));
    update_post_meta((int) $post_id, 'sitio_cero_canal_button_url', '#canales');

    update_option('sitio_cero_default_canal_ciudadano_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_canal_ciudadano', 47);

function sitio_cero_register_evento_municipal_post_type()
{
    $labels = array(
        'name'               => __('Actividades', 'sitio-cero'),
        'singular_name'      => __('Actividad', 'sitio-cero'),
        'menu_name'          => __('Próximas actividades', 'sitio-cero'),
        'name_admin_bar'     => __('Actividad', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar actividad', 'sitio-cero'),
        'new_item'           => __('Nueva actividad', 'sitio-cero'),
        'edit_item'          => __('Editar actividad', 'sitio-cero'),
        'view_item'          => __('Ver actividad', 'sitio-cero'),
        'all_items'          => __('Todas las actividades', 'sitio-cero'),
        'search_items'       => __('Buscar actividades', 'sitio-cero'),
        'not_found'          => __('No se encontraron actividades.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay actividades en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'evento_municipal',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'has_archive'       => true,
            'rewrite'           => array('slug' => 'eventos'),
            'menu_position'     => 23,
            'menu_icon'         => 'dashicons-calendar-alt',
            'supports'          => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions'),
        )
    );
}
add_action('init', 'sitio_cero_register_evento_municipal_post_type');

function sitio_cero_flush_eventos_rewrite_rules_once()
{
    if (!post_type_exists('evento_municipal')) {
        return;
    }

    $flush_version = '1';
    $stored_version = (string) get_option('sitio_cero_eventos_rewrite_flushed_version', '');
    if ($flush_version === $stored_version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('sitio_cero_eventos_rewrite_flushed_version', $flush_version);
}
add_action('init', 'sitio_cero_flush_eventos_rewrite_rules_once', 99);

function sitio_cero_add_evento_municipal_metaboxes()
{
    add_meta_box(
        'sitio_cero_evento_municipal_datos',
        __('Datos del evento', 'sitio-cero'),
        'sitio_cero_render_evento_municipal_metabox',
        'evento_municipal',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_evento_municipal_metaboxes');

function sitio_cero_sanitize_evento_date($value)
{
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return '';
    }

    $parts = explode('-', $value);
    if (3 !== count($parts)) {
        return '';
    }

    $year = (int) $parts[0];
    $month = (int) $parts[1];
    $day = (int) $parts[2];

    if (!checkdate($month, $day, $year)) {
        return '';
    }

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function sitio_cero_sanitize_evento_time($value)
{
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }

    if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
        return '';
    }

    $parts = explode(':', $value);
    if (2 !== count($parts)) {
        return '';
    }

    $hours = (int) $parts[0];
    $minutes = (int) $parts[1];

    if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
        return '';
    }

    return sprintf('%02d:%02d', $hours, $minutes);
}

function sitio_cero_get_evento_fecha($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $date_value = get_post_meta($post_id, 'sitio_cero_evento_fecha', true);
    if (!is_string($date_value)) {
        return '';
    }

    return sitio_cero_sanitize_evento_date($date_value);
}

function sitio_cero_get_evento_hora($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $time_value = get_post_meta($post_id, 'sitio_cero_evento_hora', true);
    if (!is_string($time_value)) {
        return '';
    }

    return sitio_cero_sanitize_evento_time($time_value);
}

function sitio_cero_format_evento_fecha($date_value, $format = 'd M')
{
    $clean_date = sitio_cero_sanitize_evento_date($date_value);
    if ('' === $clean_date) {
        return '';
    }

    $timestamp = strtotime($clean_date . ' 12:00:00');
    if (false === $timestamp) {
        return '';
    }

    $formatted = wp_date($format, $timestamp);
    return is_string($formatted) ? trim($formatted) : '';
}

function sitio_cero_get_evento_badge_fecha($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $date_value = sitio_cero_get_evento_fecha($post_id);
    if ('' === $date_value) {
        $date_value = get_post_time('Y-m-d', false, $post_id);
    }

    $badge = sitio_cero_format_evento_fecha($date_value, 'd M');
    if ('' === $badge) {
        return '';
    }

    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($badge, 'UTF-8');
    }

    return strtoupper($badge);
}

function sitio_cero_get_evento_full_fecha($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $date_value = sitio_cero_get_evento_fecha($post_id);
    if ('' === $date_value) {
        $date_value = get_post_time('Y-m-d', false, $post_id);
    }

    return sitio_cero_format_evento_fecha($date_value, 'd \d\e F \d\e Y');
}

function sitio_cero_get_evento_mapa_embed_url($map_url, $place = '')
{
    $map_url = trim((string) $map_url);
    $place = trim((string) $place);

    if ('' === $map_url && '' === $place) {
        return '';
    }

    $query_text = '';
    $clean_url = '';

    if ('' !== $map_url) {
        $clean_url = esc_url_raw($map_url);
        if ('' !== $clean_url) {
            $lower_url = strtolower($clean_url);
            if (
                false !== strpos($lower_url, 'google.com/maps/embed')
                || false !== strpos($lower_url, 'google.com/maps/d/embed')
            ) {
                return $clean_url;
            }

            $parsed_query = wp_parse_url($clean_url, PHP_URL_QUERY);
            if (is_string($parsed_query) && '' !== $parsed_query) {
                $query_args = array();
                parse_str($parsed_query, $query_args);

                if (is_array($query_args)) {
                    $candidate_keys = array('q', 'query', 'destination', 'daddr');
                    foreach ($candidate_keys as $candidate_key) {
                        if (!isset($query_args[$candidate_key])) {
                            continue;
                        }

                        $candidate_value = trim((string) $query_args[$candidate_key]);
                        if ('' !== $candidate_value) {
                            $query_text = $candidate_value;
                            break;
                        }
                    }
                }
            }

            if ('' === $query_text) {
                $path = wp_parse_url($clean_url, PHP_URL_PATH);
                if (is_string($path) && false !== strpos($path, '/maps/place/')) {
                    $place_parts = explode('/maps/place/', $path, 2);
                    if (isset($place_parts[1]) && '' !== trim((string) $place_parts[1])) {
                        $place_path = (string) $place_parts[1];
                        $place_segments = explode('/', $place_path);
                        $first_segment = isset($place_segments[0]) ? (string) $place_segments[0] : '';
                        if ('' !== trim($first_segment)) {
                            $query_text = str_replace('+', ' ', rawurldecode($first_segment));
                        }
                    }
                }
            }
        }
    }

    if ('' === $query_text && '' !== $place) {
        $query_text = $place;
    }

    if ('' === $query_text && '' !== $clean_url) {
        $query_text = $clean_url;
    }

    if ('' === trim($query_text)) {
        return '';
    }

    $embed_url = add_query_arg(
        array(
            'q'      => $query_text,
            'output' => 'embed',
        ),
        'https://www.google.com/maps'
    );

    return esc_url_raw($embed_url);
}

function sitio_cero_get_home_eventos($limit = 3)
{
    $limit = absint($limit);
    if ($limit <= 0 || !post_type_exists('evento_municipal')) {
        return array();
    }

    $today = wp_date('Y-m-d');
    $posts = get_posts(
        array(
            'post_type'      => 'evento_municipal',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => 'sitio_cero_evento_fecha',
            'meta_type'      => 'DATE',
            'orderby'        => array(
                'meta_value' => 'ASC',
                'menu_order' => 'ASC',
                'date'       => 'ASC',
            ),
            'meta_query'     => array(
                array(
                    'key'     => 'sitio_cero_evento_fecha',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
            'no_found_rows'  => true,
        )
    );

    if (!is_array($posts)) {
        $posts = array();
    }

    return $posts;
}

function sitio_cero_render_evento_municipal_metabox($post)
{
    wp_nonce_field('sitio_cero_save_evento_municipal_meta', 'sitio_cero_evento_municipal_meta_nonce');

    $date_value = sitio_cero_get_evento_fecha($post->ID);
    $time_value = sitio_cero_get_evento_hora($post->ID);
    $place_value = get_post_meta($post->ID, 'sitio_cero_evento_lugar', true);
    $map_value = get_post_meta($post->ID, 'sitio_cero_evento_mapa_url', true);

    if (!is_string($place_value)) {
        $place_value = '';
    }
    if (!is_string($map_value)) {
        $map_value = '';
    }
    ?>
    <p>
        <label for="sitio_cero_evento_fecha"><strong><?php esc_html_e('Fecha del evento', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_evento_fecha"
            name="sitio_cero_evento_fecha"
            type="date"
            class="widefat"
            value="<?php echo esc_attr($date_value); ?>"
        >
    </p>

    <p>
        <label for="sitio_cero_evento_hora"><strong><?php esc_html_e('Hora (opcional)', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_evento_hora"
            name="sitio_cero_evento_hora"
            type="time"
            class="widefat"
            value="<?php echo esc_attr($time_value); ?>"
        >
    </p>

    <p>
        <label for="sitio_cero_evento_lugar"><strong><?php esc_html_e('Lugar (texto)', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_evento_lugar"
            name="sitio_cero_evento_lugar"
            type="text"
            class="widefat"
            value="<?php echo esc_attr($place_value); ?>"
            placeholder="<?php esc_attr_e('Ejemplo: Plaza principal, Concepcion', 'sitio-cero'); ?>"
        >
    </p>

    <p>
        <label for="sitio_cero_evento_mapa_url"><strong><?php esc_html_e('Google Maps o enlace de ubicacion (opcional)', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_evento_mapa_url"
            name="sitio_cero_evento_mapa_url"
            type="url"
            class="widefat"
            value="<?php echo esc_attr(esc_url_raw($map_value)); ?>"
            placeholder="https://maps.google.com/..."
        >
    </p>
    <?php
}

function sitio_cero_save_evento_municipal_meta($post_id)
{
    if (!isset($_POST['sitio_cero_evento_municipal_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_evento_municipal_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_evento_municipal_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $date_value = isset($_POST['sitio_cero_evento_fecha'])
        ? sitio_cero_sanitize_evento_date(wp_unslash($_POST['sitio_cero_evento_fecha']))
        : '';
    if ('' !== $date_value) {
        update_post_meta($post_id, 'sitio_cero_evento_fecha', $date_value);
    } else {
        delete_post_meta($post_id, 'sitio_cero_evento_fecha');
    }

    $time_value = isset($_POST['sitio_cero_evento_hora'])
        ? sitio_cero_sanitize_evento_time(wp_unslash($_POST['sitio_cero_evento_hora']))
        : '';
    if ('' !== $time_value) {
        update_post_meta($post_id, 'sitio_cero_evento_hora', $time_value);
    } else {
        delete_post_meta($post_id, 'sitio_cero_evento_hora');
    }

    $place_value = isset($_POST['sitio_cero_evento_lugar'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_evento_lugar']))
        : '';
    if ('' !== $place_value) {
        update_post_meta($post_id, 'sitio_cero_evento_lugar', $place_value);
    } else {
        delete_post_meta($post_id, 'sitio_cero_evento_lugar');
    }

    $map_value = isset($_POST['sitio_cero_evento_mapa_url'])
        ? esc_url_raw(wp_unslash($_POST['sitio_cero_evento_mapa_url']))
        : '';
    if ('' !== $map_value) {
        update_post_meta($post_id, 'sitio_cero_evento_mapa_url', $map_value);
    } else {
        delete_post_meta($post_id, 'sitio_cero_evento_mapa_url');
    }
}
add_action('save_post_evento_municipal', 'sitio_cero_save_evento_municipal_meta');

function sitio_cero_seed_default_eventos_municipales()
{
    if (!post_type_exists('evento_municipal')) {
        return;
    }

    $seed_version = '1';
    $already_seeded_version = (string) get_option('sitio_cero_default_eventos_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $existing_items = get_posts(
        array(
            'post_type'      => 'evento_municipal',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing_items)) {
        update_option('sitio_cero_default_eventos_seeded_version', $seed_version);
        return;
    }

    $base_timestamp = (int) current_time('timestamp');
    $defaults = array(
        array(
            'offset_days' => 6,
            'title'       => __('Operativo de limpieza barrial', 'sitio-cero'),
            'content'     => __('Equipo municipal desplegado para retiro de residuos voluminosos y recuperacion de espacios comunes.', 'sitio-cero'),
            'lugar'       => __('Plaza principal de Concepcion', 'sitio-cero'),
            'hora'        => '09:30',
            'mapa'        => 'https://maps.google.com/?q=Plaza+de+la+Independencia+Concepcion',
        ),
        array(
            'offset_days' => 10,
            'title'       => __('Feria de servicios municipales', 'sitio-cero'),
            'content'     => __('Atencion en terreno con orientacion de tramites, beneficios sociales y programas comunitarios.', 'sitio-cero'),
            'lugar'       => __('Centro comunitario Lorenzo Arenas', 'sitio-cero'),
            'hora'        => '11:00',
            'mapa'        => 'https://maps.google.com/?q=Lorenzo+Arenas+Concepcion',
        ),
        array(
            'offset_days' => 14,
            'title'       => __('Cabildo ciudadano sector norte', 'sitio-cero'),
            'content'     => __('Instancia participativa para levantar propuestas vecinales y priorizar mejoras urbanas del sector.', 'sitio-cero'),
            'lugar'       => __('Sede vecinal sector norte', 'sitio-cero'),
            'hora'        => '18:30',
            'mapa'        => 'https://maps.google.com/?q=Concepcion+Chile',
        ),
    );

    foreach ($defaults as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
        $content = isset($item['content']) ? (string) $item['content'] : '';
        if ('' === $title) {
            continue;
        }

        $offset_days = isset($item['offset_days']) ? (int) $item['offset_days'] : 0;
        $event_timestamp = strtotime('+' . $offset_days . ' days', $base_timestamp);
        if (false === $event_timestamp) {
            $event_timestamp = $base_timestamp;
        }
        $event_date = wp_date('Y-m-d', $event_timestamp);

        $post_id = wp_insert_post(
            array(
                'post_type'    => 'evento_municipal',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_content' => $content,
                'menu_order'   => (int) $index,
            ),
            true
        );

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta($post_id, '_sitio_cero_demo_evento_municipal', '1');
        update_post_meta($post_id, 'sitio_cero_evento_fecha', $event_date);

        $event_time = isset($item['hora']) ? sitio_cero_sanitize_evento_time($item['hora']) : '';
        if ('' !== $event_time) {
            update_post_meta($post_id, 'sitio_cero_evento_hora', $event_time);
        }

        $place = isset($item['lugar']) ? sanitize_text_field((string) $item['lugar']) : '';
        if ('' !== $place) {
            update_post_meta($post_id, 'sitio_cero_evento_lugar', $place);
        }

        $map_url = isset($item['mapa']) ? esc_url_raw((string) $item['mapa']) : '';
        if ('' !== $map_url) {
            update_post_meta($post_id, 'sitio_cero_evento_mapa_url', $map_url);
        }
    }

    update_option('sitio_cero_default_eventos_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_eventos_municipales', 48);

function sitio_cero_add_direccion_municipal_metaboxes()
{
    add_meta_box(
        'sitio_cero_direccion_municipal_datos',
        __('Estructura de la direccion municipal', 'sitio-cero'),
        'sitio_cero_render_direccion_municipal_metabox',
        'direccion_municipal',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_direccion_municipal_metaboxes');

function sitio_cero_get_direccion_allowed_html()
{
    $allowed = wp_kses_allowed_html('post');

    $allowed['iframe'] = array(
        'class'           => true,
        'id'              => true,
        'src'             => true,
        'title'           => true,
        'width'           => true,
        'height'          => true,
        'frameborder'     => true,
        'allow'           => true,
        'allowfullscreen' => true,
        'loading'         => true,
        'referrerpolicy'  => true,
        'style'           => true,
    );

    $allowed['video'] = array(
        'class'      => true,
        'id'         => true,
        'src'        => true,
        'controls'   => true,
        'autoplay'   => true,
        'loop'       => true,
        'muted'      => true,
        'playsinline'=> true,
        'poster'     => true,
        'preload'    => true,
        'width'      => true,
        'height'     => true,
        'style'      => true,
    );

    $allowed['source'] = array(
        'src'  => true,
        'type' => true,
    );

    if (!isset($allowed['div'])) {
        $allowed['div'] = array();
    }
    $allowed['div']['class'] = true;
    $allowed['div']['id'] = true;
    $allowed['div']['style'] = true;

    if (!isset($allowed['span'])) {
        $allowed['span'] = array();
    }
    $allowed['span']['class'] = true;
    $allowed['span']['id'] = true;
    $allowed['span']['style'] = true;

    return $allowed;
}

function sitio_cero_sanitize_direccion_html($value)
{
    $value = (string) $value;
    $value = preg_replace('/<\/?script[^>]*>/i', '', $value);
    $value = preg_replace('/\s+on[a-z]+\s*=\s*([\'"]).*?\1/i', '', $value);

    return trim((string) wp_kses($value, sitio_cero_get_direccion_allowed_html()));
}

function sitio_cero_sanitize_css_shorthand($value, $max_length = 120)
{
    $value = trim((string) $value);
    if ('' === $value) {
        return '';
    }

    $value = preg_replace('/[^#,%.\-a-zA-Z0-9\s()\/]/', '', $value);
    $value = preg_replace('/\s+/', ' ', (string) $value);
    $value = trim((string) $value);

    $max_length = absint($max_length);
    if ($max_length <= 0) {
        $max_length = 120;
    }

    return substr($value, 0, $max_length);
}

function sitio_cero_sanitize_direccion_subtabs($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($value)) {
        return array();
    }

    $items = array();

    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
        $content = isset($item['content']) ? sitio_cero_sanitize_direccion_html((string) $item['content']) : '';

        if ('' === $title && '' === trim(wp_strip_all_tags($content))) {
            continue;
        }

        $items[] = array(
            'title'   => $title,
            'content' => $content,
        );
    }

    return $items;
}

function sitio_cero_sanitize_direccion_resource_blocks($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($value)) {
        return array();
    }

    $blocks = array();
    foreach ($value as $block) {
        if (!is_array($block)) {
            continue;
        }

        $title = isset($block['title']) ? sanitize_text_field((string) $block['title']) : '';
        $type = isset($block['type']) ? sanitize_key((string) $block['type']) : 'documentos';
        if (!in_array($type, array('documentos', 'archivos'), true)) {
            $type = 'documentos';
        }

        $html = isset($block['html']) ? sitio_cero_sanitize_direccion_html((string) $block['html']) : '';
        $links = isset($block['links']) ? sitio_cero_sanitize_aviso_links_textarea((string) $block['links']) : '';

        if (
            '' === $title
            && '' === trim((string) wp_strip_all_tags($html))
            && '' === trim($links)
        ) {
            continue;
        }

        $blocks[] = array(
            'title' => $title,
            'type'  => $type,
            'html'  => $html,
            'links' => $links,
        );
    }

    return $blocks;
}

function sitio_cero_sanitize_direccion_buttons($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($value)) {
        return array();
    }

    $items = array();

    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
        $url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
        $target = isset($item['target']) && '_blank' === $item['target'] ? '_blank' : '';

        if ('' === $label && '' === $url) {
            continue;
        }

        $items[] = array(
            'label'  => $label,
            'url'    => $url,
            'target' => $target,
        );
    }

    return $items;
}

function sitio_cero_sanitize_direccion_accordions($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($value)) {
        return array();
    }

    $items = array();

    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
        $content = isset($item['content']) ? sitio_cero_sanitize_direccion_html((string) $item['content']) : '';

        if ('' === $title && '' === trim((string) wp_strip_all_tags($content))) {
            continue;
        }

        $items[] = array(
            'title'   => $title,
            'content' => $content,
        );
    }

    return $items;
}

function sitio_cero_sanitize_direccion_sections($value)
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($value)) {
        return array();
    }

    $sections = array();
    $valid_styles = array('paper', 'soft', 'dark');
    $valid_button_styles = array('pill', 'card', 'card-dark');

    foreach ($value as $section) {
        if (!is_array($section)) {
            continue;
        }

        $title = isset($section['title']) ? sanitize_text_field((string) $section['title']) : '';
        $anchor = isset($section['anchor']) ? sanitize_title(ltrim((string) $section['anchor'], '#')) : '';
        $kicker = isset($section['kicker']) ? sanitize_text_field((string) $section['kicker']) : '';
        $content = isset($section['content']) ? sitio_cero_sanitize_direccion_html((string) $section['content']) : '';

        $style = isset($section['style']) ? sanitize_key((string) $section['style']) : '';
        if (!in_array($style, $valid_styles, true)) {
            $style = '';
        }

        $buttons_style = isset($section['buttons_style']) ? sanitize_key((string) $section['buttons_style']) : '';
        if (!in_array($buttons_style, $valid_button_styles, true)) {
            $buttons_style = '';
        }

        $buttons = isset($section['buttons']) ? sitio_cero_sanitize_direccion_buttons($section['buttons']) : array();
        $accordions = isset($section['accordions']) ? sitio_cero_sanitize_direccion_accordions($section['accordions']) : array();
        $subtabs = isset($section['subtabs']) ? sitio_cero_sanitize_direccion_subtabs($section['subtabs']) : array();

        if (
            '' === $title
            && '' === $kicker
            && '' === trim((string) wp_strip_all_tags($content))
            && empty($buttons)
            && empty($accordions)
            && empty($subtabs)
        ) {
            continue;
        }

        $sections[] = array(
            'title'         => $title,
            'anchor'        => $anchor,
            'kicker'        => $kicker,
            'content'       => $content,
            'style'         => $style,
            'buttons_style' => $buttons_style,
            'buttons'       => $buttons,
            'accordions'    => $accordions,
            'subtabs'       => $subtabs,
        );
    }

    return $sections;
}

function sitio_cero_get_direccion_resource_blocks($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $raw_blocks = get_post_meta($post_id, 'sitio_cero_direccion_resource_blocks', true);
    if (!is_array($raw_blocks)) {
        return array();
    }

    return sitio_cero_sanitize_direccion_resource_blocks($raw_blocks);
}

function sitio_cero_get_direccion_sections($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $raw_sections = get_post_meta($post_id, 'sitio_cero_direccion_sections', true);
    if (!is_array($raw_sections) && !is_string($raw_sections)) {
        return array();
    }

    return sitio_cero_sanitize_direccion_sections($raw_sections);
}

function sitio_cero_render_direccion_municipal_metabox($post)
{
    wp_nonce_field('sitio_cero_save_direccion_municipal_meta', 'sitio_cero_direccion_municipal_meta_nonce');

    $director = get_post_meta($post->ID, 'sitio_cero_direccion_director', true);
    $profesion = get_post_meta($post->ID, 'sitio_cero_direccion_profesion', true);
    $telefonos = get_post_meta($post->ID, 'sitio_cero_direccion_telefonos', true);
    $email = get_post_meta($post->ID, 'sitio_cero_direccion_email', true);
    $direccion = get_post_meta($post->ID, 'sitio_cero_direccion_direccion', true);
    $mapa_url = get_post_meta($post->ID, 'sitio_cero_direccion_mapa_url', true);
    $custom_html = get_post_meta($post->ID, 'sitio_cero_direccion_custom_html', true);
    $custom_css = get_post_meta($post->ID, 'sitio_cero_direccion_custom_css', true);

    if (!is_string($director)) {
        $director = '';
    }
    if (!is_string($profesion)) {
        $profesion = '';
    }
    if (!is_array($telefonos)) {
        $telefonos = array();
    }
    if (empty($telefonos)) {
        $telefonos = array('');
    }
    if (!is_string($email)) {
        $email = '';
    }
    if (!is_string($direccion)) {
        $direccion = '';
    }
    if (!is_string($mapa_url)) {
        $mapa_url = '';
    }
    if (!is_string($custom_html)) {
        $custom_html = '';
    }
    if (!is_string($custom_css)) {
        $custom_css = '';
    }

    $documentos = get_post_meta($post->ID, 'sitio_cero_direccion_documentos', true);
    if (!is_string($documentos)) {
        $documentos = '';
    }
    $archivos = get_post_meta($post->ID, 'sitio_cero_direccion_archivos', true);
    if (!is_string($archivos)) {
        $archivos = '';
    }
    $recursos_titulo = get_post_meta($post->ID, 'sitio_cero_direccion_recursos_titulo', true);
    if (!is_string($recursos_titulo)) {
        $recursos_titulo = '';
    }
    $documentos_titulo = get_post_meta($post->ID, 'sitio_cero_direccion_documentos_titulo', true);
    if (!is_string($documentos_titulo)) {
        $documentos_titulo = '';
    }
    $archivos_titulo = get_post_meta($post->ID, 'sitio_cero_direccion_archivos_titulo', true);
    if (!is_string($archivos_titulo)) {
        $archivos_titulo = '';
    }
    $documentos_html = get_post_meta($post->ID, 'sitio_cero_direccion_documentos_html', true);
    if (!is_string($documentos_html)) {
        $documentos_html = '';
    }
    $archivos_html = get_post_meta($post->ID, 'sitio_cero_direccion_archivos_html', true);
    if (!is_string($archivos_html)) {
        $archivos_html = '';
    }
    $icon_options = sitio_cero_get_aviso_file_icon_options();
    $resource_type_options = array(
        'documentos' => __('Documentos', 'sitio-cero'),
        'archivos'   => __('Archivos', 'sitio-cero'),
    );
    $resource_blocks = sitio_cero_get_direccion_resource_blocks($post->ID);
    $sections = function_exists('sitio_cero_get_direccion_sections')
        ? sitio_cero_get_direccion_sections($post->ID)
        : array();

    if (empty($resource_blocks)) {
        if ('' !== trim($documentos) || '' !== trim((string) wp_strip_all_tags($documentos_html)) || '' !== trim($documentos_titulo)) {
            $resource_blocks[] = array(
                'title' => '' !== trim($documentos_titulo) ? $documentos_titulo : __('Documentos', 'sitio-cero'),
                'type'  => 'documentos',
                'html'  => $documentos_html,
                'links' => $documentos,
            );
        }

        if ('' !== trim($archivos) || '' !== trim((string) wp_strip_all_tags($archivos_html)) || '' !== trim($archivos_titulo)) {
            $resource_blocks[] = array(
                'title' => '' !== trim($archivos_titulo) ? $archivos_titulo : __('Archivos', 'sitio-cero'),
                'type'  => 'archivos',
                'html'  => $archivos_html,
                'links' => $archivos,
            );
        }
    }

    if (empty($resource_blocks)) {
        $resource_blocks[] = array(
            'title' => __('Documentos', 'sitio-cero'),
            'type'  => 'documentos',
            'html'  => '',
            'links' => '',
        );
    }

    $section_style_options = array(
        ''      => __('Predeterminado', 'sitio-cero'),
        'paper' => __('Papel', 'sitio-cero'),
        'soft'  => __('Suave', 'sitio-cero'),
        'dark'  => __('Oscuro', 'sitio-cero'),
    );
    $section_button_style_options = array(
        'pill'      => __('Blanco', 'sitio-cero'),
        'card'      => __('Azul A', 'sitio-cero'),
        'card-dark' => __('Azul B', 'sitio-cero'),
    );

    ?>
    <div class="sitio-cero-dm-metabox">
        <h3><?php esc_html_e('Columna 1: Organizacion', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-dm-grid">
            <p>
                <label for="sitio_cero_direccion_director"><strong><?php esc_html_e('Director', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_direccion_director" name="sitio_cero_direccion_director" type="text" class="widefat" value="<?php echo esc_attr($director); ?>" placeholder="<?php esc_attr_e('Nombre del director o directora', 'sitio-cero'); ?>">
            </p>
            <p>
                <label for="sitio_cero_direccion_profesion"><strong><?php esc_html_e('Profesion (opcional)', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_direccion_profesion" name="sitio_cero_direccion_profesion" type="text" class="widefat" value="<?php echo esc_attr($profesion); ?>" placeholder="<?php esc_attr_e('Ejemplo: Administrador publico', 'sitio-cero'); ?>">
            </p>
            <p>
                <label for="sitio_cero_direccion_email"><strong><?php esc_html_e('Email', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_direccion_email" name="sitio_cero_direccion_email" type="email" class="widefat" value="<?php echo esc_attr($email); ?>" placeholder="direccion@municipio.cl">
            </p>
            <p>
                <label for="sitio_cero_direccion_direccion"><strong><?php esc_html_e('Direccion', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_direccion_direccion" name="sitio_cero_direccion_direccion" type="text" class="widefat" value="<?php echo esc_attr($direccion); ?>" placeholder="<?php esc_attr_e('Calle, numero, comuna', 'sitio-cero'); ?>">
            </p>
        </div>

        <p><strong><?php esc_html_e('Telefonos (puedes agregar mas de uno)', 'sitio-cero'); ?></strong></p>
        <div class="sitio-cero-dm-phones">
            <div class="sitio-cero-dm-phones__list" data-phones-list>
                <?php foreach ($telefonos as $telefono) : ?>
                    <div class="sitio-cero-dm-phones__row" data-phone-row>
                        <input type="text" class="widefat" name="sitio_cero_direccion_telefonos[]" value="<?php echo esc_attr(sanitize_text_field((string) $telefono)); ?>" placeholder="+56 41 220 0000">
                        <button type="button" class="button-link-delete" data-phone-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button button-secondary" data-phone-add><?php esc_html_e('Agregar telefono', 'sitio-cero'); ?></button>
            <template data-phone-template>
                <div class="sitio-cero-dm-phones__row" data-phone-row>
                    <input type="text" class="widefat" name="sitio_cero_direccion_telefonos[]" value="" placeholder="+56 41 220 0000">
                    <button type="button" class="button-link-delete" data-phone-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Columna 2: Mapa (Google Maps)', 'sitio-cero'); ?></h3>
        <p>
            <label for="sitio_cero_direccion_mapa_url"><strong><?php esc_html_e('URL embed de Google Maps (opcional)', 'sitio-cero'); ?></strong></label>
            <input id="sitio_cero_direccion_mapa_url" name="sitio_cero_direccion_mapa_url" type="url" class="widefat" value="<?php echo esc_attr(esc_url_raw($mapa_url)); ?>" placeholder="https://www.google.com/maps/embed?...">
            <small><?php esc_html_e('Si lo dejas vacio, el mapa se generara desde el campo Direccion.', 'sitio-cero'); ?></small>
        </p>

        <hr>
        <h3><?php esc_html_e('Documentos y archivos', 'sitio-cero'); ?></h3>

        <p>
            <label for="sitio_cero_direccion_recursos_titulo"><strong><?php esc_html_e('Titulo principal de la seccion (opcional)', 'sitio-cero'); ?></strong></label>
            <input id="sitio_cero_direccion_recursos_titulo" name="sitio_cero_direccion_recursos_titulo" type="text" class="widefat" value="<?php echo esc_attr($recursos_titulo); ?>" placeholder="<?php esc_attr_e('Ejemplo: Documentos y archivos', 'sitio-cero'); ?>">
        </p>
        <p class="description"><?php esc_html_e('Puedes crear bloques ilimitados. Cada bloque permite definir tipo (documentos o archivos), titulo, texto/HTML embebido e items con enlace e icono.', 'sitio-cero'); ?></p>

        <div class="sitio-cero-dm-resource-blocks" data-dm-resource-blocks>
            <div class="sitio-cero-dm-resource-blocks__list" data-dm-resource-blocks-list>
                <?php foreach ($resource_blocks as $block_index => $resource_block) : ?>
                    <?php
                    $block_type = isset($resource_block['type']) ? sanitize_key((string) $resource_block['type']) : 'documentos';
                    if (!in_array($block_type, array('documentos', 'archivos'), true)) {
                        $block_type = 'documentos';
                    }
                    $block_title = isset($resource_block['title']) ? sanitize_text_field((string) $resource_block['title']) : '';
                    $block_html = isset($resource_block['html']) ? (string) $resource_block['html'] : '';
                    $block_links = isset($resource_block['links']) ? (string) $resource_block['links'] : '';
                    $block_links_items = sitio_cero_parse_aviso_links_textarea($block_links);
                    $block_key = 'existing-' . $block_index;
                    $block_html_id = 'sitio_cero_direccion_resource_block_html_' . $block_key;
                    $block_links_id = 'sitio_cero_direccion_resource_block_links_' . $block_key;
                    ?>
                    <div class="sitio-cero-dm-resource-block" data-dm-resource-block-row>
                        <div class="sitio-cero-dm-resource-block__head">
                            <strong><?php echo esc_html(sprintf(__('Bloque %d', 'sitio-cero'), $block_index + 1)); ?></strong>
                            <button type="button" class="button-link-delete" data-dm-resource-block-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                        </div>
                        <div class="sitio-cero-dm-grid">
                            <p>
                                <label><strong><?php esc_html_e('Titulo del bloque', 'sitio-cero'); ?></strong></label>
                                <input type="text" class="widefat" name="sitio_cero_direccion_resource_block_title[]" value="<?php echo esc_attr($block_title); ?>" placeholder="<?php esc_attr_e('Ejemplo: Documentos tributarios', 'sitio-cero'); ?>">
                            </p>
                            <p>
                                <label><strong><?php esc_html_e('Tipo de bloque', 'sitio-cero'); ?></strong></label>
                                <select class="widefat" name="sitio_cero_direccion_resource_block_type[]">
                                    <?php foreach ($resource_type_options as $type_key => $type_label) : ?>
                                        <option value="<?php echo esc_attr($type_key); ?>"<?php selected($block_type, $type_key); ?>><?php echo esc_html($type_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        </div>

                        <p>
                            <label><strong><?php esc_html_e('Texto / HTML / embebido del bloque (opcional)', 'sitio-cero'); ?></strong></label>
                            <textarea id="<?php echo esc_attr($block_html_id); ?>" class="widefat" rows="5" name="sitio_cero_direccion_resource_block_html[]" placeholder="<?php esc_attr_e('Ejemplo: <p>Informacion del bloque...</p><iframe ...></iframe>', 'sitio-cero'); ?>"><?php echo esc_textarea($block_html); ?></textarea>
                        </p>
                        <p><strong><?php esc_html_e('Items del bloque (titulo + enlace + icono)', 'sitio-cero'); ?></strong></p>
                        <div class="sitio-cero-aviso-files" data-target="#<?php echo esc_attr($block_links_id); ?>">
                            <div class="sitio-cero-aviso-files__actions">
                                <button type="button" class="button button-secondary sitio-cero-aviso-files__library">
                                    <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
                                </button>
                                <button type="button" class="button button-secondary sitio-cero-aviso-files__add">
                                    <?php esc_html_e('Agregar item manual', 'sitio-cero'); ?>
                                </button>
                            </div>
                            <div class="sitio-cero-aviso-files__list" data-file-list>
                                <?php foreach ($block_links_items as $item) : ?>
                                    <?php
                                    $item_label = isset($item['label']) ? (string) $item['label'] : '';
                                    $item_url = isset($item['url']) ? (string) $item['url'] : '';
                                    $item_icon = isset($item['icon']) ? (string) $item['icon'] : '';
                                    ?>
                                    <div class="sitio-cero-aviso-files__row" data-file-row>
                                        <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="<?php echo esc_attr($item_label); ?>">
                                        <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="<?php echo esc_attr($item_url); ?>">
                                        <select class="sitio-cero-aviso-files__select" data-key="icon">
                                            <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                                                <option value="<?php echo esc_attr($icon_key); ?>"<?php selected($item_icon, $icon_key); ?>><?php echo esc_html($icon_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <template class="sitio-cero-aviso-files__template">
                                <div class="sitio-cero-aviso-files__row" data-file-row>
                                    <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="">
                                    <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="">
                                    <select class="sitio-cero-aviso-files__select" data-key="icon">
                                        <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                                            <option value="<?php echo esc_attr($icon_key); ?>"><?php echo esc_html($icon_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                </div>
                            </template>
                        </div>
                        <textarea id="<?php echo esc_attr($block_links_id); ?>" name="sitio_cero_direccion_resource_block_links[]" class="widefat" rows="4" hidden><?php echo esc_textarea($block_links); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-primary" data-dm-resource-block-add><?php esc_html_e('Agregar bloque', 'sitio-cero'); ?></button>

            <template data-dm-resource-block-template>
                <div class="sitio-cero-dm-resource-block" data-dm-resource-block-row>
                    <div class="sitio-cero-dm-resource-block__head">
                        <strong><?php esc_html_e('Bloque', 'sitio-cero'); ?></strong>
                        <button type="button" class="button-link-delete" data-dm-resource-block-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                    <div class="sitio-cero-dm-grid">
                        <p>
                            <label><strong><?php esc_html_e('Titulo del bloque', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_direccion_resource_block_title[]" value="" placeholder="<?php esc_attr_e('Ejemplo: Documentos tributarios', 'sitio-cero'); ?>">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Tipo de bloque', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_direccion_resource_block_type[]">
                                <?php foreach ($resource_type_options as $type_key => $type_label) : ?>
                                    <option value="<?php echo esc_attr($type_key); ?>"<?php selected('documentos', $type_key); ?>><?php echo esc_html($type_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </div>

                    <p>
                        <label><strong><?php esc_html_e('Texto / HTML / embebido del bloque (opcional)', 'sitio-cero'); ?></strong></label>
                        <textarea id="sitio_cero_direccion_resource_block_html___KEY__" class="widefat" rows="5" name="sitio_cero_direccion_resource_block_html[]" placeholder="<?php esc_attr_e('Ejemplo: <p>Informacion del bloque...</p><iframe ...></iframe>', 'sitio-cero'); ?>"></textarea>
                    </p>
                    <p><strong><?php esc_html_e('Items del bloque (titulo + enlace + icono)', 'sitio-cero'); ?></strong></p>
                    <div class="sitio-cero-aviso-files" data-target="#sitio_cero_direccion_resource_block_links___KEY__">
                        <div class="sitio-cero-aviso-files__actions">
                            <button type="button" class="button button-secondary sitio-cero-aviso-files__library">
                                <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
                            </button>
                            <button type="button" class="button button-secondary sitio-cero-aviso-files__add">
                                <?php esc_html_e('Agregar item manual', 'sitio-cero'); ?>
                            </button>
                        </div>
                        <div class="sitio-cero-aviso-files__list" data-file-list></div>
                        <template class="sitio-cero-aviso-files__template">
                            <div class="sitio-cero-aviso-files__row" data-file-row>
                                <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="">
                                <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="">
                                <select class="sitio-cero-aviso-files__select" data-key="icon">
                                    <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                                        <option value="<?php echo esc_attr($icon_key); ?>"><?php echo esc_html($icon_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                            </div>
                        </template>
                    </div>
                    <textarea id="sitio_cero_direccion_resource_block_links___KEY__" name="sitio_cero_direccion_resource_block_links[]" class="widefat" rows="4" hidden></textarea>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Bloque libre adicional', 'sitio-cero'); ?></h3>
        <p>
            <label for="sitio_cero_direccion_custom_html"><strong><?php esc_html_e('HTML libre (video, embebidos, estructura)', 'sitio-cero'); ?></strong></label>
            <textarea id="sitio_cero_direccion_custom_html" name="sitio_cero_direccion_custom_html" class="widefat" rows="7" placeholder="<?php esc_attr_e('Ejemplo: <h3>Atencion ciudadana</h3><p>Texto...</p><iframe ...></iframe>', 'sitio-cero'); ?>"><?php echo esc_textarea($custom_html); ?></textarea>
        </p>
        <p>
            <label for="sitio_cero_direccion_custom_css"><strong><?php esc_html_e('CSS libre (opcional)', 'sitio-cero'); ?></strong></label>
            <textarea id="sitio_cero_direccion_custom_css" name="sitio_cero_direccion_custom_css" class="widefat" rows="6" placeholder="<?php esc_attr_e('Usa {{selector}} para apuntar este contenido.', 'sitio-cero'); ?>"><?php echo esc_textarea($custom_css); ?></textarea>
        </p>

        <hr>
        <h3><?php esc_html_e('Secciones adicionales', 'sitio-cero'); ?></h3>
        <p class="description"><?php esc_html_e('Crea secciones que se agregan al final de la página. Cada sección puede incluir botones, acordeones y pestañas.', 'sitio-cero'); ?></p>

        <div class="sitio-cero-dm-sections" data-dm-sections>
            <div class="sitio-cero-dm-sections__list" data-dm-sections-list>
                <?php foreach ($sections as $section_index => $section) : ?>
                    <?php
                    $section_title = isset($section['title']) ? (string) $section['title'] : '';
                    $section_anchor = isset($section['anchor']) ? (string) $section['anchor'] : '';
                    $section_kicker = isset($section['kicker']) ? (string) $section['kicker'] : '';
                    $section_content = isset($section['content']) ? (string) $section['content'] : '';
                    $section_style = isset($section['style']) ? (string) $section['style'] : '';
                    $section_buttons_style = isset($section['buttons_style']) && '' !== $section['buttons_style']
                        ? (string) $section['buttons_style']
                        : 'pill';
                    $section_buttons = isset($section['buttons']) && is_array($section['buttons']) ? $section['buttons'] : array();
                    $section_accordions = isset($section['accordions']) && is_array($section['accordions']) ? $section['accordions'] : array();
                    $section_subtabs = isset($section['subtabs']) && is_array($section['subtabs']) ? $section['subtabs'] : array();
                    ?>
                    <div class="sitio-cero-dm-section" data-dm-section-row data-section-index="<?php echo esc_attr((string) $section_index); ?>">
                        <div class="sitio-cero-dm-section__head">
                            <strong data-section-label><?php echo esc_html(sprintf(__('Sección %d', 'sitio-cero'), $section_index + 1)); ?></strong>
                            <button type="button" class="button-link-delete" data-dm-section-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                        </div>

                        <div class="sitio-cero-dm-grid">
                            <p>
                                <label><strong><?php esc_html_e('Título de la sección', 'sitio-cero'); ?></strong></label>
                                <input type="text" class="widefat" name="sitio_cero_direccion_section_title[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_title[__SECTION__]" value="<?php echo esc_attr($section_title); ?>">
                            </p>
                            <p>
                                <label><strong><?php esc_html_e('Anchor (opcional)', 'sitio-cero'); ?></strong></label>
                                <input type="text" class="widefat" name="sitio_cero_direccion_section_anchor[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_anchor[__SECTION__]" value="<?php echo esc_attr($section_anchor); ?>" placeholder="mi-seccion">
                            </p>
                            <p>
                                <label><strong><?php esc_html_e('Kicker (opcional)', 'sitio-cero'); ?></strong></label>
                                <input type="text" class="widefat" name="sitio_cero_direccion_section_kicker[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_kicker[__SECTION__]" value="<?php echo esc_attr($section_kicker); ?>">
                            </p>
                            <p>
                                <label><strong><?php esc_html_e('Estilo de fondo', 'sitio-cero'); ?></strong></label>
                                <select class="widefat" name="sitio_cero_direccion_section_style[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_style[__SECTION__]">
                                    <?php foreach ($section_style_options as $style_key => $style_label) : ?>
                                        <option value="<?php echo esc_attr($style_key); ?>"<?php selected($section_style, $style_key); ?>><?php echo esc_html($style_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                        </div>

                        <p>
                            <label><strong><?php esc_html_e('Contenido principal (HTML)', 'sitio-cero'); ?></strong></label>
                            <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_content[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_content[__SECTION__]"><?php echo esc_textarea($section_content); ?></textarea>
                        </p>

                        <div class="sitio-cero-dm-section__group">
                            <div class="sitio-cero-dm-section__group-head">
                                <strong><?php esc_html_e('Botones', 'sitio-cero'); ?></strong>
                            </div>
                            <p>
                                <label><strong><?php esc_html_e('Estilo de botones', 'sitio-cero'); ?></strong></label>
                                <select class="widefat" name="sitio_cero_direccion_section_buttons_style[<?php echo esc_attr((string) $section_index); ?>]" data-name-template="sitio_cero_direccion_section_buttons_style[__SECTION__]">
                                    <?php foreach ($section_button_style_options as $style_key => $style_label) : ?>
                                        <option value="<?php echo esc_attr($style_key); ?>"<?php selected($section_buttons_style, $style_key); ?>><?php echo esc_html($style_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </p>
                            <div class="sitio-cero-dm-repeater" data-dm-repeater>
                                <div class="sitio-cero-dm-repeater__list" data-dm-list>
                                    <?php foreach ($section_buttons as $button) : ?>
                                        <?php
                                        $button_label = isset($button['label']) ? (string) $button['label'] : '';
                                        $button_url = isset($button['url']) ? (string) $button['url'] : '';
                                        $button_target = isset($button['target']) ? (string) $button['target'] : '';
                                        ?>
                                        <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--actions" data-dm-row>
                                            <p>
                                                <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                                                <input type="text" class="widefat" name="sitio_cero_direccion_section_button_label[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_button_label[__SECTION__][]" value="<?php echo esc_attr($button_label); ?>">
                                            </p>
                                            <p>
                                                <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                                                <input type="url" class="widefat" name="sitio_cero_direccion_section_button_url[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_button_url[__SECTION__][]" value="<?php echo esc_url($button_url); ?>">
                                            </p>
                                            <p>
                                                <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                                                <select class="widefat" name="sitio_cero_direccion_section_button_target[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_button_target[__SECTION__][]">
                                                    <option value=""<?php selected('', $button_target); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                                    <option value="_blank"<?php selected('_blank', $button_target); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                                                </select>
                                            </p>
                                            <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar botón', 'sitio-cero'); ?></button>
                                <template data-dm-template>
                                    <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--actions" data-dm-row>
                                        <p>
                                            <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_direccion_section_button_label[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_label[__SECTION__][]" value="">
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                                            <input type="url" class="widefat" name="sitio_cero_direccion_section_button_url[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_url[__SECTION__][]" value="">
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                                            <select class="widefat" name="sitio_cero_direccion_section_button_target[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_target[__SECTION__][]">
                                                <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                                <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                                            </select>
                                        </p>
                                        <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="sitio-cero-dm-section__group">
                            <div class="sitio-cero-dm-section__group-head">
                                <strong><?php esc_html_e('Acordeones', 'sitio-cero'); ?></strong>
                            </div>
                            <div class="sitio-cero-dm-repeater" data-dm-repeater>
                                <div class="sitio-cero-dm-repeater__list" data-dm-list>
                                    <?php foreach ($section_accordions as $accordion) : ?>
                                        <?php
                                        $accordion_title = isset($accordion['title']) ? (string) $accordion['title'] : '';
                                        $accordion_content = isset($accordion['content']) ? (string) $accordion['content'] : '';
                                        ?>
                                        <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                            <p>
                                                <label><strong><?php esc_html_e('Título del acordeón', 'sitio-cero'); ?></strong></label>
                                                <input type="text" class="widefat" name="sitio_cero_direccion_section_accordion_title[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_accordion_title[__SECTION__][]" value="<?php echo esc_attr($accordion_title); ?>">
                                            </p>
                                            <p>
                                                <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                                <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_accordion_content[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_accordion_content[__SECTION__][]"><?php echo esc_textarea($accordion_content); ?></textarea>
                                            </p>
                                            <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar acordeón', 'sitio-cero'); ?></button>
                                <template data-dm-template>
                                    <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                        <p>
                                            <label><strong><?php esc_html_e('Título del acordeón', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_direccion_section_accordion_title[__SECTION__][]" data-name-template="sitio_cero_direccion_section_accordion_title[__SECTION__][]" value="">
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                            <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_accordion_content[__SECTION__][]" data-name-template="sitio_cero_direccion_section_accordion_content[__SECTION__][]"></textarea>
                                        </p>
                                        <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="sitio-cero-dm-section__group">
                            <div class="sitio-cero-dm-section__group-head">
                                <strong><?php esc_html_e('Pestañas', 'sitio-cero'); ?></strong>
                            </div>
                            <div class="sitio-cero-dm-repeater" data-dm-repeater>
                                <div class="sitio-cero-dm-repeater__list" data-dm-list>
                                    <?php foreach ($section_subtabs as $subtab) : ?>
                                        <?php
                                        $subtab_title = isset($subtab['title']) ? (string) $subtab['title'] : '';
                                        $subtab_content = isset($subtab['content']) ? (string) $subtab['content'] : '';
                                        ?>
                                        <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                            <p>
                                                <label><strong><?php esc_html_e('Título de la pestaña', 'sitio-cero'); ?></strong></label>
                                                <input type="text" class="widefat" name="sitio_cero_direccion_section_subtab_title[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_subtab_title[__SECTION__][]" value="<?php echo esc_attr($subtab_title); ?>">
                                            </p>
                                            <p>
                                                <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                                <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_subtab_content[<?php echo esc_attr((string) $section_index); ?>][]" data-name-template="sitio_cero_direccion_section_subtab_content[__SECTION__][]"><?php echo esc_textarea($subtab_content); ?></textarea>
                                            </p>
                                            <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar pestaña', 'sitio-cero'); ?></button>
                                <template data-dm-template>
                                    <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                        <p>
                                            <label><strong><?php esc_html_e('Título de la pestaña', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_direccion_section_subtab_title[__SECTION__][]" data-name-template="sitio_cero_direccion_section_subtab_title[__SECTION__][]" value="">
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                            <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_subtab_content[__SECTION__][]" data-name-template="sitio_cero_direccion_section_subtab_content[__SECTION__][]"></textarea>
                                        </p>
                                        <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-primary" data-dm-section-add><?php esc_html_e('Agregar sección', 'sitio-cero'); ?></button>

            <template data-dm-sections-template>
                <div class="sitio-cero-dm-section" data-dm-section-row data-section-index="__SECTION__">
                    <div class="sitio-cero-dm-section__head">
                        <strong data-section-label><?php esc_html_e('Sección', 'sitio-cero'); ?></strong>
                        <button type="button" class="button-link-delete" data-dm-section-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>

                    <div class="sitio-cero-dm-grid">
                        <p>
                            <label><strong><?php esc_html_e('Título de la sección', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_direccion_section_title[__SECTION__]" data-name-template="sitio_cero_direccion_section_title[__SECTION__]" value="">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Anchor (opcional)', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_direccion_section_anchor[__SECTION__]" data-name-template="sitio_cero_direccion_section_anchor[__SECTION__]" value="" placeholder="mi-seccion">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Kicker (opcional)', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_direccion_section_kicker[__SECTION__]" data-name-template="sitio_cero_direccion_section_kicker[__SECTION__]" value="">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Estilo de fondo', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_direccion_section_style[__SECTION__]" data-name-template="sitio_cero_direccion_section_style[__SECTION__]">
                                <?php foreach ($section_style_options as $style_key => $style_label) : ?>
                                    <option value="<?php echo esc_attr($style_key); ?>"><?php echo esc_html($style_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </div>

                    <p>
                        <label><strong><?php esc_html_e('Contenido principal (HTML)', 'sitio-cero'); ?></strong></label>
                        <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_content[__SECTION__]" data-name-template="sitio_cero_direccion_section_content[__SECTION__]"></textarea>
                    </p>

                    <div class="sitio-cero-dm-section__group">
                        <div class="sitio-cero-dm-section__group-head">
                            <strong><?php esc_html_e('Botones', 'sitio-cero'); ?></strong>
                        </div>
                        <p>
                            <label><strong><?php esc_html_e('Estilo de botones', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_direccion_section_buttons_style[__SECTION__]" data-name-template="sitio_cero_direccion_section_buttons_style[__SECTION__]">
                                <?php foreach ($section_button_style_options as $style_key => $style_label) : ?>
                                    <option value="<?php echo esc_attr($style_key); ?>"<?php selected('pill', $style_key); ?>><?php echo esc_html($style_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <div class="sitio-cero-dm-repeater" data-dm-repeater>
                            <div class="sitio-cero-dm-repeater__list" data-dm-list></div>
                            <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar botón', 'sitio-cero'); ?></button>
                            <template data-dm-template>
                                <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--actions" data-dm-row>
                                    <p>
                                        <label><strong><?php esc_html_e('Texto', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_direccion_section_button_label[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_label[__SECTION__][]" value="">
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('URL', 'sitio-cero'); ?></strong></label>
                                        <input type="url" class="widefat" name="sitio_cero_direccion_section_button_url[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_url[__SECTION__][]" value="">
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                                        <select class="widefat" name="sitio_cero_direccion_section_button_target[__SECTION__][]" data-name-template="sitio_cero_direccion_section_button_target[__SECTION__][]">
                                            <option value=""><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                                            <option value="_blank"><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                                        </select>
                                    </p>
                                    <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="sitio-cero-dm-section__group">
                        <div class="sitio-cero-dm-section__group-head">
                            <strong><?php esc_html_e('Acordeones', 'sitio-cero'); ?></strong>
                        </div>
                        <div class="sitio-cero-dm-repeater" data-dm-repeater>
                            <div class="sitio-cero-dm-repeater__list" data-dm-list></div>
                            <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar acordeón', 'sitio-cero'); ?></button>
                            <template data-dm-template>
                                <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                    <p>
                                        <label><strong><?php esc_html_e('Título del acordeón', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_direccion_section_accordion_title[__SECTION__][]" data-name-template="sitio_cero_direccion_section_accordion_title[__SECTION__][]" value="">
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                        <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_accordion_content[__SECTION__][]" data-name-template="sitio_cero_direccion_section_accordion_content[__SECTION__][]"></textarea>
                                    </p>
                                    <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="sitio-cero-dm-section__group">
                        <div class="sitio-cero-dm-section__group-head">
                            <strong><?php esc_html_e('Pestañas', 'sitio-cero'); ?></strong>
                        </div>
                        <div class="sitio-cero-dm-repeater" data-dm-repeater>
                            <div class="sitio-cero-dm-repeater__list" data-dm-list></div>
                            <button type="button" class="button button-secondary" data-dm-add><?php esc_html_e('Agregar pestaña', 'sitio-cero'); ?></button>
                            <template data-dm-template>
                                <div class="sitio-cero-dm-repeater__row sitio-cero-dm-repeater__row--stack" data-dm-row>
                                    <p>
                                        <label><strong><?php esc_html_e('Título de la pestaña', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_direccion_section_subtab_title[__SECTION__][]" data-name-template="sitio_cero_direccion_section_subtab_title[__SECTION__][]" value="">
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                                        <textarea class="widefat" rows="6" name="sitio_cero_direccion_section_subtab_content[__SECTION__][]" data-name-template="sitio_cero_direccion_section_subtab_content[__SECTION__][]"></textarea>
                                    </p>
                                    <button type="button" class="button-link-delete" data-dm-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
    <?php
}

function sitio_cero_save_direccion_municipal_meta($post_id)
{
    if (!isset($_POST['sitio_cero_direccion_municipal_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_direccion_municipal_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_direccion_municipal_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $director = isset($_POST['sitio_cero_direccion_director']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_direccion_director'])) : '';
    $profesion = isset($_POST['sitio_cero_direccion_profesion']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_direccion_profesion'])) : '';
    $email = isset($_POST['sitio_cero_direccion_email']) ? sanitize_email(wp_unslash($_POST['sitio_cero_direccion_email'])) : '';
    $direccion = isset($_POST['sitio_cero_direccion_direccion']) ? sanitize_text_field(wp_unslash($_POST['sitio_cero_direccion_direccion'])) : '';
    $mapa_url = isset($_POST['sitio_cero_direccion_mapa_url']) ? esc_url_raw(wp_unslash($_POST['sitio_cero_direccion_mapa_url'])) : '';

    if ('' !== $director) {
        update_post_meta($post_id, 'sitio_cero_direccion_director', $director);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_director');
    }

    if ('' !== $profesion) {
        update_post_meta($post_id, 'sitio_cero_direccion_profesion', $profesion);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_profesion');
    }

    if ('' !== $email) {
        update_post_meta($post_id, 'sitio_cero_direccion_email', $email);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_email');
    }

    if ('' !== $direccion) {
        update_post_meta($post_id, 'sitio_cero_direccion_direccion', $direccion);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_direccion');
    }

    if ('' !== $mapa_url) {
        update_post_meta($post_id, 'sitio_cero_direccion_mapa_url', $mapa_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_mapa_url');
    }

    $telefonos = array();
    if (isset($_POST['sitio_cero_direccion_telefonos']) && is_array($_POST['sitio_cero_direccion_telefonos'])) {
        foreach (wp_unslash($_POST['sitio_cero_direccion_telefonos']) as $raw_phone) {
            $phone = sanitize_text_field((string) $raw_phone);
            $phone = preg_replace('/\s+/', ' ', $phone);
            $phone = trim((string) $phone);
            if ('' !== $phone && !in_array($phone, $telefonos, true)) {
                $telefonos[] = $phone;
            }
        }
    }

    if (!empty($telefonos)) {
        update_post_meta($post_id, 'sitio_cero_direccion_telefonos', $telefonos);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_telefonos');
    }

    $recursos_titulo = isset($_POST['sitio_cero_direccion_recursos_titulo'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_direccion_recursos_titulo']))
        : '';
    if ('' !== $recursos_titulo) {
        update_post_meta($post_id, 'sitio_cero_direccion_recursos_titulo', $recursos_titulo);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_recursos_titulo');
    }

    $resource_titles = isset($_POST['sitio_cero_direccion_resource_block_title']) && is_array($_POST['sitio_cero_direccion_resource_block_title'])
        ? wp_unslash($_POST['sitio_cero_direccion_resource_block_title'])
        : array();
    $resource_types = isset($_POST['sitio_cero_direccion_resource_block_type']) && is_array($_POST['sitio_cero_direccion_resource_block_type'])
        ? wp_unslash($_POST['sitio_cero_direccion_resource_block_type'])
        : array();
    $resource_htmls = isset($_POST['sitio_cero_direccion_resource_block_html']) && is_array($_POST['sitio_cero_direccion_resource_block_html'])
        ? wp_unslash($_POST['sitio_cero_direccion_resource_block_html'])
        : array();
    $resource_links = isset($_POST['sitio_cero_direccion_resource_block_links']) && is_array($_POST['sitio_cero_direccion_resource_block_links'])
        ? wp_unslash($_POST['sitio_cero_direccion_resource_block_links'])
        : array();

    $resource_total = max(count($resource_titles), count($resource_types), count($resource_htmls), count($resource_links));
    $resource_blocks = array();

    for ($index = 0; $index < $resource_total; $index++) {
        $block_title = isset($resource_titles[$index]) ? sanitize_text_field((string) $resource_titles[$index]) : '';
        $block_type = isset($resource_types[$index]) ? sanitize_key((string) $resource_types[$index]) : 'documentos';
        if (!in_array($block_type, array('documentos', 'archivos'), true)) {
            $block_type = 'documentos';
        }
        $block_html = isset($resource_htmls[$index]) ? sitio_cero_sanitize_direccion_html((string) $resource_htmls[$index]) : '';
        $block_links = isset($resource_links[$index]) ? sitio_cero_sanitize_aviso_links_textarea((string) $resource_links[$index]) : '';

        if (
            '' === $block_title
            && '' === trim((string) wp_strip_all_tags($block_html))
            && '' === trim($block_links)
        ) {
            continue;
        }

        $resource_blocks[] = array(
            'title' => $block_title,
            'type'  => $block_type,
            'html'  => $block_html,
            'links' => $block_links,
        );
    }

    if (!empty($resource_blocks)) {
        update_post_meta($post_id, 'sitio_cero_direccion_resource_blocks', $resource_blocks);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_resource_blocks');
    }

    $first_documentos = null;
    $first_archivos = null;
    foreach ($resource_blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        $current_type = isset($block['type']) ? sanitize_key((string) $block['type']) : 'documentos';
        if ('documentos' === $current_type && null === $first_documentos) {
            $first_documentos = $block;
        }
        if ('archivos' === $current_type && null === $first_archivos) {
            $first_archivos = $block;
        }
    }

    if (is_array($first_documentos)) {
        $legacy_title = isset($first_documentos['title']) ? sanitize_text_field((string) $first_documentos['title']) : '';
        $legacy_html = isset($first_documentos['html']) ? sitio_cero_sanitize_direccion_html((string) $first_documentos['html']) : '';
        $legacy_links = isset($first_documentos['links']) ? sitio_cero_sanitize_aviso_links_textarea((string) $first_documentos['links']) : '';

        if ('' !== $legacy_title) {
            update_post_meta($post_id, 'sitio_cero_direccion_documentos_titulo', $legacy_title);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_documentos_titulo');
        }

        if ('' !== $legacy_html) {
            update_post_meta($post_id, 'sitio_cero_direccion_documentos_html', $legacy_html);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_documentos_html');
        }

        if ('' !== $legacy_links) {
            update_post_meta($post_id, 'sitio_cero_direccion_documentos', $legacy_links);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_documentos');
        }
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_documentos_titulo');
        delete_post_meta($post_id, 'sitio_cero_direccion_documentos_html');
        delete_post_meta($post_id, 'sitio_cero_direccion_documentos');
    }

    if (is_array($first_archivos)) {
        $legacy_title = isset($first_archivos['title']) ? sanitize_text_field((string) $first_archivos['title']) : '';
        $legacy_html = isset($first_archivos['html']) ? sitio_cero_sanitize_direccion_html((string) $first_archivos['html']) : '';
        $legacy_links = isset($first_archivos['links']) ? sitio_cero_sanitize_aviso_links_textarea((string) $first_archivos['links']) : '';

        if ('' !== $legacy_title) {
            update_post_meta($post_id, 'sitio_cero_direccion_archivos_titulo', $legacy_title);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_archivos_titulo');
        }

        if ('' !== $legacy_html) {
            update_post_meta($post_id, 'sitio_cero_direccion_archivos_html', $legacy_html);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_archivos_html');
        }

        if ('' !== $legacy_links) {
            update_post_meta($post_id, 'sitio_cero_direccion_archivos', $legacy_links);
        } else {
            delete_post_meta($post_id, 'sitio_cero_direccion_archivos');
        }
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_archivos_titulo');
        delete_post_meta($post_id, 'sitio_cero_direccion_archivos_html');
        delete_post_meta($post_id, 'sitio_cero_direccion_archivos');
    }

    // Remove legacy accordion meta when saving with the new documents/files model.
    delete_post_meta($post_id, 'sitio_cero_direccion_acordeon_items');

    $custom_html = '';
    if (isset($_POST['sitio_cero_direccion_custom_html'])) {
        $custom_html = sitio_cero_sanitize_direccion_html(wp_unslash($_POST['sitio_cero_direccion_custom_html']));
    }

    if ('' !== $custom_html) {
        update_post_meta($post_id, 'sitio_cero_direccion_custom_html', $custom_html);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_custom_html');
    }

    $custom_css = '';
    if (isset($_POST['sitio_cero_direccion_custom_css'])) {
        $custom_css = wp_unslash($_POST['sitio_cero_direccion_custom_css']);
        if (!current_user_can('unfiltered_html')) {
            $custom_css = sanitize_textarea_field($custom_css);
        }

        if (function_exists('sitio_cero_sanitize_tramite_custom_css')) {
            $custom_css = sitio_cero_sanitize_tramite_custom_css($custom_css);
        } else {
            $custom_css = trim((string) wp_kses((string) $custom_css, array()));
        }
    }

    if ('' !== $custom_css) {
        update_post_meta($post_id, 'sitio_cero_direccion_custom_css', $custom_css);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_custom_css');
    }

    $section_titles = isset($_POST['sitio_cero_direccion_section_title']) && is_array($_POST['sitio_cero_direccion_section_title'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_title'])
        : array();
    $section_anchors = isset($_POST['sitio_cero_direccion_section_anchor']) && is_array($_POST['sitio_cero_direccion_section_anchor'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_anchor'])
        : array();
    $section_kickers = isset($_POST['sitio_cero_direccion_section_kicker']) && is_array($_POST['sitio_cero_direccion_section_kicker'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_kicker'])
        : array();
    $section_contents = isset($_POST['sitio_cero_direccion_section_content']) && is_array($_POST['sitio_cero_direccion_section_content'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_content'])
        : array();
    $section_styles = isset($_POST['sitio_cero_direccion_section_style']) && is_array($_POST['sitio_cero_direccion_section_style'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_style'])
        : array();
    $section_buttons_styles = isset($_POST['sitio_cero_direccion_section_buttons_style']) && is_array($_POST['sitio_cero_direccion_section_buttons_style'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_buttons_style'])
        : array();
    $section_button_labels = isset($_POST['sitio_cero_direccion_section_button_label']) && is_array($_POST['sitio_cero_direccion_section_button_label'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_button_label'])
        : array();
    $section_button_urls = isset($_POST['sitio_cero_direccion_section_button_url']) && is_array($_POST['sitio_cero_direccion_section_button_url'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_button_url'])
        : array();
    $section_button_targets = isset($_POST['sitio_cero_direccion_section_button_target']) && is_array($_POST['sitio_cero_direccion_section_button_target'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_button_target'])
        : array();
    $section_accordion_titles = isset($_POST['sitio_cero_direccion_section_accordion_title']) && is_array($_POST['sitio_cero_direccion_section_accordion_title'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_accordion_title'])
        : array();
    $section_accordion_contents = isset($_POST['sitio_cero_direccion_section_accordion_content']) && is_array($_POST['sitio_cero_direccion_section_accordion_content'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_accordion_content'])
        : array();
    $section_subtab_titles = isset($_POST['sitio_cero_direccion_section_subtab_title']) && is_array($_POST['sitio_cero_direccion_section_subtab_title'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_subtab_title'])
        : array();
    $section_subtab_contents = isset($_POST['sitio_cero_direccion_section_subtab_content']) && is_array($_POST['sitio_cero_direccion_section_subtab_content'])
        ? wp_unslash($_POST['sitio_cero_direccion_section_subtab_content'])
        : array();

    $section_total = max(
        count($section_titles),
        count($section_anchors),
        count($section_kickers),
        count($section_contents),
        count($section_styles),
        count($section_buttons_styles)
    );
    $sections = array();
    $valid_section_styles = array('paper', 'soft', 'dark');
    $valid_button_styles = array('pill', 'card', 'card-dark');

    for ($index = 0; $index < $section_total; $index++) {
        $section_title = isset($section_titles[$index]) ? sanitize_text_field((string) $section_titles[$index]) : '';
        $section_anchor = isset($section_anchors[$index]) ? sanitize_title(ltrim((string) $section_anchors[$index], '#')) : '';
        $section_kicker = isset($section_kickers[$index]) ? sanitize_text_field((string) $section_kickers[$index]) : '';
        $section_content = isset($section_contents[$index]) ? sitio_cero_sanitize_direccion_html((string) $section_contents[$index]) : '';

        $section_style = isset($section_styles[$index]) ? sanitize_key((string) $section_styles[$index]) : '';
        if (!in_array($section_style, $valid_section_styles, true)) {
            $section_style = '';
        }

        $buttons_style = isset($section_buttons_styles[$index]) ? sanitize_key((string) $section_buttons_styles[$index]) : '';
        if (!in_array($buttons_style, $valid_button_styles, true)) {
            $buttons_style = '';
        }

        $button_labels = isset($section_button_labels[$index]) && is_array($section_button_labels[$index]) ? $section_button_labels[$index] : array();
        $button_urls = isset($section_button_urls[$index]) && is_array($section_button_urls[$index]) ? $section_button_urls[$index] : array();
        $button_targets = isset($section_button_targets[$index]) && is_array($section_button_targets[$index]) ? $section_button_targets[$index] : array();
        $buttons = sitio_cero_collect_participacion_link_items($button_labels, $button_urls, $button_targets);

        $accordion_titles = isset($section_accordion_titles[$index]) && is_array($section_accordion_titles[$index]) ? $section_accordion_titles[$index] : array();
        $accordion_contents = isset($section_accordion_contents[$index]) && is_array($section_accordion_contents[$index]) ? $section_accordion_contents[$index] : array();
        $accordion_total = max(count($accordion_titles), count($accordion_contents));
        $accordions = array();

        for ($accordion_index = 0; $accordion_index < $accordion_total; $accordion_index++) {
            $accordion_title = isset($accordion_titles[$accordion_index]) ? sanitize_text_field((string) $accordion_titles[$accordion_index]) : '';
            $accordion_content = isset($accordion_contents[$accordion_index]) ? sitio_cero_sanitize_direccion_html((string) $accordion_contents[$accordion_index]) : '';

            if ('' === $accordion_title && '' === trim((string) wp_strip_all_tags($accordion_content))) {
                continue;
            }

            $accordions[] = array(
                'title'   => $accordion_title,
                'content' => $accordion_content,
            );
        }

        $subtab_titles = isset($section_subtab_titles[$index]) && is_array($section_subtab_titles[$index]) ? $section_subtab_titles[$index] : array();
        $subtab_contents = isset($section_subtab_contents[$index]) && is_array($section_subtab_contents[$index]) ? $section_subtab_contents[$index] : array();
        $subtab_total = max(count($subtab_titles), count($subtab_contents));
        $subtabs = array();

        for ($subtab_index = 0; $subtab_index < $subtab_total; $subtab_index++) {
            $subtab_title = isset($subtab_titles[$subtab_index]) ? sanitize_text_field((string) $subtab_titles[$subtab_index]) : '';
            $subtab_content = isset($subtab_contents[$subtab_index]) ? sitio_cero_sanitize_direccion_html((string) $subtab_contents[$subtab_index]) : '';

            if ('' === $subtab_title && '' === trim((string) wp_strip_all_tags($subtab_content))) {
                continue;
            }

            $subtabs[] = array(
                'title'   => $subtab_title,
                'content' => $subtab_content,
            );
        }

        if (
            '' === $section_title
            && '' === $section_kicker
            && '' === trim((string) wp_strip_all_tags($section_content))
            && empty($buttons)
            && empty($accordions)
            && empty($subtabs)
        ) {
            continue;
        }

        $sections[] = array(
            'title'         => $section_title,
            'anchor'        => $section_anchor,
            'kicker'        => $section_kicker,
            'content'       => $section_content,
            'style'         => $section_style,
            'buttons_style' => $buttons_style,
            'buttons'       => $buttons,
            'accordions'    => $accordions,
            'subtabs'       => $subtabs,
        );
    }

    if (!empty($sections)) {
        update_post_meta($post_id, 'sitio_cero_direccion_sections', $sections);
    } else {
        delete_post_meta($post_id, 'sitio_cero_direccion_sections');
    }
}
add_action('save_post_direccion_municipal', 'sitio_cero_save_direccion_municipal_meta');

function sitio_cero_get_direccion_icon_catalog()
{
    return array(
        'dideco' => array(
            'file'  => 'dideco.png',
            'title' => __('Icono DIDECO', 'sitio-cero'),
        ),
        'obras' => array(
            'file'  => 'obras.png',
            'title' => __('Icono Direccion de Obras', 'sitio-cero'),
        ),
        'transito' => array(
            'file'  => 'transito.png',
            'title' => __('Icono Direccion de Transito', 'sitio-cero'),
        ),
        'medio-ambiente' => array(
            'file'  => 'medio-ambiente.png',
            'title' => __('Icono Direccion de Medio Ambiente', 'sitio-cero'),
        ),
        'seguridad' => array(
            'file'  => 'seguridad.png',
            'title' => __('Icono Direccion de Seguridad Publica', 'sitio-cero'),
        ),
    );
}

function sitio_cero_get_or_create_direccion_icon_attachment_id($icon_key)
{
    $icon_key = sanitize_key((string) $icon_key);
    if ('' === $icon_key) {
        return 0;
    }

    $catalog = sitio_cero_get_direccion_icon_catalog();
    if (!isset($catalog[$icon_key]) || !is_array($catalog[$icon_key])) {
        return 0;
    }

    $option_key = 'sitio_cero_demo_direccion_icon_' . $icon_key . '_id';
    $cached_id = (int) get_option($option_key, 0);
    if ($cached_id > 0 && get_post($cached_id) instanceof WP_Post) {
        return $cached_id;
    }

    $file_name = isset($catalog[$icon_key]['file']) ? sanitize_file_name((string) $catalog[$icon_key]['file']) : '';
    if ('' === $file_name) {
        return 0;
    }

    $source_file = get_template_directory() . '/assets/images/direcciones-icons/' . $file_name;
    if (!file_exists($source_file)) {
        return 0;
    }

    $file_content = file_get_contents($source_file);
    if (false === $file_content || '' === $file_content) {
        return 0;
    }

    $upload = wp_upload_bits('direccion-icono-' . $file_name, null, $file_content);
    if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
        return 0;
    }

    $filetype = wp_check_filetype($upload['file']);
    $attachment_title = isset($catalog[$icon_key]['title']) && is_string($catalog[$icon_key]['title'])
        ? $catalog[$icon_key]['title']
        : __('Icono direccion municipal', 'sitio-cero');

    $attachment_id = wp_insert_attachment(
        array(
            'post_mime_type' => isset($filetype['type']) && '' !== (string) $filetype['type'] ? $filetype['type'] : 'image/png',
            'post_title'     => sanitize_text_field($attachment_title),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $upload['file']
    );

    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    if (is_array($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    update_option($option_key, (int) $attachment_id);
    return (int) $attachment_id;
}

function sitio_cero_get_default_direcciones_municipales_examples()
{
    return array(
        array(
            'title'      => __('Direccion de Desarrollo Comunitario', 'sitio-cero'),
            'content'    => '<p>Unidad encargada de programas sociales, apoyo territorial y articulacion con organizaciones comunitarias.</p><p>Atiende consultas de beneficios municipales, derivaciones y acompanamiento social.</p>',
            'director'   => 'Carolina Mendez Rojas',
            'profesion'  => 'Trabajadora social',
            'telefonos'  => array('+56 41 220 3101', '+56 41 220 3102'),
            'email'      => 'dideco@municipio.cl',
            'direccion'  => 'Orompello 570, Concepcion',
            'recursos_titulo'   => 'Documentos y archivos de apoyo',
            'documentos_titulo' => 'Documentos oficiales',
            'documentos_html'   => '<p>En este bloque puedes publicar documentos normativos y formularios principales.</p>',
            'archivos_titulo'   => 'Archivos complementarios',
            'archivos_html'     => '<p>Aqui puedes agregar anexos, material de apoyo y recursos embebidos.</p>',
            'documentos' => array(
                array(
                    'label' => 'Guia de programas sociales',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'pdf',
                ),
            ),
            'archivos'   => array(
                array(
                    'label' => 'Ficha de contacto DIDECO',
                    'url'   => 'https://concepcion.cl/contacto/',
                    'icon'  => 'doc',
                ),
            ),
            'custom_html' => '<h3>Canales digitales</h3><p>Tambien puedes gestionar solicitudes por formulario web y mesa de ayuda municipal.</p>',
            'custom_css'  => '{{selector}} h3{color:#0f2343;} {{selector}} p{line-height:1.65;}',
            'icon_key'    => 'dideco',
        ),
        array(
            'title'      => __('Direccion de Obras Municipales', 'sitio-cero'),
            'content'    => '<p>Area tecnica responsable de permisos de edificacion, recepciones finales y control normativo urbano.</p>',
            'director'   => 'Mauricio Riquelme Soto',
            'profesion'  => 'Arquitecto',
            'telefonos'  => array('+56 41 220 4201'),
            'email'      => 'dom@municipio.cl',
            'direccion'  => 'Caupolican 385, Concepcion',
            'documentos' => array(
                array(
                    'label' => 'Requisitos permisos DOM',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'pdf',
                ),
            ),
            'archivos'   => array(
                array(
                    'label' => 'Formulario solicitud DOM',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'doc',
                ),
            ),
            'custom_html' => '<div class="dm-note"><strong>Nota:</strong> Revisa requisitos actualizados antes de ingresar tu solicitud.</div>',
            'custom_css'  => '{{selector}} .dm-note{padding:12px 14px;border-radius:10px;background:#edf4ff;border:1px solid #c9d7ee;}',
            'icon_key'    => 'obras',
        ),
        array(
            'title'      => __('Direccion de Transito y Transporte Publico', 'sitio-cero'),
            'content'    => '<p>Gestiona licencias de conducir, senalizacion vial, permisos y seguridad del transito comunal.</p>',
            'director'   => 'Andrea Fuentes Herrera',
            'profesion'  => 'Ingeniera en transporte',
            'telefonos'  => array('+56 41 220 5100', '+56 41 220 5103'),
            'email'      => 'transito@municipio.cl',
            'direccion'  => 'Anibal Pinto 210, Concepcion',
            'documentos' => array(
                array(
                    'label' => 'Checklist licencia de conducir',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'pdf',
                ),
            ),
            'archivos'   => array(
                array(
                    'label' => 'Formulario renovacion licencia',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'doc',
                ),
            ),
            'custom_html' => '<p><a href="#">Ver calendario de atencion</a></p>',
            'custom_css'  => '{{selector}} a{font-weight:700;text-decoration:underline;}',
            'icon_key'    => 'transito',
        ),
        array(
            'title'      => __('Direccion de Medio Ambiente', 'sitio-cero'),
            'content'    => '<p>Coordina iniciativas de reciclaje, educacion ambiental y gestion sustentable en barrios y establecimientos.</p>',
            'director'   => 'Pablo Araya Saavedra',
            'profesion'  => 'Ingeniero ambiental',
            'telefonos'  => array('+56 41 220 6201'),
            'email'      => 'medioambiente@municipio.cl',
            'direccion'  => 'Freire 880, Concepcion',
            'documentos' => array(
                array(
                    'label' => 'Calendario reciclaje comunal',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'pdf',
                ),
            ),
            'archivos'   => array(
                array(
                    'label' => 'Material educativo ambiental',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'img',
                ),
            ),
            'custom_html' => '<p>Integra material audiovisual y recursos descargables para actividades comunitarias.</p>',
            'custom_css'  => '{{selector}} p{margin-bottom:.6rem;}',
            'icon_key'    => 'medio-ambiente',
        ),
        array(
            'title'      => __('Direccion de Seguridad Publica', 'sitio-cero'),
            'content'    => '<p>Implementa planes de prevencion situacional, coordinacion territorial y respuesta comunitaria.</p>',
            'director'   => 'Ricardo Valenzuela Cardenas',
            'profesion'  => 'Administrador publico',
            'telefonos'  => array('+56 41 220 7300'),
            'email'      => 'seguridadpublica@municipio.cl',
            'direccion'  => 'Barros Arana 1200, Concepcion',
            'documentos' => array(
                array(
                    'label' => 'Protocolo seguridad barrial',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'pdf',
                ),
            ),
            'archivos'   => array(
                array(
                    'label' => 'Formato reporte vecinal',
                    'url'   => 'https://concepcion.cl/',
                    'icon'  => 'xls',
                ),
            ),
            'custom_html' => '<iframe width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video informativo" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>',
            'custom_css'  => '{{selector}} iframe{max-width:100%;width:100%;border-radius:12px;}',
            'icon_key'    => 'seguridad',
        ),
    );
}

function sitio_cero_seed_default_direcciones_municipales()
{
    if (!post_type_exists('direccion_municipal')) {
        return;
    }

    $seed_version = '4';
    $already_seeded_version = (string) get_option('sitio_cero_default_direcciones_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $defaults = sitio_cero_get_default_direcciones_municipales_examples();

    foreach ($defaults as $index => $item) {
        if (!is_array($item) || !isset($item['title'])) {
            continue;
        }

        $title = sanitize_text_field((string) $item['title']);
        if ('' === $title) {
            continue;
        }

        $slug = sanitize_title($title);
        $existing = get_posts(
            array(
                'post_type'      => 'direccion_municipal',
                'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
                'name'           => $slug,
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        if (!empty($existing)) {
            $post_id = (int) $existing[0];
        } else {
            $content = isset($item['content']) ? (string) $item['content'] : '';
            $post_id = wp_insert_post(
                array(
                    'post_type'    => 'direccion_municipal',
                    'post_status'  => 'publish',
                    'post_title'   => $title,
                    'post_name'    => $slug,
                    'post_content' => $content,
                    'menu_order'   => (int) $index + 1,
                ),
                true
            );
        }

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta($post_id, '_sitio_cero_demo_direccion', '1');

        if (isset($item['director']) && is_string($item['director'])) {
            update_post_meta($post_id, 'sitio_cero_direccion_director', sanitize_text_field($item['director']));
        }
        if (isset($item['profesion']) && is_string($item['profesion'])) {
            update_post_meta($post_id, 'sitio_cero_direccion_profesion', sanitize_text_field($item['profesion']));
        }
        if (isset($item['telefonos']) && is_array($item['telefonos'])) {
            $phones = array();
            foreach ($item['telefonos'] as $phone) {
                $clean_phone = sanitize_text_field((string) $phone);
                if ('' !== $clean_phone) {
                    $phones[] = $clean_phone;
                }
            }
            if (!empty($phones)) {
                update_post_meta($post_id, 'sitio_cero_direccion_telefonos', $phones);
            }
        }
        if (isset($item['email']) && is_string($item['email'])) {
            $clean_email = sanitize_email($item['email']);
            if ('' !== $clean_email) {
                update_post_meta($post_id, 'sitio_cero_direccion_email', $clean_email);
            }
        }
        if (isset($item['direccion']) && is_string($item['direccion'])) {
            update_post_meta($post_id, 'sitio_cero_direccion_direccion', sanitize_text_field($item['direccion']));
        }

        if (isset($item['icon_key']) && is_string($item['icon_key']) && !has_post_thumbnail($post_id)) {
            $icon_attachment_id = sitio_cero_get_or_create_direccion_icon_attachment_id($item['icon_key']);
            if ($icon_attachment_id > 0) {
                set_post_thumbnail($post_id, $icon_attachment_id);
            }
        }

        $recursos_titulo = isset($item['recursos_titulo']) && is_string($item['recursos_titulo'])
            ? sanitize_text_field($item['recursos_titulo'])
            : __('Documentos y archivos', 'sitio-cero');
        if ('' !== $recursos_titulo) {
            update_post_meta($post_id, 'sitio_cero_direccion_recursos_titulo', $recursos_titulo);
        }

        $documentos_titulo = isset($item['documentos_titulo']) && is_string($item['documentos_titulo'])
            ? sanitize_text_field($item['documentos_titulo'])
            : __('Documentos', 'sitio-cero');
        if ('' !== $documentos_titulo) {
            update_post_meta($post_id, 'sitio_cero_direccion_documentos_titulo', $documentos_titulo);
        }

        $archivos_titulo = isset($item['archivos_titulo']) && is_string($item['archivos_titulo'])
            ? sanitize_text_field($item['archivos_titulo'])
            : __('Archivos', 'sitio-cero');
        if ('' !== $archivos_titulo) {
            update_post_meta($post_id, 'sitio_cero_direccion_archivos_titulo', $archivos_titulo);
        }

        if (isset($item['documentos_html']) && is_string($item['documentos_html'])) {
            $documentos_html = sitio_cero_sanitize_direccion_html($item['documentos_html']);
            if ('' !== $documentos_html) {
                update_post_meta($post_id, 'sitio_cero_direccion_documentos_html', $documentos_html);
            }
        }

        if (isset($item['archivos_html']) && is_string($item['archivos_html'])) {
            $archivos_html = sitio_cero_sanitize_direccion_html($item['archivos_html']);
            if ('' !== $archivos_html) {
                update_post_meta($post_id, 'sitio_cero_direccion_archivos_html', $archivos_html);
            }
        }

        if (isset($item['documentos']) && is_array($item['documentos']) && !empty($item['documentos'])) {
            $documentos_lines = array();
            foreach ($item['documentos'] as $doc_item) {
                if (!is_array($doc_item)) {
                    continue;
                }
                $label = isset($doc_item['label']) ? sanitize_text_field((string) $doc_item['label']) : '';
                $url = isset($doc_item['url']) ? esc_url_raw((string) $doc_item['url']) : '';
                $icon = isset($doc_item['icon']) ? sitio_cero_sanitize_aviso_file_icon((string) $doc_item['icon']) : '';
                if ('' === $url) {
                    continue;
                }
                $line = '' !== $label ? ($label . '|' . $url) : $url;
                if ('' !== $icon) {
                    $line .= '|' . $icon;
                }
                $documentos_lines[] = $line;
            }
            $documentos_value = sitio_cero_sanitize_aviso_links_textarea(implode("\n", $documentos_lines));
            if ('' !== $documentos_value) {
                update_post_meta($post_id, 'sitio_cero_direccion_documentos', $documentos_value);
            }
        }

        if (isset($item['archivos']) && is_array($item['archivos']) && !empty($item['archivos'])) {
            $archivos_lines = array();
            foreach ($item['archivos'] as $file_item) {
                if (!is_array($file_item)) {
                    continue;
                }
                $label = isset($file_item['label']) ? sanitize_text_field((string) $file_item['label']) : '';
                $url = isset($file_item['url']) ? esc_url_raw((string) $file_item['url']) : '';
                $icon = isset($file_item['icon']) ? sitio_cero_sanitize_aviso_file_icon((string) $file_item['icon']) : '';
                if ('' === $url) {
                    continue;
                }
                $line = '' !== $label ? ($label . '|' . $url) : $url;
                if ('' !== $icon) {
                    $line .= '|' . $icon;
                }
                $archivos_lines[] = $line;
            }
            $archivos_value = sitio_cero_sanitize_aviso_links_textarea(implode("\n", $archivos_lines));
            if ('' !== $archivos_value) {
                update_post_meta($post_id, 'sitio_cero_direccion_archivos', $archivos_value);
            }
        }

        delete_post_meta($post_id, 'sitio_cero_direccion_acordeon_items');
        if (isset($item['custom_html']) && is_string($item['custom_html'])) {
            $clean_html = sitio_cero_sanitize_direccion_html($item['custom_html']);
            if ('' !== $clean_html) {
                update_post_meta($post_id, 'sitio_cero_direccion_custom_html', $clean_html);
            }
        }
        if (isset($item['custom_css']) && is_string($item['custom_css'])) {
            $clean_css = function_exists('sitio_cero_sanitize_tramite_custom_css')
                ? sitio_cero_sanitize_tramite_custom_css($item['custom_css'])
                : trim((string) wp_kses((string) $item['custom_css'], array()));
            if ('' !== $clean_css) {
                update_post_meta($post_id, 'sitio_cero_direccion_custom_css', $clean_css);
            }
        }
    }

    update_option('sitio_cero_default_direcciones_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_direcciones_municipales', 46);

function sitio_cero_register_noticia_post_type()
{
    $labels = array(
        'name'               => __('Noticias', 'sitio-cero'),
        'singular_name'      => __('Noticia', 'sitio-cero'),
        'menu_name'          => __('Noticias', 'sitio-cero'),
        'name_admin_bar'     => __('Noticia', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar noticia', 'sitio-cero'),
        'new_item'           => __('Nueva noticia', 'sitio-cero'),
        'edit_item'          => __('Editar noticia', 'sitio-cero'),
        'view_item'          => __('Ver noticia', 'sitio-cero'),
        'all_items'          => __('Todas las noticias', 'sitio-cero'),
        'search_items'       => __('Buscar noticias', 'sitio-cero'),
        'not_found'          => __('No se encontraron noticias.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay noticias en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'noticia',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_rest'      => true,
            'has_archive'       => true,
            'rewrite'           => array('slug' => 'noticias'),
            'menu_position'     => 21,
            'menu_icon'         => 'dashicons-megaphone',
            'supports'          => array('title', 'editor', 'excerpt', 'thumbnail', 'author'),
        )
    );
}
add_action('init', 'sitio_cero_register_noticia_post_type');

function sitio_cero_register_noticia_taxonomy()
{
    $labels = array(
        'name'              => __('Categorias de noticias', 'sitio-cero'),
        'singular_name'     => __('Categoria de noticia', 'sitio-cero'),
        'search_items'      => __('Buscar categorias', 'sitio-cero'),
        'all_items'         => __('Todas las categorias', 'sitio-cero'),
        'edit_item'         => __('Editar categoria', 'sitio-cero'),
        'update_item'       => __('Actualizar categoria', 'sitio-cero'),
        'add_new_item'      => __('Agregar categoria', 'sitio-cero'),
        'new_item_name'     => __('Nombre de nueva categoria', 'sitio-cero'),
        'menu_name'         => __('Categorias de noticias', 'sitio-cero'),
    );

    register_taxonomy(
        'categoria_noticia',
        array('noticia'),
        array(
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'categoria-noticias'),
            'default_term'      => array(
                'name' => __('Noticias', 'sitio-cero'),
                'slug' => 'noticias',
            ),
        )
    );
}
add_action('init', 'sitio_cero_register_noticia_taxonomy', 11);

function sitio_cero_sanitize_attachment_ids_list($value)
{
    $ids = array();

    if (is_array($value)) {
        $raw_values = $value;
    } else {
        $raw_values = explode(',', (string) $value);
    }

    foreach ($raw_values as $raw_value) {
        $attachment_id = absint($raw_value);
        if ($attachment_id <= 0) {
            continue;
        }

        if (!in_array($attachment_id, $ids, true)) {
            $ids[] = $attachment_id;
        }
    }

    return $ids;
}

function sitio_cero_get_noticia_gallery_ids($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $gallery_ids = get_post_meta($post_id, 'sitio_cero_noticia_gallery_ids', true);
    return sitio_cero_sanitize_attachment_ids_list($gallery_ids);
}

function sitio_cero_add_noticia_metaboxes()
{
    add_meta_box(
        'sitio_cero_noticia_gallery',
        __('Galeria de imágenes', 'sitio-cero'),
        'sitio_cero_render_noticia_gallery_metabox',
        'noticia',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_noticia_metaboxes');

function sitio_cero_render_noticia_gallery_metabox($post)
{
    wp_nonce_field('sitio_cero_save_noticia_gallery_meta', 'sitio_cero_noticia_gallery_nonce');

    $gallery_ids = sitio_cero_get_noticia_gallery_ids($post->ID);
    $empty_text = __('No hay imágenes seleccionadas. Haz clic en "Seleccionar imágenes".', 'sitio-cero');
    ?>
    <div class="sitio-cero-noticia-gallery" data-empty-text="<?php echo esc_attr($empty_text); ?>">
        <input
            type="hidden"
            id="sitio_cero_noticia_gallery_ids"
            name="sitio_cero_noticia_gallery_ids"
            value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>"
        >

        <div class="sitio-cero-noticia-gallery__actions">
            <button type="button" class="button button-primary sitio-cero-noticia-gallery__choose">
                <?php esc_html_e('Seleccionar imágenes', 'sitio-cero'); ?>
            </button>
            <button type="button" class="button button-secondary sitio-cero-noticia-gallery__clear">
                <?php esc_html_e('Limpiar galeria', 'sitio-cero'); ?>
            </button>
        </div>

        <p class="description">
            <?php esc_html_e('Puedes seleccionar multiples imágenes y arrastrarlas para cambiar el orden.', 'sitio-cero'); ?>
        </p>

        <ul class="sitio-cero-noticia-gallery__list">
            <?php foreach ($gallery_ids as $attachment_id) : ?>
                <?php
                $thumb_html = wp_get_attachment_image(
                    $attachment_id,
                    'thumbnail',
                    false,
                    array(
                        'class'   => 'sitio-cero-noticia-gallery__thumb-image',
                        'loading' => 'lazy',
                    )
                );
                if ('' === $thumb_html) {
                    continue;
                }
                ?>
                <li class="sitio-cero-noticia-gallery__item" data-id="<?php echo esc_attr((string) $attachment_id); ?>">
                    <div class="sitio-cero-noticia-gallery__thumb"><?php echo $thumb_html; ?></div>
                    <button type="button" class="button-link-delete sitio-cero-noticia-gallery__remove">
                        <?php esc_html_e('Quitar', 'sitio-cero'); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="sitio-cero-noticia-gallery__empty<?php echo empty($gallery_ids) ? '' : ' is-hidden'; ?>">
            <?php echo esc_html($empty_text); ?>
        </p>
    </div>
    <?php
}

function sitio_cero_save_noticia_gallery_meta($post_id)
{
    if (!isset($_POST['sitio_cero_noticia_gallery_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_noticia_gallery_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_noticia_gallery_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $gallery_ids = array();
    if (isset($_POST['sitio_cero_noticia_gallery_ids'])) {
        $raw_gallery_ids = wp_unslash($_POST['sitio_cero_noticia_gallery_ids']);
        $gallery_ids = sitio_cero_sanitize_attachment_ids_list($raw_gallery_ids);
    }

    if (!empty($gallery_ids)) {
        update_post_meta($post_id, 'sitio_cero_noticia_gallery_ids', implode(',', $gallery_ids));
    } else {
        delete_post_meta($post_id, 'sitio_cero_noticia_gallery_ids');
    }
}
add_action('save_post_noticia', 'sitio_cero_save_noticia_gallery_meta');

function sitio_cero_get_or_create_demo_noticia_image_id()
{
    $cached_id = (int) get_option('sitio_cero_demo_noticia_image_id', 0);
    if ($cached_id > 0 && get_post($cached_id) instanceof WP_Post) {
        return $cached_id;
    }

    $source_file = get_template_directory() . '/assets/images/logo-concepcion-2025.png';
    if (!file_exists($source_file)) {
        return 0;
    }

    $upload = wp_upload_bits(
        'noticia-ejemplo-concepcion.png',
        null,
        file_get_contents($source_file)
    );

    if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
        return 0;
    }

    $filetype = wp_check_filetype($upload['file']);
    $attachment_id = wp_insert_attachment(
        array(
            'post_mime_type' => isset($filetype['type']) ? $filetype['type'] : 'image/png',
            'post_title'     => __('Imagen de ejemplo para noticias', 'sitio-cero'),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ),
        $upload['file']
    );

    if (is_wp_error($attachment_id) || !$attachment_id) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
    if (is_array($metadata)) {
        wp_update_attachment_metadata($attachment_id, $metadata);
    }

    update_option('sitio_cero_demo_noticia_image_id', (int) $attachment_id);
    return (int) $attachment_id;
}

function sitio_cero_seed_demo_noticias()
{
    if (!post_type_exists('noticia') || !taxonomy_exists('categoria_noticia')) {
        return;
    }

    $already_seeded = get_option('sitio_cero_demo_noticias_seeded', '0');
    if ('1' === (string) $already_seeded) {
        return;
    }

    $existing_demo = get_posts(
        array(
            'post_type'      => 'noticia',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => '_sitio_cero_demo_noticia',
            'meta_value'     => '1',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing_demo)) {
        update_option('sitio_cero_demo_noticias_seeded', '1');
        return;
    }

    $term = get_term_by('slug', 'noticias', 'categoria_noticia');
    if (!$term instanceof WP_Term) {
        $term_result = wp_insert_term(
            __('Noticias', 'sitio-cero'),
            'categoria_noticia',
            array('slug' => 'noticias')
        );
        if (!is_wp_error($term_result) && is_array($term_result) && isset($term_result['term_id'])) {
            $term = get_term((int) $term_result['term_id'], 'categoria_noticia');
        }
    }

    $term_id = $term instanceof WP_Term ? (int) $term->term_id : 0;
    $featured_image_id = sitio_cero_get_or_create_demo_noticia_image_id();

    $items = array(
        array(
            'title'   => __('Concepcion refuerza plan preventivo de incendios urbanos', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: equipos municipales y Senapred activan patrullajes y puntos de abastecimiento.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. Se informa el despliegue coordinado de cuadrillas, monitoreo de zonas de riesgo y campañas de autocuidado en barrios de Concepcion, dentro de un plan de alcance nacional para temporada de altas temperaturas.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Nueva agenda de seguridad publica integra apoyo regional en Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: autoridades anuncian medidas de vigilancia y recuperacion de espacios.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El municipio presenta una agenda de seguridad con enfoque preventivo, trabajo comunitario y coordinacion con instituciones regionales para fortalecer la convivencia y el uso seguro del espacio publico.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Concepcion inicia programa nacional de empleo juvenil en servicios locales', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: se abren cupos de capacitacion y practica para jovenes de la comuna.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El plan considera formacion en competencias digitales, atencion ciudadana y apoyo a proyectos locales, con foco en insercion laboral y acompanamiento durante los primeros meses.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Obras de movilidad sustentable conectaran ejes estrategicos de Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: nuevo trazado prioriza transporte publico, ciclovias y accesibilidad universal.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El proyecto incorpora mejoras de veredas, cruces seguros, paraderos y senaletica, alineado con lineamientos nacionales de ciudades sostenibles y reduccion de tiempos de traslado.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Concepcion amplifica red cultural con actividades abiertas en toda la comuna', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: cartelera incluye musica, patrimonio, lectura y talleres familiares.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. La programacion busca descentralizar el acceso cultural y activar espacios barriales mediante alianzas con organizaciones comunitarias y actores del ecosistema creativo local.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Plan comunal de salud preventiva suma operativos territoriales en Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: se refuerzan controles, orientacion y vacunacion en distintos sectores.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El despliegue considera atencion primaria en terreno, educacion para el autocuidado y derivaciones oportunas, en coordinacion con la red publica de salud y equipos de apoyo comunitario.', 'sitio-cero'),
        ),
    );

    foreach ($items as $index => $item) {
        $timestamp = strtotime('-' . (int) $index . ' days 09:00:00');
        if (false === $timestamp) {
            $timestamp = time();
        }

        $post_id = wp_insert_post(
            array(
                'post_type'     => 'noticia',
                'post_status'   => 'publish',
                'post_title'    => $item['title'],
                'post_excerpt'  => $item['excerpt'],
                'post_content'  => $item['content'],
                'post_date'     => gmdate('Y-m-d H:i:s', $timestamp + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)),
                'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
            ),
            true
        );

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        update_post_meta($post_id, '_sitio_cero_demo_noticia', '1');

        if ($term_id > 0) {
            wp_set_object_terms($post_id, array($term_id), 'categoria_noticia', false);
        }

        if ($featured_image_id > 0) {
            set_post_thumbnail($post_id, $featured_image_id);
        }
    }

    update_option('sitio_cero_demo_noticias_seeded', '1');
}
add_action('init', 'sitio_cero_seed_demo_noticias', 40);

function sitio_cero_normalize_demo_noticias()
{
    if (!post_type_exists('noticia') || !taxonomy_exists('categoria_noticia')) {
        return;
    }

    $already_normalized = get_option('sitio_cero_demo_noticias_normalized', '0');
    if ('1' === (string) $already_normalized) {
        return;
    }

    $term = get_term_by('slug', 'noticias', 'categoria_noticia');
    $term_id = $term instanceof WP_Term ? (int) $term->term_id : 0;
    $featured_image_id = sitio_cero_get_or_create_demo_noticia_image_id();

    $items = array(
        array(
            'title'   => __('Concepcion refuerza plan preventivo de incendios urbanos', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: equipos municipales y Senapred activan patrullajes y puntos de abastecimiento.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. Se informa el despliegue coordinado de cuadrillas, monitoreo de zonas de riesgo y campañas de autocuidado en barrios de Concepcion, dentro de un plan de alcance nacional para temporada de altas temperaturas.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Nueva agenda de seguridad publica integra apoyo regional en Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: autoridades anuncian medidas de vigilancia y recuperacion de espacios.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El municipio presenta una agenda de seguridad con enfoque preventivo, trabajo comunitario y coordinacion con instituciones regionales para fortalecer la convivencia y el uso seguro del espacio publico.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Concepcion inicia programa nacional de empleo juvenil en servicios locales', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: se abren cupos de capacitacion y practica para jovenes de la comuna.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El plan considera formacion en competencias digitales, atencion ciudadana y apoyo a proyectos locales, con foco en insercion laboral y acompanamiento durante los primeros meses.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Obras de movilidad sustentable conectaran ejes estrategicos de Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: nuevo trazado prioriza transporte publico, ciclovias y accesibilidad universal.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El proyecto incorpora mejoras de veredas, cruces seguros, paraderos y senaletica, alineado con lineamientos nacionales de ciudades sostenibles y reduccion de tiempos de traslado.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Concepcion amplifica red cultural con actividades abiertas en toda la comuna', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: cartelera incluye musica, patrimonio, lectura y talleres familiares.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. La programacion busca descentralizar el acceso cultural y activar espacios barriales mediante alianzas con organizaciones comunitarias y actores del ecosistema creativo local.', 'sitio-cero'),
        ),
        array(
            'title'   => __('Plan comunal de salud preventiva suma operativos territoriales en Concepcion', 'sitio-cero'),
            'excerpt' => __('Noticia de ejemplo: se refuerzan controles, orientacion y vacunacion en distintos sectores.', 'sitio-cero'),
            'content' => __('Contenido de referencia para maquetacion. El despliegue considera atencion primaria en terreno, educacion para el autocuidado y derivaciones oportunas, en coordinacion con la red publica de salud y equipos de apoyo comunitario.', 'sitio-cero'),
        ),
    );

    $existing_demo_posts = get_posts(
        array(
            'post_type'      => 'noticia',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => -1,
            'orderby'        => array(
                'date' => 'DESC',
                'ID'   => 'DESC',
            ),
            'meta_key'       => '_sitio_cero_demo_noticia',
            'meta_value'     => '1',
            'no_found_rows'  => true,
        )
    );

    $keep_ids = array();

    foreach ($items as $index => $item) {
        $post_id = 0;

        foreach ($existing_demo_posts as $candidate_post) {
            if (in_array((int) $candidate_post->ID, $keep_ids, true)) {
                continue;
            }

            if ((string) $candidate_post->post_title === (string) $item['title']) {
                $post_id = (int) $candidate_post->ID;
                break;
            }
        }

        if ($post_id <= 0) {
            $timestamp = strtotime('-' . (int) $index . ' days 09:00:00');
            if (false === $timestamp) {
                $timestamp = time();
            }

            $post_id = wp_insert_post(
                array(
                    'post_type'     => 'noticia',
                    'post_status'   => 'publish',
                    'post_title'    => $item['title'],
                    'post_excerpt'  => $item['excerpt'],
                    'post_content'  => $item['content'],
                    'post_date'     => gmdate('Y-m-d H:i:s', $timestamp + (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS)),
                    'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
                ),
                true
            );

            if (is_wp_error($post_id) || !$post_id) {
                continue;
            }
        } else {
            wp_update_post(
                array(
                    'ID'           => $post_id,
                    'post_status'  => 'publish',
                    'post_excerpt' => $item['excerpt'],
                    'post_content' => $item['content'],
                )
            );
        }

        $keep_ids[] = (int) $post_id;
        update_post_meta($post_id, '_sitio_cero_demo_noticia', '1');

        if ($term_id > 0) {
            wp_set_object_terms($post_id, array($term_id), 'categoria_noticia', false);
        }

        if ($featured_image_id > 0) {
            set_post_thumbnail($post_id, $featured_image_id);
        }
    }

    foreach ($existing_demo_posts as $candidate_post) {
        $candidate_id = (int) $candidate_post->ID;
        if (!in_array($candidate_id, $keep_ids, true)) {
            wp_trash_post($candidate_id);
        }
    }

    update_option('sitio_cero_demo_noticias_normalized', '1');
}
add_action('init', 'sitio_cero_normalize_demo_noticias', 41);

function sitio_cero_register_aviso_post_type()
{
    $labels = array(
        'name'               => __('Avisos', 'sitio-cero'),
        'singular_name'      => __('Aviso', 'sitio-cero'),
        'menu_name'          => __('Avisos', 'sitio-cero'),
        'name_admin_bar'     => __('Aviso', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar aviso', 'sitio-cero'),
        'new_item'           => __('Nuevo aviso', 'sitio-cero'),
        'edit_item'          => __('Editar aviso', 'sitio-cero'),
        'view_item'          => __('Ver aviso', 'sitio-cero'),
        'all_items'          => __('Todos los avisos', 'sitio-cero'),
        'search_items'       => __('Buscar avisos', 'sitio-cero'),
        'not_found'          => __('No se encontraron avisos.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay avisos en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'aviso',
        array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => true,
            'publicly_queryable' => true,
            'exclude_from_search'=> false,
            'has_archive'        => true,
            'rewrite'            => array('slug' => 'avisos'),
            'menu_position'      => 22,
            'menu_icon'          => 'dashicons-format-gallery',
            'supports'           => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_aviso_post_type');

function sitio_cero_add_aviso_metaboxes()
{
    add_meta_box(
        'sitio_cero_aviso_options',
        __('Opciones del aviso', 'sitio-cero'),
        'sitio_cero_render_aviso_metabox',
        'aviso',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_aviso_metaboxes');

function sitio_cero_render_aviso_metabox($post)
{
    wp_nonce_field('sitio_cero_save_aviso_meta', 'sitio_cero_aviso_meta_nonce');

    $image_url = get_post_meta($post->ID, 'sitio_cero_aviso_image_url', true);
    if (!is_string($image_url)) {
        $image_url = '';
    }

    $parrafos = get_post_meta($post->ID, 'sitio_cero_aviso_parrafos', true);
    if (!is_string($parrafos)) {
        $parrafos = '';
    }

    $documentos = get_post_meta($post->ID, 'sitio_cero_aviso_documentos', true);
    if (!is_string($documentos)) {
        $documentos = '';
    }

    $archivos = get_post_meta($post->ID, 'sitio_cero_aviso_archivos', true);
    if (!is_string($archivos)) {
        $archivos = '';
    }

    $documentos_items = sitio_cero_parse_aviso_links_textarea($documentos);
    $archivos_items = sitio_cero_parse_aviso_links_textarea($archivos);
    $icon_options = sitio_cero_get_aviso_file_icon_options();
    ?>
    <p>
        <label for="sitio_cero_aviso_image_url"><strong><?php esc_html_e('URL de imagen externa (opcional)', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_aviso_image_url"
            name="sitio_cero_aviso_image_url"
            type="url"
            class="widefat"
            value="<?php echo esc_attr($image_url); ?>"
            placeholder="https://..."
        >
    </p>
    <p class="description">
        <?php esc_html_e('Si no hay imagen destacada, se usara esta URL para mostrar el aviso en la portada.', 'sitio-cero'); ?>
    </p>

    <hr>

    <p>
        <label for="sitio_cero_aviso_parrafos"><strong><?php esc_html_e('Parrafos de texto (opcional)', 'sitio-cero'); ?></strong></label><br>
        <textarea
            id="sitio_cero_aviso_parrafos"
            name="sitio_cero_aviso_parrafos"
            class="widefat"
            rows="6"
            placeholder="<?php esc_attr_e('Escribe texto libre para la columna izquierda.', 'sitio-cero'); ?>"
        ><?php echo esc_textarea($parrafos); ?></textarea>
    </p>

    <p>
        <label><strong><?php esc_html_e('Documentos (opcional)', 'sitio-cero'); ?></strong></label>
        <div class="sitio-cero-aviso-files" data-target="#sitio_cero_aviso_documentos">
            <div class="sitio-cero-aviso-files__actions">
                <button type="button" class="button button-secondary sitio-cero-aviso-files__library">
                    <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
                </button>
                <button type="button" class="button button-secondary sitio-cero-aviso-files__add">
                    <?php esc_html_e('Agregar item manual', 'sitio-cero'); ?>
                </button>
            </div>
            <div class="sitio-cero-aviso-files__list" data-file-list>
                <?php foreach ($documentos_items as $item) : ?>
                    <?php
                    $item_label = isset($item['label']) ? (string) $item['label'] : '';
                    $item_url = isset($item['url']) ? (string) $item['url'] : '';
                    $item_icon = isset($item['icon']) ? (string) $item['icon'] : '';
                    ?>
                    <div class="sitio-cero-aviso-files__row" data-file-row>
                        <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="<?php echo esc_attr($item_label); ?>">
                        <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="<?php echo esc_attr($item_url); ?>">
                        <select class="sitio-cero-aviso-files__select" data-key="icon">
                            <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                                <option value="<?php echo esc_attr($icon_key); ?>"<?php selected($item_icon, $icon_key); ?>><?php echo esc_html($icon_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template class="sitio-cero-aviso-files__template">
                <div class="sitio-cero-aviso-files__row" data-file-row>
                    <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="">
                    <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="">
                    <select class="sitio-cero-aviso-files__select" data-key="icon">
                        <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                            <option value="<?php echo esc_attr($icon_key); ?>"><?php echo esc_html($icon_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>
        <textarea id="sitio_cero_aviso_documentos" name="sitio_cero_aviso_documentos" class="widefat" rows="4" hidden><?php echo esc_textarea($documentos); ?></textarea>
    </p>

    <p>
        <label><strong><?php esc_html_e('Archivos (opcional)', 'sitio-cero'); ?></strong></label>
        <div class="sitio-cero-aviso-files" data-target="#sitio_cero_aviso_archivos">
            <div class="sitio-cero-aviso-files__actions">
                <button type="button" class="button button-secondary sitio-cero-aviso-files__library">
                    <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
                </button>
                <button type="button" class="button button-secondary sitio-cero-aviso-files__add">
                    <?php esc_html_e('Agregar item manual', 'sitio-cero'); ?>
                </button>
            </div>
            <div class="sitio-cero-aviso-files__list" data-file-list>
                <?php foreach ($archivos_items as $item) : ?>
                    <?php
                    $item_label = isset($item['label']) ? (string) $item['label'] : '';
                    $item_url = isset($item['url']) ? (string) $item['url'] : '';
                    $item_icon = isset($item['icon']) ? (string) $item['icon'] : '';
                    ?>
                    <div class="sitio-cero-aviso-files__row" data-file-row>
                        <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="<?php echo esc_attr($item_label); ?>">
                        <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="<?php echo esc_attr($item_url); ?>">
                        <select class="sitio-cero-aviso-files__select" data-key="icon">
                            <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                                <option value="<?php echo esc_attr($icon_key); ?>"<?php selected($item_icon, $icon_key); ?>><?php echo esc_html($icon_label); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template class="sitio-cero-aviso-files__template">
                <div class="sitio-cero-aviso-files__row" data-file-row>
                    <input type="text" class="widefat sitio-cero-aviso-files__input" data-key="label" placeholder="<?php esc_attr_e('Nombre', 'sitio-cero'); ?>" value="">
                    <input type="url" class="widefat sitio-cero-aviso-files__input" data-key="url" placeholder="https://..." value="">
                    <select class="sitio-cero-aviso-files__select" data-key="icon">
                        <?php foreach ($icon_options as $icon_key => $icon_label) : ?>
                            <option value="<?php echo esc_attr($icon_key); ?>"><?php echo esc_html($icon_label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button-link-delete sitio-cero-aviso-files__remove"><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>
            </template>
        </div>
        <textarea id="sitio_cero_aviso_archivos" name="sitio_cero_aviso_archivos" class="widefat" rows="4" hidden><?php echo esc_textarea($archivos); ?></textarea>
    </p>
    <?php
}

function sitio_cero_get_aviso_file_icon_options()
{
    return array(
        ''     => __('Icono automatico', 'sitio-cero'),
        'doc'  => __('DOC', 'sitio-cero'),
        'pdf'  => __('PDF', 'sitio-cero'),
        'img'  => __('IMG', 'sitio-cero'),
        'xls'  => __('XLS', 'sitio-cero'),
        'file' => __('Archivo', 'sitio-cero'),
    );
}

function sitio_cero_sanitize_aviso_file_icon($value)
{
    $value = strtolower(trim((string) $value));
    $options = sitio_cero_get_aviso_file_icon_options();
    return isset($options[$value]) ? $value : '';
}

function sitio_cero_detect_aviso_file_icon_by_url($url)
{
    $url = is_string($url) ? trim($url) : '';
    if ('' === $url) {
        return 'file';
    }

    $path = wp_parse_url($url, PHP_URL_PATH);
    $extension = is_string($path) ? strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) : '';

    if (in_array($extension, array('pdf'), true)) {
        return 'pdf';
    }

    if (in_array($extension, array('doc', 'docx', 'odt', 'rtf', 'txt'), true)) {
        return 'doc';
    }

    if (in_array($extension, array('xls', 'xlsx', 'csv', 'ods'), true)) {
        return 'xls';
    }

    if (in_array($extension, array('jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'heic', 'bmp', 'tif', 'tiff'), true)) {
        return 'img';
    }

    return 'file';
}

function sitio_cero_get_aviso_file_icon_symbol($icon_key)
{
    $icon_key = sitio_cero_sanitize_aviso_file_icon($icon_key);
    if ('' === $icon_key) {
        $icon_key = 'file';
    }

    $map = array(
        'doc'  => 'description',
        'pdf'  => 'picture_as_pdf',
        'img'  => 'image',
        'xls'  => 'table_view',
        'file' => 'attach_file',
    );

    return isset($map[$icon_key]) ? $map[$icon_key] : 'attach_file';
}

function sitio_cero_sanitize_aviso_links_textarea($value)
{
    $value = is_string($value) ? $value : '';
    if ('' === trim($value)) {
        return '';
    }

    $lines = preg_split('/\r\n|\r|\n/', $value);
    if (!is_array($lines)) {
        return '';
    }

    $normalized = array();
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ('' === $line) {
            continue;
        }

        $parts = explode('|', $line, 3);
        $label = '';
        $url = $line;
        $icon = '';

        if (2 === count($parts)) {
            $first = trim((string) $parts[0]);
            $second = trim((string) $parts[1]);
            $second_icon = sitio_cero_sanitize_aviso_file_icon($second);

            if ('' !== esc_url_raw($first) && '' !== $second_icon && '' === esc_url_raw($second)) {
                // Legacy shorthand when label is empty: url|icon
                $url = $first;
                $icon = $second_icon;
            } else {
                $label = sanitize_text_field($first);
                $url = $second;
            }
        } elseif (3 <= count($parts)) {
            $label = sanitize_text_field(trim((string) $parts[0]));
            $url = trim((string) $parts[1]);
            $icon = sitio_cero_sanitize_aviso_file_icon((string) $parts[2]);
        }

        $url = esc_url_raw($url);
        if ('' === $url) {
            continue;
        }

        if ('' === $label) {
            $normalized_line = $url;
        } else {
            $normalized_line = $label . '|' . $url;
        }

        if ('' !== $icon) {
            $normalized_line .= '|' . $icon;
        }

        $normalized[] = $normalized_line;
    }

    return implode("\n", $normalized);
}

function sitio_cero_parse_aviso_links_textarea($value)
{
    $value = is_string($value) ? $value : '';
    if ('' === trim($value)) {
        return array();
    }

    $lines = preg_split('/\r\n|\r|\n/', $value);
    if (!is_array($lines)) {
        return array();
    }

    $items = array();
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ('' === $line) {
            continue;
        }

        $parts = explode('|', $line, 3);
        $label = '';
        $url = $line;
        $icon = '';

        if (2 === count($parts)) {
            $first = trim((string) $parts[0]);
            $second = trim((string) $parts[1]);
            $second_icon = sitio_cero_sanitize_aviso_file_icon($second);

            if ('' !== esc_url_raw($first) && '' !== $second_icon && '' === esc_url_raw($second)) {
                // Legacy shorthand when label is empty: url|icon
                $url = $first;
                $icon = $second_icon;
            } else {
                $label = sanitize_text_field($first);
                $url = $second;
            }
        } elseif (3 <= count($parts)) {
            $label = sanitize_text_field(trim((string) $parts[0]));
            $url = trim((string) $parts[1]);
            $icon = sitio_cero_sanitize_aviso_file_icon((string) $parts[2]);
        }

        $url = esc_url_raw($url);
        if ('' === $url) {
            continue;
        }

        if ('' === $label) {
            $path = wp_parse_url($url, PHP_URL_PATH);
            if (is_string($path) && '' !== trim($path)) {
                $basename = basename($path);
                if (is_string($basename) && '' !== trim($basename)) {
                    $label = sanitize_text_field(urldecode($basename));
                }
            }
        }

        if ('' === $label) {
            $label = __('Descargar archivo', 'sitio-cero');
        }

        if ('' === $icon) {
            $icon = sitio_cero_detect_aviso_file_icon_by_url($url);
        }

        $items[] = array(
            'label' => $label,
            'url'   => $url,
            'icon'  => $icon,
        );
    }

    return $items;
}

function sitio_cero_get_aviso_links($post_id, $meta_key)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $raw_value = get_post_meta($post_id, $meta_key, true);
    return sitio_cero_parse_aviso_links_textarea(is_string($raw_value) ? $raw_value : '');
}

function sitio_cero_save_aviso_meta($post_id)
{
    if (!isset($_POST['sitio_cero_aviso_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_aviso_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_aviso_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $image_url = '';
    if (isset($_POST['sitio_cero_aviso_image_url'])) {
        $image_url = esc_url_raw(wp_unslash($_POST['sitio_cero_aviso_image_url']));
    }

    if ('' !== $image_url) {
        update_post_meta($post_id, 'sitio_cero_aviso_image_url', $image_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_image_url');
    }

    $parrafos = '';
    if (isset($_POST['sitio_cero_aviso_parrafos'])) {
        $parrafos = wp_unslash($_POST['sitio_cero_aviso_parrafos']);
        if (current_user_can('unfiltered_html')) {
            $parrafos = preg_replace('/<\/?script[^>]*>/i', '', (string) $parrafos);
            $parrafos = preg_replace('/\s+on[a-z]+\s*=\s*([\'"]).*?\1/i', '', (string) $parrafos);
            $parrafos = trim((string) $parrafos);
        } else {
            $parrafos = sanitize_textarea_field((string) $parrafos);
        }
    }

    if ('' !== $parrafos) {
        update_post_meta($post_id, 'sitio_cero_aviso_parrafos', $parrafos);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_parrafos');
    }

    $documentos = '';
    if (isset($_POST['sitio_cero_aviso_documentos'])) {
        $documentos = sitio_cero_sanitize_aviso_links_textarea(wp_unslash($_POST['sitio_cero_aviso_documentos']));
    }

    if ('' !== $documentos) {
        update_post_meta($post_id, 'sitio_cero_aviso_documentos', $documentos);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_documentos');
    }

    $archivos = '';
    if (isset($_POST['sitio_cero_aviso_archivos'])) {
        $archivos = sitio_cero_sanitize_aviso_links_textarea(wp_unslash($_POST['sitio_cero_aviso_archivos']));
    }

    if ('' !== $archivos) {
        update_post_meta($post_id, 'sitio_cero_aviso_archivos', $archivos);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_archivos');
    }
}
add_action('save_post_aviso', 'sitio_cero_save_aviso_meta');

function sitio_cero_get_aviso_image_url($post_id, $size = 'large')
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    if (has_post_thumbnail($post_id)) {
        $image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), $size);
        if (is_array($image_src) && !empty($image_src[0])) {
            return (string) $image_src[0];
        }
    }

    $image_url = get_post_meta($post_id, 'sitio_cero_aviso_image_url', true);
    if (!is_string($image_url)) {
        return '';
    }

    return esc_url_raw($image_url);
}

function sitio_cero_get_default_aviso_examples()
{
    return array(
        array(
            'title'     => __('Aviso: mantenimiento nocturno en avenida principal', 'sitio-cero'),
            'excerpt'   => __('Habra ajustes operativos entre las 22:00 y 05:00 hrs durante esta semana.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-01/1400/760',
        ),
        array(
            'title'     => __('Aviso: operativo barrial de retiro de enseres', 'sitio-cero'),
            'excerpt'   => __('Revisa puntos habilitados y horarios para retiro de residuos voluminosos.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-02/1400/760',
        ),
        array(
            'title'     => __('Aviso: campaña comunal de reciclaje puerta a puerta', 'sitio-cero'),
            'excerpt'   => __('Nueva ruta semanal para plastico, carton y vidrio en sectores priorizados.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-03/1400/760',
        ),
        array(
            'title'     => __('Aviso: agenda de servicios municipales en terreno', 'sitio-cero'),
            'excerpt'   => __('Atencion ciudadana y orientacion de tramites en distintos barrios.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-04/1400/760',
        ),
        array(
            'title'     => __('Aviso: trabajos de conservacion en plazas y areas verdes', 'sitio-cero'),
            'excerpt'   => __('Intervenciones programadas para mejorar espacios de recreacion comunitaria.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-05/1400/760',
        ),
        array(
            'title'     => __('Aviso: jornada especial de salud preventiva comunal', 'sitio-cero'),
            'excerpt'   => __('Controles y orientacion gratuita en puntos territoriales definidos.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-06/1400/760',
        ),
        array(
            'title'     => __('Aviso: actualizacion de rutas de transporte local', 'sitio-cero'),
            'excerpt'   => __('Cambios temporales por mejoras viales en tramos estrategicos.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-07/1400/760',
        ),
        array(
            'title'     => __('Aviso: convocatoria cultural abierta para organizaciones', 'sitio-cero'),
            'excerpt'   => __('Postula actividades para la cartelera comunal del proximo mes.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-08/1400/760',
        ),
        array(
            'title'     => __('Aviso: plan de seguridad vial en entornos escolares', 'sitio-cero'),
            'excerpt'   => __('Refuerzo de cruces, senaletica y apoyo preventivo en horarios punta.', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-aviso-09/1400/760',
        ),
    );
}

function sitio_cero_seed_default_avisos()
{
    if (!post_type_exists('aviso')) {
        return;
    }

    $seed_version = '2';
    $already_seeded_version = (string) get_option('sitio_cero_default_avisos_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $items = sitio_cero_get_default_aviso_examples();

    $existing_demo_posts = get_posts(
        array(
            'post_type'      => 'aviso',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => -1,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'date'       => 'ASC',
            ),
            'meta_key'       => '_sitio_cero_demo_aviso',
            'meta_value'     => '1',
            'no_found_rows'  => true,
        )
    );

    $keep_ids = array();

    foreach ($items as $index => $item) {
        $post_id = 0;

        foreach ($existing_demo_posts as $candidate_post) {
            if (in_array((int) $candidate_post->ID, $keep_ids, true)) {
                continue;
            }

            if ((string) $candidate_post->post_title === (string) $item['title']) {
                $post_id = (int) $candidate_post->ID;
                break;
            }
        }

        if ($post_id <= 0) {
            $post_id = wp_insert_post(
                array(
                    'post_type'    => 'aviso',
                    'post_status'  => 'publish',
                    'post_title'   => $item['title'],
                    'post_excerpt' => $item['excerpt'],
                    'menu_order'   => $index + 1,
                ),
                true
            );
        } else {
            wp_update_post(
                array(
                    'ID'           => $post_id,
                    'post_status'  => 'publish',
                    'post_excerpt' => $item['excerpt'],
                    'menu_order'   => $index + 1,
                )
            );
        }

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        $keep_ids[] = (int) $post_id;
        update_post_meta($post_id, '_sitio_cero_demo_aviso', '1');

        if (isset($item['image_url']) && is_string($item['image_url'])) {
            $image_url = esc_url_raw($item['image_url']);
            if ('' !== $image_url) {
                update_post_meta($post_id, 'sitio_cero_aviso_image_url', $image_url);
            }
        }
    }

    foreach ($existing_demo_posts as $candidate_post) {
        $candidate_id = (int) $candidate_post->ID;
        if (!in_array($candidate_id, $keep_ids, true)) {
            wp_trash_post($candidate_id);
        }
    }

    update_option('sitio_cero_default_avisos_seeded_version', $seed_version);
    update_option('sitio_cero_default_avisos_seeded', '1');
}
add_action('init', 'sitio_cero_seed_default_avisos', 34);

function sitio_cero_register_aviso_grilla_post_type()
{
    $labels = array(
        'name'               => __('Grilla de avisos', 'sitio-cero'),
        'singular_name'      => __('Item grilla', 'sitio-cero'),
        'menu_name'          => __('Grilla avisos', 'sitio-cero'),
        'name_admin_bar'     => __('Item grilla', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar item de grilla', 'sitio-cero'),
        'new_item'           => __('Nuevo item de grilla', 'sitio-cero'),
        'edit_item'          => __('Editar item de grilla', 'sitio-cero'),
        'view_item'          => __('Ver item de grilla', 'sitio-cero'),
        'all_items'          => __('Todos los items de grilla', 'sitio-cero'),
        'search_items'       => __('Buscar items de grilla', 'sitio-cero'),
        'not_found'          => __('No se encontraron items.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay items en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'aviso_grilla',
        array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => true,
            'publicly_queryable' => true,
            'exclude_from_search'=> false,
            'has_archive'        => false,
            'rewrite'            => array('slug' => 'grilla-avisos'),
            'menu_position'      => 23,
            'menu_icon'          => 'dashicons-screenoptions',
            'supports'           => array('title', 'editor', 'thumbnail', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_aviso_grilla_post_type');

function sitio_cero_add_aviso_grilla_metaboxes()
{
    add_meta_box(
        'sitio_cero_aviso_grilla_options',
        __('Imagen de la grilla', 'sitio-cero'),
        'sitio_cero_render_aviso_grilla_metabox',
        'aviso_grilla',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_aviso_grilla_metaboxes');

function sitio_cero_render_aviso_grilla_metabox($post)
{
    wp_nonce_field('sitio_cero_save_aviso_grilla_meta', 'sitio_cero_aviso_grilla_meta_nonce');

    $image_url = get_post_meta($post->ID, 'sitio_cero_aviso_grilla_image_url', true);
    if (!is_string($image_url)) {
        $image_url = '';
    }

    $hover_image_url = get_post_meta($post->ID, 'sitio_cero_aviso_grilla_hover_image_url', true);
    if (!is_string($hover_image_url)) {
        $hover_image_url = '';
    }

    $target_url = get_post_meta($post->ID, 'sitio_cero_aviso_grilla_target_url', true);
    if (!is_string($target_url)) {
        $target_url = '';
    }
    ?>
    <p>
        <label for="sitio_cero_aviso_grilla_image_url"><strong><?php esc_html_e('URL de imagen externa (opcional)', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_aviso_grilla_image_url" name="sitio_cero_aviso_grilla_image_url" type="url" class="widefat" value="<?php echo esc_attr($image_url); ?>" placeholder="https://...">
        <button type="button" class="button button-secondary sitio-cero-media-picker" data-target="#sitio_cero_aviso_grilla_image_url">
            <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
        </button>
    </p>
    <p>
        <label for="sitio_cero_aviso_grilla_hover_image_url"><strong><?php esc_html_e('URL imagen hover (opcional)', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_aviso_grilla_hover_image_url" name="sitio_cero_aviso_grilla_hover_image_url" type="url" class="widefat" value="<?php echo esc_attr($hover_image_url); ?>" placeholder="https://...">
        <button type="button" class="button button-secondary sitio-cero-media-picker" data-target="#sitio_cero_aviso_grilla_hover_image_url">
            <?php esc_html_e('Seleccionar desde biblioteca', 'sitio-cero'); ?>
        </button>
    </p>
    <p>
        <label for="sitio_cero_aviso_grilla_target_url"><strong><?php esc_html_e('URL destino al hacer clic (opcional)', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_aviso_grilla_target_url" name="sitio_cero_aviso_grilla_target_url" type="url" class="widefat" value="<?php echo esc_attr($target_url); ?>" placeholder="https://...">
    </p>
    <p class="description">
        <?php esc_html_e('Puedes usar imagen destacada o URL externa como imagen principal, una imagen opcional para hover y un enlace de destino personalizado.', 'sitio-cero'); ?>
    </p>
    <?php
}

function sitio_cero_save_aviso_grilla_meta($post_id)
{
    if (!isset($_POST['sitio_cero_aviso_grilla_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_aviso_grilla_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_aviso_grilla_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $image_url = '';
    if (isset($_POST['sitio_cero_aviso_grilla_image_url'])) {
        $image_url = esc_url_raw(wp_unslash($_POST['sitio_cero_aviso_grilla_image_url']));
    }

    if ('' !== $image_url) {
        update_post_meta($post_id, 'sitio_cero_aviso_grilla_image_url', $image_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_grilla_image_url');
    }

    $hover_image_url = '';
    if (isset($_POST['sitio_cero_aviso_grilla_hover_image_url'])) {
        $hover_image_url = esc_url_raw(wp_unslash($_POST['sitio_cero_aviso_grilla_hover_image_url']));
    }

    if ('' !== $hover_image_url) {
        update_post_meta($post_id, 'sitio_cero_aviso_grilla_hover_image_url', $hover_image_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_grilla_hover_image_url');
    }

    $target_url = '';
    if (isset($_POST['sitio_cero_aviso_grilla_target_url'])) {
        $target_url = esc_url_raw(wp_unslash($_POST['sitio_cero_aviso_grilla_target_url']));
    }

    if ('' !== $target_url) {
        update_post_meta($post_id, 'sitio_cero_aviso_grilla_target_url', $target_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_aviso_grilla_target_url');
    }
}
add_action('save_post_aviso_grilla', 'sitio_cero_save_aviso_grilla_meta');

function sitio_cero_get_aviso_grilla_image_url($post_id, $size = 'large')
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    if (has_post_thumbnail($post_id)) {
        $image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), $size);
        if (is_array($image_src) && !empty($image_src[0])) {
            return (string) $image_src[0];
        }
    }

    $image_url = get_post_meta($post_id, 'sitio_cero_aviso_grilla_image_url', true);
    if (!is_string($image_url)) {
        return '';
    }

    return esc_url_raw($image_url);
}

function sitio_cero_get_aviso_grilla_hover_image_url($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $image_url = get_post_meta($post_id, 'sitio_cero_aviso_grilla_hover_image_url', true);
    if (!is_string($image_url)) {
        return '';
    }

    return esc_url_raw($image_url);
}

function sitio_cero_get_aviso_grilla_target_url($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $target_url = get_post_meta($post_id, 'sitio_cero_aviso_grilla_target_url', true);
    if (is_string($target_url)) {
        $target_url = esc_url_raw($target_url);
        if ('' !== $target_url) {
            return $target_url;
        }
    }

    $permalink = get_permalink($post_id);
    return is_string($permalink) ? $permalink : '';
}

function sitio_cero_get_default_aviso_grilla_examples()
{
    return array(
        array(
            'title'     => __('Grilla: servicios municipales 01', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-01/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 02', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-02/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 03', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-03/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 04', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-04/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 05', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-05/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 06', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-06/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 07', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-07/1400/900',
        ),
        array(
            'title'     => __('Grilla: servicios municipales 08', 'sitio-cero'),
            'image_url' => 'https://picsum.photos/seed/concepcion-grid-08/1400/900',
        ),
    );
}

function sitio_cero_seed_default_aviso_grilla()
{
    if (!post_type_exists('aviso_grilla')) {
        return;
    }

    $seed_version = '1';
    $already_seeded_version = (string) get_option('sitio_cero_default_aviso_grilla_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $items = sitio_cero_get_default_aviso_grilla_examples();
    $existing_demo_posts = get_posts(
        array(
            'post_type'      => 'aviso_grilla',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => -1,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'date'       => 'ASC',
            ),
            'meta_key'       => '_sitio_cero_demo_aviso_grilla',
            'meta_value'     => '1',
            'no_found_rows'  => true,
        )
    );

    $keep_ids = array();

    foreach ($items as $index => $item) {
        $post_id = 0;
        foreach ($existing_demo_posts as $candidate_post) {
            if (in_array((int) $candidate_post->ID, $keep_ids, true)) {
                continue;
            }

            if ((string) $candidate_post->post_title === (string) $item['title']) {
                $post_id = (int) $candidate_post->ID;
                break;
            }
        }

        if ($post_id <= 0) {
            $post_id = wp_insert_post(
                array(
                    'post_type'   => 'aviso_grilla',
                    'post_status' => 'publish',
                    'post_title'  => $item['title'],
                    'menu_order'  => $index + 1,
                ),
                true
            );
        } else {
            wp_update_post(
                array(
                    'ID'         => $post_id,
                    'post_status'=> 'publish',
                    'menu_order' => $index + 1,
                )
            );
        }

        if (is_wp_error($post_id) || !$post_id) {
            continue;
        }

        $keep_ids[] = (int) $post_id;
        update_post_meta($post_id, '_sitio_cero_demo_aviso_grilla', '1');

        if (isset($item['image_url']) && is_string($item['image_url'])) {
            $image_url = esc_url_raw($item['image_url']);
            if ('' !== $image_url) {
                update_post_meta($post_id, 'sitio_cero_aviso_grilla_image_url', $image_url);
            }
        }
    }

    foreach ($existing_demo_posts as $candidate_post) {
        $candidate_id = (int) $candidate_post->ID;
        if (!in_array($candidate_id, $keep_ids, true)) {
            wp_trash_post($candidate_id);
        }
    }

    update_option('sitio_cero_default_aviso_grilla_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_aviso_grilla', 35);

function sitio_cero_sanitize_tramite_custom_css($css)
{
    $css = wp_kses((string) $css, array());
    $css = preg_replace('/<\/?style[^>]*>/i', '', $css);

    return trim((string) $css);
}

function sitio_cero_get_hero_info_default_items()
{
    return array(
        array(
            'label' => __('Alcalde de Santiago', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Seguridad', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Juzgados', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Emergencias', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Vacunas', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Concejo Municipal', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Plan Regulador', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Bus Vecinal', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Mascotas', 'sitio-cero'),
            'url'   => '#',
        ),
        array(
            'label' => __('Aseo', 'sitio-cero'),
            'url'   => '#',
        ),
    );
}

function sitio_cero_get_menu_item_icon_markup($menu_item_id, $base_class = 'acciones-bt__icon')
{
    $base_class = preg_replace('/[^A-Za-z0-9_\\-]/', '', (string) $base_class);
    if ('' === trim((string) $base_class)) {
        $base_class = 'acciones-bt__icon';
    }

    $icon_value = trim((string) get_post_meta($menu_item_id, '_sitio_cero_menu_icon', true));
    if ('' === $icon_value) {
        return '';
    }

    if (0 === strpos($icon_value, 'google:')) {
        $icon_name = sitio_cero_sanitize_google_menu_icon_name(substr($icon_value, 7));
        if ('' !== $icon_name) {
            return '<span class="' . esc_attr($base_class) . ' ' . esc_attr($base_class) . '--google material-symbols-rounded" aria-hidden="true">' . esc_html($icon_name) . '</span>';
        }
    }

    if (wp_http_validate_url($icon_value)) {
        return '<span class="' . esc_attr($base_class) . ' ' . esc_attr($base_class) . '--image" aria-hidden="true"><img class="' . esc_attr($base_class) . '-image" src="' . esc_url($icon_value) . '" alt=""></span>';
    }

    $icon_classes = preg_replace('/[^A-Za-z0-9_\\-\\s]/', '', $icon_value);
    $icon_classes = trim((string) $icon_classes);
    if ('' === $icon_classes) {
        return '';
    }

    return '<span class="' . esc_attr($base_class) . ' ' . esc_attr($icon_classes) . '" aria-hidden="true"></span>';
}

function sitio_cero_primary_menu_item_title_with_icon($title, $menu_item, $args, $depth)
{
    if (!$menu_item instanceof WP_Post || !is_object($args)) {
        return $title;
    }

    if (!isset($args->theme_location) || 'primary' !== (string) $args->theme_location || $depth <= 0) {
        return $title;
    }

    $icon_markup = sitio_cero_get_menu_item_icon_markup($menu_item->ID, 'site-nav__item-icon');
    $label_markup = '<span class="site-nav__item-label">' . $title . '</span>';

    if ('' === $icon_markup) {
        return $label_markup;
    }

    return $icon_markup . $label_markup;
}
add_filter('nav_menu_item_title', 'sitio_cero_primary_menu_item_title_with_icon', 10, 4);

function sitio_cero_get_google_menu_icon_options()
{
    return array(
        'info'                    => __('Informacion', 'sitio-cero'),
        'help'                    => __('Ayuda', 'sitio-cero'),
        'contact_support'         => __('Soporte', 'sitio-cero'),
        'campaign'                => __('Noticias', 'sitio-cero'),
        'announcement'            => __('Comunicados', 'sitio-cero'),
        'newspaper'               => __('Prensa', 'sitio-cero'),
        'article'                 => __('Articulos', 'sitio-cero'),
        'feed'                    => __('Novedades', 'sitio-cero'),
        'notifications'           => __('Alertas', 'sitio-cero'),
        'warning'                 => __('Advertencia', 'sitio-cero'),
        'error'                   => __('Error', 'sitio-cero'),
        'report'                  => __('Reportes', 'sitio-cero'),
        'emergency_home'          => __('Emergencias hogar', 'sitio-cero'),
        'local_fire_department'   => __('Bomberos', 'sitio-cero'),
        'health_and_safety'       => __('Salud y seguridad', 'sitio-cero'),
        'sos'                     => __('SOS', 'sitio-cero'),
        'medical_information'     => __('Informacion medica', 'sitio-cero'),
        'local_hospital'          => __('Hospital', 'sitio-cero'),
        'vaccines'                => __('Vacunas', 'sitio-cero'),
        'medication'              => __('Medicacion', 'sitio-cero'),
        'monitor_heart'           => __('Monitoreo de salud', 'sitio-cero'),
        'contact_phone'           => __('Contacto telefonico', 'sitio-cero'),
        'phone_in_talk'           => __('Linea directa', 'sitio-cero'),
        'support_agent'           => __('Atencion ciudadana', 'sitio-cero'),
        'chat'                    => __('Chat', 'sitio-cero'),
        'forum'                   => __('Foro', 'sitio-cero'),
        'mail'                    => __('Correo', 'sitio-cero'),
        'mail_outline'            => __('Correo simple', 'sitio-cero'),
        'alternate_email'         => __('Email alternativo', 'sitio-cero'),
        'account_circle'          => __('Cuenta', 'sitio-cero'),
        'groups'                  => __('Vecinos', 'sitio-cero'),
        'group'                   => __('Grupo', 'sitio-cero'),
        'person'                  => __('Persona', 'sitio-cero'),
        'badge'                   => __('Credencial', 'sitio-cero'),
        'public'                  => __('Comunidad', 'sitio-cero'),
        'travel_explore'          => __('Turismo', 'sitio-cero'),
        'place'                   => __('Lugar', 'sitio-cero'),
        'location_on'             => __('Ubicacion', 'sitio-cero'),
        'map'                     => __('Mapa', 'sitio-cero'),
        'explore'                 => __('Explorar', 'sitio-cero'),
        'directions'              => __('Rutas', 'sitio-cero'),
        'directions_bus'          => __('Bus', 'sitio-cero'),
        'directions_car'          => __('Auto', 'sitio-cero'),
        'directions_walk'         => __('Peaton', 'sitio-cero'),
        'train'                   => __('Tren', 'sitio-cero'),
        'two_wheeler'             => __('Motocicleta', 'sitio-cero'),
        'traffic'                 => __('Transito', 'sitio-cero'),
        'local_taxi'              => __('Taxi', 'sitio-cero'),
        'payments'                => __('Pagos', 'sitio-cero'),
        'receipt_long'            => __('Boletas', 'sitio-cero'),
        'credit_card'             => __('Tarjeta', 'sitio-cero'),
        'account_balance_wallet'  => __('Billetera', 'sitio-cero'),
        'account_balance'         => __('Municipio', 'sitio-cero'),
        'request_quote'           => __('Cotizaciones', 'sitio-cero'),
        'paid'                    => __('Pagado', 'sitio-cero'),
        'gavel'                   => __('Juzgados', 'sitio-cero'),
        'policy'                  => __('Politicas', 'sitio-cero'),
        'verified_user'           => __('Usuario verificado', 'sitio-cero'),
        'fact_check'              => __('Fiscalizacion', 'sitio-cero'),
        'assignment'              => __('Tramites', 'sitio-cero'),
        'description'             => __('Documentos', 'sitio-cero'),
        'folder'                  => __('Carpetas', 'sitio-cero'),
        'folder_shared'           => __('Expedientes', 'sitio-cero'),
        'home'                    => __('Hogar', 'sitio-cero'),
        'apartment'               => __('Urbanismo', 'sitio-cero'),
        'home_work'               => __('Vivienda', 'sitio-cero'),
        'storefront'              => __('Comercio', 'sitio-cero'),
        'business'                => __('Empresas', 'sitio-cero'),
        'construction'            => __('Obras', 'sitio-cero'),
        'engineering'             => __('Ingenieria', 'sitio-cero'),
        'build'                   => __('Mantencion', 'sitio-cero'),
        'handyman'                => __('Reparaciones', 'sitio-cero'),
        'plumbing'                => __('Agua y alcantarillado', 'sitio-cero'),
        'electrical_services'     => __('Electricidad', 'sitio-cero'),
        'cleaning_services'       => __('Aseo', 'sitio-cero'),
        'delete_sweep'            => __('Limpieza urbana', 'sitio-cero'),
        'recycling'               => __('Reciclaje', 'sitio-cero'),
        'park'                    => __('Parques', 'sitio-cero'),
        'forest'                  => __('Areas verdes', 'sitio-cero'),
        'pets'                    => __('Mascotas', 'sitio-cero'),
        'eco'                     => __('Medio ambiente', 'sitio-cero'),
        'water_drop'              => __('Agua', 'sitio-cero'),
        'wb_sunny'                => __('Clima', 'sitio-cero'),
        'thunderstorm'            => __('Alerta climatica', 'sitio-cero'),
        'school'                  => __('Educacion', 'sitio-cero'),
        'menu_book'               => __('Biblioteca', 'sitio-cero'),
        'library_books'           => __('Libros', 'sitio-cero'),
        'event'                   => __('Eventos', 'sitio-cero'),
        'event_available'         => __('Agenda', 'sitio-cero'),
        'calendar_month'          => __('Calendario', 'sitio-cero'),
        'celebration'             => __('Celebraciones', 'sitio-cero'),
        'sports_soccer'           => __('Deportes', 'sitio-cero'),
        'sports_basketball'       => __('Canchas', 'sitio-cero'),
        'museum'                  => __('Museo', 'sitio-cero'),
        'theaters'                => __('Cultura', 'sitio-cero'),
        'music_note'              => __('Musica', 'sitio-cero'),
        'camera_alt'              => __('Galeria', 'sitio-cero'),
        'photo_library'           => __('Fotos', 'sitio-cero'),
        'image'                   => __('Imagen', 'sitio-cero'),
        'volunteer_activism'      => __('Voluntariado', 'sitio-cero'),
        'diversity_3'             => __('Inclusion', 'sitio-cero'),
        'local_police'            => __('Seguridad publica', 'sitio-cero'),
        'security'                => __('Seguridad', 'sitio-cero'),
        'shield'                  => __('Proteccion', 'sitio-cero'),
        'admin_panel_settings'    => __('Administracion', 'sitio-cero'),
        'bolt'                    => __('Urgente', 'sitio-cero'),
        'wifi'                    => __('Conectividad', 'sitio-cero'),
        'language'                => __('Web municipal', 'sitio-cero'),
        'search'                  => __('Busqueda', 'sitio-cero'),
    );
}

function sitio_cero_sanitize_google_menu_icon_name($icon_name)
{
    $icon_name = strtolower(trim((string) $icon_name));
    $icon_name = preg_replace('/[^a-z0-9_]/', '', $icon_name);

    $allowed_icons = sitio_cero_get_google_menu_icon_options();
    if (!isset($allowed_icons[$icon_name])) {
        return '';
    }

    return $icon_name;
}

function sitio_cero_hero_info_menu_fallback()
{
    $default_items = sitio_cero_get_hero_info_default_items();

    echo '<ul id="menu-quiero-informacion-de" class="acciones-bt">';
    foreach ($default_items as $item) {
        echo '<li class="menu-item">';
        echo '<a href="' . esc_url($item['url']) . '">';
        echo '<span class="acciones-bt__text">' . esc_html($item['label']) . '</span>';
        echo '</a>';
        echo '</li>';
    }
    echo '</ul>';
}

if (!class_exists('Sitio_Cero_Hero_Info_Menu_Walker')) {
    class Sitio_Cero_Hero_Info_Menu_Walker extends Walker_Nav_Menu
    {
        public function start_el(&$output, $data_object, $depth = 0, $args = null, $id = 0)
        {
            if (!$data_object instanceof WP_Post) {
                return;
            }

            $menu_item = $data_object;

            $classes = empty($menu_item->classes) ? array() : (array) $menu_item->classes;
            $classes[] = 'menu-item-' . $menu_item->ID;
            $class_names = '';
            if (!empty($classes)) {
                $sanitized_classes = array_filter(array_map('sanitize_html_class', $classes));
                $class_names = ' class="' . esc_attr(implode(' ', $sanitized_classes)) . '"';
            }

            $output .= '<li' . $class_names . '>';

            $atts = array();
            $atts['title']  = !empty($menu_item->attr_title) ? $menu_item->attr_title : '';
            $atts['target'] = !empty($menu_item->target) ? $menu_item->target : '';
            $atts['rel']    = !empty($menu_item->xfn) ? $menu_item->xfn : '';
            $atts['href']   = !empty($menu_item->url) ? $menu_item->url : '';

            $attributes = '';
            foreach ($atts as $attr => $value) {
                if ('' === $value) {
                    continue;
                }

                $escaped_value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $escaped_value . '"';
            }

            $title = apply_filters('the_title', $menu_item->title, $menu_item->ID);
            $title = apply_filters('nav_menu_item_title', $title, $menu_item, $args, $depth);

            $item_output = isset($args->before) ? $args->before : '';
            $item_output .= '<a' . $attributes . '>';
            $item_output .= sitio_cero_get_menu_item_icon_markup($menu_item->ID);
            $item_output .= '<span class="acciones-bt__text">';
            $item_output .= (isset($args->link_before) ? $args->link_before : '') . $title . (isset($args->link_after) ? $args->link_after : '');
            $item_output .= '</span>';
            $item_output .= '</a>';
            $item_output .= isset($args->after) ? $args->after : '';

            $output .= apply_filters('walker_nav_menu_start_el', $item_output, $menu_item, $depth, $args);
        }
    }
}

function sitio_cero_get_tramites_icon_svg_map()
{
    return array(
        'leaf' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22V12M12 12C12 6 7 4 2 4c0 5 2 10 10 8M12 12c0-6 5-8 10-8-1 5-4 10-10 8"/></svg>',
        'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.9L15 14"/><rect x="1" y="6" width="15" height="13" rx="2"/></svg>',
        'id_card' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M8 9h8M8 13h5"/></svg>',
        'document' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
        'people' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
        'pin' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'truck' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8l5 1v9l-5 1V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
        'payment' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
        'layers' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>',
        'social' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 11l-4 4-2-2"/></svg>',
        'publications' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22h16a2 2 0 002-2V4a2 2 0 00-2-2H8a2 2 0 00-2 2v2"/><path d="M2 12h10m0 0l-4-4m4 4l-4 4"/></svg>',
        'jobs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><path d="M11 8v6M8 11h6"/></svg>',
    );
}

function sitio_cero_get_tramites_icon_svg($icon_key)
{
    $icon_key = strtolower(trim((string) $icon_key));
    $icons = sitio_cero_get_tramites_icon_svg_map();
    if (!isset($icons[$icon_key])) {
        return '';
    }
    return $icons[$icon_key];
}

function sitio_cero_get_tramites_hero_default_items()
{
    return array(
        array(
            'label' => __('Permiso de circulación', 'sitio-cero'),
            'url'   => get_search_link(__('Permiso de circulación', 'sitio-cero')),
            'icon'  => 'leaf',
            'menu_icon' => 'directions_car',
        ),
        array(
            'label' => __('Patentes comerciales', 'sitio-cero'),
            'url'   => get_search_link(__('Patentes comerciales', 'sitio-cero')),
            'icon'  => 'camera',
            'menu_icon' => 'storefront',
        ),
        array(
            'label' => __('Licencias de conducir', 'sitio-cero'),
            'url'   => get_search_link(__('Licencias de conducir', 'sitio-cero')),
            'icon'  => 'id_card',
            'menu_icon' => 'badge',
        ),
        array(
            'label' => __('Permisos de obras', 'sitio-cero'),
            'url'   => get_search_link(__('Permisos de obras', 'sitio-cero')),
            'icon'  => 'document',
            'menu_icon' => 'construction',
        ),
        array(
            'label' => __('Aseo y residuos', 'sitio-cero'),
            'url'   => get_search_link(__('Aseo y residuos', 'sitio-cero')),
            'icon'  => 'truck',
            'menu_icon' => 'cleaning_services',
        ),
        array(
            'label' => __('Subsidios y beneficios', 'sitio-cero'),
            'url'   => get_search_link(__('Subsidios y beneficios', 'sitio-cero')),
            'icon'  => 'people',
            'menu_icon' => 'volunteer_activism',
        ),
        array(
            'label' => __('Transparencia', 'sitio-cero'),
            'url'   => get_search_link(__('Transparencia', 'sitio-cero')),
            'icon'  => 'star',
            'menu_icon' => 'policy',
        ),
        array(
            'label' => __('Atención ciudadana', 'sitio-cero'),
            'url'   => get_search_link(__('Atención ciudadana', 'sitio-cero')),
            'icon'  => 'pin',
            'menu_icon' => 'support_agent',
        ),
    );
}

function sitio_cero_get_tramites_grid_default_items()
{
    return array(
        array(
            'label'    => __('Pagos en línea', 'sitio-cero'),
            'subtitle' => __('Patentes, multas y más', 'sitio-cero'),
            'url'      => get_search_link(__('Pagos en línea', 'sitio-cero')),
            'icon'     => 'payment',
            'menu_icon' => 'payments',
            'classes'  => array('tramites-card--gold'),
        ),
        array(
            'label'    => __('Ordenanzas municipales', 'sitio-cero'),
            'subtitle' => __('Normativa vigente', 'sitio-cero'),
            'url'      => get_search_link(__('Ordenanzas municipales', 'sitio-cero')),
            'icon'     => 'layers',
            'menu_icon' => 'gavel',
            'classes'  => array('tramites-card--navy'),
        ),
        array(
            'label'    => __('Planes y políticas comunales', 'sitio-cero'),
            'subtitle' => __('Documentos estratégicos', 'sitio-cero'),
            'url'      => get_search_link(__('Planes y políticas comunales', 'sitio-cero')),
            'icon'     => 'document',
            'menu_icon' => 'description',
            'classes'  => array('tramites-card--rust'),
        ),
        array(
            'label'    => __('Licencias de conducir', 'sitio-cero'),
            'subtitle' => __('Trámites y renovaciones', 'sitio-cero'),
            'url'      => get_search_link(__('Licencias de conducir', 'sitio-cero')),
            'icon'     => 'id_card',
            'menu_icon' => 'badge',
            'classes'  => array('tramites-card--blue'),
        ),
        array(
            'label'    => __('Programas sociales', 'sitio-cero'),
            'subtitle' => __('Beneficios y postulaciones', 'sitio-cero'),
            'url'      => get_search_link(__('Programas sociales', 'sitio-cero')),
            'icon'     => 'social',
            'menu_icon' => 'volunteer_activism',
            'classes'  => array('tramites-card--orange'),
        ),
        array(
            'label'    => __('Publicaciones municipales', 'sitio-cero'),
            'subtitle' => __('Memorias, boletines e informes', 'sitio-cero'),
            'url'      => get_search_link(__('Publicaciones municipales', 'sitio-cero')),
            'icon'     => 'publications',
            'menu_icon' => 'library_books',
            'classes'  => array('tramites-card--ink'),
        ),
        array(
            'label'    => __('Retiro residuos domiciliarios', 'sitio-cero'),
            'subtitle' => __('Calendario y zonas', 'sitio-cero'),
            'url'      => get_search_link(__('Retiro residuos domiciliarios', 'sitio-cero')),
            'icon'     => 'truck',
            'menu_icon' => 'delete_sweep',
            'classes'  => array('tramites-card--slate'),
        ),
        array(
            'label'    => __('Ofertas laborales', 'sitio-cero'),
            'subtitle' => __('Empleos y convocatorias', 'sitio-cero'),
            'url'      => get_search_link(__('Ofertas laborales', 'sitio-cero')),
            'icon'     => 'jobs',
            'menu_icon' => 'business',
            'classes'  => array('tramites-card--green'),
        ),
    );
}

function sitio_cero_seed_tramites_hero_menu_once()
{
    $seed_option = 'sitio_cero_tramites_hero_menu_seeded';
    if ('1' === (string) get_option($seed_option, '0')) {
        return;
    }

    if (!function_exists('has_nav_menu') || !function_exists('wp_create_nav_menu')) {
        return;
    }

    if (has_nav_menu('tramites_hero')) {
        update_option($seed_option, '1');
        return;
    }

    $menu_name = __('Menu Trámites - Hero', 'sitio-cero');
    $menu_object = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu_object && isset($menu_object->term_id) ? (int) $menu_object->term_id : 0;

    if ($menu_id <= 0) {
        $created_menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($created_menu_id) || (int) $created_menu_id <= 0) {
            return;
        }
        $menu_id = (int) $created_menu_id;
    }

    $existing_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (empty($existing_items)) {
        $default_items = sitio_cero_get_tramites_hero_default_items();
        foreach ($default_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $item_url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            if ('' === $item_label || '' === $item_url) {
                continue;
            }

            $menu_item_id = wp_update_nav_menu_item(
                $menu_id,
                0,
                array(
                    'menu-item-title'  => $item_label,
                    'menu-item-type'   => 'custom',
                    'menu-item-url'    => $item_url,
                    'menu-item-status' => 'publish',
                )
            );

            if (!is_wp_error($menu_item_id) && (int) $menu_item_id > 0) {
                $menu_icon = isset($item['menu_icon']) ? sitio_cero_sanitize_google_menu_icon_name((string) $item['menu_icon']) : '';
                if ('' !== $menu_icon) {
                    update_post_meta((int) $menu_item_id, '_sitio_cero_menu_icon', 'google:' . $menu_icon);
                }
            }
        }
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = array();
    }
    $locations['tramites_hero'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    update_option($seed_option, '1');
}
add_action('init', 'sitio_cero_seed_tramites_hero_menu_once', 23);

function sitio_cero_seed_tramites_grid_menu_once()
{
    $seed_option = 'sitio_cero_tramites_grid_menu_seeded';
    if ('1' === (string) get_option($seed_option, '0')) {
        return;
    }

    if (!function_exists('has_nav_menu') || !function_exists('wp_create_nav_menu')) {
        return;
    }

    if (has_nav_menu('tramites_grid')) {
        update_option($seed_option, '1');
        return;
    }

    $menu_name = __('Menu Trámites - Grilla', 'sitio-cero');
    $menu_object = wp_get_nav_menu_object($menu_name);
    $menu_id = $menu_object && isset($menu_object->term_id) ? (int) $menu_object->term_id : 0;

    if ($menu_id <= 0) {
        $created_menu_id = wp_create_nav_menu($menu_name);
        if (is_wp_error($created_menu_id) || (int) $created_menu_id <= 0) {
            return;
        }
        $menu_id = (int) $created_menu_id;
    }

    $existing_items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
    if (empty($existing_items)) {
        $default_items = sitio_cero_get_tramites_grid_default_items();
        foreach ($default_items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item_label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
            $item_url = isset($item['url']) ? esc_url_raw((string) $item['url']) : '';
            $item_desc = isset($item['subtitle']) ? sanitize_text_field((string) $item['subtitle']) : '';
            $item_classes = '';
            if (isset($item['classes']) && is_array($item['classes'])) {
                $item_classes = implode(' ', array_filter(array_map('sanitize_html_class', $item['classes'])));
            }

            if ('' === $item_label || '' === $item_url) {
                continue;
            }

            $menu_item_args = array(
                'menu-item-title'  => $item_label,
                'menu-item-type'   => 'custom',
                'menu-item-url'    => $item_url,
                'menu-item-status' => 'publish',
            );
            if ('' !== $item_desc) {
                $menu_item_args['menu-item-description'] = $item_desc;
            }
            if ('' !== $item_classes) {
                $menu_item_args['menu-item-classes'] = $item_classes;
            }

            $menu_item_id = wp_update_nav_menu_item($menu_id, 0, $menu_item_args);
            if (!is_wp_error($menu_item_id) && (int) $menu_item_id > 0) {
                $menu_icon = isset($item['menu_icon']) ? sitio_cero_sanitize_google_menu_icon_name((string) $item['menu_icon']) : '';
                if ('' !== $menu_icon) {
                    update_post_meta((int) $menu_item_id, '_sitio_cero_menu_icon', 'google:' . $menu_icon);
                }
            }
        }
    }

    $locations = get_theme_mod('nav_menu_locations');
    if (!is_array($locations)) {
        $locations = array();
    }
    $locations['tramites_grid'] = $menu_id;
    set_theme_mod('nav_menu_locations', $locations);

    update_option($seed_option, '1');
}
add_action('init', 'sitio_cero_seed_tramites_grid_menu_once', 24);

function sitio_cero_tramites_hero_menu_fallback()
{
    $items = sitio_cero_get_tramites_hero_default_items();
    foreach ($items as $item) {
        $label = isset($item['label']) ? (string) $item['label'] : '';
        $url = isset($item['url']) ? (string) $item['url'] : '#';
        $icon = isset($item['icon']) ? sitio_cero_get_tramites_icon_svg($item['icon']) : '';

        echo '<a class="tramites-pill" href="' . esc_url($url) . '">';
        if ('' !== $icon) {
            echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo esc_html($label);
        echo '</a>';
    }
}

function sitio_cero_tramites_grid_menu_fallback()
{
    $items = sitio_cero_get_tramites_grid_default_items();
    foreach ($items as $item) {
        $label = isset($item['label']) ? (string) $item['label'] : '';
        $subtitle = isset($item['subtitle']) ? (string) $item['subtitle'] : '';
        $url = isset($item['url']) ? (string) $item['url'] : '#';
        $icon = isset($item['icon']) ? sitio_cero_get_tramites_icon_svg($item['icon']) : '';
        $classes = isset($item['classes']) && is_array($item['classes']) ? $item['classes'] : array();
        $classes[] = 'tramites-card';
        $class_names = implode(' ', array_filter(array_map('sanitize_html_class', $classes)));

        echo '<a class="' . esc_attr($class_names) . '" href="' . esc_url($url) . '">';
        echo '<span class="tramites-card__icon" aria-hidden="true">';
        if ('' !== $icon) {
            echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo '</span>';
        echo '<span class="tramites-card__body">';
        echo '<span class="tramites-card__title">' . esc_html($label) . '</span>';
        if ('' !== trim($subtitle)) {
            echo '<span class="tramites-card__sub">' . esc_html($subtitle) . '</span>';
        }
        echo '</span>';
        echo '<span class="tramites-card__arrow" aria-hidden="true">&#x2192;</span>';
        echo '</a>';
    }
}

if (!class_exists('Sitio_Cero_Tramites_Hero_Menu_Walker')) {
    class Sitio_Cero_Tramites_Hero_Menu_Walker extends Walker_Nav_Menu
    {
        public function start_el(&$output, $data_object, $depth = 0, $args = null, $id = 0)
        {
            if (!$data_object instanceof WP_Post) {
                return;
            }

            $menu_item = $data_object;

            $classes = empty($menu_item->classes) ? array() : (array) $menu_item->classes;
            $classes[] = 'tramites-pill';
            $class_names = implode(' ', array_filter(array_map('sanitize_html_class', $classes)));

            $atts = array();
            $atts['title']  = !empty($menu_item->attr_title) ? $menu_item->attr_title : '';
            $atts['target'] = !empty($menu_item->target) ? $menu_item->target : '';
            $atts['rel']    = !empty($menu_item->xfn) ? $menu_item->xfn : '';
            $atts['href']   = !empty($menu_item->url) ? $menu_item->url : '';

            $attributes = '';
            foreach ($atts as $attr => $value) {
                if ('' === $value) {
                    continue;
                }

                $escaped_value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $escaped_value . '"';
            }

            $title = apply_filters('the_title', $menu_item->title, $menu_item->ID);
            $title = apply_filters('nav_menu_item_title', $title, $menu_item, $args, $depth);

            $item_output = '<a class="' . esc_attr($class_names) . '"' . $attributes . '>';
            $icon_markup = sitio_cero_get_menu_item_icon_markup($menu_item->ID, 'tramites-pill__icon');
            if ('' !== $icon_markup) {
                $item_output .= $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            $item_output .= '<span class="tramites-pill__text">' . $title . '</span>';
            $item_output .= '</a>';

            $output .= apply_filters('walker_nav_menu_start_el', $item_output, $menu_item, $depth, $args);
        }

        public function end_el(&$output, $data_object, $depth = 0, $args = null)
        {
            return;
        }
    }
}

if (!class_exists('Sitio_Cero_Tramites_Grid_Menu_Walker')) {
    class Sitio_Cero_Tramites_Grid_Menu_Walker extends Walker_Nav_Menu
    {
        public function start_el(&$output, $data_object, $depth = 0, $args = null, $id = 0)
        {
            if (!$data_object instanceof WP_Post) {
                return;
            }

            $menu_item = $data_object;

            $classes = empty($menu_item->classes) ? array() : (array) $menu_item->classes;
            $classes[] = 'tramites-card';
            $class_names = implode(' ', array_filter(array_map('sanitize_html_class', $classes)));

            $atts = array();
            $atts['title']  = !empty($menu_item->attr_title) ? $menu_item->attr_title : '';
            $atts['target'] = !empty($menu_item->target) ? $menu_item->target : '';
            $atts['rel']    = !empty($menu_item->xfn) ? $menu_item->xfn : '';
            $atts['href']   = !empty($menu_item->url) ? $menu_item->url : '';

            $attributes = '';
            foreach ($atts as $attr => $value) {
                if ('' === $value) {
                    continue;
                }

                $escaped_value = ('href' === $attr) ? esc_url($value) : esc_attr($value);
                $attributes .= ' ' . $attr . '="' . $escaped_value . '"';
            }

            $title = apply_filters('the_title', $menu_item->title, $menu_item->ID);
            $title = apply_filters('nav_menu_item_title', $title, $menu_item, $args, $depth);
            $description = isset($menu_item->description) ? trim((string) $menu_item->description) : '';

            $icon_markup = sitio_cero_get_menu_item_icon_markup($menu_item->ID, 'tramites-card__icon');
            if ('' === $icon_markup) {
                $icon_markup = '<span class="tramites-card__icon tramites-card__icon--placeholder" aria-hidden="true"></span>';
            }

            $item_output = '<a class="' . esc_attr($class_names) . '"' . $attributes . '>';
            $item_output .= $icon_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $item_output .= '<span class="tramites-card__body">';
            $item_output .= '<span class="tramites-card__title">' . $title . '</span>';
            if ('' !== $description) {
                $item_output .= '<span class="tramites-card__sub">' . esc_html($description) . '</span>';
            }
            $item_output .= '</span>';
            $item_output .= '<span class="tramites-card__arrow" aria-hidden="true">&#x2192;</span>';
            $item_output .= '</a>';

            $output .= apply_filters('walker_nav_menu_start_el', $item_output, $menu_item, $depth, $args);
        }

        public function end_el(&$output, $data_object, $depth = 0, $args = null)
        {
            return;
        }
    }
}

function sitio_cero_render_menu_item_icon_field($item_id, $menu_item)
{
    $stored_icon_value = get_post_meta($item_id, '_sitio_cero_menu_icon', true);
    if (!is_string($stored_icon_value)) {
        $stored_icon_value = '';
    }

    $selected_google_icon = '';
    $custom_icon_value = '';

    if (0 === strpos($stored_icon_value, 'google:')) {
        $selected_google_icon = sitio_cero_sanitize_google_menu_icon_name(substr($stored_icon_value, 7));
    } else {
        $custom_icon_value = $stored_icon_value;
    }

    $icon_options = sitio_cero_get_google_menu_icon_options();
    ?>
    <p class="description description-wide sitio-cero-menu-icon-field">
        <span class="sitio-cero-icon-picker__title"><?php esc_html_e('Icono Google (Material Symbols)', 'sitio-cero'); ?></span>
        <span class="description"><?php esc_html_e('Selecciona el icono visualmente.', 'sitio-cero'); ?></span>
    </p>
    <div class="sitio-cero-icon-picker" role="radiogroup" aria-label="<?php esc_attr_e('Seleccion de icono Google', 'sitio-cero'); ?>">
        <label class="sitio-cero-icon-picker__item">
            <input
                type="radio"
                class="sitio-cero-icon-picker__input"
                name="menu-item-sitio-cero-icon-google[<?php echo esc_attr((string) $item_id); ?>]"
                value=""
                <?php checked('', $selected_google_icon); ?>
            >
            <span class="sitio-cero-icon-picker__content">
                <span class="sitio-cero-icon-picker__preview sitio-cero-icon-picker__preview--none" aria-hidden="true">x</span>
                <span class="sitio-cero-icon-picker__label"><?php esc_html_e('Sin icono', 'sitio-cero'); ?></span>
            </span>
        </label>
        <?php foreach ($icon_options as $icon_name => $icon_label) : ?>
            <label class="sitio-cero-icon-picker__item">
                <input
                    type="radio"
                    class="sitio-cero-icon-picker__input"
                    name="menu-item-sitio-cero-icon-google[<?php echo esc_attr((string) $item_id); ?>]"
                    value="<?php echo esc_attr($icon_name); ?>"
                    <?php checked($selected_google_icon, $icon_name); ?>
                >
                <span class="sitio-cero-icon-picker__content">
                    <span class="sitio-cero-icon-picker__preview material-symbols-rounded" aria-hidden="true"><?php echo esc_html($icon_name); ?></span>
                    <span class="sitio-cero-icon-picker__label"><?php echo esc_html($icon_label); ?></span>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="description description-wide sitio-cero-menu-icon-field">
        <em><?php esc_html_e('Si eliges un icono Google y tambien escribes uno personalizado, se usara el icono personalizado.', 'sitio-cero'); ?></em>
    </p>
    <p class="description description-wide sitio-cero-menu-icon-field">
        <label for="edit-menu-item-sitio-cero-icon-custom-<?php echo esc_attr((string) $item_id); ?>">
            <?php esc_html_e('Icono personalizado (opcional, clase CSS o URL)', 'sitio-cero'); ?><br>
            <input
                type="text"
                id="edit-menu-item-sitio-cero-icon-custom-<?php echo esc_attr((string) $item_id); ?>"
                class="widefat code edit-menu-item-sitio-cero-icon-custom"
                name="menu-item-sitio-cero-icon-custom[<?php echo esc_attr((string) $item_id); ?>]"
                value="<?php echo esc_attr($custom_icon_value); ?>"
                placeholder="<?php echo esc_attr__('Ej: dashicons dashicons-admin-site o https://dominio.cl/icono.svg', 'sitio-cero'); ?>"
            >
        </label>
    </p>
    <?php
}
add_action('wp_nav_menu_item_custom_fields', 'sitio_cero_render_menu_item_icon_field', 10, 2);

function sitio_cero_save_menu_item_icon_field($menu_id, $menu_item_db_id)
{
    if (!current_user_can('edit_theme_options')) {
        return;
    }

    $custom_icon_value = '';
    if (isset($_POST['menu-item-sitio-cero-icon-custom'])) {
        $raw_custom_icons = wp_unslash($_POST['menu-item-sitio-cero-icon-custom']);
        if (is_array($raw_custom_icons) && isset($raw_custom_icons[$menu_item_db_id])) {
            $custom_icon_value = sanitize_text_field($raw_custom_icons[$menu_item_db_id]);
            $custom_icon_value = trim($custom_icon_value);
        }
    }

    if ('' !== $custom_icon_value) {
        update_post_meta($menu_item_db_id, '_sitio_cero_menu_icon', $custom_icon_value);
        return;
    }

    $google_icon_value = '';
    if (isset($_POST['menu-item-sitio-cero-icon-google'])) {
        $raw_google_icons = wp_unslash($_POST['menu-item-sitio-cero-icon-google']);
        if (is_array($raw_google_icons) && isset($raw_google_icons[$menu_item_db_id])) {
            $google_icon_value = sitio_cero_sanitize_google_menu_icon_name($raw_google_icons[$menu_item_db_id]);
        }
    }

    if ('' !== $google_icon_value) {
        update_post_meta($menu_item_db_id, '_sitio_cero_menu_icon', 'google:' . $google_icon_value);
        return;
    }

    if (!isset($_POST['menu-item-sitio-cero-icon-google']) && !isset($_POST['menu-item-sitio-cero-icon-custom'])) {
        delete_post_meta($menu_item_db_id, '_sitio_cero_menu_icon');
        return;
    }

    delete_post_meta($menu_item_db_id, '_sitio_cero_menu_icon');
}
add_action('wp_update_nav_menu_item', 'sitio_cero_save_menu_item_icon_field', 10, 2);

function sitio_cero_register_concurso_publico_post_type()
{
    $labels = array(
        'name'               => __('Concursos Públicos', 'sitio-cero'),
        'singular_name'      => __('Concurso Público', 'sitio-cero'),
        'menu_name'          => __('Concursos Públicos', 'sitio-cero'),
        'name_admin_bar'     => __('Concurso Público', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar concurso público', 'sitio-cero'),
        'new_item'           => __('Nuevo concurso público', 'sitio-cero'),
        'edit_item'          => __('Editar concurso público', 'sitio-cero'),
        'view_item'          => __('Ver concurso público', 'sitio-cero'),
        'all_items'          => __('Todos los concursos públicos', 'sitio-cero'),
        'search_items'       => __('Buscar concursos públicos', 'sitio-cero'),
        'not_found'          => __('No se encontraron concursos públicos.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay concursos públicos en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'concurso_publico',
        array(
            'labels'             => $labels,
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => true,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => true,
            'publicly_queryable' => true,
            'exclude_from_search'=> false,
            'has_archive'        => true,
            'rewrite'            => array('slug' => 'concursos-publicos'),
            'menu_position'      => 24,
            'menu_icon'          => 'dashicons-awards',
            'supports'           => array('title', 'editor', 'thumbnail', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_concurso_publico_post_type');

function sitio_cero_add_concurso_publico_metaboxes()
{
    add_meta_box(
        'sitio_cero_concurso_publico',
        __('Datos del concurso', 'sitio-cero'),
        'sitio_cero_render_concurso_publico_metabox',
        'concurso_publico',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_concurso_publico_metaboxes');

function sitio_cero_enqueue_concurso_publico_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'concurso_publico' !== $screen->post_type) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'sitio-cero-admin-concursos-publicos',
        get_template_directory_uri() . '/assets/js/admin-concursos-publicos.js',
        array('jquery'),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_concurso_publico_admin_assets');

function sitio_cero_get_concurso_publico_estado($post_id)
{
    $estado = get_post_meta($post_id, 'sitio_cero_concurso_estado', true);
    if (!is_string($estado) || '' === $estado) {
        $estado = 'activo';
    }
    if ('activo' !== $estado && 'inactivo' !== $estado) {
        $estado = 'activo';
    }
    return $estado;
}

function sitio_cero_get_concurso_publico_documentos($post_id)
{
    $docs = get_post_meta($post_id, 'sitio_cero_concurso_documentos', true);
    if (!is_array($docs)) {
        return array();
    }
    return $docs;
}

function sitio_cero_render_concurso_publico_metabox($post)
{
    wp_nonce_field('sitio_cero_save_concurso_publico_meta', 'sitio_cero_concurso_publico_meta_nonce');

    $estado = sitio_cero_get_concurso_publico_estado($post->ID);
    $docs = sitio_cero_get_concurso_publico_documentos($post->ID);
    ?>
    <p>
        <label for="sitio_cero_concurso_estado"><strong><?php esc_html_e('Estado del concurso', 'sitio-cero'); ?></strong></label><br>
        <select id="sitio_cero_concurso_estado" name="sitio_cero_concurso_estado">
            <option value="activo" <?php selected($estado, 'activo'); ?>><?php esc_html_e('Activo', 'sitio-cero'); ?></option>
            <option value="inactivo" <?php selected($estado, 'inactivo'); ?>><?php esc_html_e('Inactivo', 'sitio-cero'); ?></option>
        </select>
    </p>

    <div class="sitio-cero-docs" data-docs>
        <p><strong><?php esc_html_e('Documentos adjuntos', 'sitio-cero'); ?></strong></p>
        <div class="sitio-cero-docs__list">
            <?php foreach ($docs as $index => $doc) :
                $doc_id = isset($doc['id']) ? absint($doc['id']) : 0;
                $doc_label = isset($doc['label']) ? (string) $doc['label'] : '';
                $file_name = $doc_id ? basename((string) get_attached_file($doc_id)) : '';
                ?>
                <div class="sitio-cero-docs__item" data-doc-item>
                    <input type="hidden" name="sitio_cero_concurso_documentos[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($doc_id); ?>" data-doc-id>
                    <input type="text" class="widefat" name="sitio_cero_concurso_documentos[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($doc_label); ?>" placeholder="<?php esc_attr_e('Etiqueta del documento (opcional)', 'sitio-cero'); ?>" data-doc-label>
                    <div class="sitio-cero-docs__actions">
                        <button type="button" class="button sitio-cero-docs__select" data-doc-select><?php esc_html_e('Seleccionar archivo', 'sitio-cero'); ?></button>
                        <button type="button" class="button-link-delete sitio-cero-docs__remove" data-doc-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                    <p class="description sitio-cero-docs__filename" data-doc-filename><?php echo esc_html($file_name); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button" data-doc-add><?php esc_html_e('Agregar documento', 'sitio-cero'); ?></button>
        </p>
    </div>
    <?php
}

function sitio_cero_save_concurso_publico_meta($post_id)
{
    if (!isset($_POST['sitio_cero_concurso_publico_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_concurso_publico_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_concurso_publico_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $estado = 'activo';
    if (isset($_POST['sitio_cero_concurso_estado'])) {
        $estado_input = sanitize_text_field(wp_unslash($_POST['sitio_cero_concurso_estado']));
        if ('inactivo' === $estado_input) {
            $estado = 'inactivo';
        }
    }
    update_post_meta($post_id, 'sitio_cero_concurso_estado', $estado);

    $docs_input = array();
    if (isset($_POST['sitio_cero_concurso_documentos']) && is_array($_POST['sitio_cero_concurso_documentos'])) {
        $docs_input = $_POST['sitio_cero_concurso_documentos'];
    }

    $docs = array();
    foreach ($docs_input as $doc) {
        if (!is_array($doc)) {
            continue;
        }
        $doc_id = isset($doc['id']) ? absint($doc['id']) : 0;
        if ($doc_id <= 0) {
            continue;
        }
        $label = isset($doc['label']) ? sanitize_text_field(wp_unslash($doc['label'])) : '';
        $docs[] = array(
            'id'    => $doc_id,
            'label' => $label,
        );
    }

    if (!empty($docs)) {
        update_post_meta($post_id, 'sitio_cero_concurso_documentos', $docs);
    } else {
        delete_post_meta($post_id, 'sitio_cero_concurso_documentos');
    }
}
add_action('save_post_concurso_publico', 'sitio_cero_save_concurso_publico_meta');

function sitio_cero_enqueue_concursos_publicos_assets()
{
    if (!is_page_template('page-concursos-publicos.php') && !is_post_type_archive('concurso_publico')) {
        return;
    }

    wp_enqueue_script(
        'sitio-cero-concursos-publicos',
        get_template_directory_uri() . '/assets/js/concursos-publicos.js',
        array(),
        wp_get_theme()->get('Version'),
        true
    );
}
add_action('wp_enqueue_scripts', 'sitio_cero_enqueue_concursos_publicos_assets');

function sitio_cero_render_concursos_publicos_accordion($posts)
{
    if (empty($posts)) {
        echo '<p class="pc-flow">' . esc_html__('No hay concursos disponibles por ahora.', 'sitio-cero') . '</p>';
        return;
    }

    echo '<div class="pc-accordion concurso-accordion" data-concurso-accordion>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

    foreach ($posts as $post) {
        setup_postdata($post);
        $post_id = $post->ID;
        $docs = get_post_meta($post_id, 'sitio_cero_concurso_documentos', true);
        if (!is_array($docs)) {
            $docs = array();
        }
        $content = get_the_content(null, false, $post_id);
        $content = wp_kses_post(wpautop($content));
        ?>
        <details class="concurso-item">
            <summary><?php echo esc_html(get_the_title($post_id)); ?></summary>
            <div class="pc-accordion__body">
                <?php if ('' !== trim(wp_strip_all_tags($content))) : ?>
                    <div class="concurso-desc">
                        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
                <div class="concurso-columns">
                    <div class="concurso-docs" aria-label="<?php echo esc_attr__('Documentos del concurso', 'sitio-cero'); ?>">
                        <?php
                        if (empty($docs)) {
                            echo '<p>' . esc_html__('No hay documentos asociados.', 'sitio-cero') . '</p>';
                        } else {
                            foreach ($docs as $doc) {
                                $doc_id = isset($doc['id']) ? absint($doc['id']) : 0;
                                if ($doc_id <= 0) {
                                    continue;
                                }
                                $url = wp_get_attachment_url($doc_id);
                                if (!$url) {
                                    continue;
                                }
                                $label = isset($doc['label']) ? trim((string) $doc['label']) : '';
                                if ('' === $label) {
                                    $label = get_the_title($doc_id);
                                }
                                if ('' === $label) {
                                    $label = __('Documento', 'sitio-cero');
                                }
                                ?>
                                <a class="pc-link" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($label); ?>
                                </a>
                                <?php
                            }
                        }
                        ?>
                    </div>
                    <div class="concurso-media">
                        <?php if (has_post_thumbnail($post_id)) : ?>
                            <?php
                            echo get_the_post_thumbnail(
                                $post_id,
                                'large',
                                array(
                                    'class'   => 'concurso-image',
                                    'loading' => 'lazy',
                                )
                            );
                            ?>
                        <?php else : ?>
                            <div class="concurso-image--empty">
                                <?php esc_html_e('Sin imagen referencial.', 'sitio-cero'); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </details>
        <?php
    }

    wp_reset_postdata();

    echo '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function sitio_cero_register_lamina_post_type()
{
    $labels = array(
        'name'               => __('Slide Banner', 'sitio-cero'),
        'singular_name'      => __('Lamina Hero', 'sitio-cero'),
        'menu_name'          => __('Slide Banner', 'sitio-cero'),
        'name_admin_bar'     => __('Lamina Hero', 'sitio-cero'),
        'add_new'            => __('Agregar nueva', 'sitio-cero'),
        'add_new_item'       => __('Agregar nueva lamina', 'sitio-cero'),
        'new_item'           => __('Nueva lamina', 'sitio-cero'),
        'edit_item'          => __('Editar lamina', 'sitio-cero'),
        'view_item'          => __('Ver lamina', 'sitio-cero'),
        'all_items'          => __('Todas las laminas', 'sitio-cero'),
        'search_items'       => __('Buscar laminas', 'sitio-cero'),
        'not_found'          => __('No se encontraron laminas.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay laminas en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'lamina_hero',
        array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_nav_menus'  => false,
            'show_in_admin_bar'  => true,
            'show_in_rest'       => true,
            'publicly_queryable' => false,
            'exclude_from_search'=> true,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_position'      => 21,
            'menu_icon'          => 'dashicons-images-alt2',
            'supports'           => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_lamina_post_type');

function sitio_cero_add_lamina_metaboxes()
{
    add_meta_box(
        'sitio_cero_lamina_options',
        __('Opciones de la lamina', 'sitio-cero'),
        'sitio_cero_render_lamina_metabox',
        'lamina_hero',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_lamina_metaboxes');

function sitio_cero_render_lamina_metabox($post)
{
    wp_nonce_field('sitio_cero_save_lamina_meta', 'sitio_cero_lamina_meta_nonce');

    $cta_text = get_post_meta($post->ID, 'sitio_cero_hero_cta_text', true);
    $cta_url = get_post_meta($post->ID, 'sitio_cero_hero_cta_url', true);

    if (!is_string($cta_text)) {
        $cta_text = '';
    }

    if (!is_string($cta_url)) {
        $cta_url = '';
    }
    ?>
    <p>
        <label for="sitio_cero_hero_cta_text"><strong><?php esc_html_e('Texto del boton principal', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_hero_cta_text"
            name="sitio_cero_hero_cta_text"
            type="text"
            class="widefat"
            value="<?php echo esc_attr($cta_text); ?>"
            placeholder="<?php esc_attr_e('Ej: Ver detalle', 'sitio-cero'); ?>"
        >
    </p>

    <p>
        <label for="sitio_cero_hero_cta_url"><strong><?php esc_html_e('URL del boton principal', 'sitio-cero'); ?></strong></label><br>
        <input
            id="sitio_cero_hero_cta_url"
            name="sitio_cero_hero_cta_url"
            type="url"
            class="widefat"
            value="<?php echo esc_attr($cta_url); ?>"
            placeholder="https://..."
        >
    </p>
    <?php
}

function sitio_cero_save_lamina_meta($post_id)
{
    if (!isset($_POST['sitio_cero_lamina_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_lamina_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_lamina_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $cta_text = '';
    if (isset($_POST['sitio_cero_hero_cta_text'])) {
        $cta_text = sanitize_text_field(wp_unslash($_POST['sitio_cero_hero_cta_text']));
    }

    $cta_url = '';
    if (isset($_POST['sitio_cero_hero_cta_url'])) {
        $cta_url = esc_url_raw(wp_unslash($_POST['sitio_cero_hero_cta_url']));
    }

    if ('' !== $cta_text) {
        update_post_meta($post_id, 'sitio_cero_hero_cta_text', $cta_text);
    } else {
        delete_post_meta($post_id, 'sitio_cero_hero_cta_text');
    }

    if ('' !== $cta_url) {
        update_post_meta($post_id, 'sitio_cero_hero_cta_url', $cta_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_hero_cta_url');
    }
    
    delete_post_meta($post_id, 'sitio_cero_show_quick_box');
    delete_post_meta($post_id, 'sitio_cero_quick_box_title');
    delete_post_meta($post_id, 'sitio_cero_quick_box_items');
}
add_action('save_post_lamina_hero', 'sitio_cero_save_lamina_meta');

function sitio_cero_seed_default_lamina()
{
    if (!post_type_exists('lamina_hero')) {
        return;
    }

    $already_seeded = get_option('sitio_cero_default_lamina_seeded', '0');
    if ('1' === (string) $already_seeded) {
        return;
    }

    $existing_lamina = get_posts(
        array(
            'post_type'      => 'lamina_hero',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing_lamina)) {
        update_option('sitio_cero_default_lamina_seeded', '1');
        return;
    }

    $post_id = wp_insert_post(
        array(
            'post_type'    => 'lamina_hero',
            'post_status'  => 'publish',
            'post_title'   => __('Lamina de ejemplo: Programa de limpieza comunal', 'sitio-cero'),
            'post_excerpt' => __('Revisa operativos de retiro de residuos, puntos limpios y calendario de intervencion por sectores de la comuna.', 'sitio-cero'),
            'post_content' => __('Esta es una lamina de ejemplo para que veas como funciona el slider del hero. Puedes editar este contenido o crear nuevas laminas desde el menu Slide Banner.', 'sitio-cero'),
            'menu_order'   => 1,
        ),
        true
    );

    if (is_wp_error($post_id) || !$post_id) {
        return;
    }

    update_post_meta($post_id, 'sitio_cero_hero_cta_text', __('Conocer operativos', 'sitio-cero'));
    update_post_meta($post_id, 'sitio_cero_hero_cta_url', '#avisos');

    update_option('sitio_cero_default_lamina_seeded', '1');
}
add_action('init', 'sitio_cero_seed_default_lamina', 30);

function sitio_cero_add_clone_lamina_action($actions, $post)
{
    if (!$post instanceof WP_Post) {
        return $actions;
    }

    if ('lamina_hero' !== $post->post_type) {
        return $actions;
    }

    if (!current_user_can('edit_post', $post->ID)) {
        return $actions;
    }

    $clone_url = add_query_arg(
        array(
            'action' => 'sitio_cero_clone_lamina',
            'post'   => $post->ID,
        ),
        admin_url('admin.php')
    );

    $clone_url = wp_nonce_url($clone_url, 'sitio_cero_clone_lamina_' . $post->ID);
    $actions['sitio_cero_clone'] = '<a href="' . esc_url($clone_url) . '">' . esc_html__('Clonar', 'sitio-cero') . '</a>';

    return $actions;
}
add_filter('post_row_actions', 'sitio_cero_add_clone_lamina_action', 10, 2);

function sitio_cero_handle_clone_lamina_action()
{
    if (!isset($_GET['post'])) {
        wp_die(esc_html__('No se recibio una lamina para clonar.', 'sitio-cero'));
    }

    $source_post_id = absint(wp_unslash($_GET['post']));
    if (!$source_post_id) {
        wp_die(esc_html__('ID de lamina invalido.', 'sitio-cero'));
    }

    if (!current_user_can('edit_post', $source_post_id)) {
        wp_die(esc_html__('No tienes permisos para clonar esta lamina.', 'sitio-cero'));
    }

    check_admin_referer('sitio_cero_clone_lamina_' . $source_post_id);

    $source_post = get_post($source_post_id);
    if (!$source_post instanceof WP_Post || 'lamina_hero' !== $source_post->post_type) {
        wp_die(esc_html__('La lamina origen no existe.', 'sitio-cero'));
    }

    $clone_post_id = wp_insert_post(
        array(
            'post_type'    => 'lamina_hero',
            'post_status'  => 'draft',
            'post_title'   => sprintf(__('Copia de %s', 'sitio-cero'), $source_post->post_title),
            'post_content' => $source_post->post_content,
            'post_excerpt' => $source_post->post_excerpt,
            'menu_order'   => $source_post->menu_order,
            'post_author'  => get_current_user_id(),
        ),
        true
    );

    if (is_wp_error($clone_post_id) || !$clone_post_id) {
        wp_die(esc_html__('No se pudo clonar la lamina.', 'sitio-cero'));
    }

    $source_meta = get_post_meta($source_post_id);
    $excluded_meta_keys = array('_edit_lock', '_edit_last');

    foreach ($source_meta as $meta_key => $meta_values) {
        if (in_array($meta_key, $excluded_meta_keys, true)) {
            continue;
        }

        if (!is_array($meta_values)) {
            continue;
        }

        foreach ($meta_values as $meta_value) {
            add_post_meta($clone_post_id, $meta_key, maybe_unserialize($meta_value));
        }
    }

    $taxonomies = get_object_taxonomies('lamina_hero');
    foreach ($taxonomies as $taxonomy) {
        $term_ids = wp_get_object_terms($source_post_id, $taxonomy, array('fields' => 'ids'));
        if (is_wp_error($term_ids)) {
            continue;
        }

        wp_set_object_terms($clone_post_id, $term_ids, $taxonomy);
    }

    $redirect_url = add_query_arg(
        array(
            'post'   => $clone_post_id,
            'action' => 'edit',
            'cloned' => '1',
        ),
        admin_url('post.php')
    );

    wp_safe_redirect($redirect_url);
    exit;
}
add_action('admin_action_sitio_cero_clone_lamina', 'sitio_cero_handle_clone_lamina_action');

function sitio_cero_show_clone_lamina_notice()
{
    if (!is_admin()) {
        return;
    }

    if (!isset($_GET['cloned']) || '1' !== (string) wp_unslash($_GET['cloned'])) {
        return;
    }

    if (!isset($_GET['post'])) {
        return;
    }

    $post_id = absint(wp_unslash($_GET['post']));
    if (!$post_id || 'lamina_hero' !== get_post_type($post_id)) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible"><p>';
    echo esc_html__('Lamina clonada correctamente. Ya puedes editar la copia.', 'sitio-cero');
    echo '</p></div>';
}
add_action('admin_notices', 'sitio_cero_show_clone_lamina_notice');

function sitio_cero_get_seo_supported_post_types()
{
    $candidates = array(
        'page',
        'post',
        'municipalidad',
        'noticia',
        'aviso',
        'direccion_municipal',
        'evento_municipal',
        'aviso_grilla',
    );

    $types = array();
    foreach ($candidates as $post_type) {
        if (!post_type_exists($post_type)) {
            continue;
        }

        $post_type_object = get_post_type_object($post_type);
        if (!$post_type_object || empty($post_type_object->show_ui)) {
            continue;
        }

        $types[] = (string) $post_type;
    }

    return $types;
}

function sitio_cero_seo_strlen($value)
{
    $value = (string) $value;
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }

    return (int) strlen($value);
}

function sitio_cero_seo_trim_text($value, $max_chars = 160)
{
    $value = trim((string) wp_strip_all_tags((string) $value));
    if ('' === $value) {
        return '';
    }

    $max_chars = max(20, (int) $max_chars);
    if (sitio_cero_seo_strlen($value) <= $max_chars) {
        return $value;
    }

    if (function_exists('mb_substr')) {
        $cut = (string) mb_substr($value, 0, $max_chars - 1, 'UTF-8');
    } else {
        $cut = (string) substr($value, 0, $max_chars - 1);
    }

    return rtrim($cut) . '…';
}

function sitio_cero_add_seo_metaboxes()
{
    $post_types = sitio_cero_get_seo_supported_post_types();
    foreach ($post_types as $post_type) {
        add_meta_box(
            'sitio_cero_seo_options',
            __('SEO basico', 'sitio-cero'),
            'sitio_cero_render_seo_metabox',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'sitio_cero_add_seo_metaboxes');

function sitio_cero_enqueue_seo_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array((string) $screen->post_type, sitio_cero_get_seo_supported_post_types(), true)) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_style(
        'sitio-cero-admin-seo-live',
        get_template_directory_uri() . '/assets/css/admin-seo-live.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-seo-live',
        get_template_directory_uri() . '/assets/js/admin-seo-live.js',
        array(),
        $version,
        true
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_seo_admin_assets');

function sitio_cero_render_seo_metabox($post)
{
    wp_nonce_field('sitio_cero_save_seo_meta', 'sitio_cero_seo_meta_nonce');

    $seo_title = get_post_meta($post->ID, 'sitio_cero_seo_title', true);
    if (!is_string($seo_title)) {
        $seo_title = '';
    }

    $seo_description = get_post_meta($post->ID, 'sitio_cero_seo_description', true);
    if (!is_string($seo_description)) {
        $seo_description = '';
    }

    $title_length = sitio_cero_seo_strlen($seo_title);
    $description_length = sitio_cero_seo_strlen($seo_description);
    ?>
    <div class="sitio-cero-seo-live" data-seo-live-root>
        <p class="sitio-cero-seo-live__field">
            <label for="sitio_cero_seo_title"><strong><?php esc_html_e('Titulo SEO (opcional)', 'sitio-cero'); ?></strong></label>
            <input
                id="sitio_cero_seo_title"
                name="sitio_cero_seo_title"
                type="text"
                class="widefat"
                value="<?php echo esc_attr($seo_title); ?>"
                maxlength="120"
                placeholder="<?php esc_attr_e('Ejemplo: Tramites municipales en Concepcion', 'sitio-cero'); ?>"
                data-seo-live-input="title"
            >
            <small><?php echo esc_html(sprintf(__('Largo actual: %d caracteres. Recomendado: 30-60.', 'sitio-cero'), $title_length)); ?></small>
            <span class="sitio-cero-seo-live__status" data-seo-live-status="title">
                <span class="sitio-cero-seo-live__dot" aria-hidden="true"></span>
                <span class="sitio-cero-seo-live__text"><?php esc_html_e('Analizando titulo…', 'sitio-cero'); ?></span>
            </span>
        </p>
        <p class="sitio-cero-seo-live__field">
            <label for="sitio_cero_seo_description"><strong><?php esc_html_e('Meta descripcion (opcional)', 'sitio-cero'); ?></strong></label>
            <textarea
                id="sitio_cero_seo_description"
                name="sitio_cero_seo_description"
                class="widefat"
                rows="3"
                maxlength="300"
                placeholder="<?php esc_attr_e('Describe el contenido para buscadores en una frase clara.', 'sitio-cero'); ?>"
                data-seo-live-input="description"
            ><?php echo esc_textarea($seo_description); ?></textarea>
            <small><?php echo esc_html(sprintf(__('Largo actual: %d caracteres. Recomendado: 70-160.', 'sitio-cero'), $description_length)); ?></small>
            <span class="sitio-cero-seo-live__status" data-seo-live-status="description">
                <span class="sitio-cero-seo-live__dot" aria-hidden="true"></span>
                <span class="sitio-cero-seo-live__text"><?php esc_html_e('Analizando descripcion…', 'sitio-cero'); ?></span>
            </span>
        </p>
    </div>
    <?php
}

function sitio_cero_save_seo_meta($post_id)
{
    if (!isset($_POST['sitio_cero_seo_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_seo_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_seo_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, sitio_cero_get_seo_supported_post_types(), true)) {
        return;
    }

    $seo_title = '';
    if (isset($_POST['sitio_cero_seo_title'])) {
        $seo_title = sanitize_text_field(wp_unslash($_POST['sitio_cero_seo_title']));
    }

    if ('' !== trim($seo_title)) {
        update_post_meta($post_id, 'sitio_cero_seo_title', $seo_title);
    } else {
        delete_post_meta($post_id, 'sitio_cero_seo_title');
    }

    $seo_description = '';
    if (isset($_POST['sitio_cero_seo_description'])) {
        $seo_description = sanitize_textarea_field(wp_unslash($_POST['sitio_cero_seo_description']));
    }

    if ('' !== trim($seo_description)) {
        update_post_meta($post_id, 'sitio_cero_seo_description', $seo_description);
    } else {
        delete_post_meta($post_id, 'sitio_cero_seo_description');
    }
}
add_action('save_post', 'sitio_cero_save_seo_meta');

function sitio_cero_get_singular_seo_title($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $custom_title = get_post_meta($post_id, 'sitio_cero_seo_title', true);
    if (is_string($custom_title) && '' !== trim($custom_title)) {
        return sanitize_text_field($custom_title);
    }

    return '';
}

function sitio_cero_get_singular_seo_description($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return '';
    }

    $custom_description = get_post_meta($post_id, 'sitio_cero_seo_description', true);
    if (is_string($custom_description) && '' !== trim($custom_description)) {
        return sanitize_textarea_field($custom_description);
    }

    $excerpt = get_post_field('post_excerpt', $post_id);
    if (is_string($excerpt) && '' !== trim($excerpt)) {
        return sitio_cero_seo_trim_text($excerpt, 160);
    }

    $content = get_post_field('post_content', $post_id);
    if (is_string($content) && '' !== trim((string) wp_strip_all_tags($content))) {
        return sitio_cero_seo_trim_text($content, 160);
    }

    return '';
}

function sitio_cero_filter_document_title($title)
{
    if (is_admin() || !is_singular()) {
        return $title;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return $title;
    }

    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, sitio_cero_get_seo_supported_post_types(), true)) {
        return $title;
    }

    $seo_title = sitio_cero_get_singular_seo_title($post_id);
    if ('' === $seo_title) {
        return $title;
    }

    return $seo_title;
}
add_filter('pre_get_document_title', 'sitio_cero_filter_document_title', 20);

function sitio_cero_should_skip_theme_meta_description()
{
    return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION');
}

function sitio_cero_print_meta_description()
{
    if (is_admin() || sitio_cero_should_skip_theme_meta_description()) {
        return;
    }

    $description = '';

    if (is_singular()) {
        $post_id = get_queried_object_id();
        if ($post_id > 0) {
            $description = sitio_cero_get_singular_seo_description($post_id);
        }
    } elseif (is_search()) {
        $query = sanitize_text_field((string) get_search_query());
        if ('' !== $query) {
            $description = sprintf(__('Resultados de busqueda para: %s', 'sitio-cero'), $query);
        }
    } elseif (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term instanceof WP_Term && is_string($term->description) && '' !== trim($term->description)) {
            $description = sitio_cero_seo_trim_text($term->description, 160);
        } else {
            $description = sitio_cero_seo_trim_text(wp_strip_all_tags((string) get_the_archive_title()), 160);
        }
    } elseif (is_post_type_archive() || is_archive()) {
        $description = sitio_cero_seo_trim_text(wp_strip_all_tags((string) get_the_archive_title()), 160);
    }

    if ('' === trim($description)) {
        return;
    }

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'sitio_cero_print_meta_description', 1);

function sitio_cero_count_words($value)
{
    $clean = trim((string) wp_strip_all_tags((string) $value));
    if ('' === $clean) {
        return 0;
    }

    $tokens = preg_split('/\s+/u', $clean);
    if (!is_array($tokens)) {
        return 0;
    }

    return count(array_filter($tokens, static function ($token) {
        return '' !== trim((string) $token);
    }));
}

function sitio_cero_get_seo_dashboard_report()
{
    $cache_key = 'sitio_cero_seo_dashboard_report_v1';
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $post_types = sitio_cero_get_seo_supported_post_types();
    $distribution = array();
    $distribution_total = 0;

    foreach ($post_types as $post_type) {
        $counts = wp_count_posts($post_type);
        $published = ($counts && isset($counts->publish)) ? (int) $counts->publish : 0;
        if ($published <= 0) {
            continue;
        }

        $post_type_object = get_post_type_object($post_type);
        $label = $post_type_object ? (string) $post_type_object->labels->name : (string) $post_type;
        $distribution[$post_type] = array(
            'label' => $label,
            'count' => $published,
        );
        $distribution_total += $published;
    }

    $post_ids = get_posts(
        array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    $total = is_array($post_ids) ? count($post_ids) : 0;
    $title_optimal = 0;
    $title_short = 0;
    $title_long = 0;
    $custom_title_count = 0;
    $description_missing = 0;
    $description_optimal = 0;
    $thin_content = 0;

    if (is_array($post_ids)) {
        foreach ($post_ids as $post_id) {
            $post_id = (int) $post_id;
            if ($post_id <= 0) {
                continue;
            }

            $custom_title = sitio_cero_get_singular_seo_title($post_id);
            $effective_title = '' !== $custom_title ? $custom_title : (string) get_the_title($post_id);
            if ('' !== $custom_title) {
                $custom_title_count++;
            }

            $title_length = sitio_cero_seo_strlen($effective_title);
            if ($title_length < 30) {
                $title_short++;
            } elseif ($title_length > 60) {
                $title_long++;
            } else {
                $title_optimal++;
            }

            $description = get_post_meta($post_id, 'sitio_cero_seo_description', true);
            $description = is_string($description) ? trim($description) : '';
            $description_length = sitio_cero_seo_strlen($description);

            if ('' === $description) {
                $description_missing++;
            } elseif ($description_length >= 70 && $description_length <= 160) {
                $description_optimal++;
            }

            $word_count = sitio_cero_count_words((string) get_post_field('post_content', $post_id));
            if ($word_count < 120) {
                $thin_content++;
            }
        }
    }

    $score = 0;
    if ($total > 0) {
        $score_titles = ($title_optimal / $total) * 40;
        $score_descriptions = (($total - $description_missing) / $total) * 35;
        $score_content = (($total - $thin_content) / $total) * 25;
        $score = (int) round($score_titles + $score_descriptions + $score_content);
    }

    $status = 'critico';
    if ($score >= 80) {
        $status = 'saludable';
    } elseif ($score >= 60) {
        $status = 'medio';
    }

    $recommendations = array();
    if ($title_short > 0 || $title_long > 0) {
        $recommendations[] = sprintf(
            __('Ajusta %1$d titulos fuera de rango SEO (30-60).', 'sitio-cero'),
            $title_short + $title_long
        );
    }
    if ($description_missing > 0) {
        $recommendations[] = sprintf(
            __('Completa meta descripcion en %d contenidos.', 'sitio-cero'),
            $description_missing
        );
    }
    if ($thin_content > 0) {
        $recommendations[] = sprintf(
            __('Refuerza contenido en %d entradas con menos de 120 palabras.', 'sitio-cero'),
            $thin_content
        );
    }
    if ($distribution_total > 0 && count($distribution) > 1) {
        $max_type = 0;
        foreach ($distribution as $item) {
            $max_type = max($max_type, (int) $item['count']);
        }
        $max_share = ($distribution_total > 0) ? ($max_type / $distribution_total) * 100 : 0;
        if ($max_share >= 75) {
            $recommendations[] = __('La distribucion de contenidos esta concentrada en un solo tipo. Diversifica secciones.', 'sitio-cero');
        }
    }

    $report = array(
        'score'               => $score,
        'status'              => $status,
        'total'               => $total,
        'title_optimal'       => $title_optimal,
        'title_short'         => $title_short,
        'title_long'          => $title_long,
        'custom_title_count'  => $custom_title_count,
        'description_missing' => $description_missing,
        'description_optimal' => $description_optimal,
        'thin_content'        => $thin_content,
        'distribution'        => $distribution,
        'distribution_total'  => $distribution_total,
        'recommendations'     => $recommendations,
    );

    set_transient($cache_key, $report, 10 * MINUTE_IN_SECONDS);
    return $report;
}

function sitio_cero_clear_seo_dashboard_report_cache()
{
    delete_transient('sitio_cero_seo_dashboard_report_v1');
}
add_action('save_post', 'sitio_cero_clear_seo_dashboard_report_cache');
add_action('deleted_post', 'sitio_cero_clear_seo_dashboard_report_cache');
add_action('trashed_post', 'sitio_cero_clear_seo_dashboard_report_cache');

function sitio_cero_register_seo_dashboard_widget()
{
    wp_add_dashboard_widget(
        'sitio_cero_seo_dashboard_widget',
        __('Estado SEO del sitio', 'sitio-cero'),
        'sitio_cero_render_seo_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'sitio_cero_register_seo_dashboard_widget');

function sitio_cero_render_seo_dashboard_widget()
{
    $report = sitio_cero_get_seo_dashboard_report();
    $status_map = array(
        'saludable' => array('label' => __('Saludable', 'sitio-cero'), 'color' => '#0a7f37'),
        'medio'     => array('label' => __('Medio', 'sitio-cero'), 'color' => '#a16800'),
        'critico'   => array('label' => __('Critico', 'sitio-cero'), 'color' => '#b42318'),
    );
    $status = isset($status_map[$report['status']]) ? $status_map[$report['status']] : $status_map['critico'];
    ?>
    <style>
        .sitio-cero-seo-widget__score { display:flex; align-items:center; gap:10px; margin-bottom:10px; }
        .sitio-cero-seo-widget__badge { font-weight:700; font-size:12px; padding:2px 8px; border-radius:999px; color:#fff; }
        .sitio-cero-seo-widget__grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; margin:8px 0 10px; }
        .sitio-cero-seo-widget__metric { background:#f6f8fb; border:1px solid #e3e8f1; border-radius:8px; padding:8px; }
        .sitio-cero-seo-widget__metric strong { display:block; font-size:13px; color:#0f2343; }
        .sitio-cero-seo-widget__metric span { font-size:12px; color:#4e627d; }
        .sitio-cero-seo-widget__list { margin:6px 0 0 16px; }
        .sitio-cero-seo-widget__list li { margin:0 0 4px; }
        .sitio-cero-seo-widget__distribution { margin-top:8px; border-top:1px solid #e3e8f1; padding-top:8px; }
    </style>
    <div class="sitio-cero-seo-widget">
        <div class="sitio-cero-seo-widget__score">
            <strong><?php echo esc_html(sprintf(__('Puntaje SEO: %d/100', 'sitio-cero'), (int) $report['score'])); ?></strong>
            <span class="sitio-cero-seo-widget__badge" style="background: <?php echo esc_attr($status['color']); ?>;"><?php echo esc_html($status['label']); ?></span>
        </div>

        <div class="sitio-cero-seo-widget__grid">
            <div class="sitio-cero-seo-widget__metric">
                <strong><?php echo esc_html((string) $report['total']); ?></strong>
                <span><?php esc_html_e('Contenidos analizados', 'sitio-cero'); ?></span>
            </div>
            <div class="sitio-cero-seo-widget__metric">
                <strong><?php echo esc_html((string) $report['title_optimal']); ?></strong>
                <span><?php esc_html_e('Titulos en rango 30-60', 'sitio-cero'); ?></span>
            </div>
            <div class="sitio-cero-seo-widget__metric">
                <strong><?php echo esc_html((string) $report['description_optimal']); ?></strong>
                <span><?php esc_html_e('Meta descripciones optimas', 'sitio-cero'); ?></span>
            </div>
            <div class="sitio-cero-seo-widget__metric">
                <strong><?php echo esc_html((string) $report['thin_content']); ?></strong>
                <span><?php esc_html_e('Contenidos delgados (<120 palabras)', 'sitio-cero'); ?></span>
            </div>
        </div>

        <?php if (!empty($report['distribution']) && is_array($report['distribution'])) : ?>
            <div class="sitio-cero-seo-widget__distribution">
                <strong><?php esc_html_e('Distribucion de contenido', 'sitio-cero'); ?></strong>
                <ul class="sitio-cero-seo-widget__list">
                    <?php foreach ($report['distribution'] as $row) : ?>
                        <?php
                        $count = isset($row['count']) ? (int) $row['count'] : 0;
                        $label = isset($row['label']) ? (string) $row['label'] : '';
                        $share = ($report['distribution_total'] > 0)
                            ? round(($count / (int) $report['distribution_total']) * 100)
                            : 0;
                        ?>
                        <li><?php echo esc_html($label . ': ' . $count . ' (' . $share . '%)'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="sitio-cero-seo-widget__distribution">
            <strong><?php esc_html_e('Recomendaciones', 'sitio-cero'); ?></strong>
            <?php if (!empty($report['recommendations'])) : ?>
                <ul class="sitio-cero-seo-widget__list">
                    <?php foreach ($report['recommendations'] as $recommendation) : ?>
                        <li><?php echo esc_html((string) $recommendation); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('No hay alertas criticas. Mantener consistencia en titulos y descripciones.', 'sitio-cero'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* -------------------------------------------------------------------------- */
/* Plan Regulador (Fase 1: arquitectura mínima funcional) */
/* -------------------------------------------------------------------------- */

function sitio_cero_register_plan_regulador_post_type()
{
    $labels = array(
        'name'               => __('Plan Regulador', 'sitio-cero'),
        'singular_name'      => __('Plan Regulador', 'sitio-cero'),
        'menu_name'          => __('Plan Regulador', 'sitio-cero'),
        'name_admin_bar'     => __('Plan Regulador', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar Plan Regulador', 'sitio-cero'),
        'new_item'           => __('Nuevo Plan Regulador', 'sitio-cero'),
        'edit_item'          => __('Editar Plan Regulador', 'sitio-cero'),
        'view_item'          => __('Ver Plan Regulador', 'sitio-cero'),
        'all_items'          => __('Plan Regulador', 'sitio-cero'),
        'search_items'       => __('Buscar Plan Regulador', 'sitio-cero'),
        'not_found'          => __('No se encontraron registros.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay registros en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'plan_regulador',
        array(
            'labels'            => $labels,
            'public'            => true,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_in_admin_bar' => true,
            'show_in_nav_menus' => true,
            'show_in_rest'      => true,
            'hierarchical'      => true,
            'has_archive'       => true,
            'rewrite'           => array('slug' => 'plan-regulador'),
            'menu_position'     => 27,
            'menu_icon'         => 'dashicons-media-document',
            'supports'          => array('title', 'editor', 'excerpt', 'thumbnail', 'page-attributes', 'revisions'),
        )
    );
}
add_action('init', 'sitio_cero_register_plan_regulador_post_type');

function sitio_cero_flush_plan_regulador_rewrite_rules_once()
{
    if (!post_type_exists('plan_regulador')) {
        return;
    }

    $flush_version = '1';
    $stored_version = (string) get_option('sitio_cero_plan_regulador_rewrite_flushed_version', '');
    if ($flush_version === $stored_version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('sitio_cero_plan_regulador_rewrite_flushed_version', $flush_version);
}
add_action('init', 'sitio_cero_flush_plan_regulador_rewrite_rules_once', 99);

function sitio_cero_enqueue_plan_regulador_admin_assets($hook_suffix)
{
    if ('post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || 'plan_regulador' !== $screen->post_type) {
        return;
    }

    $version = wp_get_theme()->get('Version');

    wp_enqueue_media();

    wp_enqueue_style(
        'sitio-cero-admin-direccion-municipal',
        get_template_directory_uri() . '/assets/css/admin-direccion-municipal.css',
        array(),
        $version
    );

    wp_enqueue_style(
        'sitio-cero-admin-plan-regulador',
        get_template_directory_uri() . '/assets/css/admin-plan-regulador.css',
        array('sitio-cero-admin-direccion-municipal'),
        $version
    );

    wp_enqueue_script(
        'sitio-cero-admin-plan-regulador',
        get_template_directory_uri() . '/assets/js/admin-plan-regulador.js',
        array('jquery', 'jquery-ui-sortable'),
        $version,
        true
    );

    wp_localize_script(
        'sitio-cero-admin-plan-regulador',
        'sitioCeroPlanRegulador',
        array(
            'groupLabel'  => __('Grupo', 'sitio-cero'),
            'sectionLabel'=> __('Sección', 'sitio-cero'),
            'frameTitle'  => __('Selecciona documento', 'sitio-cero'),
            'frameButton' => __('Usar documento', 'sitio-cero'),
        )
    );
}
add_action('admin_enqueue_scripts', 'sitio_cero_enqueue_plan_regulador_admin_assets');

function sitio_cero_add_plan_regulador_metaboxes()
{
    add_meta_box(
        'sitio_cero_plan_regulador_datos',
        __('Repositorio documental del Plan Regulador', 'sitio-cero'),
        'sitio_cero_render_plan_regulador_metabox',
        'plan_regulador',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_plan_regulador_metaboxes');

function sitio_cero_get_plan_regulador_group_categories()
{
    return array(
        'prcc'                 => __('PRCC', 'sitio-cero'),
        'sector-ribera-norte'  => __('Sector Ribera Norte', 'sitio-cero'),
        'enmienda'             => __('Enmienda', 'sitio-cero'),
        'modificacion'         => __('Modificación', 'sitio-cero'),
        'plano-detalle'        => __('Plano de Detalle', 'sitio-cero'),
        'otro'                 => __('Otro', 'sitio-cero'),
    );
}

function sitio_cero_get_plan_regulador_document_types()
{
    return array(
        'decreto'         => __('Decreto', 'sitio-cero'),
        'memoria'         => __('Memoria', 'sitio-cero'),
        'plano'           => __('Plano', 'sitio-cero'),
        'diario-oficial'  => __('Diario Oficial', 'sitio-cero'),
        'informe'         => __('Informe', 'sitio-cero'),
        'resolucion'      => __('Resolución', 'sitio-cero'),
        'otro'            => __('Otro', 'sitio-cero'),
    );
}

function sitio_cero_plan_regulador_normalize_documents($documents)
{
    if (!is_array($documents)) {
        return array();
    }

    $valid_types = array_keys(sitio_cero_get_plan_regulador_document_types());
    $clean = array();

    foreach ($documents as $document) {
        if (!is_array($document)) {
            continue;
        }

        $title = isset($document['title']) ? sanitize_text_field((string) $document['title']) : '';
        $url = isset($document['url']) ? esc_url_raw((string) $document['url']) : '';
        $type = isset($document['type']) ? sanitize_key((string) $document['type']) : '';
        $date = isset($document['date']) ? sanitize_text_field((string) $document['date']) : '';
        $label = isset($document['label']) ? sanitize_text_field((string) $document['label']) : '';
        $featured = !empty($document['featured']) ? '1' : '';

        if (!in_array($type, $valid_types, true)) {
            $type = '';
        }

        if ('' === $title && '' === $url) {
            continue;
        }

        $clean[] = array(
            'title'    => $title,
            'url'      => $url,
            'type'     => $type,
            'date'     => $date,
            'label'    => $label,
            'featured' => $featured,
        );
    }

    return $clean;
}

function sitio_cero_sanitize_plan_regulador_document_groups($groups)
{
    if (is_string($groups)) {
        $decoded = json_decode($groups, true);
        $groups = is_array($decoded) ? $decoded : array();
    }

    if (!is_array($groups)) {
        return array();
    }

    $valid_categories = array_keys(sitio_cero_get_plan_regulador_group_categories());
    $clean_groups = array();

    foreach ($groups as $group_index => $group) {
        if (!is_array($group)) {
            continue;
        }

        $title = isset($group['title']) ? sanitize_text_field((string) $group['title']) : '';
        $year = isset($group['year']) ? sanitize_text_field((string) $group['year']) : '';
        $category = isset($group['category']) ? sanitize_key((string) $group['category']) : '';
        $description = isset($group['description']) ? sanitize_textarea_field((string) $group['description']) : '';
        $visual_column = isset($group['visual_column']) ? sanitize_key((string) $group['visual_column']) : 'right';
        $manual_order = isset($group['manual_order']) ? absint($group['manual_order']) : absint($group_index);
        $documents = isset($group['documents']) ? sitio_cero_plan_regulador_normalize_documents($group['documents']) : array();

        if (!in_array($category, $valid_categories, true)) {
            $category = '';
        }
        if (!in_array($visual_column, array('left', 'right'), true)) {
            $visual_column = 'right';
        }

        if ('' === $title && empty($documents)) {
            continue;
        }

        $clean_groups[] = array(
            'title'       => $title,
            'year'        => $year,
            'category'    => $category,
            'description' => $description,
            'visual_column' => $visual_column,
            'manual_order' => $manual_order,
            'documents'   => $documents,
        );
    }

    return $clean_groups;
}

function sitio_cero_get_plan_regulador_document_groups($post_id)
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return array();
    }

    $raw = get_post_meta($post_id, 'sitio_cero_plan_regulador_document_groups', true);
    if (!is_array($raw) && !is_string($raw)) {
        return array();
    }

    return sitio_cero_sanitize_plan_regulador_document_groups($raw);
}

function sitio_cero_render_plan_regulador_metabox($post)
{
    wp_nonce_field('sitio_cero_save_plan_regulador_meta', 'sitio_cero_plan_regulador_meta_nonce');

    $subtitle = get_post_meta($post->ID, 'sitio_cero_plan_regulador_subtitulo', true);
    $lead = get_post_meta($post->ID, 'sitio_cero_plan_regulador_bajada', true);
    $cta_text = get_post_meta($post->ID, 'sitio_cero_plan_regulador_cta_texto', true);
    $cta_url = get_post_meta($post->ID, 'sitio_cero_plan_regulador_cta_url', true);
    $groups = sitio_cero_get_plan_regulador_document_groups($post->ID);
    $custom_html = get_post_meta($post->ID, 'sitio_cero_plan_regulador_custom_html', true);
    $custom_css = get_post_meta($post->ID, 'sitio_cero_plan_regulador_custom_css', true);

    $sections = get_post_meta($post->ID, 'sitio_cero_plan_regulador_sections', true);
    if (!is_array($sections)) {
        $sections = array();
    }

    if (!is_string($subtitle)) {
        $subtitle = '';
    }
    if (!is_string($lead)) {
        $lead = '';
    }
    if (!is_string($cta_text)) {
        $cta_text = '';
    }
    if (!is_string($cta_url)) {
        $cta_url = '';
    }
    if (!is_string($custom_html)) {
        $custom_html = '';
    }
    if (!is_string($custom_css)) {
        $custom_css = '';
    }

    if (empty($groups)) {
        $groups[] = array(
            'title'       => __('Grupo documental', 'sitio-cero'),
            'year'        => '',
            'category'    => '',
            'description' => '',
            'visual_column' => 'right',
            'manual_order' => 0,
            'documents'   => array(
                array(
                    'title'    => '',
                    'url'      => '',
                    'type'     => '',
                    'date'     => '',
                    'label'    => '',
                    'featured' => '',
                ),
            ),
        );
    }

    if (empty($sections)) {
        $sections[] = array(
            'title'   => '',
            'content' => '',
        );
    }

    $group_categories = sitio_cero_get_plan_regulador_group_categories();
    $document_types = sitio_cero_get_plan_regulador_document_types();
    ?>
    <div class="sitio-cero-dm-metabox">
        <h3><?php esc_html_e('Hero / Encabezado', 'sitio-cero'); ?></h3>
        <div class="sitio-cero-prc-grid">
            <p>
                <label for="sitio_cero_plan_regulador_subtitulo"><strong><?php esc_html_e('Subtítulo breve', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_plan_regulador_subtitulo" type="text" class="widefat" name="sitio_cero_plan_regulador_subtitulo" value="<?php echo esc_attr($subtitle); ?>">
            </p>
            <p>
                <label for="sitio_cero_plan_regulador_bajada"><strong><?php esc_html_e('Bajada descriptiva', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_plan_regulador_bajada" type="text" class="widefat" name="sitio_cero_plan_regulador_bajada" value="<?php echo esc_attr($lead); ?>">
            </p>
            <p>
                <label for="sitio_cero_plan_regulador_cta_texto"><strong><?php esc_html_e('Texto CTA (opcional)', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_plan_regulador_cta_texto" type="text" class="widefat" name="sitio_cero_plan_regulador_cta_texto" value="<?php echo esc_attr($cta_text); ?>">
            </p>
            <p>
                <label for="sitio_cero_plan_regulador_cta_url"><strong><?php esc_html_e('Enlace CTA (opcional)', 'sitio-cero'); ?></strong></label>
                <input id="sitio_cero_plan_regulador_cta_url" type="url" class="widefat" name="sitio_cero_plan_regulador_cta_url" value="<?php echo esc_attr(esc_url_raw($cta_url)); ?>">
            </p>
        </div>

        <hr>
        <h3><?php esc_html_e('Grupos documentales', 'sitio-cero'); ?></h3>
        <p class="description"><?php esc_html_e('Crea grupos y asocia documentos con enlace, tipo, fecha y etiquetas.', 'sitio-cero'); ?></p>

        <?php
        $render_group_row = static function ($group, $group_index, $group_categories, $document_types, $template_mode = false) {
            $group_title = isset($group['title']) ? (string) $group['title'] : '';
            $group_year = isset($group['year']) ? (string) $group['year'] : '';
            $group_category = isset($group['category']) ? sanitize_key((string) $group['category']) : '';
            $group_description = isset($group['description']) ? (string) $group['description'] : '';
            $group_visual_column = isset($group['visual_column']) ? sanitize_key((string) $group['visual_column']) : 'right';
            $group_manual_order = isset($group['manual_order']) ? absint($group['manual_order']) : 0;
            if (!in_array($group_visual_column, array('left', 'right'), true)) {
                $group_visual_column = 'right';
            }

            $group_documents = isset($group['documents']) && is_array($group['documents']) ? $group['documents'] : array();
            if (empty($group_documents)) {
                $group_documents[] = array();
            }
            ?>
            <div class="sitio-cero-dm-resource-block prc-admin-group-card" data-prc-group-row data-group-index="<?php echo esc_attr((string) $group_index); ?>">
                <div class="sitio-cero-prc-group-head prc-admin-group-card__bar">
                    <span class="prc-admin-group-card__drag material-symbols-outlined" aria-hidden="true">drag_indicator</span>
                    <strong data-prc-group-label><?php esc_html_e('Grupo', 'sitio-cero'); ?></strong>
                    <span class="prc-admin-group-card__title" data-prc-group-title-preview><?php echo esc_html($group_title); ?></span>
                    <button type="button" class="button button-secondary button-small prc-admin-group-card__toggle" data-prc-group-toggle><?php esc_html_e('Editar', 'sitio-cero'); ?></button>
                    <button type="button" class="button-link-delete" data-prc-group-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                </div>

                <div class="prc-admin-group-editor" data-prc-group-editor>
                    <div class="sitio-cero-prc-grid">
                        <p>
                            <label><strong><?php esc_html_e('Título del grupo', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_group_title[]" value="<?php echo esc_attr($group_title); ?>" data-prc-group-title-input>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Año (opcional)', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_group_year[]" value="<?php echo esc_attr($group_year); ?>" placeholder="2004">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Categoría (opcional)', 'sitio-cero'); ?></strong></label>
                            <select class="widefat" name="sitio_cero_plan_regulador_group_category[]">
                                <option value=""><?php esc_html_e('Sin categoría', 'sitio-cero'); ?></option>
                                <?php foreach ($group_categories as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>"<?php selected($group_category, $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Orden en la columna', 'sitio-cero'); ?></strong></label>
                            <input type="number" class="widefat" min="0" step="1" value="<?php echo esc_attr((string) $group_manual_order); ?>" data-prc-group-manual-order-input readonly>
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Descripción (opcional)', 'sitio-cero'); ?></strong></label>
                            <textarea class="widefat" rows="3" name="sitio_cero_plan_regulador_group_description[]"><?php echo esc_textarea($group_description); ?></textarea>
                        </p>
                    </div>

                    <div class="sitio-cero-prc-documents prc-admin-documents" data-prc-documents>
                        <div class="sitio-cero-prc-documents__list" data-prc-documents-list>
                            <?php foreach ($group_documents as $document) : ?>
                                <?php
                                $document_title = isset($document['title']) ? (string) $document['title'] : '';
                                $document_url = isset($document['url']) ? (string) $document['url'] : '';
                                $document_type = isset($document['type']) ? sanitize_key((string) $document['type']) : '';
                                $document_date = isset($document['date']) ? (string) $document['date'] : '';
                                $document_label = isset($document['label']) ? (string) $document['label'] : '';
                                ?>
                                <div class="sitio-cero-prc-document prc-admin-document-row" data-prc-document-row>
                                    <div class="sitio-cero-prc-document__grid prc-admin-document-editor">
                                        <p>
                                            <label><strong><?php esc_html_e('Título del documento', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_title[<?php echo esc_attr((string) $group_index); ?>][]" value="<?php echo esc_attr($document_title); ?>" data-prc-document-title>
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Tipo (opcional)', 'sitio-cero'); ?></strong></label>
                                            <select class="widefat" name="sitio_cero_plan_regulador_document_type[<?php echo esc_attr((string) $group_index); ?>][]">
                                                <option value=""><?php esc_html_e('Sin tipo', 'sitio-cero'); ?></option>
                                                <?php foreach ($document_types as $type_key => $type_label) : ?>
                                                    <option value="<?php echo esc_attr($type_key); ?>"<?php selected($document_type, $type_key); ?>><?php echo esc_html($type_label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Fecha (opcional)', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_date[<?php echo esc_attr((string) $group_index); ?>][]" value="<?php echo esc_attr($document_date); ?>" placeholder="07.08.2001">
                                        </p>
                                        <p>
                                            <label><strong><?php esc_html_e('Etiqueta (opcional)', 'sitio-cero'); ?></strong></label>
                                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_label[<?php echo esc_attr((string) $group_index); ?>][]" value="<?php echo esc_attr($document_label); ?>">
                                        </p>
                                    </div>

                                    <p class="sitio-cero-prc-document__url">
                                        <span>
                                            <label><strong><?php esc_html_e('URL del documento', 'sitio-cero'); ?></strong></label>
                                            <input type="url" class="widefat" name="sitio_cero_plan_regulador_document_url[<?php echo esc_attr((string) $group_index); ?>][]" value="<?php echo esc_attr(esc_url_raw($document_url)); ?>" data-prc-document-url>
                                        </span>
                                        <button type="button" class="button media-button" data-prc-document-pick-file><?php esc_html_e('Biblioteca', 'sitio-cero'); ?></button>
                                    </p>

                                    <div class="sitio-cero-prc-document__actions">
                                        <button type="button" class="button-link-delete" data-prc-document-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="button button-secondary" data-prc-document-add><?php esc_html_e('Agregar documento', 'sitio-cero'); ?></button>

                        <template data-prc-document-template>
                            <div class="sitio-cero-prc-document prc-admin-document-row" data-prc-document-row>
                                <div class="sitio-cero-prc-document__grid prc-admin-document-editor">
                                    <p>
                                        <label><strong><?php esc_html_e('Título del documento', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_title[<?php echo esc_attr((string) $group_index); ?>][]" value="" data-prc-document-title>
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Tipo (opcional)', 'sitio-cero'); ?></strong></label>
                                        <select class="widefat" name="sitio_cero_plan_regulador_document_type[<?php echo esc_attr((string) $group_index); ?>][]">
                                            <option value=""><?php esc_html_e('Sin tipo', 'sitio-cero'); ?></option>
                                            <?php foreach ($document_types as $type_key => $type_label) : ?>
                                                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Fecha (opcional)', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_date[<?php echo esc_attr((string) $group_index); ?>][]" value="" placeholder="07.08.2001">
                                    </p>
                                    <p>
                                        <label><strong><?php esc_html_e('Etiqueta (opcional)', 'sitio-cero'); ?></strong></label>
                                        <input type="text" class="widefat" name="sitio_cero_plan_regulador_document_label[<?php echo esc_attr((string) $group_index); ?>][]" value="">
                                    </p>
                                </div>

                                <p class="sitio-cero-prc-document__url">
                                    <span>
                                        <label><strong><?php esc_html_e('URL del documento', 'sitio-cero'); ?></strong></label>
                                        <input type="url" class="widefat" name="sitio_cero_plan_regulador_document_url[<?php echo esc_attr((string) $group_index); ?>][]" value="" data-prc-document-url>
                                    </span>
                                    <button type="button" class="button media-button" data-prc-document-pick-file><?php esc_html_e('Biblioteca', 'sitio-cero'); ?></button>
                                </p>

                                <div class="sitio-cero-prc-document__actions">
                                    <button type="button" class="button-link-delete" data-prc-document-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <input type="hidden" name="sitio_cero_plan_regulador_group_visual_column[]" value="<?php echo esc_attr($group_visual_column); ?>" data-prc-group-visual-column>
                <input type="hidden" name="sitio_cero_plan_regulador_group_manual_order[]" value="<?php echo esc_attr((string) $group_manual_order); ?>" data-prc-group-manual-order>
            </div>
            <?php
        };
        ?>
        <div class="sitio-cero-prc-groups" data-prc-groups>
            <div class="prc-admin-columns">
                <section class="prc-admin-column">
                    <header class="prc-admin-column__head">
                        <h4><?php esc_html_e('Columna izquierda', 'sitio-cero'); ?></h4>
                    </header>
                    <div class="sitio-cero-prc-groups__list prc-admin-column-list" data-prc-column-list="left"></div>
                    <button type="button" class="button button-secondary" data-prc-group-add data-target-column="left"><?php esc_html_e('Agregar grupo a columna izquierda', 'sitio-cero'); ?></button>
                </section>
                <section class="prc-admin-column">
                    <header class="prc-admin-column__head">
                        <h4><?php esc_html_e('Columna derecha', 'sitio-cero'); ?></h4>
                    </header>
                    <div class="sitio-cero-prc-groups__list prc-admin-column-list" data-prc-column-list="right">
                        <?php foreach ($groups as $group_index => $group) : ?>
                            <?php $render_group_row($group, $group_index, $group_categories, $document_types); ?>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button button-primary" data-prc-group-add data-target-column="right"><?php esc_html_e('Agregar grupo a columna derecha', 'sitio-cero'); ?></button>
                </section>
            </div>

            <template data-prc-group-template>
                <?php $render_group_row(array('visual_column' => 'right', 'manual_order' => 0), '__KEY__', $group_categories, $document_types, true); ?>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('Secciones libres opcionales', 'sitio-cero'); ?></h3>
        <p class="description"><?php esc_html_e('Agrega bloques libres con título y contenido HTML sanitizado.', 'sitio-cero'); ?></p>

        <div class="sitio-cero-prc-sections" data-prc-sections>
            <div class="sitio-cero-prc-sections__list" data-prc-sections-list>
                <?php foreach ($sections as $section_index => $section) : ?>
                    <?php
                    $section_title = isset($section['title']) ? (string) $section['title'] : '';
                    $section_content = isset($section['content']) ? (string) $section['content'] : '';
                    ?>
                    <div class="sitio-cero-prc-section" data-prc-section-row data-section-index="<?php echo esc_attr((string) $section_index); ?>">
                        <div class="sitio-cero-prc-section__head">
                            <strong data-prc-section-label><?php echo esc_html(sprintf(__('Sección %d', 'sitio-cero'), $section_index + 1)); ?></strong>
                            <button type="button" class="button-link-delete" data-prc-section-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                        </div>
                        <p>
                            <label><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                            <input type="text" class="widefat" name="sitio_cero_plan_regulador_section_title[]" value="<?php echo esc_attr($section_title); ?>">
                        </p>
                        <p>
                            <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                            <textarea class="widefat" rows="6" name="sitio_cero_plan_regulador_section_content[]"><?php echo esc_textarea($section_content); ?></textarea>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" class="button button-secondary" data-prc-section-add><?php esc_html_e('Agregar sección libre', 'sitio-cero'); ?></button>

            <template data-prc-section-template>
                <div class="sitio-cero-prc-section" data-prc-section-row>
                    <div class="sitio-cero-prc-section__head">
                        <strong data-prc-section-label><?php esc_html_e('Sección', 'sitio-cero'); ?></strong>
                        <button type="button" class="button-link-delete" data-prc-section-remove><?php esc_html_e('Quitar', 'sitio-cero'); ?></button>
                    </div>
                    <p>
                        <label><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                        <input type="text" class="widefat" name="sitio_cero_plan_regulador_section_title[]" value="">
                    </p>
                    <p>
                        <label><strong><?php esc_html_e('Contenido (HTML)', 'sitio-cero'); ?></strong></label>
                        <textarea class="widefat" rows="6" name="sitio_cero_plan_regulador_section_content[]"></textarea>
                    </p>
                </div>
            </template>
        </div>

        <hr>
        <h3><?php esc_html_e('HTML y CSS libre', 'sitio-cero'); ?></h3>
        <p>
            <label for="sitio_cero_plan_regulador_custom_html"><strong><?php esc_html_e('HTML libre (opcional)', 'sitio-cero'); ?></strong></label>
            <textarea id="sitio_cero_plan_regulador_custom_html" class="widefat" rows="6" name="sitio_cero_plan_regulador_custom_html"><?php echo esc_textarea($custom_html); ?></textarea>
        </p>
        <p>
            <label for="sitio_cero_plan_regulador_custom_css"><strong><?php esc_html_e('CSS libre (opcional)', 'sitio-cero'); ?></strong></label>
            <textarea id="sitio_cero_plan_regulador_custom_css" class="widefat" rows="6" name="sitio_cero_plan_regulador_custom_css" placeholder="<?php esc_attr_e('Usa {{selector}} para apuntar este contenido.', 'sitio-cero'); ?>"><?php echo esc_textarea($custom_css); ?></textarea>
            <small><?php esc_html_e('Se usa la misma sanitización aplicada en Direcciones.', 'sitio-cero'); ?></small>
        </p>
    </div>
    <?php
}

function sitio_cero_save_plan_regulador_meta($post_id)
{
    if (!isset($_POST['sitio_cero_plan_regulador_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_plan_regulador_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_plan_regulador_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $subtitle = isset($_POST['sitio_cero_plan_regulador_subtitulo'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_plan_regulador_subtitulo']))
        : '';
    $lead = isset($_POST['sitio_cero_plan_regulador_bajada'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_plan_regulador_bajada']))
        : '';
    $cta_text = isset($_POST['sitio_cero_plan_regulador_cta_texto'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_plan_regulador_cta_texto']))
        : '';
    $cta_url = isset($_POST['sitio_cero_plan_regulador_cta_url'])
        ? esc_url_raw(wp_unslash($_POST['sitio_cero_plan_regulador_cta_url']))
        : '';

    if ('' !== $subtitle) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_subtitulo', $subtitle);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_subtitulo');
    }

    if ('' !== $lead) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_bajada', $lead);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_bajada');
    }

    if ('' !== $cta_text) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_cta_texto', $cta_text);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_cta_texto');
    }

    if ('' !== $cta_url) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_cta_url', $cta_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_cta_url');
    }

    $group_titles = isset($_POST['sitio_cero_plan_regulador_group_title']) && is_array($_POST['sitio_cero_plan_regulador_group_title'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_title'])
        : array();
    $group_years = isset($_POST['sitio_cero_plan_regulador_group_year']) && is_array($_POST['sitio_cero_plan_regulador_group_year'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_year'])
        : array();
    $group_categories = isset($_POST['sitio_cero_plan_regulador_group_category']) && is_array($_POST['sitio_cero_plan_regulador_group_category'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_category'])
        : array();
    $group_descriptions = isset($_POST['sitio_cero_plan_regulador_group_description']) && is_array($_POST['sitio_cero_plan_regulador_group_description'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_description'])
        : array();
    $group_visual_columns = isset($_POST['sitio_cero_plan_regulador_group_visual_column']) && is_array($_POST['sitio_cero_plan_regulador_group_visual_column'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_visual_column'])
        : array();
    $group_manual_orders = isset($_POST['sitio_cero_plan_regulador_group_manual_order']) && is_array($_POST['sitio_cero_plan_regulador_group_manual_order'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_group_manual_order'])
        : array();

    $document_titles = isset($_POST['sitio_cero_plan_regulador_document_title']) && is_array($_POST['sitio_cero_plan_regulador_document_title'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_title'])
        : array();
    $document_urls = isset($_POST['sitio_cero_plan_regulador_document_url']) && is_array($_POST['sitio_cero_plan_regulador_document_url'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_url'])
        : array();
    $document_types = isset($_POST['sitio_cero_plan_regulador_document_type']) && is_array($_POST['sitio_cero_plan_regulador_document_type'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_type'])
        : array();
    $document_dates = isset($_POST['sitio_cero_plan_regulador_document_date']) && is_array($_POST['sitio_cero_plan_regulador_document_date'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_date'])
        : array();
    $document_labels = isset($_POST['sitio_cero_plan_regulador_document_label']) && is_array($_POST['sitio_cero_plan_regulador_document_label'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_label'])
        : array();
    $document_featured = isset($_POST['sitio_cero_plan_regulador_document_featured']) && is_array($_POST['sitio_cero_plan_regulador_document_featured'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_document_featured'])
        : array();

    $document_titles = array_values($document_titles);
    $document_urls = array_values($document_urls);
    $document_types = array_values($document_types);
    $document_dates = array_values($document_dates);
    $document_labels = array_values($document_labels);
    $document_featured = array_values($document_featured);

    $group_total = max(
        count($group_titles),
        count($group_years),
        count($group_categories),
        count($group_descriptions),
        count($group_visual_columns),
        count($group_manual_orders)
    );
    $groups = array();

    for ($group_index = 0; $group_index < $group_total; $group_index++) {
        $group_title = isset($group_titles[$group_index]) ? sanitize_text_field((string) $group_titles[$group_index]) : '';
        $group_year = isset($group_years[$group_index]) ? sanitize_text_field((string) $group_years[$group_index]) : '';
        $group_category = isset($group_categories[$group_index]) ? sanitize_key((string) $group_categories[$group_index]) : '';
        $group_description = isset($group_descriptions[$group_index]) ? sanitize_textarea_field((string) $group_descriptions[$group_index]) : '';
        $group_visual_column = isset($group_visual_columns[$group_index]) ? sanitize_key((string) $group_visual_columns[$group_index]) : 'right';
        if (!in_array($group_visual_column, array('left', 'right'), true)) {
            $group_visual_column = 'right';
        }
        $group_manual_order = isset($group_manual_orders[$group_index]) && '' !== trim((string) $group_manual_orders[$group_index])
            ? absint($group_manual_orders[$group_index])
            : absint($group_index);

        $group_document_titles = isset($document_titles[$group_index]) && is_array($document_titles[$group_index]) ? $document_titles[$group_index] : array();
        $group_document_urls = isset($document_urls[$group_index]) && is_array($document_urls[$group_index]) ? $document_urls[$group_index] : array();
        $group_document_types = isset($document_types[$group_index]) && is_array($document_types[$group_index]) ? $document_types[$group_index] : array();
        $group_document_dates = isset($document_dates[$group_index]) && is_array($document_dates[$group_index]) ? $document_dates[$group_index] : array();
        $group_document_labels = isset($document_labels[$group_index]) && is_array($document_labels[$group_index]) ? $document_labels[$group_index] : array();
        $group_document_featured = isset($document_featured[$group_index]) && is_array($document_featured[$group_index]) ? $document_featured[$group_index] : array();

        $document_total = max(
            count($group_document_titles),
            count($group_document_urls),
            count($group_document_types),
            count($group_document_dates),
            count($group_document_labels),
            count($group_document_featured)
        );

        $documents = array();

        for ($document_index = 0; $document_index < $document_total; $document_index++) {
            $title = isset($group_document_titles[$document_index]) ? sanitize_text_field((string) $group_document_titles[$document_index]) : '';
            $url = isset($group_document_urls[$document_index]) ? esc_url_raw((string) $group_document_urls[$document_index]) : '';
            $type = isset($group_document_types[$document_index]) ? sanitize_key((string) $group_document_types[$document_index]) : '';
            $date = isset($group_document_dates[$document_index]) ? sanitize_text_field((string) $group_document_dates[$document_index]) : '';
            $label = isset($group_document_labels[$document_index]) ? sanitize_text_field((string) $group_document_labels[$document_index]) : '';
            $featured = isset($group_document_featured[$document_index]) && '1' === (string) $group_document_featured[$document_index] ? '1' : '';

            if ('' === $title && '' === $url) {
                continue;
            }

            $documents[] = array(
                'title'    => $title,
                'url'      => $url,
                'type'     => $type,
                'date'     => $date,
                'label'    => $label,
                'featured' => $featured,
            );
        }

        if ('' === $group_title && empty($documents)) {
            continue;
        }

        $groups[] = array(
            'title'       => $group_title,
            'year'        => $group_year,
            'category'    => $group_category,
            'description' => $group_description,
            'visual_column' => $group_visual_column,
            'manual_order' => $group_manual_order,
            'documents'   => $documents,
        );
    }

    $groups = sitio_cero_sanitize_plan_regulador_document_groups($groups);

    if (!empty($groups)) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_document_groups', $groups);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_document_groups');
    }

    $section_titles = isset($_POST['sitio_cero_plan_regulador_section_title']) && is_array($_POST['sitio_cero_plan_regulador_section_title'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_section_title'])
        : array();
    $section_contents = isset($_POST['sitio_cero_plan_regulador_section_content']) && is_array($_POST['sitio_cero_plan_regulador_section_content'])
        ? wp_unslash($_POST['sitio_cero_plan_regulador_section_content'])
        : array();

    $section_total = max(count($section_titles), count($section_contents));
    $sections = array();

    for ($section_index = 0; $section_index < $section_total; $section_index++) {
        $section_title = isset($section_titles[$section_index]) ? sanitize_text_field((string) $section_titles[$section_index]) : '';
        $section_content = isset($section_contents[$section_index])
            ? sitio_cero_sanitize_direccion_html((string) $section_contents[$section_index])
            : '';

        if ('' === $section_title && '' === trim((string) wp_strip_all_tags($section_content))) {
            continue;
        }

        $sections[] = array(
            'title'   => $section_title,
            'content' => $section_content,
        );
    }

    if (!empty($sections)) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_sections', $sections);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_sections');
    }

    $custom_html = '';
    if (isset($_POST['sitio_cero_plan_regulador_custom_html'])) {
        $custom_html = sitio_cero_sanitize_direccion_html(wp_unslash($_POST['sitio_cero_plan_regulador_custom_html']));
    }

    if ('' !== $custom_html) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_custom_html', $custom_html);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_custom_html');
    }

    $custom_css = '';
    if (isset($_POST['sitio_cero_plan_regulador_custom_css'])) {
        $custom_css = wp_unslash($_POST['sitio_cero_plan_regulador_custom_css']);
        if (!current_user_can('unfiltered_html')) {
            $custom_css = sanitize_textarea_field($custom_css);
        }

        if (function_exists('sitio_cero_sanitize_tramite_custom_css')) {
            $custom_css = sitio_cero_sanitize_tramite_custom_css($custom_css);
        } else {
            $custom_css = trim((string) wp_kses((string) $custom_css, array()));
        }
    }

    if ('' !== $custom_css) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_custom_css', $custom_css);
    } else {
        delete_post_meta($post_id, 'sitio_cero_plan_regulador_custom_css');
    }
}
add_action('save_post_plan_regulador', 'sitio_cero_save_plan_regulador_meta');

function sitio_cero_enqueue_plan_regulador_custom_css()
{
    if (!is_singular('plan_regulador')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $custom_css = get_post_meta($post_id, 'sitio_cero_plan_regulador_custom_css', true);
    if (!is_string($custom_css) || '' === trim($custom_css)) {
        return;
    }

    $clean_css = function_exists('sitio_cero_sanitize_tramite_custom_css')
        ? sitio_cero_sanitize_tramite_custom_css($custom_css)
        : trim((string) wp_kses((string) $custom_css, array()));

    if ('' === $clean_css) {
        return;
    }

    $selector = '#plan-regulador-' . $post_id;
    $custom_css_output = false !== strpos($clean_css, '{{selector}}')
        ? str_replace('{{selector}}', $selector, $clean_css)
        : $selector . ' { ' . $clean_css . ' }';

    wp_add_inline_style('sitio-cero-main', $custom_css_output);
}
add_action('wp_enqueue_scripts', 'sitio_cero_enqueue_plan_regulador_custom_css', 20);

/* -------------------------------------------------------------------------- */
/* Plan Regulador (Fase 2: seed versionado con grupos y documentos) */
/* -------------------------------------------------------------------------- */

function sitio_cero_plan_regulador_infer_document_type($title)
{
    $normalized = remove_accents((string) $title);
    $normalized = strtolower($normalized);

    if (false !== strpos($normalized, 'diario oficial')) {
        return 'diario-oficial';
    }

    if (
        false !== strpos($normalized, 'decreto')
        || false !== strpos($normalized, 'dto')
        || false !== strpos($normalized, 'da_')
        || false !== strpos($normalized, 'da ')
        || false !== strpos($normalized, 'd.a.')
    ) {
        return 'decreto';
    }

    if (false !== strpos($normalized, 'resolucion') || false !== strpos($normalized, 'res_')) {
        return 'resolucion';
    }

    if (false !== strpos($normalized, 'informe') || false !== strpos($normalized, 'oficio') || false !== strpos($normalized, 'ord.')) {
        return 'informe';
    }

    if (false !== strpos($normalized, 'memoria')) {
        return 'memoria';
    }

    if (
        false !== strpos($normalized, 'plano')
        || false !== strpos($normalized, 'lamina')
        || false !== strpos($normalized, 'vialidad')
        || false !== strpos($normalized, 'zonificacion')
        || false !== strpos($normalized, 'perfil')
    ) {
        return 'plano';
    }

    return 'otro';
}

function sitio_cero_plan_regulador_infer_date($title)
{
    $title = (string) $title;
    if (preg_match('/\b(\d{2}[.\/-]\d{2}[.\/-]\d{4})\b/u', $title, $match)) {
        return sanitize_text_field((string) $match[1]);
    }

    if (preg_match('/\b(19|20)\d{2}\b/u', $title, $match)) {
        return sanitize_text_field((string) $match[0]);
    }

    return '';
}

function sitio_cero_get_plan_regulador_seed_groups()
{
    $groups = array(
        array(
            'title'    => '01 PROMULGACIÓN PRCC sector Ribera Norte DO 2001',
            'year'     => '2001',
            'category' => 'sector-ribera-norte',
            'documents'=> array(
                array('title' => '01 RES-144_GORE_PROMULGA PSRN_DO 07.08.2001', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01-RES-144_GORE_PROMULGA-PSRN_DO-07.08.2001.pdf'),
                array('title' => '02 Memoria Explicativa Seccional Ribera Norte 2000', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02-Memoria-Explicativa-Seccional-Ribera-Norte-2000.pdf'),
                array('title' => '03 PRCC 2001 L7 sector Ribera Norte Vialidad Estructurante', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/03-PRCC-2001-L7-sector-Ribera-Norte-Vialidad-Estructurante-scaled.jpg'),
                array('title' => '04 PRCC 2001 L8 sector Ribera Norte Perfiles Viales', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/04-PRCC-2001-L8-sector-RIbera-Norte-Perfiles-Viales-scaled.jpg'),
                array('title' => '05 PRCC 2001 L9 sector Ribera Norte Perfiles Viales', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/05-PRCC-2001-L9-sector-Ribera-Norte-Perfiles-Viales-scaled.jpg'),
                array('title' => '06 PRCC 2001 L10 sector Ribera Norte Zonificación', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/06-PRCC-2001-L10-sector-Ribera-Norte-Zonificacion-scaled.jpg'),
            ),
        ),
        array(
            'title'    => '02 PROMULGACIÓN PRCC DO 2004',
            'year'     => '2004',
            'category' => 'prcc',
            'documents'=> array(
                array('title' => '01 DTO-148 22-ABR-2004', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_-DTO-148_22-ABR-2004.pdf'),
                array('title' => '02 L 1 Plano Plan Regulador Concepción Lámina PRC 1 Zonificación', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02-L-1-PLANO-PLAN-REGUALDOR-CONCEPCION-LAMINA-PRC-1-ZONIFICACION-scaled.jpg'),
                array('title' => '02 L2 Plano Plan Regulador Concepción Lámina PRC 2 Vialidad Estructurante', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02-L2-PLANO-PLAN-REGULADOR-CONCEPCION-LAMINA-PRC-2-VIALIDAD-ESTRUCTURANTE-scaled.jpg'),
                array('title' => '03 L 3 Plano Plan Regulador Concepción Lámina PRC 3 Riesgos', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/03-L-3-PLANO-PLAN-REGULADOR-CONCEPCION-LAMINA-PRC-3-RIESGOS-scaled.jpg'),
                array('title' => '04 Memoria Explicativa 1 final', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/04-MEMORIA_EXPLICATIVA_1_final.pdf'),
                array('title' => '05 Memoria Explicativa 2 final', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/05-MEMORIA_EXPLICATIVA_2_final.pdf'),
                array('title' => '06 Memoria Explicativa 3 final', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/06-MEMORIA_EXPLICATIVA_3_final.pdf'),
                array('title' => '07 Estudio factibilidad servicios sanitarios', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/07-estudio-factibilida-servicios-sanitarios.pdf'),
                array('title' => '08 Certificado 247 ESSBIO', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/08-certificado-247-ESSBIO.pdf'),
                array('title' => '09 Estudio demanda vial PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/09-Estudio-demanda-vial-PRCC.pdf'),
                array('title' => '10 Resolución Exenta N°314-2000 EIA_COREMA_PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/10-Resolucion-Exenta-N°314-2000-EIA_COREMA_-PRCC.pdf'),
            ),
        ),
        array(
            'title'    => '03 PRIMERA ENMIENDA Y MODIFICACIÓN PRCC 2005',
            'year'     => '2005',
            'category' => 'enmienda',
            'documents'=> array(
                array('title' => '01 DA_78_1° MOD PRCC_DO_23.03.2005', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_78_1°-MOD-PRCC_DO_23.03.2005.pdf'),
                array('title' => 'Memoria 1 MOD_ENMIENDA_PRCC_2005', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-1-MOD_ENMIENDA_-PRCC_2005.pdf'),
            ),
        ),
        array(
            'title'    => '04 SEGUNDA MODIFICACIÓN PRCC 2006',
            'year'     => '2006',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01 DA_463_2° MOD.PRCC_DO_05.08.2006', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_463_2°-MOD.PRCC_DO_05.08.2006.pdf'),
                array('title' => 'Memoria 2°MOD PRCC2006', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-2°MOD-PRCC2006.pdf'),
            ),
        ),
        array(
            'title'    => '05 PRIMERA MODIFICACIÓN PRCC sector Ribera Norte 2006',
            'year'     => '2006',
            'category' => 'sector-ribera-norte',
            'documents'=> array(
                array('title' => '01_DA_712_1°MOD PSRN_DO_09.08.2006', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_712_1°MOD-PSRN_DO_09.08.2006.pdf'),
                array('title' => 'MEMORIA 1° MOD PSRN 2006', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-1°-MOD-PSRN-2006.pdf'),
            ),
        ),
        array(
            'title'    => '06 TERCERA MODIFICACIÓN PRCC febrero 2009',
            'year'     => '2009',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01_DA_78_3° MOD_PRCC_DO_09.02.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_78_3°-MOD_PRCC_DO_09.02.2009.pdf'),
                array('title' => 'MEM_3MOD_PRCC_26092008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEM_3MOD_PRCC_26092008.pdf'),
            ),
        ),
        array(
            'title'    => '07 SEGUNDA MODIFICACIÓN PRCC sector Ribera Norte enero 2009',
            'year'     => '2009',
            'category' => 'sector-ribera-norte',
            'documents'=> array(
                array('title' => '01_DA_98_2°MOD. PSRN_DO_09.02.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_98_2°MOD.-PSRN_DO_09.02.2009.pdf'),
                array('title' => '02_2° MOD PLAN SECCIONAL RIBERA NORTE LAMINA 7 VIALIDAD ESTRUCTURANTE 2008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02_2°-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-7-VIALIDAD-ESTRUCTURANTE-2008-scaled.jpg'),
                array('title' => '03_2° MOD PLAN SECCIONAL RIBERA NORTE LAMINA 8 PERFILES VIALES 2008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/03_2°-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-8-PERFILES-VIALES-2008-scaled.jpg'),
                array('title' => '04_2° MOD PLAN SECCIONAL RIBERA NORTE LAMINA 9 PERFILES VIALES 2008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/04_2°-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-9-PERFILES-VIALES-2008-scaled.jpg'),
                array('title' => '05_2° MOD PLAN SECCIONAL RIBERA NORTE LAMINA 10 ZONIFICACION 2008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/05_2°-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-10-ZONIFICACION-2008-scaled.jpg'),
                array('title' => 'MEM_2MOD_PSRN_26092008', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEM_2MOD_PSRN_26092008.pdf'),
            ),
        ),
        array(
            'title'    => '08 CUARTA MODIFICACIÓN PRCC septiembre 2009',
            'year'     => '2009',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01_DA_1088_4° MOD PRCC_DO_07.10.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_1088_4°-MOD-PRCC_DO_07.10.2009.pdf'),
                array('title' => '02 LAMINA PRC 2 VIALIDAD ESTRUCTURANTE 4 MODIFICACION 2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02-LAMINA-PRC-2-VIALIDAD-ESTRUCTURANTE-4-MODIFICACION-2009-scaled.jpg'),
                array('title' => '4° mod_PRCC_MEMORIA_07.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/4°-mod_PRCC_MEMORIA_07.2009.pdf'),
            ),
        ),
        array(
            'title'    => '09 TERCERA MODIFICACIÓN PRCC sector Ribera Norte diciembre 2009',
            'year'     => '2009',
            'category' => 'sector-ribera-norte',
            'documents'=> array(
                array('title' => '01_DA_1480_3°MOD PSRN_DO_30.12.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_1480_3°MOD-PSRN_DO_30.12.2009.pdf'),
                array('title' => '3 MOD PLAN SECCIONAL RIBERA NORTE LAMINA 7 VIALIDAD ESTRUCTURANTE 2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/3-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-7-VIALIDAD-ESTRUCTURANTE-2009-scaled.jpg'),
                array('title' => '3 MOD PLAN SECCIONAL RIBERA NORTE LAMINA 8 PERFILES VIALES 2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/3-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-8-PERFILES-VIALES-2009-scaled.jpg'),
                array('title' => '3 MOD PLAN SECCIONAL RIBERA NORTE LAMINA 9 PERFILES VIALES 2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/3-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-8-PERFILES-VIALES-2009-scaled.jpg'),
                array('title' => '3 MOD PLAN SECCIONAL RIBERA NORTE LAMINA 10 ZONIFICACION 2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/3-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-8-PERFILES-VIALES-2009-scaled.jpg'),
                array('title' => 'MEM_3MOD_PSRN_09.07.2009', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEM_3MOD_PSRN_09.07.2009.pdf'),
            ),
        ),
        array(
            'title'    => '10 CUARTA MODIFICACIÓN PRCC Sector Ribera Norte 2012',
            'year'     => '2012',
            'category' => 'sector-ribera-norte',
            'documents'=> array(
                array('title' => '01_DA_390_4° MOD_PSRN_DO_22.05.2012', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_390_4°-MOD_PSRN_DO_22.05.2012.pdf'),
                array('title' => '4 MOD PLAN SECCIONAL RIBERA NORTE LAMINA 10 ZONIFICACIÓN 2012', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/4-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-10-ZONIFICACION-2012.jpg'),
                array('title' => '4 MOD PLAN SECCIONAL RIBERA NORTE LAMINA VIALIDAD ESTRUCTURANTE 2012', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/4-MOD-PLAN-SECCIONAL-RIBERA-NORTE-LAMINA-VIALIDAD-ESTRUCTURANTE-2012.jpg'),
            ),
        ),
        array(
            'title'    => '11 DEJA SIN EFECTO DUP / ASIGNA NORMAS ANTIGUAS DUP 2015',
            'year'     => '2015',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01_DA 154 deja sin efecto DUP_DO_28.04.2015', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_-DA-154-deja-sin-efecto-DUP_DO_28.04.2015.pdf'),
                array('title' => '02_DA 192 asigna normas DUP caducas_DO_29.05.2015', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02_DA-192-asigna-nomas-DUP-caducas_DO_29.05.2015.pdf'),
            ),
        ),
        array(
            'title'    => '12 SEGUNDA ENMIENDA PRCC sector Ribera Norte 2016',
            'year'     => '2016',
            'category' => 'enmienda',
            'documents'=> array(
                array('title' => '01_DA_135_2° ENMIENDA PRCC sector Ribera Norte_DO_16.06.2016', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA_135_2°-ENMIENDA-PRCC-sector-Ribera-Norte_DO_16.06.2016.pdf'),
                array('title' => 'MEMORIA 2 ENMIENDA SECTOR SUR RIBERA', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-2-ENMIENDA-SECTOR-SUR-RIBERA-NORTE_07.01.2016.pdf'),
            ),
        ),
        array(
            'title'    => '13 DECIMOTERCERA MODIFICACIÓN PRCC 2019',
            'year'     => '2019',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01 DA_208_13 MO PRCC DIARIO OFICIAL_DO_03.04.2019', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01-DA_-208_-13-MO-PRCC-DIARIO-OFICIAL_DO_03.04.2019.pdf'),
                array('title' => '13 Mod – Lam 7 Vialidad RN', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/13-Mod-Lam-7-Vialidad-RN-scaled.jpg'),
                array('title' => '13 Mod – Lam 9 Perfil RN', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/13-Mod-Lam-9-Perfil-RN-scaled.jpg'),
                array('title' => '13 Mod – Lam 10 Zonificación RN', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/13-Mod-Lam-10-Zonificacion-RN-scaled.jpg'),
                array('title' => '13 Mod – Plano Vialidad PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/13-Mod-Plano-Vialidad-PRCC-scaled.jpg'),
                array('title' => '13 Mod – Plano Zonificación PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/13-Mod-Plano-Zonificacion-PRCC-scaled.jpg'),
                array('title' => 'Memoria 13° Mod. PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Memoria-13°-Mod.-PRCC.pdf'),
            ),
        ),
        array(
            'title'    => '14 DECIMOCUARTA MODIFICACIÓN PARQUE HUMEDAL PAICAVÍ PRCC 2020',
            'year'     => '2020',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01_DA 756_14°MOD PRCC_DO_23.10.2020', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01_DA-756_-14°MOD-PRCC_DO_23.10.2020.pdf'),
                array('title' => 'MEMORIA 14 MODIF PRC según Decreto Alcaldicio N 756 de 2020', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-14-MODIF-PRC-segun-Decreto-Alcaldicio-N-756-de-2020.pdf'),
                array('title' => 'Vialidad Lam 02 de 02 - 14 Mod PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Vialidad-Lam-02-de-02-14-Mod-PRCC-scaled.jpg'),
                array('title' => 'Zonificación Lam 01 de 02 - 14 Mod PRCC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Zonificacion-Lam-01-de-02-14-Mod-PRCC-scaled.jpg'),
            ),
        ),
        array(
            'title'    => '15 DECIMOQUINTA MODIFICACIÓN PRCC 2021',
            'year'     => '2021',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => '01 DA 494 15° MOD PRCC DO_06.07.2021', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/01-DA-494-15°-MOD-PRCC-DO_06.07.2021.pdf'),
                array('title' => '02 MEMORIA 15 MOD DIAGNOSTICO PARTE 1', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/02-MEMORIA-15-MOD-DIAGNOSTICOPARTE-1.pdf'),
                array('title' => '03 MEMORIA 15 MOD PLAN PROPUESTO PARTE 2', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/03-MEMORIA-15-MOD-PLAN-PROPUESTO-PARTE-2.pdf'),
                array('title' => '04 MEMORIA 15 MOD DESCRIPCIÓN DEL PLAN PARTE 3', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/04-MEMORIA-15-MOD_DESCRIPCION-DEL-PLAN-PARTE-3.pdf'),
                array('title' => 'PLANO VIALIDAD 15 MOD', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO-VIALIDAD-15-MOD-scaled.jpg'),
                array('title' => 'PLANO ZONIFICACIÓN PRC 15 MOD', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO-ZONIFICACION-PRC-15-MOD-scaled.jpg'),
                array('title' => 'PLANO ZONIFICACIÓN SECTOR RN 15 MOD', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO-ZONIFICACION-SECTOR-RN-15-MOD-scaled.jpg'),
            ),
        ),
        array(
            'title'    => '16 DECIMOSEXTA HUMEDAL PICHIMAPU PRCC 2024',
            'year'     => '2024',
            'category' => 'modificacion',
            'documents'=> array(
                array('title' => 'D.A. Nº1280 DE 2024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/D.A.-No1280-DE-2024.pdf'),
                array('title' => 'MEMORIA_HU_PICHIMAPU_25072024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA_HU_PICHIMAPU_25072024.pdf'),
                array('title' => 'PLANO_PE3-2023 Pichimapu', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO_PE3-2023-Pichimapu.pdf'),
                array('title' => 'RES_EJ_18042024_25072024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/RES_EJ_18042024_25072024.pdf'),
            ),
        ),
        array(
            'title'    => '17 DECIMOSÉPTIMA ENMIENDA PRCC 2026',
            'year'     => '2026',
            'category' => 'enmienda',
            'documents'=> array(
                array('title' => '01 DECRETO N°139 SECMUN', 'url' => 'https://concepcion.cl/wp-content/uploads/2026/02/01-DECRETO-N°139-SECMUN.pdf'),
                array('title' => '02 ACUERDO CONCEJO 0173', 'url' => 'https://concepcion.cl/wp-content/uploads/2026/02/02-ACUERDO-CONCEJO-0173.pdf'),
                array('title' => '03 17 enmienda MEMORIA CORREGIDA según IT_ver D 22 oct', 'url' => 'https://concepcion.cl/wp-content/uploads/2026/02/03-17-enmienda-MEMORIA-CORREGIDA-segun-IT_ver-D-22-oct.pdf'),
                array('title' => '04 PRC1 ZONIFICACION 2025', 'url' => 'https://concepcion.cl/wp-content/uploads/2026/02/04-PRC1-ZONIFICACION-2025.pdf'),
                array('title' => '05 PRC2 VIALIDAD 2025', 'url' => 'https://concepcion.cl/wp-content/uploads/2026/02/05-PRC2-VIALIDAD-2025.pdf'),
                array('title' => 'Publicación en Diario Oficial', 'url' => 'https://www.diariooficial.interior.gob.cl/publicaciones/2026/04/07/44418/04/2788347.pdf'),
            ),
        ),
        array(
            'title'    => 'PLANO DETALLE 1 VÍAS BONILLA Y OTRAS AGOSTO 2018',
            'year'     => '2018',
            'category' => 'plano-detalle',
            'documents'=> array(
                array('title' => '20180629 Memoria Firmada', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/20180629-Memoria-Firmada.pdf'),
                array('title' => 'Decreto Nº 893', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Decreto-No-893.pdf'),
                array('title' => 'Municipalidad de Concepción Aprueba Plano PL-DET-072018', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MUNICIPALIDAD-DE-CONCEPCION-Aprueba-Plano-PL-DET-072018.pdf'),
                array('title' => 'Oficio 1792 informe favorable planos de detalle', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/oficio-1792-informe-favorable-planos-de-detalle.pdf'),
                array('title' => 'PLANO_DET_VIAS_BONILLA_NORTE_VIALIDAD', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO_DET_VIAS_BONILLA_NORTE_VIALIDAD.jpg'),
                array('title' => 'PLANO_DET_VIAS_BONILLA_NORTE_ZONIFICACION', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO_DET_VIAS_BONILLA_NORTE_ZONIFICACION.jpg'),
                array('title' => 'PRC_1_PLANO_DETVIAS_BONILLA_NORTE_DTO-893_22-SEP-2018', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PRC_1_PLANO_DETVIAS_BONILLA_NORTE_DTO-893_22-SEP-2018.pdf'),
            ),
        ),
        array(
            'title'    => 'PLANO DETALLE 2 LO MÉNDEZ JUNIO 2022',
            'year'     => '2022',
            'category' => 'plano-detalle',
            'documents'=> array(
                array('title' => 'Decreto', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/decreto.pdf'),
                array('title' => 'Informe Plano de Detalle Lo Méndez firmado KRC', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Informe-Plano-de-Detalle-Lo-Mendez-firmado-KRC.pdf'),
                array('title' => 'Ord. N°1299 MINVU it plano Lo Méndez firmado Seremi MINVU', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/Ord.-N°1299-MINVU-it-plano-os-mendez-firmado-seremi-minvu.pdf'),
                array('title' => 'PLANO DE DETALLE LO MENDEZ FIRMADO', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO-DE-DETALLE-LO-MENDEZ-FIRMADO-scaled.jpg'),
            ),
        ),
        array(
            'title'    => 'PLANO DETALLE 3 ALEMPARTE JULIO 2022',
            'year'     => '2022',
            'category' => 'plano-detalle',
            'documents'=> array(
                array('title' => 'Acuerdo N°2118-100-2024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/ACUIERDO-N°-2118-100-2024.pdf'),
                array('title' => 'Decreto N°660-2024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/DECRETO-N°-660-2024-Copy.pdf'),
                array('title' => 'Informe Plano Detalle Alemparte_07072022', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/INFORME-PLANO-DETALLE-ALEMPARTE_07072022.pdf'),
                array('title' => 'Ord Nº2808 MINVU aprueba plano detalle Alemparte', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/10/ORD-No-2808-minvu-aprueba-plano-detalle-alemparte.pdf'),
                array('title' => 'PD_alemparte', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/10/PD_alemparte.pdf'),
            ),
        ),
        array(
            'title'    => 'PLANO DETALLE 4 TUCAPEL ROL 6900-1 MARZO 2024',
            'year'     => '2024',
            'category' => 'plano-detalle',
            'documents'=> array(
                array('title' => 'Acuerdo Concejo N°2116-100-2024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/ACUERDO-CONCEJO-N°-2116-100-2024.pdf'),
                array('title' => 'Decreto Alcaldicio aprueba PD', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/DECRETO-ALCALDICIO-APRUEBA-PD.pdf'),
                array('title' => 'Informe favorable Seremi MINVU 6900-1', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/INFORME-FAVORABLE-SEREMI-MINVU-6900-1-1.pdf'),
                array('title' => 'Informe Plano Detalle Tucapel 6900-1', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/INFORME-PLANO-DETALLE-TUCAPEL-6900-1.pdf'),
                array('title' => 'Plano de Detalle Tucapel 6900-1', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/11/Plano_detalle_tucapel_6900-1.pdf'),
            ),
        ),
        array(
            'title'    => 'PLANO DETALLE 5 TUCAPEL 6900-3 MAYO 2024',
            'year'     => '2024',
            'category' => 'plano-detalle',
            'documents'=> array(
                array('title' => 'Acuerdo Concejo N°2117-100-2024', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/ACUERDO-CONCEJO-N°-2117-100-2024.pdf'),
                array('title' => 'Decreto Alcaldicio aprueba PD-1', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/DECRETO-ALCALDICIO-APRUEBA-PD-1.pdf'),
                array('title' => 'Informe favorable Seremi MINVU 6900-3', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/INFORME-FAVORABLE-SEREMI-MINVU-6900-3.pdf'),
                array('title' => 'Memoria Explicativa Plano Detalle Tucapel 6900-3', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/MEMORIA-EXPLICATIVA-PLANO-DETALLE-TUCAPEL-6900-3.pdf'),
                array('title' => 'Plano Detalle Tucapel 6900-3', 'url' => 'https://concepcion.cl/wp-content/uploads/2025/09/PLANO-DETALLE-TUCAPEL-6900-3.pdf'),
            ),
        ),
    );

    $normalized = array();
    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }

        $documents = isset($group['documents']) && is_array($group['documents']) ? $group['documents'] : array();
        $normalized_documents = array();

        foreach ($documents as $document) {
            if (!is_array($document)) {
                continue;
            }

            $title = isset($document['title']) ? sanitize_text_field((string) $document['title']) : '';
            $url = isset($document['url']) ? esc_url_raw((string) $document['url']) : '';
            if ('' === $title && '' === $url) {
                continue;
            }

            $normalized_documents[] = array(
                'title'    => $title,
                'url'      => $url,
                'type'     => sitio_cero_plan_regulador_infer_document_type($title),
                'date'     => sitio_cero_plan_regulador_infer_date($title),
                'label'    => '',
                'featured' => '',
            );
        }

        if (empty($normalized_documents)) {
            continue;
        }

        $normalized[] = array(
            'title'       => isset($group['title']) ? sanitize_text_field((string) $group['title']) : '',
            'year'        => isset($group['year']) ? sanitize_text_field((string) $group['year']) : '',
            'category'    => isset($group['category']) ? sanitize_key((string) $group['category']) : '',
            'description' => isset($group['description']) ? sanitize_textarea_field((string) $group['description']) : '',
            'documents'   => $normalized_documents,
        );
    }

    return sitio_cero_sanitize_plan_regulador_document_groups($normalized);
}

function sitio_cero_seed_plan_regulador_content()
{
    if (!post_type_exists('plan_regulador')) {
        return;
    }

    $seed_version = '1';
    $already_seeded_version = (string) get_option('sitio_cero_plan_regulador_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $title = 'Plan Regulador';
    $slug = 'plan-regulador';

    $existing = get_posts(
        array(
            'post_type'      => 'plan_regulador',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'name'           => $slug,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing)) {
        $post_id = (int) $existing[0];
    } else {
        $post_id = wp_insert_post(
            array(
                'post_type'    => 'plan_regulador',
                'post_status'  => 'publish',
                'post_title'   => $title,
                'post_name'    => $slug,
                'post_excerpt' => 'Repositorio de instrumentos de planificación territorial del Plan Regulador Comunal de Concepción, incluyendo promulgaciones, modificaciones, enmiendas, planos de detalle y documentos normativos asociados.',
                'post_content' => '<p>Instrumentos de planificación territorial del Plan Regulador Comunal de Concepción.</p>',
            ),
            true
        );
    }

    if (is_wp_error($post_id) || !$post_id) {
        return;
    }

    $post_id = (int) $post_id;

    if (!has_excerpt($post_id)) {
        wp_update_post(
            array(
                'ID'           => $post_id,
                'post_excerpt' => 'Repositorio de instrumentos de planificación territorial del Plan Regulador Comunal de Concepción, incluyendo promulgaciones, modificaciones, enmiendas, planos de detalle y documentos normativos asociados.',
            )
        );
    }

    update_post_meta($post_id, 'sitio_cero_plan_regulador_subtitulo', 'Instrumentos de Planificación Territorial');
    update_post_meta($post_id, 'sitio_cero_plan_regulador_bajada', 'Repositorio documental del Plan Regulador Comunal de Concepción.');

    $groups = sitio_cero_get_plan_regulador_seed_groups();
    if (!empty($groups)) {
        update_post_meta($post_id, 'sitio_cero_plan_regulador_document_groups', $groups);
    }

    update_option('sitio_cero_plan_regulador_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_plan_regulador_content', 49);

function sitio_cero_normalize_news_quotes($text)
{
    if (!is_string($text) || '' === $text) {
        return $text;
    }

    return str_replace(
        array('«', '»', '&laquo;', '&raquo;', '&#171;', '&#187;'),
        '"',
        $text
    );
}

function sitio_cero_normalize_news_title_quotes($title, $post_id)
{
    if (is_admin() || !$post_id || 'noticia' !== get_post_type($post_id)) {
        return $title;
    }

    return sitio_cero_normalize_news_quotes($title);
}
add_filter('the_title', 'sitio_cero_normalize_news_title_quotes', 20, 2);

function sitio_cero_normalize_news_content_quotes($content)
{
    if (is_admin() || !is_singular('noticia')) {
        return $content;
    }

    return sitio_cero_normalize_news_quotes($content);
}
add_filter('the_content', 'sitio_cero_normalize_news_content_quotes', 20);

function sitio_cero_normalize_news_document_title_quotes($parts)
{
    if (is_admin() || !is_singular('noticia') || !is_array($parts)) {
        return $parts;
    }

    if (isset($parts['title'])) {
        $parts['title'] = sitio_cero_normalize_news_quotes((string) $parts['title']);
    }

    return $parts;
}
add_filter('document_title_parts', 'sitio_cero_normalize_news_document_title_quotes', 20);

function sitio_cero_normalize_news_document_title_text($title)
{
    if (is_admin() || !is_singular('noticia')) {
        return $title;
    }

    return sitio_cero_normalize_news_quotes($title);
}
add_filter('document_title', 'sitio_cero_normalize_news_document_title_text', 20);

function sitio_cero_get_agenda_channels_fallback_data()
{
    return array(
        'title'       => __('Canales para resolver tus consultas', 'sitio-cero'),
        'description' => '',
        'channels'    => array(
            array(
                'icon'        => '',
                'title'       => __('Presencial', 'sitio-cero'),
                'description' => __('Lunes a viernes, 08:30 a 14:00 hrs.', 'sitio-cero'),
                'url'         => '',
                'target'      => '_self',
                'visible'     => true,
                'order'       => 10,
            ),
            array(
                'icon'        => '',
                'title'       => __('Telefónico', 'sitio-cero'),
                'description' => '+56 2 3386 8000.',
                'url'         => '',
                'target'      => '_self',
                'visible'     => true,
                'order'       => 20,
            ),
            array(
                'icon'        => '',
                'title'       => __('Correo', 'sitio-cero'),
                'description' => 'contacto@municipio.cl.',
                'url'         => '',
                'target'      => '_self',
                'visible'     => true,
                'order'       => 30,
            ),
            array(
                'icon'        => '',
                'title'       => __('Oficina virtual', 'sitio-cero'),
                'description' => __('Trámites, solicitudes y seguimiento.', 'sitio-cero'),
                'url'         => '',
                'target'      => '_self',
                'visible'     => true,
                'order'       => 40,
            ),
        ),
        'cta'         => array(
            'label'  => __('Ingresar a oficina virtual', 'sitio-cero'),
            'url'    => '#',
            'target' => '_self',
        ),
    );
}

function sitio_cero_sanitize_agenda_channel_row($row)
{
    if (!is_array($row)) {
        return null;
    }

    $icon = isset($row['icon']) ? sanitize_text_field((string) $row['icon']) : '';
    $title = isset($row['title']) ? sanitize_text_field((string) $row['title']) : '';
    $description = isset($row['description']) ? sanitize_textarea_field((string) $row['description']) : '';
    $url = isset($row['url']) ? esc_url_raw((string) $row['url']) : '';
    $target = isset($row['target']) ? sanitize_key((string) $row['target']) : '_self';
    $visible = isset($row['visible']) && '1' === (string) $row['visible'];
    $order = isset($row['order']) ? (int) $row['order'] : 0;

    if ('_blank' !== $target) {
        $target = '_self';
    }

    if ('' === $title && '' === $description && '' === $url) {
        return null;
    }

    return array(
        'icon'        => $icon,
        'title'       => $title,
        'description' => $description,
        'url'         => $url,
        'target'      => $target,
        'visible'     => $visible,
        'order'       => $order,
    );
}

function sitio_cero_sanitize_agenda_channels_rows($rows)
{
    if (!is_array($rows)) {
        return array();
    }

    $normalized = array();
    foreach ($rows as $row) {
        $clean_row = sitio_cero_sanitize_agenda_channel_row($row);
        if (is_array($clean_row)) {
            $normalized[] = $clean_row;
        }
    }

    usort(
        $normalized,
        function ($a, $b) {
            $a_order = isset($a['order']) ? (int) $a['order'] : 0;
            $b_order = isset($b['order']) ? (int) $b['order'] : 0;
            if ($a_order === $b_order) {
                return 0;
            }

            return ($a_order < $b_order) ? -1 : 1;
        }
    );

    return $normalized;
}

function sitio_cero_register_agenda_canal_post_type()
{
    $labels = array(
        'name'               => __('Canales agenda', 'sitio-cero'),
        'singular_name'      => __('Canal agenda', 'sitio-cero'),
        'menu_name'          => __('Canales agenda', 'sitio-cero'),
        'name_admin_bar'     => __('Canal agenda', 'sitio-cero'),
        'add_new'            => __('Agregar nuevo', 'sitio-cero'),
        'add_new_item'       => __('Agregar canal agenda', 'sitio-cero'),
        'new_item'           => __('Nuevo canal agenda', 'sitio-cero'),
        'edit_item'          => __('Editar canal agenda', 'sitio-cero'),
        'view_item'          => __('Ver canal agenda', 'sitio-cero'),
        'all_items'          => __('Todos los canales agenda', 'sitio-cero'),
        'search_items'       => __('Buscar canales agenda', 'sitio-cero'),
        'not_found'          => __('No se encontraron canales agenda.', 'sitio-cero'),
        'not_found_in_trash' => __('No hay canales agenda en la papelera.', 'sitio-cero'),
    );

    register_post_type(
        'agenda_canal',
        array(
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'exclude_from_search'=> true,
            'publicly_queryable' => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'menu_position'      => 27,
            'menu_icon'          => 'dashicons-phone',
            'supports'           => array('title', 'revisions', 'page-attributes'),
        )
    );
}
add_action('init', 'sitio_cero_register_agenda_canal_post_type');

function sitio_cero_add_agenda_canal_metaboxes()
{
    add_meta_box(
        'sitio_cero_agenda_canal_settings',
        __('Configuracion de bloque #canales', 'sitio-cero'),
        'sitio_cero_render_agenda_canal_metabox',
        'agenda_canal',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'sitio_cero_add_agenda_canal_metaboxes');

function sitio_cero_render_agenda_canal_metabox($post)
{
    wp_nonce_field('sitio_cero_save_agenda_canal_meta', 'sitio_cero_agenda_canal_meta_nonce');

    $is_active_value = get_post_meta($post->ID, 'sitio_cero_agenda_canal_active', true);
    $is_active = '' === (string) $is_active_value || '1' === (string) $is_active_value;
    $block_title = get_post_meta($post->ID, 'sitio_cero_agenda_canal_block_title', true);
    $block_description = get_post_meta($post->ID, 'sitio_cero_agenda_canal_block_description', true);
    $cta_label = get_post_meta($post->ID, 'sitio_cero_agenda_canal_cta_label', true);
    $cta_url = get_post_meta($post->ID, 'sitio_cero_agenda_canal_cta_url', true);
    $cta_target = get_post_meta($post->ID, 'sitio_cero_agenda_canal_cta_target', true);
    $channels = get_post_meta($post->ID, 'sitio_cero_agenda_canal_channels', true);

    if (!is_string($block_title)) {
        $block_title = '';
    }
    if (!is_string($block_description)) {
        $block_description = '';
    }
    if (!is_string($cta_label)) {
        $cta_label = '';
    }
    if (!is_string($cta_url)) {
        $cta_url = '';
    }
    if (!is_string($cta_target) || ('_blank' !== $cta_target && '_self' !== $cta_target)) {
        $cta_target = '_self';
    }
    if (!is_array($channels)) {
        $channels = array();
    }

    $channels = sitio_cero_sanitize_agenda_channels_rows($channels);
    if (empty($channels)) {
        $channels = sitio_cero_get_agenda_channels_fallback_data()['channels'];
    }
    ?>
    <p>
        <label>
            <input type="checkbox" name="sitio_cero_agenda_canal_active" value="1"<?php checked($is_active); ?>>
            <?php esc_html_e('Usar este contenido en el bloque #canales de la portada', 'sitio-cero'); ?>
        </label>
    </p>
    <p>
        <label for="sitio_cero_agenda_canal_block_title"><strong><?php esc_html_e('Título del bloque', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_agenda_canal_block_title" type="text" name="sitio_cero_agenda_canal_block_title" class="widefat" value="<?php echo esc_attr($block_title); ?>" placeholder="<?php esc_attr_e('Canales para resolver tus consultas', 'sitio-cero'); ?>">
    </p>
    <p>
        <label for="sitio_cero_agenda_canal_block_description"><strong><?php esc_html_e('Descripción breve (opcional)', 'sitio-cero'); ?></strong></label>
        <textarea id="sitio_cero_agenda_canal_block_description" name="sitio_cero_agenda_canal_block_description" class="widefat" rows="3"><?php echo esc_textarea($block_description); ?></textarea>
    </p>
    <hr>
    <p><strong><?php esc_html_e('Canales', 'sitio-cero'); ?></strong></p>
    <div id="sitio-cero-agenda-canales-rows">
        <?php foreach ($channels as $index => $channel) : ?>
            <?php
            if (!is_array($channel)) {
                continue;
            }
            $row_icon = isset($channel['icon']) ? (string) $channel['icon'] : '';
            $row_title = isset($channel['title']) ? (string) $channel['title'] : '';
            $row_description = isset($channel['description']) ? (string) $channel['description'] : '';
            $row_url = isset($channel['url']) ? (string) $channel['url'] : '';
            $row_target = isset($channel['target']) ? (string) $channel['target'] : '_self';
            $row_visible = !isset($channel['visible']) || !empty($channel['visible']);
            $row_order = isset($channel['order']) ? (int) $channel['order'] : (($index + 1) * 10);
            ?>
            <fieldset class="sitio-cero-agenda-canal-row" style="border:1px solid #dcdcde;padding:12px;margin:0 0 10px 0;">
                <p>
                    <label><strong><?php esc_html_e('Título', 'sitio-cero'); ?></strong></label>
                    <input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][title]" value="<?php echo esc_attr($row_title); ?>">
                </p>
                <p>
                    <label><strong><?php esc_html_e('Descripción', 'sitio-cero'); ?></strong></label>
                    <input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][description]" value="<?php echo esc_attr($row_description); ?>">
                </p>
                <p>
                    <label><strong><?php esc_html_e('Icono Material Symbols (opcional)', 'sitio-cero'); ?></strong></label>
                    <input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][icon]" value="<?php echo esc_attr($row_icon); ?>" placeholder="call">
                </p>
                <p>
                    <label><strong><?php esc_html_e('URL (opcional)', 'sitio-cero'); ?></strong></label>
                    <input type="url" class="widefat" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][url]" value="<?php echo esc_attr(esc_url_raw($row_url)); ?>">
                </p>
                <p>
                    <label><strong><?php esc_html_e('Destino', 'sitio-cero'); ?></strong></label>
                    <select name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][target]">
                        <option value="_self"<?php selected('_self', $row_target); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
                        <option value="_blank"<?php selected('_blank', $row_target); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
                    </select>
                </p>
                <p>
                    <label><strong><?php esc_html_e('Orden', 'sitio-cero'); ?></strong></label>
                    <input type="number" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][order]" value="<?php echo esc_attr((string) $row_order); ?>" min="0" step="1">
                </p>
                <p>
                    <label>
                        <input type="checkbox" name="sitio_cero_agenda_canal_channels[<?php echo esc_attr((string) $index); ?>][visible]" value="1"<?php checked($row_visible); ?>>
                        <?php esc_html_e('Visible', 'sitio-cero'); ?>
                    </label>
                </p>
                <p>
                    <button type="button" class="button-link-delete sitio-cero-agenda-canal-remove-row"><?php esc_html_e('Eliminar canal', 'sitio-cero'); ?></button>
                </p>
            </fieldset>
        <?php endforeach; ?>
    </div>
    <p>
        <button type="button" class="button" id="sitio-cero-agenda-canal-add-row"><?php esc_html_e('Agregar canal', 'sitio-cero'); ?></button>
    </p>
    <hr>
    <p><strong><?php esc_html_e('Botón principal (CTA)', 'sitio-cero'); ?></strong></p>
    <p>
        <label for="sitio_cero_agenda_canal_cta_label"><strong><?php esc_html_e('Texto del botón', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_agenda_canal_cta_label" type="text" name="sitio_cero_agenda_canal_cta_label" class="widefat" value="<?php echo esc_attr($cta_label); ?>" placeholder="<?php esc_attr_e('Ingresar a oficina virtual', 'sitio-cero'); ?>">
    </p>
    <p>
        <label for="sitio_cero_agenda_canal_cta_url"><strong><?php esc_html_e('URL del botón', 'sitio-cero'); ?></strong></label>
        <input id="sitio_cero_agenda_canal_cta_url" type="url" name="sitio_cero_agenda_canal_cta_url" class="widefat" value="<?php echo esc_attr(esc_url_raw($cta_url)); ?>" placeholder="#">
    </p>
    <p>
        <label for="sitio_cero_agenda_canal_cta_target"><strong><?php esc_html_e('Destino del botón', 'sitio-cero'); ?></strong></label>
        <select id="sitio_cero_agenda_canal_cta_target" name="sitio_cero_agenda_canal_cta_target">
            <option value="_self"<?php selected('_self', $cta_target); ?>><?php esc_html_e('Misma pestaña', 'sitio-cero'); ?></option>
            <option value="_blank"<?php selected('_blank', $cta_target); ?>><?php esc_html_e('Nueva pestaña', 'sitio-cero'); ?></option>
        </select>
    </p>
    <script>
    (function () {
        var rowsContainer = document.getElementById('sitio-cero-agenda-canales-rows');
        var addButton = document.getElementById('sitio-cero-agenda-canal-add-row');
        if (!rowsContainer || !addButton) {
            return;
        }

        var nextIndex = rowsContainer.querySelectorAll('.sitio-cero-agenda-canal-row').length;

        var buildRow = function (index) {
            var wrapper = document.createElement('fieldset');
            wrapper.className = 'sitio-cero-agenda-canal-row';
            wrapper.style.cssText = 'border:1px solid #dcdcde;padding:12px;margin:0 0 10px 0;';
            wrapper.innerHTML = ''
                + '<p><label><strong><?php echo esc_js(__('Título', 'sitio-cero')); ?></strong></label><input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[' + index + '][title]"></p>'
                + '<p><label><strong><?php echo esc_js(__('Descripción', 'sitio-cero')); ?></strong></label><input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[' + index + '][description]"></p>'
                + '<p><label><strong><?php echo esc_js(__('Icono Material Symbols (opcional)', 'sitio-cero')); ?></strong></label><input type="text" class="widefat" name="sitio_cero_agenda_canal_channels[' + index + '][icon]" placeholder="call"></p>'
                + '<p><label><strong><?php echo esc_js(__('URL (opcional)', 'sitio-cero')); ?></strong></label><input type="url" class="widefat" name="sitio_cero_agenda_canal_channels[' + index + '][url]"></p>'
                + '<p><label><strong><?php echo esc_js(__('Destino', 'sitio-cero')); ?></strong></label><select name="sitio_cero_agenda_canal_channels[' + index + '][target]"><option value="_self"><?php echo esc_js(__('Misma pestaña', 'sitio-cero')); ?></option><option value="_blank"><?php echo esc_js(__('Nueva pestaña', 'sitio-cero')); ?></option></select></p>'
                + '<p><label><strong><?php echo esc_js(__('Orden', 'sitio-cero')); ?></strong></label><input type="number" name="sitio_cero_agenda_canal_channels[' + index + '][order]" min="0" step="1" value="' + ((index + 1) * 10) + '"></p>'
                + '<p><label><input type="checkbox" name="sitio_cero_agenda_canal_channels[' + index + '][visible]" value="1" checked> <?php echo esc_js(__('Visible', 'sitio-cero')); ?></label></p>'
                + '<p><button type="button" class="button-link-delete sitio-cero-agenda-canal-remove-row"><?php echo esc_js(__('Eliminar canal', 'sitio-cero')); ?></button></p>';

            return wrapper;
        };

        addButton.addEventListener('click', function () {
            rowsContainer.appendChild(buildRow(nextIndex));
            nextIndex += 1;
        });

        rowsContainer.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.classList.contains('sitio-cero-agenda-canal-remove-row')) {
                return;
            }

            event.preventDefault();
            var row = target.closest('.sitio-cero-agenda-canal-row');
            if (row) {
                row.remove();
            }
        });
    })();
    </script>
    <?php
}

function sitio_cero_save_agenda_canal_meta($post_id)
{
    if (!isset($_POST['sitio_cero_agenda_canal_meta_nonce'])) {
        return;
    }

    $nonce = sanitize_text_field(wp_unslash($_POST['sitio_cero_agenda_canal_meta_nonce']));
    if (!wp_verify_nonce($nonce, 'sitio_cero_save_agenda_canal_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $is_active = isset($_POST['sitio_cero_agenda_canal_active']) && '1' === (string) wp_unslash($_POST['sitio_cero_agenda_canal_active']);
    update_post_meta($post_id, 'sitio_cero_agenda_canal_active', $is_active ? '1' : '0');

    $block_title = isset($_POST['sitio_cero_agenda_canal_block_title'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_agenda_canal_block_title']))
        : '';
    if ('' !== $block_title) {
        update_post_meta($post_id, 'sitio_cero_agenda_canal_block_title', $block_title);
    } else {
        delete_post_meta($post_id, 'sitio_cero_agenda_canal_block_title');
    }

    $block_description = isset($_POST['sitio_cero_agenda_canal_block_description'])
        ? sanitize_textarea_field(wp_unslash($_POST['sitio_cero_agenda_canal_block_description']))
        : '';
    if ('' !== $block_description) {
        update_post_meta($post_id, 'sitio_cero_agenda_canal_block_description', $block_description);
    } else {
        delete_post_meta($post_id, 'sitio_cero_agenda_canal_block_description');
    }

    $channels_raw = isset($_POST['sitio_cero_agenda_canal_channels']) ? wp_unslash($_POST['sitio_cero_agenda_canal_channels']) : array();
    if (!is_array($channels_raw)) {
        $channels_raw = array();
    }
    $channels = sitio_cero_sanitize_agenda_channels_rows($channels_raw);
    if (!empty($channels)) {
        update_post_meta($post_id, 'sitio_cero_agenda_canal_channels', $channels);
    } else {
        delete_post_meta($post_id, 'sitio_cero_agenda_canal_channels');
    }

    $cta_label = isset($_POST['sitio_cero_agenda_canal_cta_label'])
        ? sanitize_text_field(wp_unslash($_POST['sitio_cero_agenda_canal_cta_label']))
        : '';
    if ('' !== $cta_label) {
        update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_label', $cta_label);
    } else {
        delete_post_meta($post_id, 'sitio_cero_agenda_canal_cta_label');
    }

    $cta_url = isset($_POST['sitio_cero_agenda_canal_cta_url'])
        ? esc_url_raw(wp_unslash($_POST['sitio_cero_agenda_canal_cta_url']))
        : '';
    if ('' !== $cta_url) {
        update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_url', $cta_url);
    } else {
        delete_post_meta($post_id, 'sitio_cero_agenda_canal_cta_url');
    }

    $cta_target = isset($_POST['sitio_cero_agenda_canal_cta_target'])
        ? sanitize_key(wp_unslash($_POST['sitio_cero_agenda_canal_cta_target']))
        : '_self';
    if ('_blank' !== $cta_target) {
        $cta_target = '_self';
    }
    update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_target', $cta_target);
}
add_action('save_post_agenda_canal', 'sitio_cero_save_agenda_canal_meta');

function sitio_cero_get_agenda_channels_block_data()
{
    $fallback = sitio_cero_get_agenda_channels_fallback_data();

    if (!post_type_exists('agenda_canal')) {
        return $fallback;
    }

    $query = new WP_Query(
        array(
            'post_type'      => 'agenda_canal',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => array(
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'sitio_cero_agenda_canal_active',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'sitio_cero_agenda_canal_active',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
            'no_found_rows'  => true,
        )
    );

    if (!$query->have_posts()) {
        return $fallback;
    }

    $query->the_post();
    $post_id = get_the_ID();

    $title = get_post_meta($post_id, 'sitio_cero_agenda_canal_block_title', true);
    $description = get_post_meta($post_id, 'sitio_cero_agenda_canal_block_description', true);
    $cta_label = get_post_meta($post_id, 'sitio_cero_agenda_canal_cta_label', true);
    $cta_url = get_post_meta($post_id, 'sitio_cero_agenda_canal_cta_url', true);
    $cta_target = get_post_meta($post_id, 'sitio_cero_agenda_canal_cta_target', true);
    $channels = get_post_meta($post_id, 'sitio_cero_agenda_canal_channels', true);
    wp_reset_postdata();

    if (!is_string($title) || '' === trim($title)) {
        $title = $fallback['title'];
    }
    if (!is_string($description)) {
        $description = '';
    }
    if (!is_string($cta_label) || '' === trim($cta_label)) {
        $cta_label = isset($fallback['cta']['label']) ? (string) $fallback['cta']['label'] : '';
    }
    if (!is_string($cta_url) || '' === trim($cta_url)) {
        $cta_url = isset($fallback['cta']['url']) ? (string) $fallback['cta']['url'] : '';
    }
    if (!is_string($cta_target) || ('_blank' !== $cta_target && '_self' !== $cta_target)) {
        $cta_target = '_self';
    }

    $channels = sitio_cero_sanitize_agenda_channels_rows($channels);
    $channels = array_values(
        array_filter(
            $channels,
            function ($channel) {
                return is_array($channel) && isset($channel['visible']) && !empty($channel['visible']);
            }
        )
    );
    if (empty($channels)) {
        $channels = $fallback['channels'];
    }

    return array(
        'title'       => $title,
        'description' => $description,
        'channels'    => $channels,
        'cta'         => array(
            'label'  => $cta_label,
            'url'    => $cta_url,
            'target' => $cta_target,
        ),
    );
}

function sitio_cero_seed_default_agenda_canal_content()
{
    if (!post_type_exists('agenda_canal')) {
        return;
    }

    $seed_version = '1';
    $already_seeded_version = (string) get_option('sitio_cero_default_agenda_canal_seeded_version', '');
    if ($seed_version === $already_seeded_version) {
        return;
    }

    $existing = get_posts(
        array(
            'post_type'      => 'agenda_canal',
            'post_status'    => array('publish', 'draft', 'pending', 'future', 'private'),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        )
    );

    if (!empty($existing)) {
        update_option('sitio_cero_default_agenda_canal_seeded_version', $seed_version);
        return;
    }

    $post_id = wp_insert_post(
        array(
            'post_type'   => 'agenda_canal',
            'post_status' => 'publish',
            'post_title'  => __('Canales de atención principal', 'sitio-cero'),
            'menu_order'  => 1,
        ),
        true
    );

    if (is_wp_error($post_id) || !$post_id) {
        return;
    }

    $post_id = (int) $post_id;

    $channels = array(
        array(
            'icon'        => 'home_pin',
            'title'       => __('Presencial', 'sitio-cero'),
            'description' => __('Lunes a viernes, 08:30 a 14:00 hrs. Atención en edificio consistorial.', 'sitio-cero'),
            'url'         => '',
            'target'      => '_self',
            'visible'     => true,
            'order'       => 10,
        ),
        array(
            'icon'        => 'call',
            'title'       => __('Telefónico', 'sitio-cero'),
            'description' => __('+56 2 3386 8000', 'sitio-cero'),
            'url'         => 'tel:+56233868000',
            'target'      => '_self',
            'visible'     => true,
            'order'       => 20,
        ),
        array(
            'icon'        => 'mail',
            'title'       => __('Correo', 'sitio-cero'),
            'description' => __('contacto@municipio.cl', 'sitio-cero'),
            'url'         => 'mailto:contacto@municipio.cl',
            'target'      => '_self',
            'visible'     => true,
            'order'       => 30,
        ),
        array(
            'icon'        => 'language',
            'title'       => __('Oficina virtual', 'sitio-cero'),
            'description' => __('Trámites, solicitudes y seguimiento en línea.', 'sitio-cero'),
            'url'         => home_url('/oficina-virtual/'),
            'target'      => '_self',
            'visible'     => true,
            'order'       => 40,
        ),
    );

    update_post_meta($post_id, '_sitio_cero_demo_agenda_canal', '1');
    update_post_meta($post_id, 'sitio_cero_agenda_canal_active', '1');
    update_post_meta($post_id, 'sitio_cero_agenda_canal_block_title', __('Canales para resolver tus consultas', 'sitio-cero'));
    update_post_meta($post_id, 'sitio_cero_agenda_canal_block_description', __('Elige el canal que más te acomode para recibir orientación municipal.', 'sitio-cero'));
    update_post_meta($post_id, 'sitio_cero_agenda_canal_channels', sitio_cero_sanitize_agenda_channels_rows($channels));
    update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_label', __('Ingresar a oficina virtual', 'sitio-cero'));
    update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_url', esc_url_raw(home_url('/oficina-virtual/')));
    update_post_meta($post_id, 'sitio_cero_agenda_canal_cta_target', '_self');

    update_option('sitio_cero_default_agenda_canal_seeded_version', $seed_version);
}
add_action('init', 'sitio_cero_seed_default_agenda_canal_content', 48);
