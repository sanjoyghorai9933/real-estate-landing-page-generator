/**
 * vendor-shim.js
 * ---------------------------------------------------------
 * main.js is a generic multi-purpose theme framework bundle that
 * unconditionally calls into several optional plugins (Swiper, WOW,
 * isotope, stellar parallax, magnificPopup, etc.) as part of its
 * generic page-init routine. This landing page theme does not use
 * any of those features (no sliders/portfolio grids/parallax on this
 * page) and does not bundle those plugin libraries, so those calls
 * were throwing "X is not defined" and silently halting the rest of
 * main.js's initialization — including things this page DOES use,
 * like the mobile menu and sticky header.
 *
 * This shim defines harmless no-op fallbacks for exactly those calls,
 * only if the real library isn't already present, so main.js can run
 * to completion. It changes no visible behavior on this page.
 */
(function ($) {
    if (typeof window.Swiper === 'undefined') {
        window.Swiper = function () {
            return {
                update: function () {},
                destroy: function () {},
                detachEvents: function () {},
                slideTo: function () {},
                activeIndex: 0,
            };
        };
    }

    if (typeof window.WOW === 'undefined') {
        window.WOW = function () {
            return { init: function () {}, removeBox: function () {} };
        };
    }

    if (typeof window.classie === 'undefined') {
        window.classie = {
            toggle: function () {},
            add: function () {},
            remove: function () {},
            has: function () { return false; },
        };
    }

    if (typeof window.revslider_showDoubleJqueryError === 'undefined') {
        window.revslider_showDoubleJqueryError = function () {};
    }

    if ($ && $.fn) {
        ['imagesLoaded', 'isotope', 'appear', 'countTo', 'countdown', 'equalize', 'justifiedGallery', 'magnificPopup', 'smoothScroll', 'fitVids', 'skillBars']
            .forEach(function (name) {
                if (typeof $.fn[name] === 'undefined') {
                    $.fn[name] = function (opts, cb) {
                        // imagesLoaded/appear/etc are commonly called with a
                        // callback as the first or second argument — invoke it
                        // immediately so any dependent code still runs.
                        if (typeof opts === 'function') opts.call(this);
                        else if (typeof cb === 'function') cb.call(this);
                        return this;
                    };
                }
            });

        if (typeof $.stellar === 'undefined') {
            $.stellar = function () {};
        }
    }
})(window.jQuery);
