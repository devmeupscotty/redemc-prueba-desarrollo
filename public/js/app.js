import { createApi } from './api.js';
import { createForm } from './form.js';
import { createList, FIRST_PAGE } from './list.js';
import { createValidator } from './validator.js';

const $ = window.jQuery;

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
