import { beforeEach, describe, expect, it, vi } from 'vitest';
import $ from 'jquery';
import { createValidator } from '../../public/js/validator.js';
import { loadServerRules } from './server-rules.js';

window.jQuery = $;
const app = await import('../../public/js/app.js');

const rules = loadServerRules();
const PAGE_SIZE = 8;

const PAGE_MARKUP = `
    <form id="person-form" novalidate>
        <input class="form-control" id="name" name="name">
        <div class="invalid-feedback" id="name-error"></div>
        <input class="form-control" id="email" name="email">
        <div class="invalid-feedback" id="email-error"></div>
        <input class="form-control" id="phone" name="phone">
        <div class="invalid-feedback" id="phone-error"></div>
        <div class="alert d-none" id="form-alert"></div>
        <button type="submit" id="submit-button"><span class="spinner-border d-none"></span>Guardar</button>
    </form>
    <span id="total-badge"></span>
    <div id="person-rows"></div>
    <ul id="pagination"></ul>
    <template id="person-row-template">
        <div class="person-row" role="listitem">
            <div class="person-cell"><span data-field="name"></span></div>
            <div class="person-cell"><span data-field="email"></span></div>
            <div class="person-cell"><span data-field="phone"></span></div>
            <div class="person-cell"><span data-field="createdAt"></span></div>
        </div>
    </template>`;

const person = (overrides = {}) => ({
    id: 1,
    name: 'Ana Pérez',
    email: 'ana@mail.com',
    phone: '097680102',
    createdAt: '2026-11-25T15:30:00+00:00',
    ...overrides,
});

const resolved = (value) => $.Deferred().resolve(value).promise();
const rejected = (xhr) => $.Deferred().reject({ statusText: 'error', ...xhr }).promise();
const template = () => document.getElementById('person-row-template');

beforeEach(() => {
    document.body.innerHTML = PAGE_MARKUP;
});

describe('renderRows', () => {
    it('renders user data as text, never as markup', () => {
        app.renderRows($('#person-rows'), [person({ name: '<script>alert(1)</script>', email: '<img src=x onerror=alert(1)>' })], template());

        const $rows = $('#person-rows');
        expect($rows.find('script, img')).toHaveLength(0);
        expect($rows.find('[data-field="name"]').text()).toBe('<script>alert(1)</script>');
        expect($rows.find('[data-field="email"]').text()).toBe('<img src=x onerror=alert(1)>');
    });

    it('keeps the server order and renders a missing phone as empty', () => {
        app.renderRows($('#person-rows'), [person({ id: 2, name: 'Luis', phone: null }), person()], template());

        const $rows = $('#person-rows .person-row');
        expect($rows).toHaveLength(2);
        expect($rows.eq(0).find('[data-field="name"]').text()).toBe('Luis');
        expect($rows.eq(0).find('[data-field="phone"]').text()).toBe('');
    });

    it('formats the insertion date in Uruguay time', () => {
        app.renderRows($('#person-rows'), [person()], template());

        const date = $('#person-rows [data-field="createdAt"]').text();
        expect(date).toContain('25/11');
        expect(date).toContain('12:30');
    });
});

describe('list states', () => {
    it('renders the requested amount of hidden skeleton rows', () => {
        app.renderSkeleton($('#person-rows'), PAGE_SIZE, template());

        const $rows = $('#person-rows .person-row--skeleton');
        expect($rows).toHaveLength(PAGE_SIZE);
        expect($rows.first().attr('aria-hidden')).toBe('true');
        expect($rows.first().find('.skeleton-bar')).toHaveLength(4);
    });

    it('renders messages as text', () => {
        app.renderMessage($('#person-rows'), '<b>x</b>');

        expect($('#person-rows b')).toHaveLength(0);
        expect($('#person-rows .list-empty').text()).toBe('<b>x</b>');
    });
});

describe('pageWindow', () => {
    it.each([
        [1, 1, [1]],
        [1, 3, [1, 2, 3]],
        [1, 10, [1, 2, '…', 10]],
        [5, 10, [1, '…', 4, 5, 6, '…', 10]],
        [10, 10, [1, '…', 9, 10]],
        [3, 5, [1, 2, 3, 4, 5]],
    ])('page %i of %i gives %j', (current, total, expected) => {
        expect(app.pageWindow(current, total)).toEqual(expected);
    });
});

describe('renderPagination', () => {
    it('renders nothing for a single page', () => {
        app.renderPagination($('#pagination'), { page: 1, totalPages: 1 });

        expect($('#pagination').children()).toHaveLength(0);
    });

    it('disables previous on the first page and marks the current one', () => {
        app.renderPagination($('#pagination'), { page: 1, totalPages: 3 });

        const $buttons = $('#pagination button');
        expect($buttons.first().prop('disabled')).toBe(true);
        expect($buttons.last().prop('disabled')).toBe(false);
        expect($('#pagination [aria-current="page"]').text()).toBe('1');
    });

    it('disables next on the last page and shows ellipses for gaps', () => {
        app.renderPagination($('#pagination'), { page: 10, totalPages: 10 });

        expect($('#pagination button').last().prop('disabled')).toBe(true);
        expect($('#pagination span.page-link').text()).toBe('…');
        expect($('#pagination button[data-page="2"]')).toHaveLength(0);
    });
});

describe('createList', () => {
    const listFor = (api) => app.createList({
        $rows: $('#person-rows'),
        $pagination: $('#pagination'),
        $total: $('#total-badge'),
        template: template(),
        api,
        pageSize: PAGE_SIZE,
    });

    it('renders rows, total and pagination for the loaded page', () => {
        const api = { list: vi.fn(() => resolved({ data: [person()], meta: { page: 1, perPage: 8, total: 9, totalPages: 2 } })) };

        listFor(api).loadPage(1);

        expect(api.list).toHaveBeenCalledWith(1);
        expect($('#person-rows .person-row')).toHaveLength(1);
        expect($('#total-badge').text()).toBe('9 registros');
        expect($('#person-rows').attr('aria-busy')).toBe('false');
        expect($('#pagination button[data-page="2"]')).toHaveLength(2);
    });

    it('loads another page without reloading when a page button is clicked', () => {
        const api = { list: vi.fn((page) => resolved({ data: [person({ id: page })], meta: { page, perPage: 8, total: 9, totalPages: 2 } })) };
        listFor(api).loadPage(1);

        $('#pagination button[aria-label="Página 2"]').trigger('click');

        expect(api.list).toHaveBeenLastCalledWith(2);
        expect($('#pagination [aria-current="page"]').text()).toBe('2');
    });

    it('shows the empty state and a singular total', () => {
        listFor({ list: () => resolved({ data: [], meta: { page: 1, perPage: 8, total: 0, totalPages: 1 } }) }).loadPage(1);

        expect($('#person-rows .list-empty')).toHaveLength(1);
        expect($('#total-badge').text()).toBe('0 registros');
    });

    it('shows an error message when loading fails, but ignores aborted requests', () => {
        listFor({ list: () => rejected({ status: 500 }) }).loadPage(1);
        expect($('#person-rows .list-empty').text()).toContain('No se pudo cargar');

        document.body.innerHTML = PAGE_MARKUP;
        listFor({ list: () => rejected({ status: 0, statusText: 'abort' }) }).loadPage(1);
        expect($('#person-rows .person-row--skeleton')).toHaveLength(PAGE_SIZE);
    });
});

describe('createForm', () => {
    const fill = (values) => {
        for (const [field, value] of Object.entries(values)) {
            $(`#${field}`).val(value);
        }
    };

    const formWith = (api, onCreated = vi.fn()) => {
        app.createForm({ $form: $('#person-form'), fields: rules.fields, validator: createValidator(rules), api, onCreated });

        return onCreated;
    };

    it('blocks invalid submissions on the client without calling the API', () => {
        const api = { create: vi.fn() };
        formWith(api);
        fill({ name: '   ', email: 'mal', phone: '123' });

        $('#person-form').trigger('submit');

        expect(api.create).not.toHaveBeenCalled();
        expect($('.is-invalid')).toHaveLength(3);
        expect($('#name-error').text()).toBe(rules.messages.name.required);
        expect($('#email').val()).toBe('mal');
    });

    it('shows server errors on the matching fields and keeps every value', () => {
        const errors = { email: rules.messages.email.duplicate };
        formWith({ create: () => rejected({ status: 422, responseJSON: { errors } }) });
        fill({ name: 'Ana Pérez', email: 'ana@mail.com', phone: '097680102' });

        $('#person-form').trigger('submit');

        expect($('#email').hasClass('is-invalid')).toBe(true);
        expect($('#email-error').text()).toBe(errors.email);
        expect($('#name').val()).toBe('Ana Pérez');
        expect($('#email').val()).toBe('ana@mail.com');
        expect($('#phone').val()).toBe('097680102');
        expect($('#submit-button').prop('disabled')).toBe(false);
    });

    it('resets the form and reports the created person on success', () => {
        const created = person();
        const api = { create: vi.fn(() => resolved({ data: created })) };
        const onCreated = formWith(api);
        fill({ name: 'Ana Pérez', email: 'ana@mail.com', phone: '' });

        $('#person-form').trigger('submit');

        expect(api.create).toHaveBeenCalledWith({ name: 'Ana Pérez', email: 'ana@mail.com', phone: '' });
        expect(onCreated).toHaveBeenCalledWith(created);
        expect($('#name').val()).toBe('');
        expect($('.is-invalid, .is-valid')).toHaveLength(0);
    });

    it('shows a connection alert when the server is unreachable', () => {
        formWith({ create: () => rejected({ status: 0 }) });
        fill({ name: 'Ana', email: 'ana@mail.com' });

        $('#person-form').trigger('submit');

        expect($('#form-alert').hasClass('d-none')).toBe(false);
        expect($('#form-alert').text()).toContain('No se pudo conectar');
        expect($('#name').val()).toBe('Ana');
    });

    it('validates live once a field has been touched', () => {
        formWith({ create: vi.fn() });
        const $email = $('#email');

        $email.val('mal').trigger('input');
        expect($email.hasClass('is-invalid')).toBe(false);

        $email.trigger('blur');
        expect($email.hasClass('is-invalid')).toBe(true);

        $email.val('ana@mail.com').trigger('input');
        expect($email.hasClass('is-invalid')).toBe(false);
        expect($email.hasClass('is-valid')).toBe(true);
    });
});

describe('createApi', () => {
    it('aborts the previous list request when a new page is requested', () => {
        const first = { abort: vi.fn() };
        const ajax = vi.spyOn($, 'ajax').mockReturnValueOnce(first).mockReturnValueOnce({ abort: vi.fn() });
        const api = app.createApi('/api/persons');

        api.list(1);
        api.list(2);

        expect(first.abort).toHaveBeenCalledOnce();
        expect(ajax).toHaveBeenLastCalledWith(expect.objectContaining({ url: '/api/persons', data: { page: 2 } }));
        ajax.mockRestore();
    });

    it('posts new people to the endpoint', () => {
        const ajax = vi.spyOn($, 'ajax').mockReturnValue({});

        app.createApi('/api/persons').create({ name: 'Ana' });

        expect(ajax).toHaveBeenCalledWith(expect.objectContaining({ url: '/api/persons', method: 'POST', data: { name: 'Ana' } }));
        ajax.mockRestore();
    });
});
