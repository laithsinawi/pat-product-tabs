(function ($) {
    function getEditorSettings() {
        return {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,wp_more,fullscreen',
                toolbar2: '',
                toolbar3: '',
                toolbar4: ''
            },
            quicktags: true,
            mediaButtons: false
        };
    }

    function getOrderInput(row) {
        return row.querySelector('input[type="number"][name*="[order]"]');
    }

    function getRowOrder(row) {
        const orderInput = getOrderInput(row);
        const value = orderInput ? parseInt(orderInput.value, 10) : NaN;

        return Number.isFinite(value) ? value : 10;
    }

    function setNextOrder(rows) {
        let maxOrder = 0;

        rows.querySelectorAll('tr').forEach(function (row) {
            const order = getRowOrder(row);
            if (order > maxOrder) {
                maxOrder = order;
            }
        });

        return maxOrder + 10;
    }

    function renumberRows(rows) {
        const rowElements = rows.querySelectorAll('tr');
        let order = 10;

        rowElements.forEach(function (row) {
            const orderInput = getOrderInput(row);
            if (orderInput) {
                orderInput.value = String(order);
            }
            order += 10;
        });
    }

    function moveRow(row, direction) {
        const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;

        if (!sibling) {
            return false;
        }

        if (direction === 'up') {
            row.parentNode.insertBefore(row, sibling);
        } else {
            row.parentNode.insertBefore(sibling, row);
        }

        return true;
    }

    function initEditor(row) {
        const editorId = row.getAttribute('data-editor-id');
        const textarea = editorId ? document.getElementById(editorId) : null;

        if (!editorId || !textarea || row.getAttribute('data-editor-initialized') === '1') {
            return;
        }

        if (window.wp && wp.editor && typeof wp.editor.initialize === 'function') {
            wp.editor.initialize(editorId, getEditorSettings());
            row.setAttribute('data-editor-initialized', '1');
        }
    }

    function initExistingEditors(rows) {
        rows.querySelectorAll('tr').forEach(initEditor);
    }

    function initSortable(rows, onReorder) {
        if (!$.fn.sortable) {
            return;
        }

        $(rows).sortable({
            items: '> tr',
            handle: '.pat-product-tabs-drag-handle',
            axis: 'y',
            helper: function (e, ui) {
                ui.children().each(function () {
                    $(this).width($(this).width());
                });
                return ui;
            },
            update: function () {
                renumberRows(rows);
                if (typeof onReorder === 'function') {
                    onReorder();
                }
            }
        });
    }

    function addRow(template, rows, nextIndex, nextOrder) {
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(nextIndex));
        const row = wrapper.firstElementChild;
        const orderInput = getOrderInput(row);

        if (orderInput) {
            orderInput.value = String(nextOrder);
        }

        rows.appendChild(row);
        initEditor(row);
        return row;
    }

    $(function () {
        const addButton = document.getElementById('pat-product-tabs-add');
        const rows = document.getElementById('pat-product-tabs-rows');
        const template = document.getElementById('pat-product-tabs-row-template');

        if (!addButton || !rows || !template) {
            return;
        }

        initExistingEditors(rows);
        let nextIndex = 0;
        rows.querySelectorAll('tr').forEach(function (row) {
            const value = parseInt(row.getAttribute('data-row-index') || '', 10);
            if (Number.isFinite(value) && value >= nextIndex) {
                nextIndex = value + 1;
            }
        });
        let nextOrder = setNextOrder(rows);

        initSortable(rows, function () {
            nextOrder = setNextOrder(rows);
        });

        addButton.addEventListener('click', function () {
            addRow(template, rows, nextIndex, nextOrder);
            nextIndex++;
            nextOrder += 10;
        });

        rows.addEventListener('click', function (event) {
            const moveButton = event.target.closest('[data-pat-move-row]');
            if (moveButton) {
                const row = moveButton.closest('tr');
                if (!row) {
                    return;
                }

                const direction = moveButton.getAttribute('data-pat-move-row');
                if (moveRow(row, direction)) {
                    renumberRows(rows);
                    nextOrder = setNextOrder(rows);
                }

                return;
            }

            const button = event.target.closest('[data-pat-remove-row]');
            if (!button) {
                return;
            }

            const row = button.closest('tr');
            if (!row) {
                return;
            }

            const editorId = row.getAttribute('data-editor-id');
            if (editorId && window.wp && wp.editor && typeof wp.editor.remove === 'function') {
                wp.editor.remove(editorId);
            }

            row.remove();
            nextOrder = setNextOrder(rows);
        });
    });
})(jQuery);
