<?php
get_header();
?>

<main id="content" class="site-main section container content-single">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <?php
        $is_noticia = 'noticia' === get_post_type();
        $is_aviso = 'aviso' === get_post_type();
        $is_evento = 'evento_municipal' === get_post_type();
        $has_media = has_post_thumbnail();
        $gallery_items = array();
        $aviso_image_url = '';
        $aviso_parrafos = '';
        $aviso_documentos = array();
        $aviso_archivos = array();
        $evento_fecha = '';
        $evento_hora = '';
        $evento_lugar = '';
        $evento_mapa_url = '';
        $evento_mapa_embed_url = '';

        if ($is_noticia && function_exists('sitio_cero_get_noticia_gallery_ids')) {
            $gallery_ids = sitio_cero_get_noticia_gallery_ids(get_the_ID());

            foreach ($gallery_ids as $gallery_id) {
                $image_large = wp_get_attachment_image_src($gallery_id, 'large');
                if (!is_array($image_large) || empty($image_large[0])) {
                    continue;
                }

                $image_full = wp_get_attachment_image_src($gallery_id, 'full');
                $alt_text = get_post_meta($gallery_id, '_wp_attachment_image_alt', true);

                if (!is_string($alt_text) || '' === trim($alt_text)) {
                    $alt_text = get_the_title($gallery_id);
                }

                if (!is_string($alt_text)) {
                    $alt_text = '';
                }

                $gallery_items[] = array(
                    'id'    => (int) $gallery_id,
                    'large' => $image_large[0],
                    'full'  => (is_array($image_full) && !empty($image_full[0])) ? $image_full[0] : $image_large[0],
                    'alt'   => $alt_text,
                );
            }
        }

        if ($is_aviso) {
            if (function_exists('sitio_cero_get_aviso_image_url')) {
                $aviso_image_url = sitio_cero_get_aviso_image_url(get_the_ID(), 'large');
            }

            $aviso_parrafos = get_post_meta(get_the_ID(), 'sitio_cero_aviso_parrafos', true);
            if (!is_string($aviso_parrafos)) {
                $aviso_parrafos = '';
            }

            if ('' === trim($aviso_parrafos)) {
                $raw_content = get_the_content(null, false, get_the_ID());
                if (is_string($raw_content)) {
                    $aviso_parrafos = trim((string) apply_filters('the_content', $raw_content));
                }
            } else {
                $aviso_parrafos = wpautop(wp_kses_post($aviso_parrafos));
            }

            if (function_exists('sitio_cero_get_aviso_links')) {
                $aviso_documentos = sitio_cero_get_aviso_links(get_the_ID(), 'sitio_cero_aviso_documentos');
                $aviso_archivos = sitio_cero_get_aviso_links(get_the_ID(), 'sitio_cero_aviso_archivos');
            }
        }

        if ($is_evento) {
            $evento_fecha = function_exists('sitio_cero_get_evento_full_fecha')
                ? sitio_cero_get_evento_full_fecha(get_the_ID())
                : get_the_date('d M Y');
            $evento_hora = function_exists('sitio_cero_get_evento_hora')
                ? sitio_cero_get_evento_hora(get_the_ID())
                : '';
            $evento_lugar = get_post_meta(get_the_ID(), 'sitio_cero_evento_lugar', true);
            $evento_mapa_url = get_post_meta(get_the_ID(), 'sitio_cero_evento_mapa_url', true);

            if (!is_string($evento_fecha)) {
                $evento_fecha = '';
            }
            if (!is_string($evento_hora)) {
                $evento_hora = '';
            }
            if (!is_string($evento_lugar)) {
                $evento_lugar = '';
            }
            if (!is_string($evento_mapa_url)) {
                $evento_mapa_url = '';
            }

            if (function_exists('sitio_cero_get_evento_mapa_embed_url')) {
                $evento_mapa_embed_url = sitio_cero_get_evento_mapa_embed_url($evento_mapa_url, $evento_lugar);
            } else {
                $evento_mapa_embed_url = '';
            }

            if (!is_string($evento_mapa_embed_url)) {
                $evento_mapa_embed_url = '';
            }
        }
        ?>
        <article <?php post_class(); ?>>
            <header class="section__header">
                <h1><?php the_title(); ?></h1>
                <?php if ($is_noticia) : ?>
                    <p class="meta"><?php echo esc_html(get_the_date()); ?></p>
                <?php elseif ($is_evento) : ?>
                    <?php if ('' !== trim($evento_fecha)) : ?>
                        <p class="meta"><strong><?php esc_html_e('Fecha:', 'sitio-cero'); ?></strong> <?php echo esc_html($evento_fecha); ?></p>
                    <?php endif; ?>
                    <?php if ('' !== trim($evento_hora)) : ?>
                        <p class="meta"><strong><?php esc_html_e('Hora:', 'sitio-cero'); ?></strong> <?php echo esc_html($evento_hora); ?> hrs</p>
                    <?php endif; ?>
                    <?php if ('' !== trim($evento_lugar)) : ?>
                        <p class="meta"><strong><?php esc_html_e('Lugar:', 'sitio-cero'); ?></strong> <?php echo esc_html($evento_lugar); ?></p>
                    <?php endif; ?>
                    <?php if ('' !== trim($evento_mapa_url)) : ?>
                        <p class="meta"><a href="<?php echo esc_url($evento_mapa_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Ver en Google Maps', 'sitio-cero'); ?></a></p>
                    <?php endif; ?>
                <?php endif; ?>
            </header>

            <?php if ($is_noticia) : ?>
	                <div class="content-body single-noticia__content single-noticia__content--with-media">
	                    <?php if ($has_media) : ?>
	                        <figure class="single-noticia__media-inline">
	                            <?php the_post_thumbnail('large', array('class' => 'single-noticia__image', 'loading' => 'eager')); ?>
	                        </figure>
	                    <?php endif; ?>
	                    <?php the_content(); ?>
	                </div>

                <?php if (!empty($gallery_items)) : ?>
                    <?php $gallery_total = count($gallery_items); ?>
                    <section class="single-noticia-gallery" data-news-gallery>
                        <div class="single-noticia-gallery__header">
                            <h2><?php esc_html_e('Galería de imágenes', 'sitio-cero'); ?></h2>
                        </div>

                        <div class="single-noticia-gallery__stage">
                            <?php if ($gallery_total > 1) : ?>
                                <button type="button" class="single-noticia-gallery__arrow single-noticia-gallery__arrow--prev" data-news-gallery-prev aria-label="<?php esc_attr_e('Imagen anterior', 'sitio-cero'); ?>">
                                    <span aria-hidden="true">&#10094;</span>
                                </button>
                            <?php endif; ?>

                            <div class="single-noticia-gallery__slides">
                                <?php foreach ($gallery_items as $index => $item) : ?>
                                    <figure
                                        class="single-noticia-gallery__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                        data-news-gallery-slide
                                        data-index="<?php echo esc_attr((string) $index); ?>"
                                        aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>"
                                    >
                                        <button type="button" class="single-noticia-gallery__zoom" data-news-gallery-open aria-label="<?php esc_attr_e('Abrir imagen en grande', 'sitio-cero'); ?>">
                                            <img
                                                class="single-noticia-gallery__image"
                                                src="<?php echo esc_url($item['large']); ?>"
                                                data-full-src="<?php echo esc_url($item['full']); ?>"
                                                alt="<?php echo esc_attr($item['alt']); ?>"
                                                loading="<?php echo 0 === $index ? 'eager' : 'lazy'; ?>"
                                            >
                                        </button>
                                    </figure>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($gallery_total > 1) : ?>
                                <button type="button" class="single-noticia-gallery__arrow single-noticia-gallery__arrow--next" data-news-gallery-next aria-label="<?php esc_attr_e('Imagen siguiente', 'sitio-cero'); ?>">
                                    <span aria-hidden="true">&#10095;</span>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($gallery_total > 1) : ?>
                            <div class="single-noticia-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e('Miniaturas de la galería', 'sitio-cero'); ?>">
                                <?php foreach ($gallery_items as $index => $item) : ?>
                                    <button
                                        type="button"
                                        class="single-noticia-gallery__thumb<?php echo 0 === $index ? ' is-active' : ''; ?>"
                                        data-news-gallery-thumb
                                        data-index="<?php echo esc_attr((string) $index); ?>"
                                        role="tab"
                                        aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
                                        aria-label="<?php echo esc_attr(sprintf(__('Ver imagen %d', 'sitio-cero'), $index + 1)); ?>"
                                    >
                                        <img src="<?php echo esc_url($item['large']); ?>" alt="<?php echo esc_attr($item['alt']); ?>" loading="lazy">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="single-noticia-gallery__lightbox" data-news-gallery-lightbox hidden aria-hidden="true">
                            <button type="button" class="single-noticia-gallery__backdrop" data-news-gallery-close aria-label="<?php esc_attr_e('Cerrar galería', 'sitio-cero'); ?>"></button>
                            <div class="single-noticia-gallery__lightbox-dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Galería de imágenes', 'sitio-cero'); ?>">
                                <button type="button" class="single-noticia-gallery__lightbox-close" data-news-gallery-close aria-label="<?php esc_attr_e('Cerrar', 'sitio-cero'); ?>">&times;</button>
                                <?php if ($gallery_total > 1) : ?>
                                    <button type="button" class="single-noticia-gallery__lightbox-arrow single-noticia-gallery__lightbox-arrow--prev" data-news-gallery-prev aria-label="<?php esc_attr_e('Imagen anterior', 'sitio-cero'); ?>">
                                        <span aria-hidden="true">&#10094;</span>
                                    </button>
                                    <button type="button" class="single-noticia-gallery__lightbox-arrow single-noticia-gallery__lightbox-arrow--next" data-news-gallery-next aria-label="<?php esc_attr_e('Imagen siguiente', 'sitio-cero'); ?>">
                                        <span aria-hidden="true">&#10095;</span>
                                    </button>
                                <?php endif; ?>
                                <img class="single-noticia-gallery__lightbox-image" data-news-gallery-lightbox-image src="<?php echo esc_url($gallery_items[0]['full']); ?>" alt="<?php echo esc_attr($gallery_items[0]['alt']); ?>">
                            </div>
                        </div>
                    </section>
                <?php endif; ?>
            <?php elseif ($is_aviso) : ?>
                <div class="content-body aviso-single-layout">
                    <div class="aviso-single-layout__left">
                        <?php if ('' !== trim((string) $aviso_parrafos)) : ?>
                            <section class="aviso-single-block aviso-single-block--text">
                                <div class="aviso-single-block__content">
                                    <?php echo wp_kses_post($aviso_parrafos); ?>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if (!empty($aviso_documentos)) : ?>
                            <section class="aviso-single-block">
                                <h2><?php esc_html_e('Documentos', 'sitio-cero'); ?></h2>
                                <ul class="aviso-single-list">
                                    <?php foreach ($aviso_documentos as $item) : ?>
                                        <?php if (!is_array($item) || empty($item['url'])) {
                                            continue;
                                        } ?>
                                        <?php
                                        $icon_key = isset($item['icon']) ? (string) $item['icon'] : '';
                                        if ('' === $icon_key && function_exists('sitio_cero_detect_aviso_file_icon_by_url')) {
                                            $icon_key = sitio_cero_detect_aviso_file_icon_by_url((string) $item['url']);
                                        }
                                        $icon_key = is_string($icon_key) ? $icon_key : 'file';
                                        $icon_symbol = function_exists('sitio_cero_get_aviso_file_icon_symbol')
                                            ? sitio_cero_get_aviso_file_icon_symbol($icon_key)
                                            : 'attach_file';
                                        ?>
                                        <li>
                                            <a class="aviso-single-file-btn aviso-single-file-btn--<?php echo esc_attr($icon_key); ?>" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <span class="material-symbols-rounded aviso-single-file-btn__icon" aria-hidden="true"><?php echo esc_html($icon_symbol); ?></span>
                                                <span class="aviso-single-file-btn__text"><?php echo esc_html(isset($item['label']) ? (string) $item['label'] : 'Documento'); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>

                        <?php if (!empty($aviso_archivos)) : ?>
                            <section class="aviso-single-block">
                                <h2><?php esc_html_e('Archivos', 'sitio-cero'); ?></h2>
                                <ul class="aviso-single-list">
                                    <?php foreach ($aviso_archivos as $item) : ?>
                                        <?php if (!is_array($item) || empty($item['url'])) {
                                            continue;
                                        } ?>
                                        <?php
                                        $icon_key = isset($item['icon']) ? (string) $item['icon'] : '';
                                        if ('' === $icon_key && function_exists('sitio_cero_detect_aviso_file_icon_by_url')) {
                                            $icon_key = sitio_cero_detect_aviso_file_icon_by_url((string) $item['url']);
                                        }
                                        $icon_key = is_string($icon_key) ? $icon_key : 'file';
                                        $icon_symbol = function_exists('sitio_cero_get_aviso_file_icon_symbol')
                                            ? sitio_cero_get_aviso_file_icon_symbol($icon_key)
                                            : 'attach_file';
                                        ?>
                                        <li>
                                            <a class="aviso-single-file-btn aviso-single-file-btn--<?php echo esc_attr($icon_key); ?>" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer">
                                                <span class="material-symbols-rounded aviso-single-file-btn__icon" aria-hidden="true"><?php echo esc_html($icon_symbol); ?></span>
                                                <span class="aviso-single-file-btn__text"><?php echo esc_html(isset($item['label']) ? (string) $item['label'] : 'Archivo'); ?></span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        <?php endif; ?>
                    </div>

                    <aside class="aviso-single-layout__right">
                        <?php if ('' !== trim($aviso_image_url)) : ?>
                            <figure class="aviso-single-media">
                                <img src="<?php echo esc_url($aviso_image_url); ?>" alt="<?php the_title_attribute(); ?>" class="aviso-single-media__image" loading="eager">
                            </figure>
                        <?php elseif ($has_media) : ?>
                            <figure class="aviso-single-media">
                                <?php the_post_thumbnail('large', array('class' => 'aviso-single-media__image', 'loading' => 'eager')); ?>
                            </figure>
                        <?php endif; ?>
                    </aside>
                </div>
            <?php elseif ($is_evento) : ?>
                <div class="content-body evento-single__content">
                    <?php the_content(); ?>
                </div>

                <?php if ('' !== trim($evento_mapa_embed_url)) : ?>
                    <section class="evento-single-map">
                        <h2><?php esc_html_e('Ubicación del evento', 'sitio-cero'); ?></h2>
                        <div class="evento-single-map__frame-wrap">
                            <iframe
                                class="evento-single-map__frame"
                                src="<?php echo esc_url($evento_mapa_embed_url); ?>"
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade"
                                allowfullscreen
                                title="<?php echo esc_attr(sprintf(__('Mapa de %s', 'sitio-cero'), get_the_title())); ?>"
                            ></iframe>
                        </div>
                    </section>
                <?php endif; ?>
            <?php else : ?>
                <div class="content-body">
                    <?php the_content(); ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endwhile; endif; ?>
</main>

<?php
get_footer();
