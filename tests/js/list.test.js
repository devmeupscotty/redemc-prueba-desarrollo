import { beforeEach, describe, expect, it, vi } from 'vitest';
import { $, PAGE_SIZE, person, rejected, resolved } from './support.js';
import {
    createList,
    pageWindow,
    renderMessage,
    renderPagination,
    renderRows,
    renderSkeleton,
} from '../../public/js/list.js';

const LIST_MARKUP = `
    <input id="unrelated-input">
    <span id="total-badge"></span>
    <div id="person-rows"></div>
    <ul id="pagination"></ul>
    <template id="person-row-template">
        <div class="person-row" role="listitem">
            <div class="person-cell"><span data-field="name"></span></div>
            <div class="person-cell"><span data-field="email"></span></div>
            <div class="person-cell"><span data-field="phone"></span></div>
            <div class="person-cell"><span data-field="createdAt" data-format="date"></span></div>
        </div>
    </template>`;

const template = () => document.getElementById('person-row-template');
const meta = (page, total) => ({ page, perPage: PAGE_SIZE, total, totalPages: Math.max(1, Math.ceil(total / PAGE_SIZE)) });

beforeEach(() => {
    document.body.innerHTML = LIST_MARKUP;
});

describe('renderRows', () => {
    it('renders user data as text, never as markup', () => {
        renderRows($('#person-rows'), [person({ name: '<script>alert(1)</script>', email: '<img src=x onerror=alert(1)>' })], template());

        const $rows = $('#person-rows');
        expect($rows.find('script, img')).toHaveLength(0);
        expect($rows.find('[data-field="name"]').text()).toBe('<script>alert(1)</script>');
        expect($rows.find('[data-field="email"]').text()).toBe('<img src=x onerror=alert(1)>');
    });

    it('keeps the server order and renders a missing phone as empty', () => {
        renderRows($('#person-rows'), [person({ id: 2, name: 'Luis', phone: null }), person()], template());

        const $rows = $('#person-rows .person-row');
        expect($rows).toHaveLength(2);
        expect($rows.eq(0).find('[data-field="name"]').text()).toBe('Luis');
        expect($rows.eq(0).find('[data-field="phone"]').text()).toBe('');
    });

    it('formats the insertion date in Uruguay time', () => {
        renderRows($('#person-rows'), [person()], template());

        const date = $('#person-rows [data-field="createdAt"]').text();
        expect(date).toContain('25/11');
        expect(date).toContain('12:30');
    });
});

describe('list states', () => {
    it('renders the requested amount of hidden skeleton rows', () => {
        renderSkeleton($('#person-rows'), PAGE_SIZE, template());

        const $rows = $('#person-rows .person-row--skeleton');
        expect($rows).toHaveLength(PAGE_SIZE);
        expect($rows.first().attr('aria-hidden')).toBe('true');
        expect($rows.first().find('.skeleton-bar')).toHaveLength(4);
    });

    it('renders messages as text list items', () => {
        renderMessage($('#person-rows'), '<b>x</b>');

        expect($('#person-rows b')).toHaveLength(0);
        expect($('#person-rows .list-empty').text()).toBe('<b>x</b>');
        expect($('#person-rows .list-empty').attr('role')).toBe('listitem');
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
        expect(pageWindow(current, total)).toEqual(expected);
    });
});

describe('renderPagination', () => {
    it('renders nothing for a single page', () => {
        renderPagination($('#pagination'), { page: 1, totalPages: 1 });

        expect($('#pagination').children()).toHaveLength(0);
    });

    it('disables previous on the first page and marks the current one', () => {
        renderPagination($('#pagination'), { page: 1, totalPages: 3 });

        const $buttons = $('#pagination button');
        expect($buttons.first().prop('disabled')).toBe(true);
        expect($buttons.last().prop('disabled')).toBe(false);
        expect($('#pagination [aria-current="page"]').text()).toBe('1');
    });

    it('disables next on the last page and shows ellipses for gaps', () => {
        renderPagination($('#pagination'), { page: 10, totalPages: 10 });

        expect($('#pagination button').last().prop('disabled')).toBe(true);
        expect($('#pagination span.page-link').text()).toBe('…');
        expect($('#pagination button[data-page="2"]')).toHaveLength(0);
    });
});

describe('createList', () => {
    const listFor = (api) => createList({
        $rows: $('#person-rows'),
        $pagination: $('#pagination'),
        $total: $('#total-badge'),
        template: template(),
        api,
        pageSize: PAGE_SIZE,
    });
    const pagedApi = () => ({ list: vi.fn((page) => resolved({ data: [person({ id: page })], meta: meta(page, 9) })) });

    it('renders rows, total and pagination for the loaded page', () => {
        const api = pagedApi();

        listFor(api).loadPage(1);

        expect(api.list).toHaveBeenCalledWith(1);
        expect($('#person-rows .person-row')).toHaveLength(1);
        expect($('#total-badge').text()).toBe('9 registros');
        expect($('#person-rows').attr('aria-busy')).toBe('false');
        expect($('#pagination button[data-page="2"]')).toHaveLength(2);
    });

    it('loads another page without reloading when a page button is clicked', () => {
        const api = pagedApi();
        listFor(api).loadPage(1);

        $('#pagination button[aria-label="Página 2"]').trigger('click');

        expect(api.list).toHaveBeenLastCalledWith(2);
        expect($('#pagination [aria-current="page"]').text()).toBe('2');
    });

    it('keeps keyboard focus on the pagination after changing page', () => {
        listFor(pagedApi()).loadPage(1);

        $('#pagination button[aria-label="Página 2"]').trigger('focus').trigger('click');

        expect(document.activeElement.getAttribute('aria-current')).toBe('page');
        expect(document.activeElement.textContent).toBe('2');
    });

    it('does not steal focus when the list reloads for other reasons', () => {
        $('#unrelated-input').trigger('focus');

        listFor(pagedApi()).loadPage(1);

        expect(document.activeElement.id).toBe('unrelated-input');
    });

    it('shows the empty state and a plural zero total', () => {
        listFor({ list: () => resolved({ data: [], meta: meta(1, 0) }) }).loadPage(1);

        expect($('#person-rows .list-empty')).toHaveLength(1);
        expect($('#total-badge').text()).toBe('0 registros');
    });

    it('uses the singular for a single record', () => {
        listFor({ list: () => resolved({ data: [person()], meta: meta(1, 1) }) }).loadPage(1);

        expect($('#total-badge').text()).toBe('1 registro');
    });

    it('shows an error message when loading fails, but ignores aborted requests', () => {
        listFor({ list: () => rejected({ status: 500 }) }).loadPage(1);
        expect($('#person-rows .list-empty').text()).toContain('No se pudo cargar');

        document.body.innerHTML = LIST_MARKUP;
        listFor({ list: () => rejected({ status: 0, statusText: 'abort' }) }).loadPage(1);
        expect($('#person-rows .person-row--skeleton')).toHaveLength(PAGE_SIZE);
    });
});
