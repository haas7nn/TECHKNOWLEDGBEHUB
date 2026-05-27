// shared JS utilities used across all viewer and student panel pages

'use strict';

// auto dismiss flash messages after a short delay
(function () {
    // look for a flash message element on the page
    var flash = document.querySelector('.flash-message, .alert-flash, .alert');
    if (flash) {
        // fade the message out after 4.5 seconds then remove it from the DOM
        setTimeout(function () {
            flash.style.transition = 'opacity .6s';
            flash.style.opacity   = '0';
            setTimeout(function () { flash.remove(); }, 650);
        }, 4500);
    }
})();

// make all anchor links on the page scroll smoothly to their target
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            // find the element the link is pointing to
            var target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                // prevent the default jump and smoothly scroll to the target instead
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
});

// lazy load images using IntersectionObserver so they only load when scrolled into view
document.addEventListener('DOMContentLoaded', function () {
    // only run this if the browser supports IntersectionObserver
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                // when an image enters the viewport swap in the real src from data-src
                if (entry.isIntersecting) {
                    var img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        // remove the attribute so the image is not loaded again
                        img.removeAttribute('data-src');
                    }
                    // stop watching this image once it has loaded
                    observer.unobserve(img);
                }
            });
        });
        // start watching every image that has a data-src attribute
        document.querySelectorAll('img[data-src]').forEach(function (img) {
            observer.observe(img);
        });
    }
});


// clear the search input when the user presses Escape while typing in it
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.search-input').forEach(function (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                // empty the field and keep focus so the user can start a new search
                input.value = '';
                input.focus();
            }
        });
    });
});
