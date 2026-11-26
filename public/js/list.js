const $ = window.jQuery;

export const FIRST_PAGE = 1;

const ABORTED = 'abort';
const PAGE_WINDOW_RADIUS = 1;
const ELLIPSIS = '…';
const DATE_VALUE = 'date';
const DATE_FORMAT = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Montevideo',
});

const CSS = Object.freeze({
    skeletonRow: 'person-row--skeleton',
    skeletonBar: 'skeleton-bar',
    empty: 'list-empty',
});

const TEXT = Object.freeze({
    emptyList: 'Todavía no hay registros. Completá el formulario para agregar el primero.',
    listError: 'No se pudo cargar el listado. Intentá nuevamente en unos instantes.',
    previous: 'Anterior',
    next: 'Siguiente',
    pageLabel: (page) => `Página ${page}`,
    total: (total) => `${total} ${total === 1 ? 'registro' : 'registros'}`,
});

export function formatDate(isoDate) {
    return isoDate ? DATE_FORMAT.format(new Date(isoDate)) : '';
}

function cloneRow(template) {
    return $(template.content.cloneNode(true)).children().first();
}

export function renderRows($container, people, template) {
    const rows = people.map((person) => {
        const $row = cloneRow(template);
        $row.find('[data-field]').each((_, element) => {
            const value = person[element.dataset.field];
            $(element).text((element.dataset.format === DATE_VALUE ? formatDate(value) : value) ?? '');
        });

        return $row;
    });

    $container.empty().append(rows);
}

export function renderSkeleton($container, count, template) {
    const rows = Array.from({ length: count }, () => {
        const $row = cloneRow(template).addClass(CSS.skeletonRow).attr('aria-hidden', 'true');
        $row.find('[data-field]').empty().append($('<span>').addClass(CSS.skeletonBar));

        return $row;
    });

    $container.empty().append(rows);
}

export function renderMessage($container, message) {
    $container.empty().append($('<div role="listitem">').addClass(CSS.empty).text(message));
}

export function pageWindow(current, total, radius = PAGE_WINDOW_RADIUS) {
    const pages = [];
    for (let page = FIRST_PAGE; page <= total; page += 1) {
        if (page === FIRST_PAGE || page === total || Math.abs(page - current) <= radius) {
            pages.push(page);
        } else if (pages.at(-1) !== ELLIPSIS) {
            pages.push(ELLIPSIS);
        }
    }

    return pages;
}

function pageItem(label, page, { active = false, disabled = false, ariaLabel = label } = {}) {
    const $item = $('<li>').addClass('page-item').toggleClass('active', active).toggleClass('disabled', disabled);
    const $control = disabled && page === null
        ? $('<span>').addClass('page-link').text(label)
        : $('<button type="button">').addClass('page-link').text(label)
            .attr({ 'data-page': page, 'aria-label': ariaLabel })
            .prop('disabled', disabled);

    if (active) {
        $control.attr('aria-current', 'page');
    }

    return $item.append($control);
}

export function renderPagination($list, { page, totalPages }) {
    $list.empty();
    if (totalPages <= FIRST_PAGE) {
        return;
    }

    const items = [pageItem(TEXT.previous, page - 1, { disabled: page === FIRST_PAGE })];
    for (const entry of pageWindow(page, totalPages)) {
        items.push(entry === ELLIPSIS
            ? pageItem(ELLIPSIS, null, { disabled: true })
            : pageItem(String(entry), entry, { active: entry === page, ariaLabel: TEXT.pageLabel(entry) }));
    }
    items.push(pageItem(TEXT.next, page + 1, { disabled: page === totalPages }));

    $list.append(items);
}

export function createList({ $rows, $pagination, $total, template, api, pageSize }) {
    let currentPage = FIRST_PAGE;

    function loadPage(page) {
        const keepPaginationFocus = $pagination[0].contains(document.activeElement);
        $rows.attr('aria-busy', 'true');
        renderSkeleton($rows, pageSize, template);

        api.list(page)
            .done(({ data, meta }) => {
                currentPage = meta.page;
                if (data.length === 0) {
                    renderMessage($rows, TEXT.emptyList);
                } else {
                    renderRows($rows, data, template);
                }
                renderPagination($pagination, meta);
                $total.text(TEXT.total(meta.total));
                $rows.attr('aria-busy', 'false');
                if (keepPaginationFocus) {
                    $pagination.find('[aria-current="page"]').trigger('focus');
                }
            })
            .fail((xhr) => {
                if (xhr.statusText !== ABORTED) {
                    renderMessage($rows, TEXT.listError);
                    $rows.attr('aria-busy', 'false');
                }
            });
    }

    $pagination.on('click', '[data-page]', (event) => {
        const page = Number(event.currentTarget.dataset.page);
        if (page !== currentPage) {
            loadPage(page);
        }
    });

    return { loadPage };
}
