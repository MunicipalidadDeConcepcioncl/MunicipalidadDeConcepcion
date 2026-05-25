(function ($) {
    'use strict';

    const config = window.sitioCeroPlanRegulador || {};
    const groupLabel = String(config.groupLabel || 'Grupo');
    const sectionLabel = String(config.sectionLabel || 'Seccion');

    const GROUP_DRAG_CANCEL = 'input, textarea, select, button, a, .button, .wp-editor-wrap, .media-button, .prc-admin-group-editor, .prc-admin-document-editor, .prc-admin-documents, .prc-admin-document-row';

    const createRowFromTemplate = (template, selector) => {
        if (!(template instanceof HTMLTemplateElement)) {
            return $();
        }

        const fragment = template.content.cloneNode(true);
        const $row = $(fragment).filter(selector).first();
        if ($row.length > 0) {
            return $row;
        }

        return $(fragment).find(selector).first();
    };

    const createGroupFromTemplate = (template, key) => {
        if (!(template instanceof HTMLTemplateElement)) {
            return $();
        }

        const html = String(template.innerHTML || '').replace(/__KEY__/g, key);
        const $content = $(html);
        const $row = $content.filter('[data-prc-group-row]').first();
        if ($row.length > 0) {
            return $row;
        }

        return $content.find('[data-prc-group-row]').first();
    };

    const refreshSectionLabels = ($list) => {
        $list.find('[data-prc-section-row]').each((index, row) => {
            $(row)
                .attr('data-section-index', index)
                .find('[data-prc-section-label]')
                .text(`${sectionLabel} ${index + 1}`);
        });
    };

    const updateGroupTitlePreview = ($group) => {
        const $title = $group.find('[data-prc-group-title-input]').first();
        const $preview = $group.find('[data-prc-group-title-preview]').first();
        if ($title.length === 0 || $preview.length === 0) {
            return;
        }

        const value = String($title.val() || '').trim();
        $preview.text(value === '' ? '—' : value);
    };

    const updateGroupDocumentCount = ($group) => {
        const count = $group.find('[data-prc-document-row]').length;
        $group.attr('data-prc-documents-count', count);
    };

    const refreshGroupLabels = ($root) => {
        $root.find('[data-prc-group-row]').each((index, row) => {
            $(row)
                .attr('data-group-index', index)
                .find('[data-prc-group-label]')
                .text(`${groupLabel} ${index + 1}`);
        });
    };

    const openMediaFrameForUrlInput = (inputElement) => {
        if (!inputElement || typeof wp === 'undefined' || !wp.media) {
            return;
        }

        const frame = wp.media({
            title: config.frameTitle || 'Selecciona archivo',
            button: {
                text: config.frameButton || 'Usar archivo'
            },
            multiple: false
        });

        frame.on('select', () => {
            const selection = frame.state().get('selection');
            if (!selection) {
                return;
            }

            const attachment = selection.first();
            if (!attachment) {
                return;
            }

            const json = attachment.toJSON();
            const url = String(json.url || '').trim();
            if (url === '') {
                return;
            }

            const $input = $(inputElement);
            $input.val(url).trigger('input').trigger('change');

            const $row = $input.closest('[data-prc-document-row]');
            const $title = $row.find('[data-prc-document-title]').first();
            if ($title.length > 0 && String($title.val() || '').trim() === '') {
                $title.val(String(json.title || json.filename || '').trim()).trigger('change');
            }
        });

        frame.open();
    };

    const ensureAtLeastOneDocumentRow = ($group) => {
        const $docsList = $group.find('[data-prc-documents-list]').first();
        const template = $group.find('template[data-prc-document-template]')[0];

        if ($docsList.length === 0 || !(template instanceof HTMLTemplateElement)) {
            return;
        }

        if ($docsList.find('[data-prc-document-row]').length > 0) {
            return;
        }

        const $row = createRowFromTemplate(template, '[data-prc-document-row]');
        if ($row.length > 0) {
            $docsList.append($row);
        }
    };

    const updatePlanReguladorGroupsOrder = ($root) => {
        const columnNames = ['left', 'right'];

        columnNames.forEach((columnName) => {
            const $list = $root.find(`[data-prc-column-list="${columnName}"]`).first();
            if ($list.length === 0) {
                return;
            }

            $list.find('[data-prc-group-row]').each((index, row) => {
                const $row = $(row);
                $row.find('[data-prc-group-visual-column]').first().val(columnName);
                $row.find('[data-prc-group-manual-order]').first().val(index);

                const $manualInput = $row.find('[data-prc-group-manual-order-input]').first();
                if ($manualInput.length > 0) {
                    $manualInput.val(index);
                }
            });
        });

        refreshGroupLabels($root);
    };

    const sortAndDistributeGroupsBySavedLayout = ($root) => {
        const $leftList = $root.find('[data-prc-column-list="left"]').first();
        const $rightList = $root.find('[data-prc-column-list="right"]').first();

        if ($leftList.length === 0 || $rightList.length === 0) {
            return;
        }

        const groups = [];
        $root.find('[data-prc-group-row]').each((originalIndex, row) => {
            const $row = $(row);
            const rawColumn = String($row.find('[data-prc-group-visual-column]').first().val() || 'right');
            const column = rawColumn === 'left' ? 'left' : 'right';

            const orderRaw = $row.find('[data-prc-group-manual-order]').first().val();
            const parsedOrder = Number.parseInt(String(orderRaw || ''), 10);
            const manualOrder = Number.isFinite(parsedOrder) && parsedOrder >= 0 ? parsedOrder : originalIndex;

            groups.push({
                $row,
                column,
                manualOrder,
                originalIndex
            });
        });

        groups.sort((a, b) => {
            if (a.column !== b.column) {
                return a.column === 'left' ? -1 : 1;
            }
            if (a.manualOrder !== b.manualOrder) {
                return a.manualOrder - b.manualOrder;
            }
            return a.originalIndex - b.originalIndex;
        });

        groups.forEach((entry) => {
            const $target = entry.column === 'left' ? $leftList : $rightList;
            $target.append(entry.$row.detach());
        });

        updatePlanReguladorGroupsOrder($root);
    };

    const initSortableColumns = ($root) => {
        const $lists = $root.find('[data-prc-column-list]');

        if ($lists.length === 0 || typeof $lists.sortable !== 'function') {
            return;
        }

        $lists.sortable({
            connectWith: '[data-prc-column-list]',
            items: '[data-prc-group-row]',
            handle: '.prc-admin-group-card__drag',
            placeholder: 'prc-admin-group-card--placeholder',
            cancel: GROUP_DRAG_CANCEL,
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            update(event, ui) {
                if (this !== ui.item.parent()[0]) {
                    return;
                }
                updatePlanReguladorGroupsOrder($root);
            },
            receive() {
                updatePlanReguladorGroupsOrder($root);
            }
        });
    };

    const initGroup = ($group) => {
        const $docsList = $group.find('[data-prc-documents-list]').first();
        const $addDocument = $group.find('[data-prc-document-add]').first();
        const template = $group.find('template[data-prc-document-template]')[0];

        if ($docsList.length > 0 && template instanceof HTMLTemplateElement) {
            $addDocument.on('click', (event) => {
                event.preventDefault();
                const $row = createRowFromTemplate(template, '[data-prc-document-row]');
                if ($row.length > 0) {
                    $docsList.append($row);
                    updateGroupDocumentCount($group);
                }
            });

            $group.on('click', '[data-prc-document-remove]', (event) => {
                event.preventDefault();
                $(event.currentTarget).closest('[data-prc-document-row]').remove();
                ensureAtLeastOneDocumentRow($group);
                updateGroupDocumentCount($group);
            });

            ensureAtLeastOneDocumentRow($group);
        }

        $group.on('click', '[data-prc-document-pick-file]', (event) => {
            event.preventDefault();
            const $row = $(event.currentTarget).closest('[data-prc-document-row]');
            const input = $row.find('[data-prc-document-url]').get(0);
            openMediaFrameForUrlInput(input);
        });

        $group.on('click', '[data-prc-group-toggle]', (event) => {
            event.preventDefault();
            const $editor = $group.find('[data-prc-group-editor]').first();
            if ($editor.length === 0) {
                return;
            }

            const isHidden = $editor.prop('hidden');
            if (isHidden) {
                $editor.prop('hidden', false);
                $(event.currentTarget).text('Editar');
                return;
            }

            $editor.prop('hidden', true);
            $(event.currentTarget).text('Mostrar');
        });

        $group.on('input change', '[data-prc-group-title-input]', () => {
            updateGroupTitlePreview($group);
        });

        updateGroupTitlePreview($group);
        updateGroupDocumentCount($group);
    };

    const initGroups = () => {
        $('[data-prc-groups]').each((_, rootElement) => {
            const $root = $(rootElement);
            const $leftList = $root.find('[data-prc-column-list="left"]').first();
            const $rightList = $root.find('[data-prc-column-list="right"]').first();
            const $addButtons = $root.find('[data-prc-group-add]');
            const template = $root.find('template[data-prc-group-template]')[0];

            if ($leftList.length === 0 || $rightList.length === 0 || !(template instanceof HTMLTemplateElement)) {
                return;
            }

            let counter = $root.find('[data-prc-group-row]').length;

            const addGroup = (targetColumn) => {
                const safeColumn = targetColumn === 'left' ? 'left' : 'right';
                const key = `group-${Date.now()}-${counter++}`;
                const $row = createGroupFromTemplate(template, key);
                if ($row.length === 0) {
                    return;
                }

                const $targetList = safeColumn === 'left' ? $leftList : $rightList;
                $targetList.append($row);
                initGroup($row);
                updatePlanReguladorGroupsOrder($root);
            };

            $addButtons.on('click', (event) => {
                event.preventDefault();
                const targetColumn = String($(event.currentTarget).attr('data-target-column') || 'right');
                addGroup(targetColumn);
            });

            $root.on('click', '[data-prc-group-remove]', (event) => {
                event.preventDefault();
                $(event.currentTarget).closest('[data-prc-group-row]').remove();

                if ($root.find('[data-prc-group-row]').length === 0) {
                    addGroup('right');
                    return;
                }

                updatePlanReguladorGroupsOrder($root);
            });

            $root.find('[data-prc-group-row]').each((_, rowElement) => {
                initGroup($(rowElement));
            });

            sortAndDistributeGroupsBySavedLayout($root);
            initSortableColumns($root);
            updatePlanReguladorGroupsOrder($root);

            $('#post').on('submit', () => {
                updatePlanReguladorGroupsOrder($root);
            });
        });
    };

    const initSections = () => {
        $('[data-prc-sections]').each((_, rootElement) => {
            const $root = $(rootElement);
            const $list = $root.find('[data-prc-sections-list]').first();
            const $addButton = $root.find('[data-prc-section-add]').first();
            const template = $root.find('template[data-prc-section-template]')[0];

            if ($list.length === 0 || !(template instanceof HTMLTemplateElement)) {
                return;
            }

            const addSection = () => {
                const $row = createRowFromTemplate(template, '[data-prc-section-row]');
                if ($row.length === 0) {
                    return;
                }

                $list.append($row);
                refreshSectionLabels($list);
            };

            $addButton.on('click', (event) => {
                event.preventDefault();
                addSection();
            });

            $root.on('click', '[data-prc-section-remove]', (event) => {
                event.preventDefault();
                $(event.currentTarget).closest('[data-prc-section-row]').remove();

                if ($list.find('[data-prc-section-row]').length === 0) {
                    addSection();
                } else {
                    refreshSectionLabels($list);
                }
            });

            refreshSectionLabels($list);
        });
    };

    $(() => {
        initGroups();
        initSections();
    });
})(jQuery);
