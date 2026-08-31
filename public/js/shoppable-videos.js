(function ($) {
    'use strict';

    function endpoint() {
        return window.wc_add_to_cart_params && wc_add_to_cart_params.wc_ajax_url ? wc_add_to_cart_params.wc_ajax_url.replace('%%endpoint%%', 'add_to_cart') : '/?wc-ajax=add_to_cart';
    }

    function ajaxUrl(root) {
        return root.data('ajax-url') || window.ajaxurl || '/wp-admin/admin-ajax.php';
    }

    function rootFor(element) {
        var root = $(element).closest('[data-superwoo-videos]');
        if (!root.length) {
            root = $(element).closest('.superwoo-video-viewer').data('superwoo-root') || $();
        }
        return root;
    }

    function track(root, type, video, product) {
        if (!root.length || !video) {
            return;
        }
        $.post(ajaxUrl(root), {
            action: 'superwoo_video_event',
            nonce: root.data('nonce'),
            event_type: type,
            video_id: video,
            product_id: product || 0
        });
    }

    function add(button, id, video, variation, attributes) {
        button.prop('disabled', true);
        $.post(endpoint(), {
            product_id: id,
            variation_id: variation || 0,
            variation: attributes || {},
            quantity: 1,
            superwoo_video_id: video
        }, function (response) {
            if (response && response.fragments) {
                $(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, button]);
                button.text('✓ Added');
                var root = rootFor(button);
                track(root, 'cart_add', video, id);
                if (String(root.data('quick-buy')) === '1' && window.wc_checkout_params && wc_checkout_params.checkout_url) {
                    window.location.assign(wc_checkout_params.checkout_url);
                }
            }
        }).always(function () {
            button.prop('disabled', false);
        });
    }

    function selectVariation(button) {
        var root = rootFor(button);
        var id = button.data('product-id');
        var video = button.data('video-id');

        $.post(ajaxUrl(root), {
            action: 'superwoo_video_variations',
            nonce: root.data('nonce'),
            product_id: id
        }, function (response) {
            if (!response || !response.success) {
                return;
            }

            var panel = $('<div class="superwoo-video-variation" role="dialog" aria-modal="true"><div class="superwoo-video-variation__panel"><button type="button" class="superwoo-video-variation__close" aria-label="Close">×</button><h3>Select options</h3></div></div>');
            var body = panel.find('.superwoo-video-variation__panel');

            $.each(response.data.attributes, function (name, options) {
                var select = $('<select>').attr('data-key', name).append('<option value="">Choose option</option>');
                $.each(options, function (_, value) {
                    select.append($('<option>').val(value).text(value));
                });
                body.append(select);
            });

            $('<button type="button" class="button">Add to cart</button>').on('click', function () {
                var chosen = {};
                var found = null;
                body.find('select').each(function () {
                    chosen[$(this).data('key')] = $(this).val();
                });
                $.each(response.data.variations, function (_, item) {
                    var match = true;
                    $.each(item.attributes, function (key, value) {
                        if (chosen[key] !== value) {
                            match = false;
                        }
                    });
                    if (match) {
                        found = item;
                        return false;
                    }
                });
                if (found) {
                    add($(this), id, video, found.id, chosen);
                    panel.remove();
                }
            }).appendTo(body);
            $('body').append(panel);
        });
    }

    function updateTimedProducts(video) {
        var current = video.currentTime || 0;
        var card = $(video).closest('.superwoo-video-card');
        card.find('.superwoo-video-product').each(function () {
            var product = $(this);
            var start = parseFloat(product.data('superwoo-start')) || 0;
            var end = parseFloat(product.data('superwoo-end')) || 0;
            product.prop('hidden', current < start || (end > 0 && current > end));
        });
    }

    function closeViewer(viewer) {
        var root = viewer.data('superwoo-root');
        var video = viewer.data('superwoo-video-id');
        track(root, 'viewer_close', video);
        viewer.remove();
        $('body').removeClass('superwoo-video-viewer-open');
    }

    function trapViewerFocus(event, viewer) {
        var focusable = viewer.find('button:not([disabled]), a[href], select, input, video[controls]').filter(':visible');
        if (!focusable.length) {
            return;
        }
        var first = focusable.first().get(0);
        var last = focusable.last().get(0);
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openViewer(card) {
        var root = card.closest('[data-superwoo-videos]');
        var source = card.find('source').attr('src');
        var poster = card.find('video').attr('poster');
        var title = card.find('.superwoo-video-card__title').text();
        var products = card.find('.superwoo-video-card__tray-inner').html() || '';
        var viewer = $('<div class="superwoo-video-viewer" role="dialog" aria-modal="true"><button type="button" class="superwoo-video-viewer__close" aria-label="Close">×</button><div class="superwoo-video-viewer__content"></div></div>');
        var content = viewer.find('.superwoo-video-viewer__content');
        viewer.attr('aria-label', title).data('superwoo-root', root).data('superwoo-video-id', card.data('superwoo-video-id'));

        if (source) {
            content.append($('<video controls playsinline autoplay>').attr({ src: source, poster: poster || '' }));
        }
        content.append($('<div class="superwoo-video-viewer__products">').html(products));
        $('body').append(viewer).addClass('superwoo-video-viewer-open');
        viewer.find('.superwoo-video-viewer__close').trigger('focus');
        track(root, 'viewer_open', card.data('superwoo-video-id'));
    }

    $(document)
        .on('click', '[data-superwoo-video-play]', function () {
            var card = $(this).closest('.superwoo-video-card');
            var video = card.find('video').get(0);
            if (!video) {
                return;
            }
            if (video.paused) {
                video.play().catch(function () {});
                track(card.closest('[data-superwoo-videos]'), 'start', card.data('superwoo-video-id'));
            } else {
                video.pause();
            }
        })
        .on('click', '[data-superwoo-products]', function () {
            var card = $(this).closest('.superwoo-video-card');
            var tray = card.find('.superwoo-video-card__tray');
            tray.prop('hidden', !tray.prop('hidden'));
            track(card.closest('[data-superwoo-videos]'), 'product_view', card.data('superwoo-video-id'));
        })
        .on('click', '[data-superwoo-video-add]', function () {
            var button = $(this);
            if (button.data('product-type') === 'variable') {
                selectVariation(button);
                return;
            }
            add(button, button.data('product-id'), button.data('video-id'));
        })
        .on('click', '.superwoo-video-product a', function () {
            var card = $(this).closest('.superwoo-video-card');
            var viewer = $(this).closest('.superwoo-video-viewer');
            track(rootFor(this), 'product_click', card.length ? card.data('superwoo-video-id') : viewer.data('superwoo-video-id'), $(this).closest('.superwoo-video-product').data('product-id'));
        })
        .on('click', '[data-superwoo-open-viewer]', function () {
            openViewer($(this).closest('.superwoo-video-card'));
        })
        .on('click', '.superwoo-video-viewer__close', function () {
            closeViewer($(this).closest('.superwoo-video-viewer'));
        })
        .on('click', '.superwoo-video-variation__close', function () {
            $(this).closest('.superwoo-video-variation').remove();
        })
        .on('keydown', function (event) {
            var viewer = $('.superwoo-video-viewer').last();
            if (event.key === 'Escape' && viewer.length) {
                closeViewer(viewer);
            } else if (event.key === 'Tab' && viewer.length) {
                trapViewerFocus(event, viewer);
            }
        });

    $(function () {
        $('[data-superwoo-videos]').each(function () {
            var root = $(this);
            root.find('video').each(function () {
                var video = this;
                var card = $(video).closest('.superwoo-video-card');
                var id = card.data('superwoo-video-id');

                if ($(video).is('[data-superwoo-autoplay]') && 'IntersectionObserver' in window) {
                    new IntersectionObserver(function (entries) {
                        $.each(entries, function (_, entry) {
                            if (entry.isIntersecting) {
                                video.play().catch(function () {});
                            } else {
                                video.pause();
                            }
                        });
                    }, { threshold: 0.6 }).observe(video);
                }

                $(video).on('play', function () {
                    if (!card.data('superwoo-started')) {
                        card.data('superwoo-started', true);
                        track(root, 'start', id);
                    }
                }).on('timeupdate', function () {
                    var progress = video.duration ? video.currentTime / video.duration : 0;
                    updateTimedProducts(video);
                    if (progress >= 0.25 && !card.data('p25')) { card.data('p25', true); track(root, 'watch_25', id); }
                    if (progress >= 0.5 && !card.data('p50')) { card.data('p50', true); track(root, 'watch_50', id); }
                    if (progress >= 0.75 && !card.data('p75')) { card.data('p75', true); track(root, 'watch_75', id); }
                }).on('loadedmetadata', function () {
                    updateTimedProducts(video);
                }).on('ended', function () {
                    track(root, 'complete', id);
                });
            });

            root.find('.superwoo-video-card').each(function () {
                track(root, 'impression', $(this).data('superwoo-video-id'));
            });
        });
    });
}(jQuery));
