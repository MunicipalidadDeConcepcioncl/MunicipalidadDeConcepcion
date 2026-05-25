<?php
get_header();
?>

<main id="content" class="site-main dm-single">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <?php
        $post_id = get_the_ID();

        $subtitle = get_post_meta($post_id, 'sitio_cero_plan_regulador_subtitulo', true);
        $lead = get_post_meta($post_id, 'sitio_cero_plan_regulador_bajada', true);
        $cta_text = get_post_meta($post_id, 'sitio_cero_plan_regulador_cta_texto', true);
        $cta_url = get_post_meta($post_id, 'sitio_cero_plan_regulador_cta_url', true);

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

        $cta_url = esc_url($cta_url);
        $document_types = function_exists('sitio_cero_get_plan_regulador_document_types')
            ? sitio_cero_get_plan_regulador_document_types()
            : array();

        $groups = function_exists('sitio_cero_get_plan_regulador_document_groups')
            ? sitio_cero_get_plan_regulador_document_groups($post_id)
            : array();

        $sections = get_post_meta($post_id, 'sitio_cero_plan_regulador_sections', true);
        if (!is_array($sections)) {
            $sections = array();
        }

        $custom_html = get_post_meta($post_id, 'sitio_cero_plan_regulador_custom_html', true);
        if (!is_string($custom_html)) {
            $custom_html = '';
        }

        $allowed_html = function_exists('sitio_cero_get_direccion_allowed_html')
            ? sitio_cero_get_direccion_allowed_html()
            : wp_kses_allowed_html('post');

        $custom_html_output = '';
        if ('' !== trim($custom_html)) {
            $custom_html_output = do_shortcode(wp_kses($custom_html, $allowed_html));
        }

        $has_content = '' !== trim((string) get_the_content());
        $has_groups = !empty($groups);
        $has_sections = !empty($sections);
        $has_custom = '' !== trim($custom_html_output);

        $left_groups = array();
        $right_groups = array();

        if ($has_groups) {
            foreach ($groups as $group_index => $group) {
                if (!is_array($group)) {
                    continue;
                }

                $group_visual_column = isset($group['visual_column']) ? sanitize_key((string) $group['visual_column']) : 'right';
                if (!in_array($group_visual_column, array('left', 'right'), true)) {
                    $group_visual_column = 'right';
                }

                $group['_original_index'] = (int) $group_index;
                $group['_manual_order'] = isset($group['manual_order'])
                    ? absint($group['manual_order'])
                    : absint($group_index);

                if ('left' === $group_visual_column) {
                    $left_groups[] = $group;
                } else {
                    $right_groups[] = $group;
                }
            }

            $sort_groups = static function ($group_a, $group_b) {
                $order_a = isset($group_a['_manual_order']) ? absint($group_a['_manual_order']) : 0;
                $order_b = isset($group_b['_manual_order']) ? absint($group_b['_manual_order']) : 0;

                if ($order_a === $order_b) {
                    $index_a = isset($group_a['_original_index']) ? (int) $group_a['_original_index'] : 0;
                    $index_b = isset($group_b['_original_index']) ? (int) $group_b['_original_index'] : 0;
                    return $index_a <=> $index_b;
                }

                return $order_a <=> $order_b;
            };

            usort($left_groups, $sort_groups);
            usort($right_groups, $sort_groups);
        }

        $render_document_groups = static function ($column_groups, $document_types) {
            if (empty($column_groups)) {
                echo '<p>' . esc_html__('No hay grupos documentales en esta columna.', 'sitio-cero') . '</p>';
                return;
            }

            foreach ($column_groups as $group_index => $group) {
                if (!is_array($group)) {
                    continue;
                }

                $group_title = isset($group['title']) ? sanitize_text_field((string) $group['title']) : '';
                $group_description = isset($group['description']) ? sanitize_textarea_field((string) $group['description']) : '';
                $group_documents = isset($group['documents']) && is_array($group['documents']) ? $group['documents'] : array();

                if ('' === $group_title && empty($group_documents)) {
                    continue;
                }
                ?>
                <details class="prc-document-group">
                    <summary class="prc-document-group__toggle">
                        <span class="prc-document-group__title"><?php echo esc_html($group_title); ?></span>
                    </summary>
                    <div class="pc-accordion__body prc-document-group__body">
                        <?php if ('' !== trim($group_description)) : ?>
                            <p><?php echo esc_html($group_description); ?></p>
                        <?php endif; ?>

                        <?php if (!empty($group_documents)) : ?>
                            <div class="prc-document-links">
                                <?php foreach ($group_documents as $document) : ?>
                                    <?php
                                    if (!is_array($document)) {
                                        continue;
                                    }

                                    $document_title = isset($document['title']) ? sanitize_text_field((string) $document['title']) : '';
                                    $document_url = isset($document['url']) ? esc_url((string) $document['url']) : '';
                                    $document_type = isset($document['type']) ? sanitize_key((string) $document['type']) : '';
                                    $document_date = isset($document['date']) ? sanitize_text_field((string) $document['date']) : '';
                                    $document_label = isset($document['label']) ? sanitize_text_field((string) $document['label']) : '';

                                    if ('' === $document_title && '' === $document_url) {
                                        continue;
                                    }

                                    $type_label = isset($document_types[$document_type]) ? (string) $document_types[$document_type] : '';
                                    $display_title = '' !== trim($document_title) ? $document_title : __('Documento', 'sitio-cero');
                                    ?>
                                    <?php if ('' !== trim($document_url)) : ?>
                                        <a class="pc-card prc-document-link" href="<?php echo esc_url($document_url); ?>" target="_blank" rel="noopener">
                                            <span class="material-symbols-rounded" aria-hidden="true">description</span>
                                            <span class="prc-document-link__content">
                                                <span class="prc-document-link__title"><?php echo esc_html($display_title); ?></span>
                                                <span class="prc-document-link__meta">
                                                    <?php if ('' !== trim($type_label)) : ?>
                                                        <span class="prc-badge prc-badge--type"><?php echo esc_html($type_label); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ('' !== trim($document_date)) : ?>
                                                        <span class="prc-badge prc-badge--date"><?php echo esc_html($document_date); ?></span>
                                                    <?php endif; ?>
                                                    <?php if ('' !== trim($document_label)) : ?>
                                                        <span class="prc-badge prc-badge--label"><?php echo esc_html($document_label); ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </span>
                                        </a>
                                    <?php else : ?>
                                        <div class="pc-card prc-document-link prc-document-link--pending">
                                            <span class="material-symbols-rounded" aria-hidden="true">schedule</span>
                                            <span class="prc-document-link__content">
                                                <span class="prc-document-link__title"><?php echo esc_html($display_title); ?></span>
                                                <span class="prc-badge prc-badge--pending"><?php esc_html_e('URL pendiente', 'sitio-cero'); ?></span>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
                <?php
            }
        };
        ?>

        <article id="plan-regulador-<?php the_ID(); ?>" <?php post_class('dm-page pc-page plan-regulador-page'); ?>>
            <section class="pc-hero">
                <div class="pc-hero__inner dm-hero prc-hero">
                    <p class="pc-hero__eyebrow"><?php esc_html_e('Plan Regulador Comunal', 'sitio-cero'); ?></p>
                    <h1 class="pc-hero__title"><?php the_title(); ?></h1>
                    <?php if ('' !== trim($subtitle)) : ?>
                        <p class="pc-hero__lead prc-hero__subtitle"><?php echo esc_html($subtitle); ?></p>
                    <?php endif; ?>
                    <?php if ('' !== trim($lead)) : ?>
                        <p class="pc-hero__lead"><?php echo esc_html($lead); ?></p>
                    <?php endif; ?>
                    <?php if ('' !== trim($cta_text) && '' !== trim($cta_url)) : ?>
                        <div class="pc-hero__actions">
                            <a class="pc-card" href="<?php echo esc_url($cta_url); ?>" target="_blank" rel="noopener"><?php echo esc_html($cta_text); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($has_groups) : ?>
                <section id="documentos" class="pc-section pc-section--soft dm-section prc-documents">
                    <div class="pc-section__inner">
                        <header class="pc-section__header dm-section__header">
                            <p class="pc-kicker dm-eyebrow"><?php esc_html_e('Plan Regulador Comunal', 'sitio-cero'); ?></p>
                            <h2><?php esc_html_e('Instrumentos de Planificación Territorial', 'sitio-cero'); ?></h2>
                            <p><?php esc_html_e('Consulta promulgaciones, modificaciones, enmiendas y planos de detalle del Plan Regulador Comunal de Concepción.', 'sitio-cero'); ?></p>
                        </header>
                        <div class="prc-documents-layout">
                            <section class="prc-documents-column prc-documents-column--left">
                                <div class="pc-accordion prc-document-groups" aria-label="<?php esc_attr_e('Grupos documentales columna izquierda', 'sitio-cero'); ?>">
                                    <?php $render_document_groups($left_groups, $document_types); ?>
                                </div>
                            </section>

                            <section class="prc-documents-column prc-documents-column--right">
                                <div class="pc-accordion prc-document-groups" aria-label="<?php esc_attr_e('Grupos documentales columna derecha', 'sitio-cero'); ?>">
                                    <?php $render_document_groups($right_groups, $document_types); ?>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($has_sections) : ?>
                <?php foreach ($sections as $section_index => $section) : ?>
                    <?php
                    if (!is_array($section)) {
                        continue;
                    }

                    $section_title = isset($section['title']) ? sanitize_text_field((string) $section['title']) : '';
                    $section_content = isset($section['content']) ? (string) $section['content'] : '';
                    if ('' === $section_title && '' === trim($section_content)) {
                        continue;
                    }

                    $section_id = 'seccion-libre-' . ($section_index + 1);
                    $section_output = do_shortcode(wp_kses($section_content, $allowed_html));
                    ?>
                    <section id="<?php echo esc_attr($section_id); ?>" class="pc-section pc-section--paper">
                        <div class="pc-section__inner">
                            <?php if ('' !== trim($section_title)) : ?>
                                <header class="pc-section__header">
                                    <h2><?php echo esc_html($section_title); ?></h2>
                                </header>
                            <?php endif; ?>
                            <?php if ('' !== trim($section_output)) : ?>
                                <div class="pc-flow content-body dm-content">
                                    <?php echo $section_output; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if ($has_custom) : ?>
                <section id="extra" class="pc-section">
                    <div class="pc-section__inner">
                        <header class="pc-section__header">
                            <p class="pc-kicker"><?php esc_html_e('Complemento', 'sitio-cero'); ?></p>
                            <h2><?php esc_html_e('Información adicional', 'sitio-cero'); ?></h2>
                        </header>
                        <div class="pc-flow content-body dm-content">
                            <?php echo $custom_html_output; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
        </article>
    <?php endwhile; else : ?>
        <?php get_template_part('template-parts/content', 'none'); ?>
    <?php endif; ?>
</main>

<?php
get_footer();
