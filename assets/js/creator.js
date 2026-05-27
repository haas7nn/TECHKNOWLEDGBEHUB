// shared JS utilities used across all creator panel pages

'use strict';

// auto dismiss flash messages after a short delay so they do not stay on screen forever
(function () {
    // look for a flash or alert element on the current page
    const flash = document.querySelector('.flash-message, .alert');
    if (flash) {
        // wait 4 seconds then fade the message out and remove it from the DOM
        setTimeout(function () {
            flash.style.transition = 'opacity .6s';
            flash.style.opacity   = '0';
            setTimeout(function () { flash.remove(); }, 650);
        }, 4000);
    }
})();

// show a confirmation dialog before any form that has a data-confirm attribute is submitted
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            // if the user clicks cancel stop the form from submitting
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});

// add a live character counter below any textarea that has a data-maxlength attribute
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-maxlength]').forEach(function (ta) {
        // read the maximum length from the attribute
        var max      = parseInt(ta.dataset.maxlength, 10);
        // create a small element to display the current count
        var counter  = document.createElement('small');
        counter.className = 'char-counter';
        counter.style.cssText = 'color:#7f8c8d;font-size:12px;float:right;';
        ta.parentNode.insertBefore(counter, ta.nextSibling);
        // update the counter text and turn it red when running low on characters
        function update() {
            var left = max - ta.value.length;
            counter.textContent = ta.value.length + ' / ' + max;
            counter.style.color = left < 20 ? '#e74c3c' : '#7f8c8d';
        }
        // update whenever the user types something
        ta.addEventListener('input', update);
        // show the initial count when the page loads
        update();
    });
});

// highlight the nav item that matches the current page URL
document.addEventListener('DOMContentLoaded', function () {
    // get just the filename part of the current URL
    var path = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(function (a) {
        // add the active class if the link href matches the current filename
        if (a.getAttribute('href') === path) {
            a.classList.add('active');
        }
    });
});

// show a modal by finding it by id and setting its display to flex
window.openModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'flex';
};

// hide a modal by finding it by id and setting its display to none
window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
};

// close any open modal when the user clicks on the dark backdrop behind it
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop') || e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});
