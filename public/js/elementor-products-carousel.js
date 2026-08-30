(function () {
    'use strict';

    var instanceKey = '__superwooProductsCarousel';

    function deviceValue(values) {
        var mode = 'desktop';
        if (window.elementorFrontend && typeof window.elementorFrontend.getCurrentDeviceMode === 'function') {
            mode = window.elementorFrontend.getCurrentDeviceMode();
        } else if (window.matchMedia('(max-width: 767px)').matches) {
            mode = 'mobile';
        } else if (window.matchMedia('(max-width: 1024px)').matches) {
            mode = 'tablet';
        }
        if (mode.indexOf('mobile') !== -1) {
            return values.mobile;
        }
        if (mode.indexOf('tablet') !== -1) {
            return values.tablet;
        }
        return values.desktop;
    }

    function Carousel(root, config) {
        this.root = root;
        this.config = config;
        this.track = root.querySelector('.elementor-widget-container ul.products');
        this.index = 0;
        this.timer = null;
        this.pointerStart = null;
        this.resizeObserver = null;
        if (!this.track || !this.track.children.length) {
            return;
        }
        this.slides = Array.prototype.slice.call(this.track.children);
        this.build();
        this.syncExtendedClass();
        this.bind();
        this.update();
        this.startAutoplay();
    }

    Carousel.prototype.build = function () {
        this.viewport = document.createElement('div');
        this.viewport.className = 'superwoo-carousel__viewport';
        this.track.parentNode.insertBefore(this.viewport, this.track);
        this.viewport.appendChild(this.track);
        this.track.classList.add('superwoo-carousel__track');
        this.slides.forEach(function (slide, index) {
            slide.classList.add('superwoo-carousel__slide');
            slide.setAttribute('role', 'group');
            slide.setAttribute('aria-label', (index + 1) + ' / ' + this.slides.length);
        }, this);

        if (this.config.arrows) {
            this.previous = this.button('previous', '\u2039');
            this.next = this.button('next', '\u203a');
            this.root.appendChild(this.previous);
            this.root.appendChild(this.next);
        }
        if (this.config.dots) {
            this.dots = document.createElement('div');
            this.dots.className = 'superwoo-carousel__dots';
            this.dots.setAttribute('role', 'tablist');
            this.root.appendChild(this.dots);
        }
        this.root.classList.add('superwoo-carousel--ready');
    };

    Carousel.prototype.button = function (direction, text) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'superwoo-carousel__arrow superwoo-carousel__arrow--' + direction;
        button.setAttribute('aria-label', direction === 'previous' ? 'Previous products' : 'Next products');
        button.innerHTML = '<span aria-hidden="true">' + text + '</span>';
        return button;
    };

    Carousel.prototype.bind = function () {
        this.onResize = this.update.bind(this);
        this.onKeydown = function (event) {
            if (event.key === 'ArrowLeft') { this.go(-1); }
            if (event.key === 'ArrowRight') { this.go(1); }
        }.bind(this);
        this.onPointerDown = function (event) {
            if (event.pointerType !== 'mouse' || event.button === 0) {
                this.pointerStart = event.clientX;
                this.viewport.classList.add('is-grabbing');
                if (this.viewport.setPointerCapture) {
                    this.viewport.setPointerCapture(event.pointerId);
                }
            }
        }.bind(this);
        this.onPointerUp = function (event) {
            if (this.pointerStart === null) { return; }
            var distance = event.clientX - this.pointerStart;
            this.pointerStart = null;
            this.viewport.classList.remove('is-grabbing');
            if (this.viewport.releasePointerCapture && this.viewport.hasPointerCapture && this.viewport.hasPointerCapture(event.pointerId)) {
                this.viewport.releasePointerCapture(event.pointerId);
            }
            if (Math.abs(distance) > 40) { this.go(distance > 0 ? -1 : 1); }
        }.bind(this);
        window.addEventListener('resize', this.onResize);
        this.viewport.addEventListener('keydown', this.onKeydown);
        this.viewport.addEventListener('pointerdown', this.onPointerDown);
        this.viewport.addEventListener('pointerup', this.onPointerUp);
        this.viewport.addEventListener('pointercancel', this.onPointerUp);
        this.viewport.setAttribute('tabindex', '0');
        if (this.previous) { this.previous.addEventListener('click', function () { this.go(-1); }.bind(this)); }
        if (this.next) { this.next.addEventListener('click', function () { this.go(1); }.bind(this)); }
        if (this.config.pauseOnHover) {
            this.onMouseEnter = this.stopAutoplay.bind(this);
            this.onMouseLeave = this.startAutoplay.bind(this);
            this.root.addEventListener('mouseenter', this.onMouseEnter);
            this.root.addEventListener('mouseleave', this.onMouseLeave);
        }
        if (window.ResizeObserver) {
            this.resizeObserver = new ResizeObserver(this.onResize);
            this.resizeObserver.observe(this.viewport);
        }
    };

    Carousel.prototype.metrics = function () {
        var show = Math.max(1, Number(deviceValue(this.config.slidesToShow)) || 1);
        var extended = deviceValue(this.config.extended || { desktop: 'none', tablet: 'none', mobile: 'none' });
        if (extended === 'both') { show += 0.5; }
        if (extended === 'left' || extended === 'right') { show += 0.25; }
        return { show: show, gap: Math.max(0, Number(deviceValue(this.config.spaceBetween)) || 0) };
    };

    Carousel.prototype.syncExtendedClass = function () {
        var extended = deviceValue(this.config.extended || { desktop: 'none', tablet: 'none', mobile: 'none' });
        this.root.classList.remove('superwoo-carousel-extended-none', 'superwoo-carousel-extended-both', 'superwoo-carousel-extended-left', 'superwoo-carousel-extended-right');
        this.root.classList.add('superwoo-carousel-extended-' + extended);
    };

    Carousel.prototype.update = function () {
        if (!this.viewport) { return; }
        var metrics = this.metrics();
        this.syncExtendedClass();
        var width = this.viewport.clientWidth;
        this.slideWidth = (width - (metrics.show - 1) * metrics.gap) / metrics.show;
        this.maxIndex = Math.max(0, this.slides.length - Math.ceil(metrics.show));
        this.index = Math.min(this.index, this.maxIndex);
        this.track.style.gap = metrics.gap + 'px';
        this.slides.forEach(function (slide) {
            slide.style.flex = '0 0 ' + this.slideWidth + 'px';
            slide.style.width = this.slideWidth + 'px';
        }, this);
        this.renderDots();
        this.render();
    };

    Carousel.prototype.go = function (direction) {
        var step = Math.max(1, Math.round(Number(this.config.slidesToScroll) || 1));
        var target = this.index + direction * step;
        if (this.config.loop && this.maxIndex > 0) {
            target = target > this.maxIndex ? 0 : (target < 0 ? this.maxIndex : target);
        } else {
            target = Math.max(0, Math.min(this.maxIndex, target));
        }
        this.index = target;
        this.render();
    };

    Carousel.prototype.render = function () {
        var gap = this.metrics().gap;
        this.track.style.transform = 'translate3d(' + (-this.index * (this.slideWidth + gap)) + 'px,0,0)';
        if (this.previous) {
            this.previous.disabled = !this.config.loop && this.index === 0;
            this.next.disabled = !this.config.loop && this.index === this.maxIndex;
        }
        if (this.dots) {
            Array.prototype.forEach.call(this.dots.children, function (dot) {
                var active = Number(dot.dataset.index) === this.index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            }, this);
        }
    };

    Carousel.prototype.renderDots = function () {
        if (!this.dots) { return; }
        var step = Math.max(1, Math.round(Number(this.config.slidesToScroll) || 1));
        var indexes = [];
        for (var index = 0; index <= this.maxIndex; index += step) { indexes.push(index); }
        if (indexes[indexes.length - 1] !== this.maxIndex) { indexes.push(this.maxIndex); }
        this.dots.innerHTML = '';
        indexes.forEach(function (target, position) {
            var dot = document.createElement('button');
            dot.type = 'button'; dot.dataset.index = target;
            dot.setAttribute('aria-label', 'Go to product group ' + (position + 1));
            dot.addEventListener('click', function () { this.index = target; this.render(); }.bind(this));
            this.dots.appendChild(dot);
        }, this);
    };

    Carousel.prototype.startAutoplay = function () {
        if (!this.config.autoplay || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !this.viewport) { return; }
        this.stopAutoplay();
        this.timer = window.setInterval(function () { this.go(1); }.bind(this), this.config.autoplaySpeed || 3000);
    };
    Carousel.prototype.stopAutoplay = function () { window.clearInterval(this.timer); this.timer = null; };

    Carousel.prototype.destroy = function () {
        this.stopAutoplay();
        window.removeEventListener('resize', this.onResize);
        if (this.onMouseEnter) {
            this.root.removeEventListener('mouseenter', this.onMouseEnter);
            this.root.removeEventListener('mouseleave', this.onMouseLeave);
        }
        if (this.resizeObserver) { this.resizeObserver.disconnect(); }
        if (!this.viewport) { return; }
        this.slides.forEach(function (slide) {
            slide.classList.remove('superwoo-carousel__slide'); slide.style.flex = ''; slide.style.width = '';
            slide.removeAttribute('role'); slide.removeAttribute('aria-label');
        });
        this.track.classList.remove('superwoo-carousel__track'); this.track.style.gap = ''; this.track.style.transform = '';
        this.viewport.parentNode.insertBefore(this.track, this.viewport); this.viewport.remove();
        if (this.previous) { this.previous.remove(); this.next.remove(); }
        if (this.dots) { this.dots.remove(); }
        this.root.classList.remove('superwoo-carousel--ready');
    };

    function initialize(scope) {
        var root = scope && scope.nodeType ? scope : (scope && scope[0] && scope[0].nodeType ? scope[0] : document);
        if (root.matches && !root.matches('[data-superwoo-products-carousel]')) {
            root = root.querySelector('[data-superwoo-products-carousel]');
        }
        if (!root || !root.matches || !root.matches('[data-superwoo-products-carousel]')) { return; }
        if (root[instanceKey]) { root[instanceKey].destroy(); }
        try { root[instanceKey] = new Carousel(root, JSON.parse(root.getAttribute('data-superwoo-products-carousel'))); } catch (error) { root[instanceKey] = null; }
    }

    function initializeAll() {
        document.querySelectorAll('[data-superwoo-products-carousel]').forEach(initialize);
    }
    document.addEventListener('DOMContentLoaded', initializeAll);
    window.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-products.default', initialize);
        }
        initializeAll();
    });
}());
