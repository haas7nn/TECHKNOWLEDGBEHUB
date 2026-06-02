// creator panel utilities

'use strict';

// auto dismiss flash after delay
(function () {
    const flash = document.querySelector('.flash-message, .alert');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity .6s';
            flash.style.opacity   = '0';
            setTimeout(function () { flash.remove(); }, 650);
        }, 4000);
    }
})();

// confirm dialog before data-confirm forms submit
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});

// live character counter for data-maxlength textareas
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('textarea[data-maxlength]').forEach(function (ta) {
        var max     = parseInt(ta.dataset.maxlength, 10);
        var counter = document.createElement('small');
        counter.className = 'char-counter';
        counter.style.cssText = 'color:#7f8c8d;font-size:12px;float:right;';
        ta.parentNode.insertBefore(counter, ta.nextSibling);
        function update() {
            var left = max - ta.value.length;
            counter.textContent = ta.value.length + ' / ' + max;
            // turn red when fewer than 20 chars remain
            counter.style.color = left < 20 ? '#e74c3c' : '#7f8c8d';
        }
        ta.addEventListener('input', update);
        update();
    });
});

// mark active nav item by matching current filename
document.addEventListener('DOMContentLoaded', function () {
    var path = window.location.pathname.split('/').pop();
    document.querySelectorAll('.nav-item').forEach(function (a) {
        if (a.getAttribute('href') === path) {
            a.classList.add('active');
        }
    });
});

// open modal by id
window.openModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'flex';
};

// close modal by id
window.closeModal = function (id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'none';
};

// click backdrop to close modal
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('modal-backdrop') || e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});
