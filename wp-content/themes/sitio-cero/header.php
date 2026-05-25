<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#content"><?php esc_html_e('Saltar al contenido', 'sitio-cero'); ?></a>

<header class="site-topbar">
    <div class="container topbar__inner">
        <?php
        $topbar_items = function_exists('sitio_cero_get_topbar_items') ? sitio_cero_get_topbar_items() : array();
        $topbar_left_items = array();
        $topbar_right_items = array();

        if (!empty($topbar_items)) {
            foreach ($topbar_items as $topbar_item) {
                if (!is_array($topbar_item)) {
                    continue;
                }

                $item_type = isset($topbar_item['type']) ? (string) $topbar_item['type'] : 'info';
                if ('info' === $item_type) {
                    $topbar_left_items[] = $topbar_item;
                    continue;
                }

                $topbar_right_items[] = $topbar_item;
            }
        }
        ?>

        <?php if (empty($topbar_items)) : ?>
            <p class="topbar__item"><strong>Fono central:</strong> +56 2 3386 8000</p>
            <p class="topbar__item"><strong>Emergencias:</strong> 1414</p>
            <div class="topbar__right">
                <a class="topbar__cta topbar__cta--sai" href="https://www.portaltransparencia.cl/PortalPdT/ingreso-sai-v2" target="_blank" rel="noopener noreferrer">
                    <span class="material-symbols-rounded topbar__cta-icon" aria-hidden="true">info</span>
                    <span class="topbar__cta-text">
                        <span><?php esc_html_e('Solicitud de información', 'sitio-cero'); ?></span>
                        <span class="topbar__cta-sub"><?php esc_html_e('Ley de Transparencia', 'sitio-cero'); ?></span>
                    </span>
                </a>
                <a class="topbar__cta topbar__cta--ta" href="https://www.portaltransparencia.cl/PortalPdT/directorio-de-organismos-regulados" target="_blank" rel="noopener noreferrer">
                    <span class="material-symbols-rounded topbar__cta-icon" aria-hidden="true">folder_open</span>
                    <span class="topbar__cta-text">
                        <span><?php esc_html_e('Transparencia activa', 'sitio-cero'); ?></span>
                        <span class="topbar__cta-sub"><?php esc_html_e('Ley de Transparencia', 'sitio-cero'); ?></span>
                    </span>
                </a>
                <a class="topbar__link" href="<?php echo esc_url(home_url('/#canales')); ?>">Canales de atención</a>
            </div>
        <?php else : ?>
            <?php foreach ($topbar_left_items as $topbar_item) : ?>
                <?php
                $item_title = isset($topbar_item['title']) ? (string) $topbar_item['title'] : '';
                $item_subtitle = isset($topbar_item['subtitle']) ? (string) $topbar_item['subtitle'] : '';
                ?>
                <p class="topbar__item">
                    <?php if ('' !== trim($item_title)) : ?><strong><?php echo esc_html($item_title); ?>:</strong><?php endif; ?>
                    <?php if ('' !== trim($item_subtitle)) : ?> <?php echo esc_html($item_subtitle); ?><?php endif; ?>
                </p>
            <?php endforeach; ?>

            <?php if (!empty($topbar_right_items)) : ?>
                <div class="topbar__right">
                    <?php foreach ($topbar_right_items as $topbar_item) : ?>
                        <?php
                        $item_type = isset($topbar_item['type']) ? (string) $topbar_item['type'] : 'link';
                        $item_title = isset($topbar_item['title']) ? (string) $topbar_item['title'] : '';
                        $item_subtitle = isset($topbar_item['subtitle']) ? (string) $topbar_item['subtitle'] : '';
                        $item_url = isset($topbar_item['url']) ? (string) $topbar_item['url'] : '';
                        if ('' === trim($item_url)) {
                            $item_url = '#';
                        }
                        $item_icon = isset($topbar_item['icon']) ? (string) $topbar_item['icon'] : '';
                        $item_target_blank = !empty($topbar_item['target_blank']);
                        $item_target_attr = $item_target_blank ? ' target="_blank" rel="noopener noreferrer"' : '';
                        ?>

                        <?php if ('cta' === $item_type) : ?>
                            <a class="topbar__cta" href="<?php echo esc_url($item_url); ?>"<?php echo $item_target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                <?php if ('' !== trim($item_icon)) : ?>
                                    <span class="material-symbols-rounded topbar__cta-icon" aria-hidden="true"><?php echo esc_html($item_icon); ?></span>
                                <?php endif; ?>
                                <span class="topbar__cta-text">
                                    <span><?php echo esc_html($item_title); ?></span>
                                    <?php if ('' !== trim($item_subtitle)) : ?>
                                        <span class="topbar__cta-sub"><?php echo esc_html($item_subtitle); ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        <?php else : ?>
                            <a class="topbar__link" href="<?php echo esc_url($item_url); ?>"<?php echo $item_target_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html($item_title); ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<?php
if (function_exists('sitio_cero_render_breadcrumbs')) {
    sitio_cero_render_breadcrumbs();
}
?>

<div class="site-brandbar">
    <div class="container site-brandbar__inner">
        <?php $brand_logo = sitio_cero_get_brand_logo_data(); ?>
        <a class="brand-logo-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Inicio', 'sitio-cero'); ?>">
            <img
                class="brand-logo-image"
                src="<?php echo esc_url($brand_logo['url']); ?>"
                alt="<?php esc_attr_e('Municipalidad de Concepción', 'sitio-cero'); ?>"
                width="<?php echo esc_attr((string) $brand_logo['width']); ?>"
                height="<?php echo esc_attr((string) $brand_logo['height']); ?>"
                loading="eager"
                decoding="async"
            >
        </a>
    </div>
</div>

<header class="site-header">
    <div class="container site-header__inner">
        <button
            class="nav-toggle"
            type="button"
            aria-expanded="false"
            aria-controls="menu-principal"
        >
            Menú
        </button>

        <nav id="menu-principal" class="site-nav" aria-label="<?php esc_attr_e('Menú principal', 'sitio-cero'); ?>">
            <?php
            wp_nav_menu(
                array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'site-nav__list',
                    'depth'          => 2,
                    'fallback_cb'    => 'sitio_cero_menu_fallback',
                )
            );
            ?>
        </nav>

        <div
            class="site-header-edge-tools"
            data-header-search
            data-search-endpoint="<?php echo esc_url(rest_url('wp/v2/search')); ?>"
            data-search-types="post,page,noticia,aviso,direccion_municipal,evento_municipal,municipalidad,plan_regulador"
        >
            <div class="site-header-search-shell" data-header-search-shell>
                <button
                    class="site-header-search-btn"
                    type="button"
                    aria-expanded="false"
                    aria-controls="site-header-search-panel"
                    aria-label="<?php esc_attr_e('Abrir buscador', 'sitio-cero'); ?>"
                    data-header-search-trigger
                >
                    <span class="material-symbols-rounded site-header-search-btn__icon" aria-hidden="true">search</span>
                    <span class="site-header-search-btn__label"><?php esc_html_e('Buscar', 'sitio-cero'); ?></span>
                </button>

                <div class="site-header-search-panel" id="site-header-search-panel" aria-hidden="true" data-header-search-panel>
                    <form class="site-header-search-form" action="<?php echo esc_url(home_url('/')); ?>" method="get" data-header-search-form>
                        <label class="site-header-search-form__label" for="site-header-search-input"><?php esc_html_e('Buscar en el sitio', 'sitio-cero'); ?></label>
                        <div class="site-header-search-form__row">
                            <input
                                id="site-header-search-input"
                                class="site-header-search-form__input"
                                type="search"
                                name="s"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e('Buscar noticias, direcciones, avisos...', 'sitio-cero'); ?>"
                                data-header-search-input
                            >
                            <input type="hidden" name="mostrar_google" value="1">
                            <button class="site-header-search-form__submit" type="submit"><?php esc_html_e('Buscar', 'sitio-cero'); ?></button>
                        </div>
                    </form>

                    <div class="site-header-search-suggest">
                        <p class="site-header-search-suggest__title"><?php esc_html_e('Búsquedas recomendadas', 'sitio-cero'); ?></p>
                        <ul class="site-header-search-suggest__list" data-header-search-suggestions></ul>
                    </div>
                </div>
            </div>
            <a class="site-header-edge-btn" href="tel:*4110">
                <span class="site-header-edge-btn__phone">*4110</span>
                <span class="site-header-edge-btn__text"><?php esc_html_e('Emergencias', 'sitio-cero'); ?></span>
            </a>
        </div>
    </div>
</header>
