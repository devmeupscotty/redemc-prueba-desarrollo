const $ = window.jQuery;

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
