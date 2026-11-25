import { createValidator } from './validator.js';

const $ = window.jQuery;

const HTTP_UNPROCESSABLE = 422;
const ABORTED = 'abort';

const CSS = Object.freeze({
    invalid: 'is-invalid',
    valid: 'is-valid',
    hidden: 'd-none',
});

const TEXT = Object.freeze({
    networkError: 'No se pudo conectar con el servidor. Revisá tu conexión e intentá nuevamente.',
    serverError: 'Ocurrió un error inesperado. Intentá nuevamente en unos instantes.',
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

export function createForm({ $form, fields, validator, api, onCreated = () => {} }) {
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
        if (xhr.status === HTTP_UNPROCESSABLE && xhr.responseJSON?.errors) {
            applyServerErrors($form, xhr.responseJSON.errors);
            return;
        }
        showAlert(xhr.status === 0 ? TEXT.networkError : TEXT.serverError);
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
            .fail((xhr) => {
                if (xhr.statusText !== ABORTED) {
                    handleFailure(xhr);
                }
            })
            .always(() => setLoading(false));
    });

    return { reset };
}

function showToast(element) {
    if (element && window.bootstrap) {
        window.bootstrap.Toast.getOrCreateInstance(element).show();
    }
}

function init() {
    const config = JSON.parse($('#app-config').text());
    const api = createApi(config.endpoint);

    createForm({
        $form: $('#person-form'),
        fields: config.fields,
        validator: createValidator(config),
        api,
        onCreated: () => showToast(document.getElementById('success-toast')),
    });
}

if (document.getElementById('app-config')) {
    $(init);
}
