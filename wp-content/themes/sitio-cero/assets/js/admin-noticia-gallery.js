(function ($) {
    'use strict';

    const config = window.sitioCeroNoticiaGallery || {};

    const createItem = (attachment) => {
        const attachmentId = Number.parseInt(attachment.id, 10);
        if (!attachmentId) {
            return null;
        }

        const thumbUrl = attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;

        if (!thumbUrl) {
            return null;
        }

        const altText = attachment.alt || attachment.title || '';
        const removeText = config.removeText || 'Quitar';

        const $item = $('<li/>', {
            class: 'sitio-cero-noticia-gallery__item',
            'data-id': attachmentId
        });

        const $thumb = $('<div/>', {
            class: 'sitio-cero-noticia-gallery__thumb'
        });

        $('<img/>', {
            class: 'sitio-cero-noticia-gallery__thumb-image',
            src: thumbUrl,
            alt: altText,
            loading: 'lazy'
        }).appendTo($thumb);

        const $remove = $('<button/>', {
            type: 'button',
            class: 'button-link-delete sitio-cero-noticia-gallery__remove',
            text: removeText
        });

        $item.append($thumb, $remove);
        return $item;
    };

    const syncValue = ($list, $input, $empty) => {
        const ids = $list
            .children('.sitio-cero-noticia-gallery__item')
            .map((_, element) => Number.parseInt($(element).attr('data-id'), 10))
            .get()
            .filter((id) => Number.isInteger(id) && id > 0);

        $input.val(ids.join(','));

        if (ids.length > 0) {
            $empty.addClass('is-hidden');
            $list.removeClass('is-empty');
        } else {
            $empty.removeClass('is-hidden');
            $list.addClass('is-empty');
        }
    };

    const appendAttachments = ($list, $input, $empty, attachments) => {
        const existingIds = new Set(
            $list
                .children('.sitio-cero-noticia-gallery__item')
                .map((_, element) => Number.parseInt($(element).attr('data-id'), 10))
                .get()
                .filter((id) => Number.isInteger(id) && id > 0)
        );

        attachments.forEach((attachment) => {
            const attachmentId = Number.parseInt(attachment.id, 10);
            if (!attachmentId || existingIds.has(attachmentId)) {
                return;
            }

            const $item = createItem(attachment);
            if (!$item) {
                return;
            }

            existingIds.add(attachmentId);
            $list.append($item);
        });

        syncValue($list, $input, $empty);
    };

    const initGallery = (rootElement) => {
        const $root = $(rootElement);
        const $input = $root.find('#sitio_cero_noticia_gallery_ids');
        const $list = $root.find('.sitio-cero-noticia-gallery__list');
        const $empty = $root.find('.sitio-cero-noticia-gallery__empty');

        if ($input.length === 0 || $list.length === 0 || $empty.length === 0) {
            return;
        }

        $list.sortable({
            items: '> .sitio-cero-noticia-gallery__item',
            placeholder: 'sitio-cero-noticia-gallery__placeholder',
            forcePlaceholderSize: true,
            tolerance: 'pointer',
            update: () => syncValue($list, $input, $empty)
        });

        $root.on('click', '.sitio-cero-noticia-gallery__choose', (event) => {
            event.preventDefault();

            let frame = $root.data('sitioCeroMediaFrame');

            if (!frame) {
                frame = wp.media({
                    title: config.frameTitle || 'Selecciona imágenes para la galeria',
                    button: {
                        text: config.frameButton || 'Usar imágenes'
                    },
                    library: {
                        type: 'image'
                    },
                    multiple: true
                });

                frame.on('select', () => {
                    const selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }

                    const attachments = selection.toJSON();
                    appendAttachments($list, $input, $empty, attachments);
                });

                $root.data('sitioCeroMediaFrame', frame);
            }

            frame.open();
        });

        $root.on('click', '.sitio-cero-noticia-gallery__remove', (event) => {
            event.preventDefault();
            $(event.currentTarget).closest('.sitio-cero-noticia-gallery__item').remove();
            syncValue($list, $input, $empty);
        });

        $root.on('click', '.sitio-cero-noticia-gallery__clear', (event) => {
            event.preventDefault();
            $list.empty();
            syncValue($list, $input, $empty);
        });

        syncValue($list, $input, $empty);
    };

    $(() => {
        $('.sitio-cero-noticia-gallery').each((_, element) => initGallery(element));
    });
})(jQuery);
