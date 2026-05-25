<?php
get_header();

$search_term = trim((string) get_search_query(false));
$search_normalized = remove_accents($search_term);
if (function_exists('mb_strtolower')) {
    $search_normalized = mb_strtolower($search_normalized, 'UTF-8');
} else {
    $search_normalized = strtolower($search_normalized);
}
$search_normalized = trim((string) $search_normalized);

$raw_tokens = preg_split('/\s+/u', $search_normalized);
if (!is_array($raw_tokens)) {
    $raw_tokens = array();
}

$search_tokens = array();
$blocked_search_tokens = array(
    'wp-admin',
    'wp-content',
    'wp-includes',
    'wp-json',
    'wp-login',
    'xmlrpc',
    'wordpress',
    'wp',
);
foreach ($raw_tokens as $token) {
    $token = trim((string) $token);
    $token = preg_replace('/[^a-z0-9_-]/i', '', $token);
    if (!is_string($token)) {
        $token = '';
    }
    if ('' === $token) {
        continue;
    }

    if (in_array($token, $blocked_search_tokens, true)) {
        continue;
    }

    if (!in_array($token, $search_tokens, true)) {
        $search_tokens[] = $token;
    }
}

$sanitized_search_term = trim(
    implode(
        ' ',
        array_filter(
            array_map(
                static function ($token) {
                    return is_string($token) ? trim($token) : '';
                },
                $search_tokens
            )
        )
    )
);
$search_normalized_clean = $sanitized_search_term;

$search_post_types = array_values(
    array_filter(
        array('post', 'page', 'noticia', 'aviso', 'direccion_municipal', 'evento_municipal', 'municipalidad', 'plan_regulador'),
        static function ($post_type) {
            return post_type_exists($post_type);
        }
    )
);

$search_scores = array();
$add_search_score = static function ($post_ids, $score) use (&$search_scores) {
    if (!is_array($post_ids)) {
        return;
    }

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        if (!isset($search_scores[$post_id])) {
            $search_scores[$post_id] = 0;
        }

        $search_scores[$post_id] += (int) $score;
    }
};

if ('' !== $sanitized_search_term && !empty($search_post_types)) {
    $exact_ids = get_posts(
        array(
            'post_type'        => $search_post_types,
            'post_status'      => 'publish',
            's'                => $sanitized_search_term,
            'posts_per_page'   => 120,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
        )
    );
    $add_search_score($exact_ids, 220);

    foreach ($search_tokens as $token) {
        if ('' === $token) {
            continue;
        }

        $token_ids = get_posts(
            array(
                'post_type'        => $search_post_types,
                'post_status'      => 'publish',
                's'                => $token,
                'posts_per_page'   => 90,
                'fields'           => 'ids',
                'no_found_rows'    => true,
                'suppress_filters' => false,
            )
        );

        $token_score = 65 + min((int) strlen($token) * 3, 24);
        $add_search_score($token_ids, $token_score);
    }
}

if (!empty($search_scores)) {
    arsort($search_scores, SORT_NUMERIC);
}

$matched_post_ids = array_map('intval', array_keys($search_scores));
$total_internal_results = count($matched_post_ids);
$per_page = (int) get_option('posts_per_page', 10);
if ($per_page <= 0) {
    $per_page = 10;
}
$paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
$total_pages = max(1, (int) ceil(max($total_internal_results, 1) / $per_page));
if ($paged > $total_pages) {
    $paged = $total_pages;
}
$offset = max(0, ($paged - 1) * $per_page);
$page_post_ids = array_slice($matched_post_ids, $offset, $per_page);

$results_query = null;
if (!empty($page_post_ids)) {
    $results_query = new WP_Query(
        array(
            'post_type'           => $search_post_types,
            'post_status'         => 'publish',
            'post__in'            => $page_post_ids,
            'orderby'             => 'post__in',
            'posts_per_page'      => count($page_post_ids),
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true,
        )
    );
}

$site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
if (!is_string($site_host) || '' === trim($site_host)) {
    $site_host = '';
}

$google_base_query = '' !== $sanitized_search_term ? $sanitized_search_term : $search_term;
if ($site_host !== '') {
    $google_base_query .= ' site:' . $site_host;
}

$google_maps_query = '' !== $sanitized_search_term ? $sanitized_search_term : $search_term;
$google_web_url = 'https://www.google.com/search?q=' . rawurlencode($google_base_query);
$google_news_url = 'https://www.google.com/search?tbm=nws&q=' . rawurlencode($google_base_query);
$google_images_url = 'https://www.google.com/search?tbm=isch&q=' . rawurlencode($google_base_query);
$google_maps_url = 'https://www.google.com/maps/search/' . rawurlencode($google_maps_query);
$related_news_categories = array();
$related_taxonomies = array();

if ('' !== $sanitized_search_term) {
    $public_taxonomies = get_taxonomies(array('public' => true), 'objects');
    if (is_array($public_taxonomies)) {
        foreach ($public_taxonomies as $taxonomy => $taxonomy_object) {
            if (!is_string($taxonomy) || '' === $taxonomy || !($taxonomy_object instanceof WP_Taxonomy)) {
                continue;
            }

            $terms_lookup_values = array($sanitized_search_term);
            foreach ($search_tokens as $token) {
                if (strlen($token) < 2) {
                    continue;
                }
                $terms_lookup_values[] = $token;
            }
            $terms_lookup_values = array_values(array_unique(array_filter(array_map('trim', $terms_lookup_values))));

            $taxonomy_terms_map = array();
            foreach ($terms_lookup_values as $lookup_value) {
                if ('' === $lookup_value) {
                    continue;
                }

                $terms = get_terms(
                    array(
                        'taxonomy'   => $taxonomy,
                        'hide_empty' => false,
                        'number'     => 18,
                        'name__like' => $lookup_value,
                    )
                );

                if (is_wp_error($terms) || !is_array($terms)) {
                    continue;
                }

                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term) {
                        continue;
                    }
                    $taxonomy_terms_map[(string) $term->term_id] = $term;
                }
            }

            foreach ($taxonomy_terms_map as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $term_name_normalized = remove_accents((string) $term->name);
                $term_slug_normalized = remove_accents((string) $term->slug);
                if (function_exists('mb_strtolower')) {
                    $term_name_normalized = mb_strtolower($term_name_normalized, 'UTF-8');
                    $term_slug_normalized = mb_strtolower($term_slug_normalized, 'UTF-8');
                } else {
                    $term_name_normalized = strtolower($term_name_normalized);
                    $term_slug_normalized = strtolower($term_slug_normalized);
                }

                $score = 0;
                if ('' !== $search_normalized_clean && false !== strpos($term_name_normalized, $search_normalized_clean)) {
                    $score += 140;
                }
                if ('' !== $search_normalized_clean && false !== strpos($term_slug_normalized, $search_normalized_clean)) {
                    $score += 130;
                }

                foreach ($search_tokens as $token) {
                    if ('' === $token) {
                        continue;
                    }

                    if (false !== strpos($term_name_normalized, $token)) {
                        $score += 35;
                    } elseif (false !== strpos($term_slug_normalized, $token)) {
                        $score += 30;
                    }
                }

                if (false !== strpos($search_normalized_clean, 'noticia') && 'categoria_noticia' === $taxonomy && 'noticias' === $term->slug) {
                    $score += 210;
                }

                if ($score <= 0) {
                    continue;
                }

                $term_link = get_term_link($term);
                if (is_wp_error($term_link)) {
                    continue;
                }

                $term_key = $taxonomy . '|' . (string) $term->term_id;
                $taxonomy_label = isset($taxonomy_object->labels->singular_name)
                    ? (string) $taxonomy_object->labels->singular_name
                    : (string) $taxonomy_object->label;

                $candidate = array(
                    'name'           => (string) $term->name,
                    'url'            => (string) $term_link,
                    'count'          => (int) $term->count,
                    'score'          => (int) $score,
                    'taxonomy_label' => $taxonomy_label,
                    'taxonomy'       => $taxonomy,
                );

                if (!isset($related_taxonomies[$term_key]) || $candidate['score'] > (int) $related_taxonomies[$term_key]['score']) {
                    $related_taxonomies[$term_key] = $candidate;
                }
            }
        }
    }
}

if (!empty($related_taxonomies)) {
    $related_news_categories = array_values($related_taxonomies);
    usort(
        $related_news_categories,
        static function ($left, $right) {
            $left_score = isset($left['score']) ? (int) $left['score'] : 0;
            $right_score = isset($right['score']) ? (int) $right['score'] : 0;

            if ($left_score === $right_score) {
                $left_name = isset($left['name']) ? (string) $left['name'] : '';
                $right_name = isset($right['name']) ? (string) $right['name'] : '';
                return strcmp($left_name, $right_name);
            }

            return $right_score <=> $left_score;
        }
    );

    $related_news_categories = array_slice($related_news_categories, 0, 10);
}
?>

<main id="content" class="site-main section container search-results-page">
    <header class="section__header search-results-page__header">
        <h1><?php esc_html_e('Resultados de búsqueda', 'sitio-cero'); ?></h1>
        <?php if ('' !== $search_term) : ?>
            <p class="search-results-page__query">
                <?php
                printf(
                    /* translators: %s: search query. */
                    esc_html__('Consulta: "%s"', 'sitio-cero'),
                    esc_html($search_term)
                );
                ?>
            </p>
        <?php endif; ?>
    </header>

    <div class="search-results-page__layout">
        <section class="search-results-page__internal">
            <h2><?php esc_html_e('Resultados en el sitio', 'sitio-cero'); ?></h2>

            <?php if (!empty($related_news_categories)) : ?>
                <div class="search-results-page__taxonomy">
                    <h3><?php esc_html_e('Categorías relacionadas', 'sitio-cero'); ?></h3>
                    <ul class="search-results-page__taxonomy-list">
                        <?php foreach ($related_news_categories as $related_category) : ?>
                            <li class="search-results-page__taxonomy-item">
                                <a class="search-results-page__taxonomy-link" href="<?php echo esc_url((string) $related_category['url']); ?>">
                                    <span class="search-results-page__taxonomy-name"><?php echo esc_html((string) $related_category['name']); ?></span>
                                    <?php if (!empty($related_category['taxonomy_label'])) : ?>
                                        <span class="search-results-page__taxonomy-type"><?php echo esc_html((string) $related_category['taxonomy_label']); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($results_query instanceof WP_Query && $results_query->have_posts()) : ?>
                <div class="posts-grid search-results-page__grid">
                    <?php while ($results_query->have_posts()) : $results_query->the_post(); ?>
                        <?php $result_permalink = get_permalink(); ?>
                        <article <?php post_class('post-card news-card'); ?>>
                            <a class="news-card__media" href="<?php echo esc_url($result_permalink); ?>">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('medium_large', array('class' => 'news-card__image', 'loading' => 'lazy')); ?>
                                <?php else : ?>
                                    <span class="news-card__image news-card__image--placeholder"><?php esc_html_e('Sin imagen', 'sitio-cero'); ?></span>
                                <?php endif; ?>
                            </a>
                            <div class="news-card__body">
                                <p class="news-card__meta">
                                    <?php
                                    $post_type_object = get_post_type_object(get_post_type());
                                    echo esc_html($post_type_object instanceof WP_Post_Type ? $post_type_object->labels->singular_name : __('Contenido', 'sitio-cero'));
                                    ?>
                                </p>
                                <h3 class="news-card__title"><a href="<?php echo esc_url($result_permalink); ?>"><?php the_title(); ?></a></h3>
                                <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 20)); ?></p>
                                <a class="news-card__link post-card__link" href="<?php echo esc_url($result_permalink); ?>"><?php esc_html_e('Ver resultado', 'sitio-cero'); ?></a>
                            </div>
                        </article>
                    <?php endwhile; ?>
                </div>

                <div class="search-results-page__pagination">
                    <?php
                    $pagination = paginate_links(
                        array(
                            'base'      => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
                            'format'    => '?paged=%#%',
                            'current'   => $paged,
                            'total'     => max(1, $total_pages),
                            'mid_size'  => 1,
                            'prev_text' => __('Anterior', 'sitio-cero'),
                            'next_text' => __('Siguiente', 'sitio-cero'),
                            'type'      => 'list',
                            'add_args'  => array(
                                's'              => $search_term,
                                'mostrar_google' => isset($_GET['mostrar_google']) ? sanitize_text_field(wp_unslash($_GET['mostrar_google'])) : '',
                            ),
                        )
                    );
                    if (is_string($pagination) && '' !== trim($pagination)) {
                        echo wp_kses_post($pagination);
                    }
                    ?>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <div class="search-results-page__empty">
                    <p><?php esc_html_e('No encontramos resultados internos para esta búsqueda.', 'sitio-cero'); ?></p>
                </div>
            <?php endif; ?>
        </section>

        <aside class="search-results-page__google">
            <h2><?php esc_html_e('Resultados en Google', 'sitio-cero'); ?></h2>
            <p><?php esc_html_e('Puedes complementar la búsqueda con Google para encontrar más referencias relacionadas.', 'sitio-cero'); ?></p>

            <a class="button search-results-page__google-main-link" href="<?php echo esc_url($google_web_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Abrir resultados web en Google', 'sitio-cero'); ?>
            </a>

            <ul class="search-results-page__google-links">
                <li><a href="<?php echo esc_url($google_news_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver noticias en Google', 'sitio-cero'); ?></a></li>
                <li><a href="<?php echo esc_url($google_images_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver imágenes en Google', 'sitio-cero'); ?></a></li>
                <li><a href="<?php echo esc_url($google_maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver ubicaciones en Google Maps', 'sitio-cero'); ?></a></li>
            </ul>
        </aside>
    </div>
</main>

<?php
get_footer();
