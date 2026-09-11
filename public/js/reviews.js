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
        var mediaFilter = root.querySelector('[data-superwoo-review-media-filter]');
        var filterButtons = toArray(root.querySelectorAll('[data-superwoo-rating-filter]'));
        var filterToggle = root.querySelector('[data-superwoo-filter-toggle]');
        var filterMenu = root.querySelector('[data-superwoo-filter-menu]');
        var filterLabel = root.querySelector('[data-superwoo-filter-label]');
        var viewButtons = toArray(root.querySelectorAll('[data-superwoo-review-view]'));
        var showMore = root.querySelector('[data-superwoo-show-more]');
        var showMoreWrap = root.querySelector('[data-superwoo-show-more-wrap]');
        var externalShowMore = document.querySelector('.elementor-1065 .elementor-element.elementor-element-5a3dc6b');
        var results = root.querySelector('[data-superwoo-review-results]');
        var noMatches = root.querySelector('[data-superwoo-review-no-matches]');
        var writeButton = root.querySelector('[data-superwoo-write-review]');
        var formPanel = root.querySelector('[data-superwoo-review-form]');
        var mediaInput = root.querySelector('[data-superwoo-review-media]');
        var mediaHelp = root.querySelector('[data-superwoo-review-media-help]');
        var visibleLimit = 3;
        var activeRating = 0;
        var activeMedia = 'all';

        function showSubmissionNotice() {
            var submitted = window.location.hash.match(/^#superwoo-review-submitted-(\d+)-(pending|received)$/);
            var notice = root.querySelector('[data-superwoo-review-notice]');

            if (!submitted || submitted[1] !== root.getAttribute('data-product-id') || !notice) {
                return;
            }

            var pending = notice.querySelector('[data-superwoo-review-pending]');
            var reviewsTab = document.querySelector('.wc-tabs a[href="#tab-reviews"]');
            if (reviewsTab && root.closest('#tab-reviews')) {
                reviewsTab.click();
            }
            if (pending) {
                pending.hidden = submitted[2] !== 'pending';
            }
            notice.hidden = false;
            notice.focus({ preventScroll: true });
            notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Wait for WooCommerce to finish selecting its initial product tab.
        if (document.readyState === 'complete') {
            showSubmissionNotice();
        } else {
            window.addEventListener('load', showSubmissionNotice, { once: true });
        }

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

            if (activeMedia === 'photos' && card.getAttribute('data-has-images') !== '1') {
                return false;
            }

            if (activeMedia === 'media' && !cardHasMedia(card)) {
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
                externalShowMore.hidden = !!showMoreWrap || matched.length <= visibleLimit;
                externalShowMore.classList.toggle('superwoo-review-external-view-all', !showMoreWrap && matched.length > visibleLimit);
            }

            if (noMatches) {
                noMatches.hidden = !cards.length || matched.length > 0;
            }

            if (results) {
                results.textContent = matched.length ? matched.length + ' review' + (matched.length === 1 ? '' : 's') : 'No matching reviews';
            }
        }

        if (search) {
            search.addEventListener('input', function () {
                visibleLimit = 3;
                update();
            });
        }

        if (sort) {
            sort.addEventListener('change', function () {
                visibleLimit = 3;
                update();
            });
        }

        if (mediaFilter) {
            mediaFilter.addEventListener('change', function () {
                activeMedia = mediaFilter.value || 'all';
                visibleLimit = 3;
                update();
            });
        }

        filterButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                activeRating = parseInt(button.getAttribute('data-superwoo-rating-filter') || '0', 10) || 0;
                visibleLimit = 3;
                filterButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                    item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
                });
                if (filterLabel) {
                    filterLabel.textContent = activeRating ? activeRating + ' Star' + (activeRating === 1 ? '' : 's') : filterLabel.getAttribute('data-default-label');
                }
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

            root.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !filterMenu.hidden) {
                    filterMenu.hidden = true;
                    filterToggle.setAttribute('aria-expanded', 'false');
                    filterToggle.focus();
                }
            });

            document.addEventListener('click', function (event) {
                if (filterMenu.hidden || root.contains(event.target) && (filterMenu.contains(event.target) || filterToggle.contains(event.target))) {
                    return;
                }

                filterMenu.hidden = true;
                filterToggle.setAttribute('aria-expanded', 'false');
            });
        }

        viewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var view = button.getAttribute('data-superwoo-review-view') || 'grid';
                if (grid) {
                    grid.classList.toggle('is-list', view === 'list');
                }
                viewButtons.forEach(function (item) {
                    item.classList.toggle('is-active', item === button);
                });
            });
        });

        if (showMore) {
            showMore.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                visibleLimit += 3;
                update();
            });
        }

        if (externalShowMore) {
            externalShowMore.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                visibleLimit += 3;
                update();
            }, true);
        }

        if (writeButton && formPanel) {
            var previousOverflow;
            var closeButton = formPanel.querySelector('[data-superwoo-review-close]');
            var reviewText = formPanel.querySelector('[data-superwoo-review-text]');
            var reviewCount = formPanel.querySelector('[data-superwoo-review-count]');
            var ratingInputs = toArray(formPanel.querySelectorAll('input[name="rating"]'));
            var ratingHint = formPanel.querySelector('[data-superwoo-rating-hint]');
            var form = formPanel.querySelector('form');
            var uploadPanel = formPanel.querySelector('.superwoo-review-upload-panel');
            var tipsPanel = formPanel.querySelector('.superwoo-review-tips');

            // Keep WordPress's fields and submission intact; place optional content alongside it.
            if (form && uploadPanel && tipsPanel) {
                var body = document.createElement('div');
                body.className = 'superwoo-review-modal-body';
                var sidebar = document.createElement('div');
                sidebar.className = 'superwoo-review-modal-sidebar';
                form.parentNode.insertBefore(body, form);
                body.appendChild(form);
                body.appendChild(sidebar);
                sidebar.appendChild(uploadPanel);
                sidebar.appendChild(tipsPanel);
                if (mediaInput) {
                    if (!form.id) {
                        form.id = 'superwoo-review-submit-' + root.getAttribute('data-product-id');
                    }
                    mediaInput.setAttribute('form', form.id);
                    form.setAttribute('enctype', 'multipart/form-data');
                }
            }

            function closeReviewForm() {
                formPanel.close();
            }

            writeButton.addEventListener('click', function () {
                previousOverflow = document.body.style.overflow;
                formPanel.hidden = false;
                formPanel.showModal();
                document.body.style.overflow = 'hidden';
                writeButton.setAttribute('aria-expanded', 'true');
                // Start at the form, while native dialog provides focus containment.
                var firstField = formPanel.querySelector('input[name="rating"]:checked') || formPanel.querySelector('input[name="rating"], textarea, input:not([type="hidden"])');
                if (firstField) {
                    firstField.focus({ preventScroll: true });
                }
            });
            if (closeButton) {
                closeButton.addEventListener('click', closeReviewForm);
            }
            formPanel.addEventListener('click', function (event) {
                if (event.target !== formPanel) {
                    return;
                }
                var bounds = formPanel.getBoundingClientRect();
                if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
                    closeReviewForm();
                }
            });
            formPanel.addEventListener('close', function () {
                formPanel.hidden = true;
                document.body.style.overflow = previousOverflow;
                writeButton.setAttribute('aria-expanded', 'false');
                writeButton.focus({ preventScroll: true });
            });

            function updateRating() {
                var selected = ratingInputs.filter(function (input) { return input.checked; })[0];
                ratingInputs.forEach(function (input) {
                    input.parentNode.classList.toggle('is-selected', !!selected && Number(input.value) <= Number(selected.value));
                });
                if (selected && ratingHint) {
                    ratingHint.textContent = selected.getAttribute('aria-label');
                }
            }
            ratingInputs.forEach(function (input) { input.addEventListener('change', updateRating); });
            updateRating();
            if (reviewText && reviewCount) {
                function updateCount() {
                    reviewCount.textContent = reviewText.value.length + '/' + reviewText.maxLength;
                }
                reviewText.addEventListener('input', updateCount);
                updateCount();
            }
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
