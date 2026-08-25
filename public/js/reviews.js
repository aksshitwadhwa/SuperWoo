(function () {
    'use strict';

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function setupReviews(root) {
        var grid = root.querySelector('[data-superwoo-review-grid]');
        var cards = grid ? toArray(grid.querySelectorAll('[data-superwoo-review-card]')) : [];
        var search = root.querySelector('[data-superwoo-review-search]');
        var sort = root.querySelector('[data-superwoo-review-sort]');
        var filterButtons = toArray(root.querySelectorAll('[data-superwoo-rating-filter]'));
        var filterToggle = root.querySelector('[data-superwoo-filter-toggle]');
        var filterMenu = root.querySelector('[data-superwoo-filter-menu]');
        var showMore = root.querySelector('[data-superwoo-show-more]');
        var showMoreWrap = root.querySelector('[data-superwoo-show-more-wrap]');
        var externalShowMore = document.querySelector('.elementor-1065 .elementor-element.elementor-element-5a3dc6b');
        var results = root.querySelector('[data-superwoo-review-results]');
        var writeButton = root.querySelector('[data-superwoo-write-review]');
        var formPanel = root.querySelector('[data-superwoo-review-form]');
        var mediaInput = root.querySelector('[data-superwoo-review-media]');
        var mediaHelp = root.querySelector('[data-superwoo-review-media-help]');
        var visibleLimit = 8;
        var activeRating = 0;

        function cardRating(card) {
            return parseInt(card.getAttribute('data-rating') || '0', 10) || 0;
        }

        function cardDate(card) {
            return parseInt(card.getAttribute('data-date') || '0', 10) || 0;
        }

        function cardHasMedia(card) {
            return card.getAttribute('data-has-media') === '1' || card.getAttribute('data-has-images') === '1' ? 1 : 0;
        }

        function matches(card) {
            var term = search ? search.value.trim().toLowerCase() : '';
            var haystack = card.getAttribute('data-search') || '';

            if (activeRating && cardRating(card) !== activeRating) {
                return false;
            }

            return !term || haystack.indexOf(term) !== -1;
        }

        function sortedCards(list) {
            var mode = sort ? sort.value : 'pictures';
            var next = list.slice();

            next.sort(function (a, b) {
                if (mode === 'highest') {
                    return cardRating(b) - cardRating(a) || cardDate(b) - cardDate(a);
                }

                if (mode === 'lowest') {
                    return cardRating(a) - cardRating(b) || cardDate(b) - cardDate(a);
                }

                if (mode === 'newest') {
                    return cardDate(b) - cardDate(a);
                }

                return cardHasMedia(b) - cardHasMedia(a) || cardDate(b) - cardDate(a);
            });

            return next;
        }

        function update() {
            var matched = sortedCards(cards.filter(matches));

            if (grid) {
                matched.forEach(function (card) {
                    grid.appendChild(card);
                });
            }

            cards.forEach(function (card) {
                card.hidden = true;
            });

            matched.slice(0, visibleLimit).forEach(function (card) {
                card.hidden = false;
            });

            if (showMore) {
                showMore.hidden = matched.length <= visibleLimit;
            }

            if (showMoreWrap) {
                showMoreWrap.hidden = matched.length <= visibleLimit;
            }

            if (externalShowMore) {
                externalShowMore.hidden = matched.length <= visibleLimit;
                externalShowMore.classList.toggle('superwoo-review-external-view-all', matched.length > visibleLimit);
            }

            if (results) {
                results.textContent = matched.length ? matched.length + ' review' + (matched.length === 1 ? '' : 's') : 'No matching reviews';
            }
        }

        if (search) {
            search.addEventListener('input', function () {
                visibleLimit = 8;
                update();
            });
        }

        if (sort) {
            sort.addEventListener('change', function () {
                visibleLimit = 8;
                update();
            });
        }

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeRating = parseInt(button.getAttribute('data-superwoo-rating-filter') || '0', 10) || 0;
                visibleLimit = 8;
                filterButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
                if (filterMenu && filterToggle) {
                    filterMenu.hidden = true;
                    filterToggle.setAttribute('aria-expanded', 'false');
                }
                update();
            });
        });

        if (filterToggle && filterMenu) {
            filterToggle.addEventListener('click', function () {
                filterMenu.hidden = !filterMenu.hidden;
                filterToggle.setAttribute('aria-expanded', filterMenu.hidden ? 'false' : 'true');
            });

            document.addEventListener('click', function (event) {
                if (filterMenu.hidden || root.contains(event.target) && (filterMenu.contains(event.target) || filterToggle.contains(event.target))) {
                    return;
                }

                filterMenu.hidden = true;
                filterToggle.setAttribute('aria-expanded', 'false');
            });
        }

        if (showMore) {
            showMore.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                visibleLimit += 8;
                update();
            });
        }

        if (externalShowMore) {
            externalShowMore.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                visibleLimit += 8;
                update();
            }, true);
        }

        if (writeButton && formPanel) {
            writeButton.addEventListener('click', function () {
                formPanel.hidden = !formPanel.hidden;
                if (!formPanel.hidden) {
                    formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        if (mediaInput) {
            if (mediaInput.form) {
                mediaInput.form.setAttribute('enctype', 'multipart/form-data');
            }

            mediaInput.addEventListener('change', function () {
                var files = mediaInput.files ? toArray(mediaInput.files) : [];
                var photos = files.filter(function (file) {
                    return file.type && file.type.indexOf('image/') === 0;
                }).length;
                var videos = files.filter(function (file) {
                    return file.type && file.type.indexOf('video/') === 0;
                }).length;
                var parts = [];

                if (!mediaHelp) {
                    return;
                }

                if (!files.length) {
                    mediaHelp.textContent = 'Optional. Upload up to 4 photos and 2 videos.';
                    return;
                }

                if (photos) {
                    parts.push(photos + ' photo' + (photos === 1 ? '' : 's'));
                }
                if (videos) {
                    parts.push(videos + ' video' + (videos === 1 ? '' : 's'));
                }

                mediaHelp.textContent = (parts.length ? parts.join(' and ') : files.length + ' file' + (files.length === 1 ? '' : 's')) + ' selected. Up to 4 photos and 2 videos will be uploaded.';
            });
        }

        update();
    }

    document.addEventListener('DOMContentLoaded', function () {
        toArray(document.querySelectorAll('[data-superwoo-reviews]')).forEach(setupReviews);
    });
})();
