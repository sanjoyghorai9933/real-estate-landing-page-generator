$(function () {
    var $carousel = $("#carouselExampleCaptions");
    if (!$carousel.length || typeof $carousel.carousel !== "function") {
        return;
    }

    if ($carousel.data("bs.carousel")) {
        $carousel.carousel("dispose");
    }

    $carousel.carousel({
        interval: 5000,
        pause: false,
        wrap: true,
        touch: true
    });

    $carousel.on("slide.bs.carousel", function () {
        $carousel.find(".hero-banner__carousel-btn").prop("disabled", true);
    });

    $carousel.on("slid.bs.carousel", function () {
        $carousel.find(".hero-banner__carousel-btn").prop("disabled", false);
    });

    $(".hero-banner__carousel-btn").on("click", function (e) {
        e.preventDefault();
        var action = $(this).attr("data-slide");
        if (!action) {
            return;
        }
        $carousel.carousel(action);
    });

    $(".hero-banner__dots [data-slide-to]").on("click", function (e) {
        e.preventDefault();
        var index = parseInt($(this).attr("data-slide-to"), 10);
        if (!isNaN(index)) {
            $carousel.carousel(index);
        }
    });
});

$(".mobile-trigger").click(function () {
    $("body").toggleClass("mobile-open");
    if ($("body").hasClass("mobile-open")) {
        $(".menu-wrapper").scrollTop(0);
    }
});

$(".menu-wrapper .nav-link, .mobile-menu__cta").click(function () {
    $("body").removeClass("mobile-open");
});

$(".has-submenu").click(function () {
    $(this).toggleClass("child-open");
    $(this).children(".submenu").slideToggle();
});


// read more button
$(".moreless-button").click(function () {
    $(".moretext").slideToggle(10);
    if ($(".moreless-button").text() == "Read more") {
        $(this).text("Read less");
    } else {
        $(this).text("Read more");
    }
});

// popup js
$('.without-caption').magnificPopup({
    type: 'image',
    closeOnContentClick: true,
    closeBtnInside: false,
    mainClass: 'mfp-no-margins mfp-with-zoom', // class to remove default margin from left and right side
    image: {
        verticalFit: true
    },
    zoom: {
        enabled: true,
        duration: 300 // don't foget to change the duration also in CSS
    }
});

$('.with-caption').magnificPopup({
    type: 'image',
    closeOnContentClick: true,
    closeBtnInside: false,
    mainClass: 'mfp-with-zoom mfp-img-mobile',
    image: {
        verticalFit: true,
        titleSrc: function (item) {
            return item.el.attr('title') + ' &middot; <a class="image-source-link" href="' + item.el.attr('data-source') + '" target="_blank"></a>';
        }
    },
    zoom: {
        enabled: true
    }
});


// floor plan tab js
if ($(".tabs-box").length) {
    $(".tabs-box .tab-buttons .tab-btn").on("click", function (e) {
        e.preventDefault();
        var target = $($(this).attr("data-tab"));

        if ($(target).is(":visible")) {
            return false;
        } else {
            target
                .parents(".tabs-box")
                .find(".tab-buttons")
                .find(".tab-btn")
                .removeClass("active-btn");
            $(this).addClass("active-btn");
            target
                .parents(".tabs-box")
                .find(".tabs-content")
                .find(".tab")
                .fadeOut(0);
            target
                .parents(".tabs-box")
                .find(".tabs-content")
                .find(".tab")
                .removeClass("active-tab");
            $(target).fadeIn(300);
            $(target).addClass("active-tab");
        }
    });
}


$(document).ready(function () {
    if (window.matchMedia("(min-width: 769px)").matches) {
        $('.banner-form .close_outer').on('click', function () {
            $(this).closest('.banner-form').addClass('bottom');
            $(".lower-form-part").slideUp(500);
        })

        setTimeout(function () {
            $('.banner-form').addClass('active');
        }, 3000)
    }

})


// FORM SLIDE UP & DOWN

$(document).ready(function () {
    $(window).scroll(function () {

        if (window.matchMedia("(min-width: 769px)").matches) {
            if ($(window).width() > 768 && $(this).scrollTop() > 200) {
                $(".lower-form-part").slideUp(500);
                $('.banner-form').addClass('bottom');
            } else {
                $(".lower-form-part").slideDown(500);
                $('.banner-form').removeClass('bottom');
            }
        }

    });

    $(".form-top").click(function () {
        if (window.matchMedia("(min-width: 769px)").matches) {
            $(".lower-form-part").slideToggle();
        }
    });
});

// Desktop nav scrollspy — highlight active section on scroll
function initDesktopScrollspy() {
    var desktopMq = window.matchMedia("(min-width: 768px)");
    var sectionIds = [
        "overview",
        "highlight",
        "amenities",
        "price-list",
        "floor-plan",
        "gallery",
        "location",
        "about-developer",
        "contact-us"
    ];
    var sections = sectionIds
        .map(function (id) {
            return document.getElementById(id);
        })
        .filter(Boolean);
    var navLinks = document.querySelectorAll("header .navbar-nav .nav-link[href^='#']");
    var headerOffset = 96;
    var ticking = false;

    if (!sections.length || !navLinks.length) {
        return;
    }

    function clearActive() {
        navLinks.forEach(function (link) {
            link.classList.remove("active");
        });
    }

    function setActive(sectionId) {
        if (!sectionId) {
            clearActive();
            return;
        }

        navLinks.forEach(function (link) {
            var href = link.getAttribute("href");
            link.classList.toggle("active", href === "#" + sectionId);
        });
    }

    function getActiveSection() {
        var scrollPos = window.pageYOffset || document.documentElement.scrollTop || 0;
        var marker = scrollPos + headerOffset + 40;
        var activeId = sections[0].id;

        sections.forEach(function (section) {
            var top = section.getBoundingClientRect().top + scrollPos;
            if (marker >= top) {
                activeId = section.id;
            }
        });

        return activeId;
    }

    function updateScrollspy() {
        ticking = false;

        if (!desktopMq.matches) {
            clearActive();
            return;
        }

        setActive(getActiveSection());
    }

    function onScroll() {
        if (!ticking) {
            ticking = true;
            window.requestAnimationFrame(updateScrollspy);
        }
    }

    window.initDesktopScrollspy = updateScrollspy;
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", updateScrollspy);
    desktopMq.addEventListener("change", updateScrollspy);
    updateScrollspy();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDesktopScrollspy);
} else {
    initDesktopScrollspy();
}