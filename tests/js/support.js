import $ from 'jquery';

window.jQuery = $;

export { $ };

export const PAGE_SIZE = 8;

export const person = (overrides = {}) => ({
    id: 1,
    name: 'Ana Pérez',
    email: 'ana@mail.com',
    phone: '097680102',
    createdAt: '2026-11-25T15:30:00+00:00',
    ...overrides,
});

export const resolved = (value) => $.Deferred().resolve(value).promise();
export const rejected = (xhr) => $.Deferred().reject({ statusText: 'error', ...xhr }).promise();
