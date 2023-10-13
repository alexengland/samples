mpa.listLoader = function() {
    'use strict';
    let loader = mpa.html(null, 'div', null, ['list-loader'], null);
    loader.appendChild(mpa.html(null, 'div', null, ['cube1'], null));
    loader.appendChild(mpa.html(null, 'div', null, ['cube2'], null));
    return loader;
};
mpa.alignPager = function() {
    'use strict';
    let paging = document.getElementById('paging');
    let pv = paging.value;
    let dv = document.getElementById('limit').value;
    let rows = Math.floor(mpa.cache[1]['total'] / dv);
    if (isNaN(rows)) { rows = 0; }
    let valid = [];
    let processed = 0;
    while (processed <= rows) {
        let start = Math.floor(parseInt(processed * dv) + 1);
        let end = Math.floor(start + parseInt(dv) - 1);
        if (start > mpa.cache[1]['total']) {
            break;
        }
        if (end > mpa.cache[1]['total']) {
            end = mpa.cache[1]['total'];
        }
        let value = start + ' - ' + end;
        valid.push(value);
        processed++
    }
    if (valid.indexOf(pv) === -1) {
        if (paging.value > mpa.cache[1]['total']) { dv = mpa.cache[1]['total']; }
        paging.value = (valid[0]) ? valid[0] : '1 - ' + dv;

    }
};
mpa.pagination = function(){
    'use strict';
    let paging, select, rows, html, processed, start, end, value;
    paging = document.getElementById('paging');
    select = document.querySelector('#paging-container .select-group');
    select.innerHTML = '';
    if (!mpa.cache[1]['count'] || mpa.cache[1]['count'] == 0) {
        paging.value = '0';
        return;
    }
    rows = Math.floor(mpa.cache[1]['total'] / mpa.cache[1]['count']);
    html = new DocumentFragment();
    processed = 0;
    while (processed <= rows) {
        start = Math.floor(parseInt(processed * mpa.cache[1]['limit']) + 1);
        end = Math.floor(start + parseInt(mpa.cache[1]['limit']) - 1);
        if (start > mpa.cache[1]['total']) {
            break;
        }
        if (end > mpa.cache[1]['total']) {
            end = mpa.cache[1]['total'];
        }
        value = start + ' - ' + end;
        html.appendChild(mpa.html(null, 'div', null, ['option-box', 'selected'], value));
        processed++
    }
    select.appendChild(html);
    mpa.alignPager();
};
mpa.searchResultsNotification = function() {
    'use strict';
    let offset, limit, total, count, offsetCount, plural;
    offset = (mpa.cache[1]['offset']) ? mpa.cache[1]['offset'] : 1;
    limit = (mpa.cache[1]['limit']) ? mpa.cache[1]['limit'] : 0;
    count = (mpa.cache[1]['count']) ? mpa.cache[1]['count'] : 0;
    total = (mpa.cache[1]['total']) ? mpa.cache[1]['total'] : 0;
    count = (parseInt(offset) + parseInt(limit - 1));
    if (count > total) { count = total; }
    if (total < 1) { offsetCount = '0'; } else { offsetCount = offset + ' - ' + count; }
    if (limit > total) { limit = total; }
    plural = (count !== 1) ? 'es' : '';
    if (total === 0) {
        mpa.tip(document.querySelector('#alerts'), true, ['tip'],
            total + ' match' + plural + ' found', ['Search using the column fields.', 'Results can be ordered using the sort arrows.']);
    } else {
        let test = document.querySelectorAll('.list-group .header input:not([value=""])');
        if (test.length > 0) {
            mpa.tip(document.querySelector('#alerts'), true, ['tip', 'pass'],
                'Showing ' + offsetCount + ' of ' + total + ' match' + plural + '', ['Results can be further refined.', 'Enter additional column searches as required.']);
        }
    }
};
mpa.listSorting = function(e) {
    'use strict';
    let x, c, i;
    x = e.target || e.srcElement;
    i = x.parentNode.parentNode;
    if (x.classList.contains('order-asc')) { c = 'order-desc'; }
    else if (x.classList.contains('order-desc')) { c = 'order-asc'; }
    else if (x.parentElement.querySelector('.order-asc')) { c = 'order-asc'; }
    else { c = 'order-desc'; }
    mpa.removeClasses(x.parentElement, '.orderby', false);
    mpa.removeClasses(x.parentElement, '.order-asc', false);
    mpa.removeClasses(x.parentElement, '.order-desc', false);
    x.classList.add('orderby');
    x.classList.add(c);
    if (document.querySelector('#' + i.id + ' > div:not(.header)')) { mpa.search(i.dataset.group); } // Only rerun search if we have results showing.
};
mpa.searchStorage = function(s) {
    'use strict';
    let inputs, i, storeValues, existingValue, orderByCell, orderBy, order;
    inputs = document.querySelectorAll('.list-group .header > div input');
    storeValues = {'values': {}, 'limit': '', 'offset': '', 'orderby': '', 'order': ''};
    for (i = 0; i < inputs.length; i++) {
        existingValue = inputs[i].value;
        if (existingValue && existingValue.replace(/\s/g, '').length) {
            storeValues.values[inputs[i].id] = existingValue;
        } else {
            storeValues.values[inputs[i].id] = '';
        }
    }
    orderByCell = document.querySelector('.orderby');
    orderBy = (orderByCell) ? orderByCell.dataset.orderby : 'id';
    order = (orderByCell.classList.contains('order-asc')) ? 'asc' : 'desc';
    storeValues.limit = document.getElementById('limit').value;
    storeValues.offset = document.getElementById('paging').value;
    storeValues.orderby = orderBy;
    storeValues.order = order;
    mpa.store(s, storeValues, 86400, 's', '/');
    return storeValues;
};
mpa.search = function(c) {
    'use strict';
    let storage, value, params = {}, filter, base;
    storage = mpa.searchStorage(c);
    mpa.listen(true, 'click', '.link', mpa.link, false);
    mpa.removeClasses(document.body, '.list-loader', true);
    mpa.removeClasses(document.body, '.tip', true);
    document.getElementById('loader').appendChild(mpa.listLoader());
    params['limit'] = storage.limit;
    params['offset'] = storage.offset;
    params['orderby'] = storage.orderby;
    params['order'] = storage.order;
    filter = document.querySelector('.list-group');
    if (filter.dataset.filter) { params['filter'] = filter.dataset.filter; }
    if (filter.dataset.designation) { params['designation'] = filter.dataset.designation; }
    for (value in storage.values) { params[value] = storage.values[value]; }
    base = c.split('_');
    base = base[0] + '_' + base[1];
    switch (base) {
        case 'order_search':
            mpa.api(false, false, 'rao', params, true, mpa.ordersList);
            break;
        case 'note_search':
            mpa.api(false, false, 'rae', params, true, mpa.notesList);
            break;
        case 'company_search':
            mpa.api(false, false, 'ran', params, true, mpa.companiesList);
            break;
        case 'address_search':
            mpa.api(false, false, 'raa', params, true, mpa.addressesList);
            break;
        case 'log_search':
        case 'log_communication':
            mpa.api(false, false, 'ral', params, true, mpa.logsList);
            break;
        default:
            break;
    };
};