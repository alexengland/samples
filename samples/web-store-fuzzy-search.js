mpa.selectSearchProducts = function(e) {
    'use strict';
    let source, parent, selectGroup, value, values, products, matches, match, count, descriptor, product, productName, versions, word, version, versionName, css, html;
    source = e.target || e.srcElement;
    parent = source.parentNode;
    selectGroup = parent.querySelector('.select-group');
    value = source.value;
    value = mpa.singlespace(value.toLowerCase()).trim();
    values = value.split(' ');
    products = mpa.cache[3][0];
    matches = {};
    if (products && values.length) {
        for (product in products) {
            count = 0;
            productName = products[product]['name'].toLowerCase();
            versions = products[product]['versions'];
            if (versions) {
                for (word in values) {
                    if (values[word] == '' || !values[word]) { continue; }
                    if (productName.indexOf(values[word]) > -1) {
                        count++;
                    }
                }
                for (version in versions) {
                    versionName = versions[version].toLowerCase();
                    for (word in values) {
                        if (values[word] == '' || !values[word]) { continue; }
                        if (versionName.indexOf(values[word]) > -1) {
                            count++;
                        }
                    }
                }
            }
            if (count > 0) {

                descriptor = (products[product]['descriptor']) ? ' per ' + products[product]['descriptor'].toLowerCase() : '';
                matches[productName] = ['<strong>' + products[product]['name'] + '</strong>&nbsp;&nbsp;&middot;&nbsp;&nbsp;' + products[product]['category'] + '&nbsp;&nbsp;&middot;&nbsp;&nbsp;Starts from $' + products[product]['start'] + ' ' + mpa.cache[3][2] + descriptor, products[product]['uri']];

            }
        }
    }
    mpa.removeClasses(selectGroup, '.option-box', true);
    if (Object.keys(matches).length) {
        for (match in matches) {
            html = mpa.html(null, 'div', {'data-link': mpa.appPath + 'store/' + matches[match][1], 'data-mode': '1'}, ['option-box'], matches[match][0]);
            selectGroup.appendChild(html);
        }
        selectGroup.style.setProperty('display', 'block', 'important');
        document.querySelector('.store-categories').classList.add('dim');
    } else {
        selectGroup.style.setProperty('display', 'none', 'important');
        document.querySelector('.store-categories').classList.remove('dim');
    }
    mpa.listen(true, 'focusout', source, function() { document.querySelector('.store-categories').classList.remove('dim'); }, false);
    mpa.selectListeners();
};