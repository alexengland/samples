
    // mpa.json takes a return JSON string from the API server and extracts the JSON content and the server's response code into a uniform and digestible format.

    mpa.json = function(x) {
        'use strict';
        let rt, rc, rs, ro = {'rc': 403, 'rs': ''};
        try {
            rt = JSON.parse(x.responseText);
            ro['rc'] = (!isNaN(rt.rc)) ? parseInt(rt.rc) : 403;
            ro['rs'] = rt.rs;
            if (rt.ca) {
                if (rt.ca.length > 10) { mpa.cache[0] = rt.ca; }
                else { mpa.cache[0] = ''; }
            }
            return ro;
        } catch (err) {
            return {'rc': 403, 'rs': ''};
        };
    };

    // mpa.pack takes data from calling functions and packages into an acceptable format for transmission to the API. Encoding can be auto detected based on attachments. A tz stamp is added to the package so the API knows the senders timezone.

    mpa.pack = function(m,o,f) {
        'use strict';
        let fd, fn, fe, d = [], oi, z, zo;
        if (f === 'multipart/form-data') {
            d = new FormData(document.getElementById(o['form']));
            d.append('form', o['form']);
            d.append('cache', mpa.cache[0]);
        } else {
            for (oi in o) {
                d.push(oi + '=' + encodeURIComponent(o[oi]));
            }
            if (m === true) {
                z = mpa.timezone();
                for (zo in z) { d.push(zo + '=' + encodeURIComponent(z[zo])); }
                d.push('cache=' + mpa.cache[0]);
            }
            d = d.join('&');
        }
        return d;
    };

    // mpa.api takes care of transmitting data to the API using XMLHttpRequest. It can utilise different encoding, GET or POST methods and handle errors gracefully. It also detects if the browser is online prior to transmission, handles timeouts and can forward further actions to calling functions on completion.

    mpa.api = function(m,f,a,o,c,s) {
        'use strict';
        let d, u, x, r, b, i, p;
        if (!mpa.onlineCheck('Your browser went offline!', ['You cannot save any changes while offline.', 'Please check your connection is stable and try again.'])) { return; }
        f = (!f) ? 'application/x-www-form-urlencoded' : 'multipart/form-data';
        d = mpa.pack(m,o,f);
        u = mpa.appPath + 'api/' + a;
        if (m === false) { u += '?' + d; }
        m = (m === true) ? 'POST' : 'GET';
        x = new XMLHttpRequest();
        x.open(m, u, true);
        if (f === 'application/x-www-form-urlencoded') { x.setRequestHeader('Content-Type', f); }
        x.setRequestHeader('Accept', 'application/json, text/plain; q=0.01');
        x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        x.setRequestHeader('Cache-Control', 'no-cache');
        x.onreadystatechange = function() {
            if (x.status != 200) {
                if (x.status == 403) {
                    mpa.error(x.status);
                }
                mpa.network('Connection problem!', ['Something went wrong between your browser and the server.', 'Try refreshing the page, and possibly your browser cache, then try again.']);
                x.abort();
                return;
            }
            if (x.readyState == 4 && x.status == 200 && x.responseURL == u) {
                try {
                    r = mpa.json(x);
                    mpa.cache[1] = '';
                    switch (r.rc) {
                        case 790:
                            mpa.freeze(null, false);
                            mpa.tip(document.getElementById('midway-alerts'), true, ['tip', 'warn'], 'Payment processing error...', ['The error returned by your bank was: <u>' + r.rs[0] + '</u>', 'Please check that the details are correct, and that there are no blocks on the card before trying again.'], true, true, false);
                            mpa.attribute(document.querySelector('input[name="order_id"]'), {'value': r.rs[1]}, false);
                            break;
                        case 791:
                            mpa.freeze(null, false);
                            mpa.tip(document.getElementById('midway-alerts'), true, ['tip', 'warn'], 'PayPal system error...', ['The PayPal gateway appears to be down right now.', 'Please try again in 15 minutes or try an alternative payment method.'], true, true, false);
                            mpa.attribute(document.querySelector('input[name="order_id"]'), {'value': r.rs}, false);
                            break;
                        case 800:
                            if (c === true) { mpa.cache[1] = r.rs; }
                            if (s !== null) { s(); }
                            break;
                        case 805:
                            mpa.freeze(null, false);
                            if (r.rs[3]) {
                                b = r.rs[3].split('|');
                                if (b && b[0] == 'button') {
                                    r.rs[3] = '<input type="button" class="' + b[1] + '" value="' + b[2] + '" data-u="' + b[3] + '" data-m="' + b[4] + '" tabindex="-1">';
                                    r.rs[2].push(r.rs[3]);
                                }
                            }
                            if (r.rs[4]) {
                                p = r.rs[4];
                            } else {
                                p = [true, true, true];
                            }
                            mpa.tip(document.getElementById('alerts'), true, r.rs[0], r.rs[1], r.rs[2], p[0], p[1], p[2]);
                            mpa.listen(true, 'click', '.link', mpa.link, false);
                            break;
                        case 806:
                            mpa.freeze(null, false);
                            mpa.tip(document.getElementById('alerts'), true, r.rs[0], r.rs[1], r.rs[2], true, false, false);
                            if (r.rs[3]) {
                                let form = document.getElementById(r.rs[3]);
                                let objects = form.querySelectorAll('input:not([type=button]):not([name=csrf]), textarea, .div-form-input');
                                for (let i = 0; i < objects.length; i++) {
                                    mpa.attribute(objects[i], {'value': ''}, false);
                                }
                                let focus = form.querySelector('[autofocus]');
                                if (focus) { focus.focus(); }
                            }
                            break;
                        case 810:
                            window.location.href = mpa.appPath + 'login';
                            break;
                        default:
                            throw r.rc;
                            break;
                    }
                } catch(err) {
                    x.abort();
                    mpa.network('Browser error detected!', ['Make sure you are running an updated browser like Chrome, Safari or similar.', 'Try refreshing the page, and possibly your browser cache, then try again.']);
                    mpa.error(err);
                    mpa.freeze(null, false);
                    return false;
                }
            }
        }
        x.timeout = 30000;
        x.ontimeout = function() {
            x.abort();
            mpa.error(408);
            return false;
        }
        if (m === 'POST') { x.send(d); }
        else { x.send(); }
    };