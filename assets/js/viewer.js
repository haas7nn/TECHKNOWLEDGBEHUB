// viewer and student panel utilities

'use strict';

// auto dismiss flash after delay
(function () {
    var flash = document.querySelector('.flash-message, .alert-flash, .alert');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity .6s';
            flash.style.opacity   = '0';
            setTimeout(function () { flash.remove(); }, 650);
        }, 4500);
    }
})();

// smooth scroll for anchor links
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                // smooth scroll instead of instant jump
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

// lazy load images with IntersectionObserver
document.addEventListener('DOMContentLoaded', function () {
    // only if browser supports IntersectionObserver
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        // swap in real src from data-src
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });
        document.querySelectorAll('img[data-src]').forEach(function (img) {
            observer.observe(img);
        });
    }
});


// escape clears and refocuses search input
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.search-input').forEach(function (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = '';
                input.focus();
            }
        });
    });
});
