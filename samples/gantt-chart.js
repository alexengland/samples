mpc.data = {
    "tasks": {
        1000001: {"type": "1", "title": "Task1", "description": "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse orci ligula, malesuada vel sapien eu, eleifend consectetur velit. Phasellus sed porttitor diam. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Nullam rhoncus ut neque vel vehicula. Donec consequat enim ac massa eleifend cursus. Mauris feugiat ornare maximus. Aliquam viverra ligula id nunc luctus rutrum. Nulla iaculis urna vitae est semper, ac egestas lorem blandit. Nunc facilisis mattis nisi, sit amet finibus justo varius vitae.", "start": 1603061876, "end": 1604487599, "effort": 432000, "align": true, "progress": 12.00},
        1000002: {"type": "1", "title": "Task2", "description": "Lorem ipsum dolor sit amet, consectetur adipiscing elit.", "start": 1603061876, "end": 1604487599, "effort": null, "align": true, "progress": 9.00},
        1000003: {"type": "1", "title": "Task3", "description": "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse orci ligula, malesuada vel sapien eu, eleifend consectetur velit. Phasellus sed porttitor diam. Orci varius natoque penatibus et magnis dis parturient montes, nascetur ridiculus mus. Nullam rhoncus ut neque vel vehicula. Donec consequat enim ac massa eleifend cursus.", "start": 1603195200, "end": 1608863129, "effort": 432000, "align": true, "progress": 0},
        1000004: {"type": "1", "title": "Task4", "description": "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse orci ligula, malesuada vel sapien eu, eleifend consectetur velit.", "start": 1603026000, "end": 1604487599, "effort": 432000, "align": true, "progress": 0},
        1000005: {"type": "2", "title": "Task5", "description": "Lorem ipsum dolor sit amet, consectetur adipiscing elit. Suspendisse orci ligula, malesuada vel sapien eu, eleifend consectetur velit.", "start": 1604487599, "end": 1604487599, "effort": 432000, "align": true, "progress": 0},
        1000006: {"type": "1", "title": "Task6", "description": "Nulla iaculis urna vitae est semper, ac egestas lorem blandit. Nunc facilisis mattis nisi, sit amet finibus justo varius vitae.", "start": 1603061876, "end": 1604487599, "effort": 432000, "align": true, "progress": 33.00}
    },
    "index": [
        {"id": 1000001, "children": [
                {"id": 1000002, "children": [
                        {"id": 1000003},
                        {"id": 1000004}
                    ]},
                {"id": 1000005}
            ]},
        {"id": 1000006}
    ],
    "increments": {
        "tasks": 1000006,
        "assignments": 2000003,
        "materials": 3000003,
        "services": 4000002,
        "people": 5000002,
        "miscellaneous": 6000002,
        "notes": 7000002,
        "breaks": 8000004,
        "holidays": 9000003
    },
    "relationships": [
        {'from': 1000002, "to": 1000003, "type": 1},
        {'from': 1000001, "to": 1000004, "type": 2}
    ],
    "progress": {
        1000002: [{"person": 5000002, "role": "Team Leader", "hours": "1"}]
    },
    "assignments": {
        1000002: [{"id": 2000002, "person": 5000002, "role": "Team Leader", "assigned": 14760}],
        1000003: [{"id": 2000003, "person": 5000002, "role": "Team Leader", "assigned": 14760}]
    },
    "materials": {
        1000002: [
            {"id": 3000002, "name": "Timber", "base": 10.00, "unit": 10, "markup": 10.00, "type": "percentage", "currency": "AUD", "status": "purchased"},
            {"id": 3000003, "name": "Paint", "base": 12.00, "unit": 4, "markup": 10.00, "type": "flat", "currency": "AUD", "status": "pending"}
        ]
    },
    "services": {
        1000002: [
            {"id": 4000002, "name": "CAD Design", "base": 190.00, "unit": 1, "markup": 0.00, "type": "percentage", "currency": "AUD", "status": "in progress", "status": "AUD"}
        ]
    },
    "people": {
        1000002: [
            {"id": 5000002, "name": "Joe Smith", "base": 50.00, "overtime": 70.00, "block": "per day", "workdays": ["","08:00-16:00","08:00-16:00","08:00-12:00","08:00-16:00","8:00-16:00",""], "leave": [1607382360, 1607382361], "markup": 0.00, "type": "percentage", "currency": "AUD", "phone": "0400800900", "email": "email@domain.com"}
        ]
    },
    "miscellaneous": {
        1000002: [
            {"id": 6000002, "name": "Lunches", "base": 60.00, "unit": 5, "markup": 0.00, "type": "percentage", "currency": "AUD", "status": "purchased", "status": "AUD"}
        ]
    },
    "notes": {
        1000002: [
            {"id": 7000001, "task": 1000002, "created": 1607382360, "note": "1"},
            {"id": 7000002, "task": 1000002, "created": 1607382374, "note": "2"}
        ]
    },
    "preferences": {
        "projectStart": 0,
        "projectEnd": 0,
        "dayRange": 0,
        "segmentPreference": 1,
        "operatingSchedule": [
            {}, // Sunday
            {"start": "08:00", "end": "16:00"},
            {"start": "08:00", "end": "16:00"},
            {"start": "08:00", "end": "16:00"},
            {"start": "08:00", "end": "16:00"},
            {"start": "08:00", "end": "16:00"},
            {} // Saturday
        ],
        "breaks": [
            {"id": 8000002, "start": "15:30", "end": "15:45", "days": [false, true, true, true, true, true, false]},
            {"id": 8000003, "start": "12:30", "end": "13:15", "days": [false, true, true, true, true, true, false]},
            {"id": 8000004, "start": "15:00", "end": "15:15", "days": [false, true, true, true, true, true, false]}
        ],
        "holidays": [
            {"id": 9000002, "day": 1601902800, "description": ""},
            {"id": 9000003, "day": 1601989200, "description": ""}
        ],
        "taskWidthAdjuster": 200,
        "focus": [],
        "relationshipMode": false,
        "relationshipFrom": null,
        "dragged": [],
        "controlDrag": [],
        "controlResize": [],
        "controlSize": [],
        "controlSnapped": '',
        "definitions": {
            "segments": [3600,86400,604800,2592000]
        },
        "view": [],
        "sortedDays": [1,2,3,4,5,6,0], // Start from 1 so Monday is first day.
        "weekDays": ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
    }
};

mpc.defaults = { // When blank new projects are created or a base reference is needed, these user settings are applied.
    "operatingSchedule": [
        {}, // Sunday
        {"start": "09:00", "end": "16:00"},
        {"start": "08:00", "end": "16:00"},
        {"start": "08:00", "end": "16:00"},
        {"start": "02:00", "end": "15:00"},
        {"start": "08:00", "end": "16:00"},
        {} // Saturday
    ]
};

// Generic System Functions

mpc.nospace = function(v) {
    'use strict';
    return (v) ? v.replace(/\s/g, '') : '';
};
mpc.singlespace = function(v) {
    'use strict';
    return (v) ? v.replace(/\s\s+/g, ' ') : '';
};
mpc.titlecase = function(v) {
    'use strict';
    let split, i;
    split = v.split(' ');
    for (i = 0; i < split.length; i++) {
        split[i] = split[i].charAt(0).toUpperCase() + split[i].substring(1);
    }
    return split.join(' ');
};
mpc.titlecaseall = function(v) {
    'use strict';
    if (!v) return '';
    v = v.split(' ');
    for (var i = 0; i < v.length; i++) {
        if (v[i]) { v[i] = v[i].charAt(0).toUpperCase() + v[i].slice(1).toLowerCase(); }
    }
    return (v) ? v.join(' ') : '';
};
mpc.sentence = function(v) {
    'use strict';
    if (!v) return '';
    v = mpc.singlespace(v);
    return v.charAt(0).toUpperCase() + v.substring(1);
};
mpc.numeric = function(v) {
    'use strict';
    return v.replace(/[^0-9]/gi, '');
};
mpc.decimal = function(v) {
    'use strict';
    return v.replace(/[^0-9.-]/gi, '');
};
mpc.thousands = function(v,r,z) {
    'use strict';
    if (r === true) { v = Math.round(v); }
    if (z === true) {
        var p = v.split('.');
        if (p[1] && p[1] === '00') { v = Math.round(v); }
    }
    return v.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
};
mpc.makeDecimal = function(v) {
    'use strict';
    v = mpc.decimal(v);
    let exploded = v.split('.', 2);
    if (exploded) {
        if (!exploded[0] || isNaN(parseInt(exploded[0]))) {
            exploded[0] = '0';
        }
        while (exploded[0].length > 1 && exploded[0].charAt(0) == '0') {
            exploded[0] = exploded[0].substr(1);
        }
        if (!exploded[1] || isNaN(parseInt(exploded[1]))) {
            exploded[1] = '00';
        }
        if (exploded[1].length === 1) {
            exploded[1] = exploded[1].toString() + '0';
        }
        exploded[1] = exploded[1].substring(0, 2);
        v = exploded[0] + '.' + exploded[1];
    } else {
        v = '0.00';
    }
    return v;
};
mpc.fractionSeconds = function(v) {
    'use strict';
    v = v.toString();
    v = v.split('.');
    v[0] = parseInt(v[0]) * 3600;
    if (v[1] && v[1].length < 2) {
        v[1] = v[1] + '0';
    } else {
        v[1] = '0';
    }
    v[1] = parseInt(v[1].substring(0, 2));
    v[1] = v[1] * 60;
    return v[0] + v[1];
};
mpc.fractionHoursMinutes = function(v) {
    'use strict';
    v = v.toString();
    v = v.split('.');
    v[0] = parseInt(v[0]);
    if (v[1].length < 2) {
        v[1] = v[1] + '0';
    }
    v[1] = parseInt(v[1].substring(0, 2));
    v[1] = (60 / 100) * v[1];
    return [v[0],v[1]];
};