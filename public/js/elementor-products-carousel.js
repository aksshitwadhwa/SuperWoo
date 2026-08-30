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

    function arrowGlyph(style, direction) {
        var glyphs = {
            chevron: { previous: '\u2039', next: '\u203a' },
            angle: { previous: '\u276e', next: '\u276f' },
            arrow: { previous: '\u2190', next: '\u2192' },
            triangle: { previous: '\u25c0', next: '\u25b6' }
        };
        var selected = glyphs[style] || glyphs.chevron;
        return selected[direction];
    }

    function number(value, fallback, min, max) {
        value = Number(value);
        if (!isFinite(value)) { value = fallback; }
        return Math.max(min, Math.min(max, value));
    }

    function sliderValue(value, fallback) {
        return number(value && typeof value === 'object' ? value.size : value, fallback, 0, 100);
    }

    function cssValue(root, property, fallback) {
        var value = window.getComputedStyle(root).getPropertyValue(property).trim();
        return value || fallback;
    }

    function previewConfigFromCss(root, config) {
        var isEditor = root.classList.contains('elementor-element-edit-mode');
        if (!isEditor) { return config; }
        if (!root.classList.contains('superwoo-carousel-enabled-yes')) { return null; }
        var allowedExtended = ['none', 'both', 'left', 'right'];
        var allowedPosition = ['inside', 'outside', 'top-right', 'bottom-right'];
        var extended = cssValue(root, '--superwoo-preview-extended', 'none');
        var position = root.className.match(/superwoo-carousel-arrow-position-([\w-]+)/);
        var style = root.className.match(/superwoo-carousel-arrow-style-([\w-]+)/);
        var slides = number(cssValue(root, '--superwoo-preview-slides', 4), 4, 1, 8);
        var gap = number(cssValue(root, '--superwoo-preview-gap', 20), 20, 0, 100);
        var previewExtended = allowedExtended.indexOf(extended) !== -1 ? extended : 'none';
        config = config || {};
        // Elementor resolves responsive CSS custom properties for the active preview device.
        // Use that resolved value for every breakpoint so a control change is reflected immediately.
        config.slidesToShow = { desktop: slides, tablet: slides, mobile: slides };
        config.spaceBetween = { desktop: gap, tablet: gap, mobile: gap };
        config.slidesToScroll = number(cssValue(root, '--superwoo-preview-scroll', 1), 1, 1, 8);
        config.productsLimit = number(cssValue(root, '--superwoo-preview-product-limit', 12), 12, 1, 100);
        config.arrows = root.classList.contains('superwoo-carousel-arrows-yes');
        config.dots = root.classList.contains('superwoo-carousel-dots-yes');
        config.autoplay = root.classList.contains('superwoo-carousel-autoplay-yes');
        config.loop = root.classList.contains('superwoo-carousel-loop-yes');
        config.pauseOnHover = root.classList.contains('superwoo-carousel-pause_hover-yes');
        config.arrowStyle = style && ['chevron', 'angle', 'arrow', 'triangle'].indexOf(style[1]) !== -1 ? style[1] : 'chevron';
        config.arrowPosition = position && allowedPosition.indexOf(position[1]) !== -1 ? position[1] : 'inside';
        config.extended = { desktop: previewExtended, tablet: previewExtended, mobile: previewExtended };
        return config;
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
        this.allSlides = Array.prototype.slice.call(this.track.children);
        this.syncProductsLimit();
        this.build();
        this.syncExtendedClass();
        this.bind();
        this.update();
        this.startAutoplay();
    }

    Carousel.prototype.syncProductsLimit = function () {
        var limit = number(this.config.productsLimit, this.allSlides.length, 1, 100);
        this.slides = this.allSlides.filter(function (slide, index) {
            slide.hidden = index >= limit;
            return index < limit;
        });
    };

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
            this.previous = this.button('previous');
            this.next = this.button('next');
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
        this.root.classList.add('superwoo-carousel-arrows-' + (this.config.arrowPosition || 'inside'));
    };

    Carousel.prototype.button = function (direction) {
        var button = document.createElement('button');
        var icon = document.createElement('span');
        button.type = 'button';
        button.className = 'superwoo-carousel__arrow superwoo-carousel__arrow--' + direction;
        button.setAttribute('aria-label', direction === 'previous' ? 'Previous products' : 'Next products');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = arrowGlyph(this.config.arrowStyle, direction);
        button.appendChild(icon);
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
                this.track.style.transition = 'none';
                this.viewport.classList.add('is-grabbing');
                if (this.viewport.setPointerCapture) {
                    this.viewport.setPointerCapture(event.pointerId);
                }
            }
        }.bind(this);
        this.onPointerMove = function (event) {
            if (this.pointerStart === null) { return; }
            var distance = event.clientX - this.pointerStart;
            this.track.style.transform = 'translate3d(' + ((-this.index * (this.slideWidth + this.metrics().gap)) + distance) + 'px,0,0)';
        }.bind(this);
        this.onPointerUp = function (event) {
            if (this.pointerStart === null) { return; }
            var distance = event.clientX - this.pointerStart;
            this.pointerStart = null;
            this.track.style.transition = '';
            this.viewport.classList.remove('is-grabbing');
            if (this.viewport.releasePointerCapture && this.viewport.hasPointerCapture && this.viewport.hasPointerCapture(event.pointerId)) {
                this.viewport.releasePointerCapture(event.pointerId);
            }
            if (Math.abs(distance) > 40) { this.go(distance > 0 ? -1 : 1); }
        }.bind(this);
        window.addEventListener('resize', this.onResize);
        this.viewport.addEventListener('keydown', this.onKeydown);
        this.viewport.addEventListener('pointerdown', this.onPointerDown);
        this.viewport.addEventListener('pointermove', this.onPointerMove);
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
        var isEditorPreview = this.root.classList.contains('elementor-element-edit-mode');
        // Elementor's preview iframe can report "desktop" even while its mobile
        // canvas is active. Its generated custom properties are the reliable
        // source for the current preview device.
        var show = isEditorPreview
            ? number(cssValue(this.root, '--superwoo-preview-slides', 4), 4, 1, 8)
            : Math.max(1, Number(deviceValue(this.config.slidesToShow)) || 1);
        var extended = isEditorPreview
            ? cssValue(this.root, '--superwoo-preview-extended', 'none')
            : deviceValue(this.config.extended || { desktop: 'none', tablet: 'none', mobile: 'none' });
        if (['none', 'both', 'left', 'right'].indexOf(extended) === -1) { extended = 'none'; }
        var gap = isEditorPreview
            ? number(cssValue(this.root, '--superwoo-preview-gap', 20), 20, 0, 100)
            : Math.max(0, Number(deviceValue(this.config.spaceBetween)) || 0);
        if (extended === 'both') { show += 0.5; }
        if (extended === 'left' || extended === 'right') { show += 0.25; }
        return { show: show, gap: gap, extended: extended };
    };

    Carousel.prototype.syncExtendedClass = function () {
        var extended = this.metrics().extended;
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
            slide.style.setProperty('flex', '0 0 ' + this.slideWidth + 'px', 'important');
            slide.style.setProperty('width', this.slideWidth + 'px', 'important');
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
        this.allSlides.forEach(function (slide) {
            slide.classList.remove('superwoo-carousel__slide'); slide.style.removeProperty('flex'); slide.style.removeProperty('width');
            slide.removeAttribute('role'); slide.removeAttribute('aria-label'); slide.hidden = false;
        });
        this.track.classList.remove('superwoo-carousel__track'); this.track.style.gap = ''; this.track.style.transform = '';
        this.viewport.parentNode.insertBefore(this.track, this.viewport); this.viewport.remove();
        if (this.previous) { this.previous.remove(); this.next.remove(); }
        if (this.dots) { this.dots.remove(); }
        this.root.classList.remove('superwoo-carousel--ready', 'superwoo-carousel-arrows-inside', 'superwoo-carousel-arrows-outside', 'superwoo-carousel-arrows-top-right', 'superwoo-carousel-arrows-bottom-right');
    };

    function initialize(scope) {
        var root = scope && scope.nodeType ? scope : (scope && scope[0] && scope[0].nodeType ? scope[0] : document);
        if (root.matches && !root.matches('[data-superwoo-products-carousel], .superwoo-carousel-enabled-yes')) {
            root = root.querySelector('[data-superwoo-products-carousel], .superwoo-carousel-enabled-yes');
        }
        if (!root || !root.matches || !root.matches('[data-superwoo-products-carousel], .superwoo-carousel-enabled-yes')) { return; }
        var config = null;
        try { config = root.hasAttribute('data-superwoo-products-carousel') ? JSON.parse(root.getAttribute('data-superwoo-products-carousel')) : {}; } catch (error) { config = {}; }
        config = previewConfigFromCss(root, config);
        if (!config) {
            if (root[instanceKey]) { root[instanceKey].destroy(); root[instanceKey] = null; }
            return;
        }
        var signature = JSON.stringify(config);
        if (root[instanceKey] && root.__superwooCarouselSignature === signature) { return; }
        if (root[instanceKey]) { root[instanceKey].destroy(); }
        try {
            root[instanceKey] = new Carousel(root, config);
            root.__superwooCarouselSignature = signature;
        } catch (error) { root[instanceKey] = null; }
    }

    function initializeAll() {
        document.querySelectorAll('[data-superwoo-products-carousel], .superwoo-carousel-enabled-yes').forEach(initialize);
    }

    function bindEditorStylePreview() {
        if (!document.body.classList.contains('elementor-editor-active') || !window.MutationObserver) { return; }
        var queued = false;
        var refresh = function () {
            if (queued) { return; }
            queued = true;
            window.requestAnimationFrame(function () { queued = false; initializeAll(); });
        };
        new MutationObserver(refresh).observe(document.head, { childList: true, subtree: true, characterData: true });
        new MutationObserver(refresh).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['class'] });
    }

    function editorConfig(settings) {
        var enabled = settings.superwoo_carousel_enabled === 'yes';
        if (!enabled) { return null; }
        var value = function (key, fallback) { return settings[key] === undefined || settings[key] === '' ? fallback : settings[key]; };
        return {
            slidesToShow: {
                desktop: number(value('superwoo_carousel_slides_to_show', 4), 4, 1, 8),
                tablet: number(value('superwoo_carousel_slides_to_show_tablet', 2), 2, 1, 8),
                mobile: number(value('superwoo_carousel_slides_to_show_mobile', 1), 1, 1, 8)
            },
            spaceBetween: {
                desktop: sliderValue(value('superwoo_carousel_space_between', 20), 20),
                tablet: sliderValue(value('superwoo_carousel_space_between_tablet', 20), 20),
                mobile: sliderValue(value('superwoo_carousel_space_between_mobile', 20), 20)
            },
            slidesToScroll: number(value('superwoo_carousel_slides_to_scroll', 1), 1, 1, 8),
            productsLimit: number(value('superwoo_carousel_products_limit', 12), 12, 1, 100),
            arrows: value('superwoo_carousel_arrows', 'yes') === 'yes',
            arrowStyle: ['chevron', 'angle', 'arrow', 'triangle'].indexOf(value('superwoo_carousel_arrow_style', 'chevron')) !== -1 ? value('superwoo_carousel_arrow_style', 'chevron') : 'chevron',
            dots: value('superwoo_carousel_dots', '') === 'yes',
            autoplay: value('superwoo_carousel_autoplay', '') === 'yes',
            autoplaySpeed: number(value('superwoo_carousel_autoplay_speed', 3000), 3000, 500, 60000),
            loop: value('superwoo_carousel_loop', 'yes') === 'yes',
            pauseOnHover: value('superwoo_carousel_pause_hover', 'yes') === 'yes',
            extended: {
                desktop: value('superwoo_carousel_extended', 'none'),
                tablet: value('superwoo_carousel_extended_tablet', value('superwoo_carousel_extended', 'none')),
                mobile: value('superwoo_carousel_extended_mobile', value('superwoo_carousel_extended_tablet', value('superwoo_carousel_extended', 'none')))
            },
            arrowPosition: value('superwoo_carousel_arrow_position', 'inside')
        };
    }

    function bindEditorPreview() {
        if (!window.elementor || !window.elementor.channels || !window.elementor.channels.editor) { return; }
        window.elementor.channels.editor.on('change:element', function (model) {
            if (!model || model.get('widgetType') !== 'woocommerce-products') { return; }
            var settings = model.get('settings');
            var attributes = settings && settings.attributes ? settings.attributes : null;
            var config = attributes && editorConfig(attributes);
            var id = model.get('id');
            var root = id ? document.querySelector('.elementor-element-' + id) : null;
            if (!root) { return; }
            if (!config) {
                if (root[instanceKey]) { root[instanceKey].destroy(); root[instanceKey] = null; }
                root.removeAttribute('data-superwoo-products-carousel');
                return;
            }
            root.setAttribute('data-superwoo-products-carousel', JSON.stringify(config));
            initialize(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initializeAll();
        bindEditorStylePreview();
    });
    window.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/woocommerce-products.default', initialize);
        }
        initializeAll();
        bindEditorPreview();
        bindEditorStylePreview();
    });
}());
