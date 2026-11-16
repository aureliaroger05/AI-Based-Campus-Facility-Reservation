(function () {
    const tableSelector = 'table[data-pagination-page-size]';
    const tablePaginators = new WeakMap();

    function createControl(label, ariaLabel) {
        const item = document.createElement('li');
        item.className = 'page-item';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'page-link';
        button.textContent = label;
        button.setAttribute('aria-label', ariaLabel);
        item.appendChild(button);

        return { item: item, button: button };
    }

    function initializeTable(table) {
        if (tablePaginators.has(table) || !table.tBodies.length) return;

        const rows = Array.from(table.tBodies[0].rows);
        const requestedPageSize = Number.parseInt(table.dataset.paginationPageSize, 10);
        if (!Number.isInteger(requestedPageSize) || requestedPageSize < 1 || rows.length <= requestedPageSize) {
            return;
        }

        const pageSize = requestedPageSize;
        const wrapper = table.closest('.table-responsive') || table;
        const label = table.dataset.paginationLabel || 'Table rows';
        const pagination = document.createElement('nav');
        pagination.className = 'table-pagination';
        pagination.setAttribute('aria-label', label + ' pagination');
        pagination.hidden = true;

        const list = document.createElement('ul');
        list.className = 'pagination pagination-sm mb-0';
        const previous = createControl('Previous', 'Previous table page');
        list.appendChild(previous.item);

        const statusItem = document.createElement('li');
        statusItem.className = 'page-item active';
        const status = document.createElement('span');
        status.className = 'page-link';
        status.setAttribute('aria-current', 'page');
        status.setAttribute('aria-live', 'polite');
        statusItem.appendChild(status);
        list.appendChild(statusItem);

        const next = createControl('Next', 'Next table page');
        list.appendChild(next.item);
        pagination.appendChild(list);
        wrapper.insertAdjacentElement('afterend', pagination);

        let currentPage = 0;
        function renderPage(resetToFirstPage) {
            if (resetToFirstPage) currentPage = 0;

            const matchingRows = rows.filter(function (row) {
                return row.dataset.tableSearchMatch !== 'false';
            });
            const pageCount = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            currentPage = Math.min(currentPage, pageCount - 1);

            const firstRow = currentPage * pageSize;
            const lastRow = Math.min(firstRow + pageSize, matchingRows.length);
            const visibleRows = new Set(matchingRows.slice(firstRow, lastRow));
            rows.forEach(function (row) {
                row.hidden = !visibleRows.has(row);
            });

            previous.button.disabled = currentPage === 0;
            next.button.disabled = currentPage === pageCount - 1;
            previous.item.classList.toggle('disabled', previous.button.disabled);
            next.item.classList.toggle('disabled', next.button.disabled);
            status.textContent = matchingRows.length === 0
                ? 'No matching rows'
                : 'Page ' + (currentPage + 1) + ' of ' + pageCount +
                    ' (rows ' + (firstRow + 1) + ' to ' + lastRow + ' of ' + matchingRows.length + ')';
            pagination.hidden = matchingRows.length <= pageSize;
        }

        previous.button.addEventListener('click', function () {
            if (!previous.button.disabled) {
                currentPage -= 1;
                renderPage(false);
            }
        });
        next.button.addEventListener('click', function () {
            if (!next.button.disabled) {
                currentPage += 1;
                renderPage(false);
            }
        });

        tablePaginators.set(table, renderPage);
        renderPage(false);
    }

    function initializeTables(root, resetToFirstPage) {
        const scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        if (scope.matches && scope.matches(tableSelector)) {
            initializeTable(scope);
        }
        scope.querySelectorAll(tableSelector).forEach(function (table) {
            initializeTable(table);
            const renderPage = tablePaginators.get(table);
            if (renderPage) renderPage(resetToFirstPage);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeTables(document, false);
    });
    document.addEventListener('admin:view-loaded', function () {
        initializeTables(document.querySelector('.admin-dashboard-workspace main') || document, false);
    });
    document.addEventListener('table-pagination:refresh', function () {
        initializeTables(document, true);
    });
})();
