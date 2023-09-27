
    //mpa.store receives a string and then attempts to store in localStorage, sessionStorage or as a cookie. The function allows the calling function to select the desired storage format.

    mpa.store = function(k,v,x,m,p) {
        'use strict';
        try {
            var d, t, r = [], z;
            d = new Date();
            t = Math.round((d).getTime() / 1000);
            k = mpa.storageMap + k;
            v = JSON.stringify([t, (t + x), v]);
            if (m == 'l' || m == 'lc') {
                localStorage.setItem(k, v);
                r[0] = (localStorage.getItem(k)) ? true : false;
            }
            if (m == 's'  || m == 'sc') {
                sessionStorage.setItem(k, v);
                r[1] = (sessionStorage.getItem(k)) ? true : false;
            }
            if ((m == 'c' || m == 'lc' || m == 'sc') && navigator.cookieEnabled) {
                d.setTime(d.getTime() + (x*1000));
                z = '; expires=' + d.toGMTString();
                document.cookie = k + '=' + encodeURIComponent(v) + z + ';path=' + p + ';secure;samesite=strict;domain=' + mpa.domainGroup[0];
                r[2] = (document.cookie.indexOf(k) != -1) ? true : false;
            }
            return r;
        } catch(err) { return []; }
    };

    // mpa.retrieve looks for a key value previously stored as localStorage, sessionStorage or as a cookie.

    mpa.retrieve = function(k,m) {
        'use strict';
        try {
            var t, r = [], lsv, ssv, ck, ca, i, ac, v, va, j, av = [], fv = [];
            t = (Math.round((new Date()).getTime() / 1000));
            k = mpa.storageMap + k;
            if (m == 'l' || m == 'lc') {
                lsv = localStorage.getItem(k);
                if (lsv) { r.push(lsv); }
            }
            if (m == 's' || m == 'sc') {
                ssv = sessionStorage.getItem(k);
                if (ssv) { r.push(ssv); }
            }
            if ((m == 'c' || m == 'lc' || m == 'sc') && navigator.cookieEnabled) {
                ck = decodeURIComponent(document.cookie);
                ca = ck.split(';');
                for (i = 0; i < ca.length; i++) {
                    if (ca[i].indexOf(k + '=') != -1) {
                        ac = ca[i].split('=');
                        if (ac[1]) { r.push(ac[1]); }
                        break;
                    }
                }
            }
            if (!r || r.length < 1) {
                v = false;
            } else if (r.length == 1) {
                va = JSON.parse(r);
                if (va[1] > t) {
                    v = va[2];
                }
            } else {
                for (j = 0; j < r.length; j++) {
                    va = JSON.parse(r[j]);
                    if (va[1] > t) {
                        av.push(parseInt(va[0]));
                        fv.push(va[2]);
                    }
                }
                v = fv[av.indexOf(Math.max.apply(null, av))]; // Add the newer entry.
            }
            return v;
        } catch(err) { return err; }
    };

    // mpa.cleanLocalStore purges current local stores which have expired. Useful during SPA initialisation.

    mpa.cleanLocalStore = function(r) {
        'use strict';
        try {
            let t, k, va, kv, pk, pt;
            t = (Math.round((new Date()).getTime() / 1000));
            for (k in localStorage) {
                if (k.indexOf(mpa.storageMap) != -1) {
                    va = JSON.parse(localStorage[k]);
                    kv = parseInt(va[1]);
                    if (!kv || isNaN(kv) || r === true || kv <= t) {
                        localStorage.removeItem(k);
                    }
                }
            }
            return true;
        } catch(e) { return false; }
    };

    // mpa.cleanSessionStore purges current session stores which have expired. Useful during SPA initialisation.

    mpa.cleanSessionStore = function(r) {
        'use strict';
        try { return;
            let t, k, va, kv, pk, pt;
            t = (Math.round((new Date()).getTime() / 1000));
            for (k in sessionStorage) {
                if (k.indexOf(mpa.storageMap) != -1) {
                    va = JSON.parse(sessionStorage[k]);
                    kv = parseInt(va[1]);
                    if (!kv || isNaN(kv) || r === true || kv <= t) {
                        sessionStorage.removeItem(k);
                    }
                }
            }
            return true;
        } catch(e) { return false; }
    };

    // mpa.fullStorageClean purges all storage modes. Typically used in response to login or shopping cart errors.

    mpa.fullStorageClean = function(ra, re, cn) {
        'use strict';
        mpa.cleanLocalStore(true);
        mpa.cleanSessionStore(true);
        mpa.store(cn, '', 1, 'sc', '/');
        if (re === true) { window.location.href = mpa.currentPath; }
    };