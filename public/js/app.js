import { createValidator } from './validator.js';

const $ = window.jQuery;

const ABORTED = 'abort';
const FIRST_PAGE = 1;
const PAGE_WINDOW_RADIUS = 1;
const ELLIPSIS = '…';
const DATE_VALUE = 'date';
const DATE_FORMAT = new Intl.DateTimeFormat('es-UY', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'America/Montevideo',
});

const CSS = Object.freeze({
    invalid: 'is-invalid',
    valid: 'is-valid',
    hidden: 'd-none',
    skeletonRow: 'person-row--skeleton',
    skeletonBar: 'skeleton-bar',
    empty: 'list-empty',
});

const TEXT = Object.freeze({
    networkError: 'No se pudo conectar con el servidor. Revisá tu conexión e intentá nuevamente.',
    emptyList: 'Todavía no hay registros. Completá el formulario para agregar el primero.',
    listError: 'No se pudo cargar el listado. Intentá nuevamente en unos instantes.',
    previous: 'Anterior',
    next: 'Siguiente',
    pageLabel: (page) => `Página ${page}`,
    total: (total) => `${total} ${total === 1 ? 'registro' : 'registros'}`,
});

export function createApi(endpoint) {
    let pendingList = null;

    return {
        list(page) {
            pendingList?.abort();
            pendingList = $.ajax({ url: endpoint, method: 'GET', data: { page }, dataType: 'json' });

            return pendingList;
        },
        create(values) {
            return $.ajax({ url: endpoint, method: 'POST', data: values, dataType: 'json' });
        },
    };
}

export function showFieldError($form, field, message) {
    const $input = $form.find(`[name="${field}"]`);

    $input.toggleClass(CSS.invalid, message !== null)
        .toggleClass(CSS.valid, message === null && $input.val() !== '')
        .attr('aria-invalid', message !== null ? 'true' : 'false');
    $form.find(`#${field}-error`).text(message ?? '');
}

export function applyServerErrors($form, errors) {
    const fields = Object.keys(errors);
    for (const field of fields) {
        showFieldError($form, field, errors[field]);
    }
    if (fields.length > 0) {
        $form.find(`[name="${fields[0]}"]`).trigger('focus');
    }
}

export function readValues($form, fields) {
    return Object.fromEntries(fields.map((field) => [field, String($form.find(`[name="${field}"]`).val() ?? '')]));
}

export function createForm({
    $form,
    fields,
    validator,
    api,
    unprocessableStatus,
    serverErrorMessage,
    onCreated = () => {},
}) {
    const touched = new Set();
    const $alert = $form.find('#form-alert');
    const $button = $form.find('#submit-button');

    function validateInput(input) {
        showFieldError($form, input.name, validator.validateField(input.name, input.value));
    }

    function setLoading(loading) {
        $button.prop('disabled', loading).attr('aria-busy', String(loading));
        $button.find('.spinner-border').toggleClass(CSS.hidden, !loading);
    }

    function showAlert(message) {
        $alert.text(message ?? '').toggleClass(CSS.hidden, !message);
    }

    function reset() {
        $form.trigger('reset');
        touched.clear();
        $form.find('.form-control').removeClass(`${CSS.invalid} ${CSS.valid}`).removeAttr('aria-invalid');
        $form.find('.invalid-feedback').text('');
    }

    function handleFailure(xhr) {
        if (xhr.status === unprocessableStatus && xhr.responseJSON?.errors) {
            applyServerErrors($form, xhr.responseJSON.errors);
            return;
        }
        showAlert(xhr.status === 0 ? TEXT.networkError : serverErrorMessage);
    }

    $form.on('blur', '.form-control', (event) => {
        touched.add(event.target.name);
        validateInput(event.target);
    });

    $form.on('input', '.form-control', (event) => {
        if (touched.has(event.target.name)) {
            validateInput(event.target);
        }
    });

    $form.on('submit', (event) => {
        event.preventDefault();
        showAlert(null);

        const values = readValues($form, fields);
        const errors = validator.validate(values);
        fields.forEach((field) => touched.add(field));
        fields.forEach((field) => showFieldError($form, field, errors[field] ?? null));

        if (Object.keys(errors).length > 0) {
            $form.find(`[name="${Object.keys(errors)[0]}"]`).trigger('focus');
            return;
        }

        setLoading(true);
        api.create(values)
            .done((response) => {
                reset();
                onCreated(response.data);
            })
            .fail(handleFailure)
            .always(() => setLoading(false));
    });
}

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
    $container.empty().append($('<div>').addClass(CSS.empty).text(message));
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

function showToast(element) {
    if (element && window.bootstrap) {
        window.bootstrap.Toast.getOrCreateInstance(element).show();
    }
}

function init() {
    const config = JSON.parse($('#app-config').text());
    const api = createApi(config.endpoint);
    const list = createList({
        $rows: $('#person-rows'),
        $pagination: $('#pagination'),
        $total: $('#total-badge'),
        template: document.getElementById('person-row-template'),
        api,
        pageSize: config.pageSize,
    });

    list.loadPage(FIRST_PAGE);

    createForm({
        $form: $('#person-form'),
        fields: config.fields,
        validator: createValidator(config),
        api,
        unprocessableStatus: config.unprocessableStatus,
        serverErrorMessage: config.serverError,
        onCreated: () => {
            showToast(document.getElementById('success-toast'));
            list.loadPage(FIRST_PAGE);
        },
    });
}

if (document.getElementById('app-config')) {
    $(init);
}
