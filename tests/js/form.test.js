import { beforeEach, describe, expect, it, vi } from 'vitest';
import { $, person, rejected, resolved } from './support.js';
import { createForm } from '../../public/js/form.js';
import { createValidator } from '../../public/js/validator.js';
import { loadServerRules } from './server-rules.js';

const rules = loadServerRules();
const UNPROCESSABLE_STATUS = 422;
const SERVER_ERROR_MESSAGE = 'Error inesperado del servidor.';

const FORM_MARKUP = `
    <form id="person-form" novalidate>
        <input class="form-control" id="name" name="name">
        <div class="invalid-feedback" id="name-error"></div>
        <input class="form-control" id="email" name="email">
        <div class="invalid-feedback" id="email-error"></div>
        <input class="form-control" id="phone" name="phone">
        <div class="invalid-feedback" id="phone-error"></div>
        <div class="alert d-none" id="form-alert"></div>
        <button type="submit" id="submit-button"><span class="spinner-border d-none"></span>Guardar</button>
    </form>`;

const fill = (values) => {
    for (const [field, value] of Object.entries(values)) {
        $(`#${field}`).val(value);
    }
};

const formWith = (api, onCreated = vi.fn()) => {
    createForm({
        $form: $('#person-form'),
        fields: rules.fields,
        validator: createValidator(rules),
        api,
        unprocessableStatus: UNPROCESSABLE_STATUS,
        serverErrorMessage: SERVER_ERROR_MESSAGE,
        onCreated,
    });

    return onCreated;
};

beforeEach(() => {
    document.body.innerHTML = FORM_MARKUP;
});

describe('createForm', () => {
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
        formWith({ create: () => rejected({ status: UNPROCESSABLE_STATUS, responseJSON: { errors } }) });
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

    it('shows the server error message on unexpected failures', () => {
        formWith({ create: () => rejected({ status: 500 }) });
        fill({ name: 'Ana', email: 'ana@mail.com' });

        $('#person-form').trigger('submit');

        expect($('#form-alert').text()).toBe(SERVER_ERROR_MESSAGE);
        expect($('#form-alert').hasClass('d-none')).toBe(false);
        expect($('#email').val()).toBe('ana@mail.com');
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
