    mpa.animateBlob = function() {
        'use strict';
        var blob1, blob2, blob3, current, i, radius = [], random;

        blob1 = document.getElementById('blob1');
        blob2 = document.getElementById('blob2');
        blob3 = document.getElementById('blob3');

        function randomPercentage() {
            return Math.round(Math.random() * (100 - 1) + 1);
        }

        function randomValues() {

            radius = []

            for (i = 0; i < 9; i++) {

                if (i === 4) {

                    radius.push('/');

                } else {

                    radius.push(randomPercentage() + '%');

                }

            }
            return radius.join(' ');

        }

        if (blob1) {

            blob1.style.borderRadius = randomValues();
            blob2.style.borderRadius = randomValues();
            blob3.style.borderRadius = randomValues();

        }

    };

    @keyframes lava {

        0%     { filter:brightness(90%); }
        50.0%  { filter:brightness(120%); }
        100.0% { filter:brightness(90%); }
    }

    .blob {
        filter:brightness(90%);
        -webkit-animation: lava 7000ms infinite ease-in-out;
        animation: lava 7000ms infinite ease-in-out;
        -webkit-transition:all 1500ms linear;
        transition:all 1500ms linear;
    }