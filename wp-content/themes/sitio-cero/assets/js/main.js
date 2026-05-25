(() => {
    const initNavigation = () => {
        const nav = document.querySelector('.site-nav');
        const navToggle = document.querySelector('.nav-toggle');

        if (!nav) {
            return;
        }

        const navLinks = Array.from(nav.querySelectorAll('.site-nav__list a[href]'));
        const megaParents = Array.from(nav.querySelectorAll('.site-nav__list > li.menu-item-has-children'));
        const megaCloseDelayMs = 300;
        let megaCloseTimer = null;

        const isDesktopViewport = () => window.innerWidth > 860;

        const clearMegaCloseTimer = () => {
            if (megaCloseTimer) {
                window.clearTimeout(megaCloseTimer);
                megaCloseTimer = null;
            }
        };

        const closeMegaMenus = () => {
            clearMegaCloseTimer();
            megaParents.forEach((menuItem) => {
                const triggerLink = menuItem.querySelector(':scope > a[data-mega-trigger="1"]');
                menuItem.classList.remove('is-mega-open');
                if (triggerLink) {
                    triggerLink.setAttribute('aria-expanded', 'false');
                }
            });
        };

        const scheduleMegaClose = (menuItem, triggerLink) => {
            clearMegaCloseTimer();

            megaCloseTimer = window.setTimeout(() => {
                if (!isDesktopViewport()) {
                    return;
                }

                if (menuItem.matches(':hover') || menuItem.matches(':focus-within')) {
                    return;
                }

                menuItem.classList.remove('is-mega-open');
                if (triggerLink) {
                    triggerLink.setAttribute('aria-expanded', 'false');
                }
            }, megaCloseDelayMs);
        };

        megaParents.forEach((menuItem) => {
            const triggerLink = menuItem.querySelector(':scope > a');
            const subMenu = menuItem.querySelector(':scope > .sub-menu');
            if (!triggerLink || !subMenu) {
                return;
            }

            const triggerHref = String(triggerLink.getAttribute('href') || '').toLowerCase();
            const isDireccionesParent = (
                triggerHref.includes('post_type=direccion_municipal')
                || triggerHref.includes('/direcciones-municipales')
            );

            if (isDireccionesParent) {
                menuItem.classList.add('is-direcciones-parent');
            }

            triggerLink.setAttribute('data-mega-trigger', '1');
            triggerLink.setAttribute('aria-haspopup', 'true');
            triggerLink.setAttribute('aria-expanded', 'false');

            menuItem.addEventListener('mouseenter', () => {
                if (!isDesktopViewport()) {
                    return;
                }

                clearMegaCloseTimer();
                closeMegaMenus();
                menuItem.classList.add('is-mega-open');
                triggerLink.setAttribute('aria-expanded', 'true');
            });

            menuItem.addEventListener('mouseleave', () => {
                if (!isDesktopViewport()) {
                    return;
                }

                scheduleMegaClose(menuItem, triggerLink);
            });

            subMenu.addEventListener('mouseenter', () => {
                if (!isDesktopViewport()) {
                    return;
                }

                clearMegaCloseTimer();
                menuItem.classList.add('is-mega-open');
                triggerLink.setAttribute('aria-expanded', 'true');
            });

            subMenu.addEventListener('mouseleave', () => {
                if (!isDesktopViewport()) {
                    return;
                }

                scheduleMegaClose(menuItem, triggerLink);
            });

            triggerLink.addEventListener('click', (event) => {
                event.preventDefault();

                const shouldOpen = !menuItem.classList.contains('is-mega-open');
                closeMegaMenus();

                if (shouldOpen) {
                    menuItem.classList.add('is-mega-open');
                    triggerLink.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', (event) => {
            if (megaParents.length === 0) {
                return;
            }

            const target = event.target;
            if (!(target instanceof Node)) {
                return;
            }

            if (nav.contains(target) || (navToggle && navToggle.contains(target))) {
                return;
            }

            closeMegaMenus();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMegaMenus();
            }
        });

        const closeMenu = () => {
            nav.classList.remove('is-open');
            if (navToggle) {
                navToggle.setAttribute('aria-expanded', 'false');
            }
            closeMegaMenus();
        };

        if (navToggle) {
            navToggle.addEventListener('click', () => {
                const isOpen = nav.classList.toggle('is-open');
                navToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        }

        navLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                if (event.defaultPrevented || link.getAttribute('data-mega-trigger') === '1') {
                    return;
                }
                closeMenu();
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 860) {
                closeMenu();
                return;
            }

            closeMegaMenus();
        });

        const normalizePath = (path) => {
            const cleanPath = String(path || '').replace(/\/+$/, '');
            return cleanPath === '' ? '/' : cleanPath;
        };

        const decodeHashId = (hash) => {
            const raw = String(hash || '').replace(/^#/, '').trim();
            if (raw === '') {
                return '';
            }

            try {
                return decodeURIComponent(raw);
            } catch (_error) {
                return raw;
            }
        };

        const currentUrl = new URL(window.location.href);
        const currentOrigin = currentUrl.origin;
        const currentPath = normalizePath(currentUrl.pathname);
        const currentSearch = String(currentUrl.search || '');

        const sectionEntries = [];

        navLinks.forEach((link) => {
            const href = String(link.getAttribute('href') || '').trim();
            if (href === '') {
                return;
            }

            let parsedUrl = null;
            try {
                parsedUrl = new URL(href, currentOrigin);
            } catch (_error) {
                return;
            }

            if (parsedUrl.origin !== currentOrigin) {
                return;
            }

            const targetPath = normalizePath(parsedUrl.pathname);
            const targetSearch = String(parsedUrl.search || '');
            const isSameDocument = targetPath === currentPath && targetSearch === currentSearch;

            if (!isSameDocument) {
                return;
            }

            const hashId = decodeHashId(parsedUrl.hash);
            const menuItem = link.closest('li');

            if (hashId === '') {
                return;
            }

            const section = document.getElementById(hashId);
            if (!section) {
                return;
            }

            sectionEntries.push({
                id: hashId,
                link,
                menuItem,
                section
            });
        });

        if (sectionEntries.length === 0) {
            return;
        }

        nav.classList.add('has-scrollspy');

        const setActiveLink = (activeLink) => {
            navLinks.forEach((link) => {
                const isActive = link === activeLink;
                const menuItem = link.closest('li');

                link.classList.toggle('is-active', isActive);
                if (menuItem) {
                    menuItem.classList.toggle('is-active', isActive);
                }

                if (isActive) {
                    link.setAttribute('data-scrollspy-current', '1');
                    link.setAttribute('aria-current', 'page');
                    return;
                }

                if (link.getAttribute('data-scrollspy-current') === '1') {
                    link.removeAttribute('data-scrollspy-current');
                    link.removeAttribute('aria-current');
                }
            });
        };

        const resolveActiveEntryByViewport = () => {
            const header = document.querySelector('.site-header');
            const headerOffset = header ? header.getBoundingClientRect().height : 0;
            const scanLine = window.scrollY + headerOffset + 20;

            const positionedEntries = sectionEntries
                .map((entry) => {
                    const rect = entry.section.getBoundingClientRect();
                    const top = rect.top + window.scrollY;

                    return {
                        entry,
                        top,
                        bottom: top + Math.max(rect.height, 1)
                    };
                })
                .sort((a, b) => a.top - b.top);

            for (let index = 0; index < positionedEntries.length; index += 1) {
                const current = positionedEntries[index];
                if (scanLine >= current.top && scanLine < current.bottom) {
                    return current.entry;
                }
            }

            return null;
        };

        const syncActiveState = () => {
            const activeEntry = resolveActiveEntryByViewport();
            setActiveLink(activeEntry ? activeEntry.link : null);
        };

        let pending = false;
        const scheduleSync = () => {
            if (pending) {
                return;
            }

            pending = true;
            window.requestAnimationFrame(() => {
                pending = false;
                syncActiveState();
            });
        };

        window.addEventListener('scroll', scheduleSync, { passive: true });
        window.addEventListener('resize', scheduleSync);
        window.addEventListener('hashchange', scheduleSync);
        window.addEventListener('load', scheduleSync, { once: true });

        scheduleSync();
    };

    const initHeroSlider = (slider) => {
        const slides = Array.from(slider.querySelectorAll('[data-slide]'));
        const dots = Array.from(slider.querySelectorAll('[data-slide-dot]'));
        const prevButton = slider.querySelector('[data-slide-prev]');
        const nextButton = slider.querySelector('[data-slide-next]');

        if (slides.length === 0) {
            return;
        }

        let currentIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        if (currentIndex < 0) {
            currentIndex = 0;
        }

        const setSlide = (index) => {
            const normalizedIndex = (index + slides.length) % slides.length;
            currentIndex = normalizedIndex;

            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === normalizedIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dots.forEach((dot, dotIndex) => {
                const isActive = dotIndex === normalizedIndex;
                dot.classList.toggle('is-active', isActive);
                dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        };

        setSlide(currentIndex);

        if (prevButton) {
            prevButton.addEventListener('click', () => {
                setSlide(currentIndex - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => {
                setSlide(currentIndex + 1);
            });
        }

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                const targetIndex = Number.parseInt(dot.dataset.slideDot || '0', 10);
                if (!Number.isNaN(targetIndex)) {
                    setSlide(targetIndex);
                }
            });
        });

        if (slider.dataset.autoplay !== 'true' || slides.length < 2) {
            return;
        }

        let autoplayTimer = null;

        const stopAutoplay = () => {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        };

        const startAutoplay = () => {
            stopAutoplay();
            autoplayTimer = window.setInterval(() => {
                setSlide(currentIndex + 1);
            }, 6000);
        };

        slider.addEventListener('mouseenter', stopAutoplay);
        slider.addEventListener('mouseleave', startAutoplay);
        slider.addEventListener('focusin', stopAutoplay);
        slider.addEventListener('focusout', (event) => {
            if (!slider.contains(event.relatedTarget)) {
                startAutoplay();
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stopAutoplay();
            } else {
                startAutoplay();
            }
        });

        startAutoplay();
    };

    const initHeroSliders = () => {
        const sliders = document.querySelectorAll('[data-hero-slider]');
        sliders.forEach((slider) => initHeroSlider(slider));
    };

    const initAvisosCarousel = (carousel) => {
        const viewport = carousel.querySelector('[data-avisos-viewport]');
        const track = carousel.querySelector('[data-avisos-track]');
        const cards = Array.from(carousel.querySelectorAll('[data-aviso-card]'));
        const prevButton = carousel.querySelector('[data-avisos-prev]');
        const nextButton = carousel.querySelector('[data-avisos-next]');

        if (!viewport || !track || cards.length === 0) {
            return;
        }

        let currentIndex = 0;
        let maxIndex = 0;
        let autoplayTimer = null;
        let wheelLocked = false;
        let swipeStartX = 0;
        let swipeStartY = 0;

        const getPerView = () => {
            const rawValue = window.getComputedStyle(carousel).getPropertyValue('--avisos-per-view');
            const parsed = Number.parseInt(rawValue || '5', 10);
            if (Number.isNaN(parsed) || parsed <= 0) {
                return 5;
            }
            return parsed;
        };

        const updateControls = () => {
            const isStatic = maxIndex <= 0;
            if (prevButton) {
                prevButton.disabled = isStatic;
            }
            if (nextButton) {
                nextButton.disabled = isStatic;
            }
        };

        const getTrackGap = () => {
            const styles = window.getComputedStyle(track);
            const rawGap = styles.columnGap || styles.gap || '0';
            const gap = Number.parseFloat(rawGap);
            if (Number.isNaN(gap) || gap < 0) {
                return 0;
            }
            return gap;
        };

        const getCardStep = () => {
            if (!cards[0]) {
                return viewport.clientWidth;
            }
            return cards[0].getBoundingClientRect().width + getTrackGap();
        };

        const applyTransform = () => {
            const step = getCardStep();
            const x = currentIndex * step;
            track.style.transform = `translateX(-${x}px)`;
        };

        const recalculateBounds = () => {
            const perView = getPerView();
            maxIndex = Math.max(cards.length - perView, 0);
            currentIndex = Math.max(0, Math.min(currentIndex, maxIndex));
        };

        const refresh = () => {
            recalculateBounds();
            applyTransform();
            updateControls();
        };

        const goToIndex = (index, wrap = false) => {
            if (maxIndex <= 0) {
                return;
            }

            if (wrap) {
                const totalPositions = maxIndex + 1;
                currentIndex = ((index % totalPositions) + totalPositions) % totalPositions;
            } else {
                currentIndex = Math.max(0, Math.min(index, maxIndex));
            }

            applyTransform();
            updateControls();
        };

        if (prevButton) {
            prevButton.addEventListener('click', () => {
                goToIndex(currentIndex - 1, false);
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => {
                goToIndex(currentIndex + 1, false);
            });
        }

        const stopAutoplay = () => {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        };

        const startAutoplay = () => {
            stopAutoplay();
            if (maxIndex <= 0) {
                return;
            }
            autoplayTimer = window.setInterval(() => {
                goToIndex(currentIndex + 1, true);
            }, 5200);
        };

        viewport.addEventListener(
            'wheel',
            (event) => {
                if (maxIndex <= 0) {
                    return;
                }

                const primaryDelta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
                if (Math.abs(primaryDelta) < 16) {
                    return;
                }

                event.preventDefault();
                if (wheelLocked) {
                    return;
                }

                wheelLocked = true;
                stopAutoplay();
                goToIndex(currentIndex + (primaryDelta > 0 ? 1 : -1), false);
                window.setTimeout(() => {
                    wheelLocked = false;
                    startAutoplay();
                }, 260);
            },
            { passive: false }
        );

        viewport.addEventListener(
            'touchstart',
            (event) => {
                if (!event.touches || event.touches.length !== 1) {
                    return;
                }
                swipeStartX = event.touches[0].clientX;
                swipeStartY = event.touches[0].clientY;
            },
            { passive: true }
        );

        viewport.addEventListener(
            'touchend',
            (event) => {
                if (!event.changedTouches || event.changedTouches.length !== 1) {
                    return;
                }

                const deltaX = event.changedTouches[0].clientX - swipeStartX;
                const deltaY = event.changedTouches[0].clientY - swipeStartY;
                if (Math.abs(deltaX) < 42 || Math.abs(deltaX) <= Math.abs(deltaY)) {
                    return;
                }

                stopAutoplay();
                goToIndex(currentIndex + (deltaX < 0 ? 1 : -1), false);
                startAutoplay();
            },
            { passive: true }
        );

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) {
                startAutoplay();
            }
        });

        window.addEventListener('resize', () => {
            refresh();
        });

        refresh();
        startAutoplay();
    };

    const initAvisosCarousels = () => {
        const carousels = document.querySelectorAll('[data-avisos-carousel]');
        carousels.forEach((carousel) => initAvisosCarousel(carousel));
    };

    const initNewsCarousel = (carousel) => {
        const track = carousel.querySelector('[data-news-track]') || carousel.querySelector('.news-carousel__track');
        const cards = Array.from(carousel.querySelectorAll('.news-card'));
        const controlsRoot = carousel.parentElement || carousel;
        const prevButton = controlsRoot.querySelector('[data-news-prev]');
        const nextButton = controlsRoot.querySelector('[data-news-next]');

        if (!track || cards.length === 0) {
            return;
        }

        let currentIndex = 0;
        let maxIndex = 0;
        let autoplayTimer = null;
        let scrollRaf = null;
        let pointerDown = false;

        const getPerView = () => {
            const rawValue = window.getComputedStyle(carousel).getPropertyValue('--news-visible');
            const parsed = Number.parseInt(rawValue || '4', 10);
            if (Number.isNaN(parsed) || parsed <= 0) {
                return 4;
            }
            return parsed;
        };

        const updateControls = () => {
            const isStatic = maxIndex <= 0;
            if (prevButton) {
                prevButton.disabled = isStatic;
            }
            if (nextButton) {
                nextButton.disabled = isStatic;
            }
        };

        const getTrackGap = () => {
            const styles = window.getComputedStyle(track);
            const rawGap = styles.columnGap || styles.gap || '0';
            const gap = Number.parseFloat(rawGap);
            if (Number.isNaN(gap) || gap < 0) {
                return 0;
            }
            return gap;
        };

        const getCardStep = () => {
            if (!cards[0]) {
                return carousel.clientWidth;
            }
            return cards[0].getBoundingClientRect().width + getTrackGap();
        };

        const recalculateBounds = () => {
            const perView = getPerView();
            maxIndex = Math.max(cards.length - perView, 0);
            currentIndex = Math.max(0, Math.min(currentIndex, maxIndex));
        };

        const goToIndex = (index, options = {}) => {
            const { wrap = false, behavior = 'smooth' } = options;
            if (maxIndex <= 0) {
                currentIndex = 0;
                carousel.scrollTo({ left: 0, behavior });
                updateControls();
                return;
            }
            if (wrap) {
                const totalPositions = maxIndex + 1;
                currentIndex = ((index % totalPositions) + totalPositions) % totalPositions;
            } else {
                currentIndex = Math.max(0, Math.min(index, maxIndex));
            }

            const step = getCardStep();
            carousel.scrollTo({
                left: currentIndex * step,
                behavior,
            });
            updateControls();
        };

        const syncIndexFromScroll = () => {
            if (scrollRaf) {
                return;
            }
            scrollRaf = window.requestAnimationFrame(() => {
                scrollRaf = null;
                const step = getCardStep();
                if (step <= 0) {
                    return;
                }
                const rawIndex = Math.round(carousel.scrollLeft / step);
                currentIndex = Math.max(0, Math.min(rawIndex, maxIndex));
                updateControls();
            });
        };

        const stopAutoplay = () => {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        };

        const startAutoplay = () => {
            stopAutoplay();
            if (maxIndex <= 0) {
                return;
            }
            autoplayTimer = window.setInterval(() => {
                goToIndex(currentIndex + 1, { wrap: true, behavior: 'smooth' });
            }, 5200);
        };

        if (prevButton) {
            prevButton.addEventListener('click', () => {
                stopAutoplay();
                goToIndex(currentIndex - 1, { wrap: false, behavior: 'smooth' });
                startAutoplay();
            });
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => {
                stopAutoplay();
                goToIndex(currentIndex + 1, { wrap: false, behavior: 'smooth' });
                startAutoplay();
            });
        }

        carousel.addEventListener('scroll', syncIndexFromScroll, { passive: true });
        carousel.addEventListener('pointerdown', () => {
            pointerDown = true;
            stopAutoplay();
        });
        carousel.addEventListener('pointerup', () => {
            if (!pointerDown) {
                return;
            }
            pointerDown = false;
            startAutoplay();
        });
        carousel.addEventListener('pointerleave', () => {
            if (!pointerDown) {
                return;
            }
            pointerDown = false;
            startAutoplay();
        });

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', (event) => {
            if (!carousel.contains(event.relatedTarget)) {
                startAutoplay();
            }
        });

        window.addEventListener('resize', () => {
            recalculateBounds();
            goToIndex(currentIndex, { wrap: false, behavior: 'auto' });
        });

        recalculateBounds();
        goToIndex(0, { wrap: false, behavior: 'auto' });
        startAutoplay();
    };

    const initNewsCarousels = () => {
        const carousels = document.querySelectorAll('[data-news-carousel]');
        carousels.forEach((carousel) => initNewsCarousel(carousel));
    };

    const initNewsGallery = (gallery) => {
        const slides = Array.from(gallery.querySelectorAll('[data-news-gallery-slide]'));
        if (slides.length === 0) {
            return;
        }

        const thumbs = Array.from(gallery.querySelectorAll('[data-news-gallery-thumb]'));
        const prevButtons = Array.from(gallery.querySelectorAll('[data-news-gallery-prev]'));
        const nextButtons = Array.from(gallery.querySelectorAll('[data-news-gallery-next]'));
        const openButtons = Array.from(gallery.querySelectorAll('[data-news-gallery-open]'));
        const closeButtons = Array.from(gallery.querySelectorAll('[data-news-gallery-close]'));
        const lightbox = gallery.querySelector('[data-news-gallery-lightbox]');
        const lightboxImage = gallery.querySelector('[data-news-gallery-lightbox-image]');

        let currentIndex = 0;

        const setSlide = (index) => {
            const normalizedIndex = (index + slides.length) % slides.length;
            currentIndex = normalizedIndex;

            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === normalizedIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            thumbs.forEach((thumb, thumbIndex) => {
                const isActive = thumbIndex === normalizedIndex;
                thumb.classList.toggle('is-active', isActive);
                thumb.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            if (lightbox && lightboxImage) {
                const activeImage = slides[normalizedIndex].querySelector('.single-noticia-gallery__image');
                if (activeImage) {
                    const fullSrc = activeImage.getAttribute('data-full-src') || activeImage.getAttribute('src') || '';
                    const altText = activeImage.getAttribute('alt') || '';
                    lightboxImage.setAttribute('src', fullSrc);
                    lightboxImage.setAttribute('alt', altText);
                }
            }
        };

        const toggleLightbox = (isOpen) => {
            if (!lightbox) {
                return;
            }

            if (isOpen) {
                lightbox.removeAttribute('hidden');
                lightbox.setAttribute('aria-hidden', 'false');
                document.body.classList.add('has-news-gallery-lightbox');
            } else {
                lightbox.setAttribute('hidden', 'hidden');
                lightbox.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('has-news-gallery-lightbox');
            }
        };

        prevButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setSlide(currentIndex - 1);
            });
        });

        nextButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setSlide(currentIndex + 1);
            });
        });

        thumbs.forEach((thumb) => {
            thumb.addEventListener('click', () => {
                const index = Number.parseInt(thumb.dataset.index || '0', 10);
                if (!Number.isNaN(index)) {
                    setSlide(index);
                }
            });
        });

        openButtons.forEach((button) => {
            button.addEventListener('click', () => {
                toggleLightbox(true);
            });
        });

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                toggleLightbox(false);
            });
        });

        if (lightbox) {
            lightbox.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                if (target === lightbox) {
                    toggleLightbox(false);
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                toggleLightbox(false);
            }

            if (event.key === 'ArrowLeft') {
                const isLightboxOpen = lightbox && !lightbox.hasAttribute('hidden');
                if (isLightboxOpen) {
                    event.preventDefault();
                    setSlide(currentIndex - 1);
                }
            }

            if (event.key === 'ArrowRight') {
                const isLightboxOpen = lightbox && !lightbox.hasAttribute('hidden');
                if (isLightboxOpen) {
                    event.preventDefault();
                    setSlide(currentIndex + 1);
                }
            }
        });

        setSlide(0);
    };

    const initNewsGalleries = () => {
        const galleries = document.querySelectorAll('[data-news-gallery]');
        galleries.forEach((gallery) => initNewsGallery(gallery));
    };

    const initDireccionAccordion = (accordion) => {
        const items = Array.from(accordion.querySelectorAll('[data-direccion-accordion-item]'));
        if (items.length === 0) {
            return;
        }

        items.forEach((item, index) => {
            const button = item.querySelector('[data-direccion-accordion-toggle]');
            const panel = item.querySelector('[data-direccion-accordion-panel]');
            if (!button || !panel) {
                return;
            }

            const isInitiallyOpen = index === 0 && !panel.hasAttribute('hidden');
            button.setAttribute('aria-expanded', isInitiallyOpen ? 'true' : 'false');
            button.classList.toggle('is-active', isInitiallyOpen);

            button.addEventListener('click', () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                const nextState = !isExpanded;
                button.setAttribute('aria-expanded', nextState ? 'true' : 'false');
                button.classList.toggle('is-active', nextState);

                if (nextState) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', 'hidden');
                }
            });
        });
    };

    const initDireccionSubtabs = (subtabsRoot) => {
        const items = Array.from(subtabsRoot.querySelectorAll('[data-direccion-subtab-item]'));
        if (items.length === 0) {
            return;
        }

        const setItemState = (button, panel, shouldOpen) => {
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            button.classList.toggle('elementor-active', shouldOpen);
            panel.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
        };

        const entries = items
            .map((item) => {
                const button = item.querySelector('[data-direccion-subtab-toggle]');
                const panel = item.querySelector('[data-direccion-subtab-panel]');
                if (!button || !panel) {
                    return null;
                }
                return { button, panel };
            })
            .filter(Boolean);

        entries.forEach((entry) => {
            const { button, panel } = entry;
            setItemState(button, panel, false);
        });

        entries.forEach((entry) => {
            const { button, panel } = entry;
            const closeOthers = () => {
                entries.forEach((otherEntry) => {
                    if (otherEntry.button !== button) {
                        setItemState(otherEntry.button, otherEntry.panel, false);
                    }
                });
            };

            const toggle = () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                if (isExpanded) {
                    setItemState(button, panel, false);
                    return;
                }
                closeOthers();
                setItemState(button, panel, true);
            };

            button.addEventListener('click', () => {
                toggle();
            });

            button.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    toggle();
                }
            });
        });
    };

    const initDireccionAccordions = () => {
        const accordions = document.querySelectorAll('[data-direccion-accordion]');
        accordions.forEach((accordion) => initDireccionAccordion(accordion));

        const subtabs = document.querySelectorAll('[data-direccion-subtabs]');
        subtabs.forEach((subtabsRoot) => initDireccionSubtabs(subtabsRoot));
    };


    const initHeaderSearch = () => {
        const searchRoots = document.querySelectorAll('[data-header-search]');
        if (searchRoots.length === 0) {
            return;
        }

        const toAbsoluteUrl = (path) => {
            const value = String(path || '').trim();
            if (value === '') {
                return '';
            }

            try {
                return new URL(value, window.location.origin).href;
            } catch (_error) {
                return value;
            }
        };

        const isBlockedWordPressUrl = (url) => {
            const absoluteUrl = toAbsoluteUrl(url);
            if (absoluteUrl === '') {
                return true;
            }

            let parsedUrl = null;
            try {
                parsedUrl = new URL(absoluteUrl, window.location.origin);
            } catch (_error) {
                return true;
            }

            const normalizedPath = String(parsedUrl.pathname || '').toLowerCase();
            const normalizedSearch = String(parsedUrl.search || '').toLowerCase();
            const blockedPathTokens = ['/wp-admin', '/wp-content', '/wp-includes', '/wp-login.php', '/xmlrpc.php'];

            if (blockedPathTokens.some((token) => normalizedPath.includes(token))) {
                return true;
            }

            const blockedQueryTokens = ['wp-admin', 'wp-content', 'wp-includes', 'rest_route=/wp/', '/wp-json/wp/'];
            return blockedQueryTokens.some((token) => normalizedSearch.includes(token));
        };

        const recommendedEntries = [
            { label: 'Permiso de circulacion', url: toAbsoluteUrl('/?s=permiso+circulacion') },
            { label: 'Pago de patentes', url: toAbsoluteUrl('/?s=patentes') },
            { label: 'Noticias municipales', url: toAbsoluteUrl('/?categoria_noticia=noticias') },
            { label: 'Categoria noticias', url: toAbsoluteUrl('/?categoria_noticia=noticias') },
            { label: 'Direcciones municipales', url: toAbsoluteUrl('/?post_type=direccion_municipal') },
            { label: 'Plan Regulador', url: toAbsoluteUrl('/?post_type=plan_regulador') },
            { label: 'Eventos en Concepcion', url: toAbsoluteUrl('/?post_type=evento_municipal') },
            { label: 'Avisos municipales', url: toAbsoluteUrl('/?post_type=aviso') },
            { label: 'Oficina virtual', url: toAbsoluteUrl('/#temas-ciudadanos') },
            { label: 'Transparencia municipal', url: toAbsoluteUrl('/?s=transparencia') }
        ];
        const noticiasCategoryEntry = { label: 'Categoria noticias', url: toAbsoluteUrl('/?categoria_noticia=noticias') };

        const normalizeText = (value) => String(value || '')
            .trim()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');

        const blockedSearchTokens = new Set([
            'wp-admin',
            'wp-content',
            'wp-includes',
            'wp-json',
            'wp-login',
            'xmlrpc',
            'wordpress',
            'wp'
        ]);

        const sanitizeQueryForMatching = (value) => normalizeText(value)
            .split(/\s+/)
            .map((token) => token.trim().replace(/[^a-z0-9_-]/g, ''))
            .filter((token) => token !== '' && !blockedSearchTokens.has(token))
            .join(' ');

        const escapeHtml = (value) => String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        searchRoots.forEach((root) => {
            const searchShell = root.querySelector('[data-header-search-shell]');
            const trigger = root.querySelector('[data-header-search-trigger]');
            const panel = root.querySelector('[data-header-search-panel]');
            const form = root.querySelector('[data-header-search-form]');
            const input = root.querySelector('[data-header-search-input]');
            const suggestionsList = root.querySelector('[data-header-search-suggestions]');

            if (!searchShell || !trigger || !panel || !form || !input || !suggestionsList) {
                return;
            }

            const endpoint = String(root.getAttribute('data-search-endpoint') || '').trim();
            const subtypeRaw = String(root.getAttribute('data-search-types') || '').trim();
            const subtypes = subtypeRaw === ''
                ? []
                : subtypeRaw.split(',').map((value) => value.trim()).filter((value) => value !== '');

            let isOpen = false;
            let closeTimer = null;
            let debounceTimer = null;
            let abortController = null;
            let activeIndex = -1;
            let renderedSuggestions = [];
            const siteLinkEntries = Array.from(document.querySelectorAll('a[href]'))
                .map((link) => {
                    if (!(link instanceof HTMLAnchorElement)) {
                        return null;
                    }

                    const href = String(link.getAttribute('href') || '').trim();
                    if (href === '' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('tel:') || href.startsWith('mailto:')) {
                        return null;
                    }

                    let parsedUrl = null;
                    try {
                        parsedUrl = new URL(href, window.location.origin);
                    } catch (_error) {
                        return null;
                    }

                    if (parsedUrl.origin !== window.location.origin) {
                        return null;
                    }

                    const label = String(link.textContent || '').replace(/\s+/g, ' ').trim();
                    const normalizedPath = `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
                    const absoluteUrl = toAbsoluteUrl(normalizedPath);

                    if (absoluteUrl === '' || isBlockedWordPressUrl(absoluteUrl)) {
                        return null;
                    }

                    return {
                        label: label !== '' ? label : absoluteUrl,
                        url: absoluteUrl
                    };
                })
                .filter(Boolean);

            const setOpenState = (nextOpenState) => {
                isOpen = nextOpenState;
                root.classList.toggle('is-search-open', nextOpenState);
                trigger.setAttribute('aria-expanded', nextOpenState ? 'true' : 'false');
                panel.setAttribute('aria-hidden', nextOpenState ? 'false' : 'true');
            };

            const openSearch = (focusInput = false) => {
                if (closeTimer) {
                    window.clearTimeout(closeTimer);
                    closeTimer = null;
                }
                setOpenState(true);
                if (focusInput) {
                    window.requestAnimationFrame(() => {
                        input.focus({ preventScroll: true });
                    });
                }
            };

            const closeSearch = () => {
                setOpenState(false);
                activeIndex = -1;
                suggestionsList
                    .querySelectorAll('.site-header-search-suggest__btn.is-active')
                    .forEach((button) => button.classList.remove('is-active'));
            };

            const scheduleClose = () => {
                if (closeTimer) {
                    window.clearTimeout(closeTimer);
                }
                closeTimer = window.setTimeout(() => {
                    closeSearch();
                }, 220);
            };

            const toSuggestion = (item) => {
                if (typeof item === 'string') {
                    return {
                        label: item.trim(),
                        url: ''
                    };
                }

                if (!item || typeof item !== 'object') {
                    return {
                        label: '',
                        url: ''
                    };
                }

                const rawLabel = typeof item.label === 'string' ? item.label : '';
                const rawUrl = typeof item.url === 'string' ? item.url : '';
                const label = rawLabel.trim();
                const url = rawUrl.trim();

                if (label !== '') {
                    return { label, url };
                }

                return {
                    label: url,
                    url
                };
            };

            const dedupeSuggestions = (items) => {
                const seen = new Set();
                const unique = [];

                items.forEach((item) => {
                    const suggestion = toSuggestion(item);
                    if (suggestion.label === '') {
                        return;
                    }

                    const key = `${normalizeText(suggestion.label)}|${normalizeText(suggestion.url)}`;
                    if (key === '' || seen.has(key)) {
                        return;
                    }

                    seen.add(key);
                    unique.push(suggestion);
                });

                return unique;
            };

            const isSubsequenceMatch = (candidate, query) => {
                if (query === '') {
                    return true;
                }

                let queryIndex = 0;
                for (let index = 0; index < candidate.length; index += 1) {
                    if (candidate[index] === query[queryIndex]) {
                        queryIndex += 1;
                        if (queryIndex >= query.length) {
                            return true;
                        }
                    }
                }

                return false;
            };

            const tokenizeQuery = (value) => sanitizeQueryForMatching(value)
                .split(/\s+/)
                .map((token) => token.trim())
                .filter((token) => token !== '');

            const levenshteinDistance = (left, right) => {
                const leftLength = left.length;
                const rightLength = right.length;

                if (leftLength === 0) {
                    return rightLength;
                }
                if (rightLength === 0) {
                    return leftLength;
                }

                const matrix = Array.from({ length: rightLength + 1 }, () => new Array(leftLength + 1).fill(0));
                for (let leftIndex = 0; leftIndex <= leftLength; leftIndex += 1) {
                    matrix[0][leftIndex] = leftIndex;
                }
                for (let rightIndex = 0; rightIndex <= rightLength; rightIndex += 1) {
                    matrix[rightIndex][0] = rightIndex;
                }

                for (let rightIndex = 1; rightIndex <= rightLength; rightIndex += 1) {
                    for (let leftIndex = 1; leftIndex <= leftLength; leftIndex += 1) {
                        const substitutionCost = left[leftIndex - 1] === right[rightIndex - 1] ? 0 : 1;
                        matrix[rightIndex][leftIndex] = Math.min(
                            matrix[rightIndex - 1][leftIndex] + 1,
                            matrix[rightIndex][leftIndex - 1] + 1,
                            matrix[rightIndex - 1][leftIndex - 1] + substitutionCost
                        );
                    }
                }

                return matrix[rightLength][leftLength];
            };

            const getSuggestionScore = (candidateText, queryText) => {
                const candidate = normalizeText(candidateText);
                const query = sanitizeQueryForMatching(queryText);
                const queryTokens = tokenizeQuery(query);

                if (candidate === '' || query === '') {
                    return 0;
                }

                let score = 0;

                if (candidate === query) {
                    score += 220;
                }

                if (candidate.startsWith(query)) {
                    score += 140;
                }

                const words = candidate.split(/\s+/).filter((word) => word !== '');
                if (words.some((word) => word.startsWith(query))) {
                    score += 120;
                }

                if (candidate.includes(query)) {
                    score += 98;
                }

                if (isSubsequenceMatch(candidate, query)) {
                    score += 76;
                }

                if (queryTokens.length > 1) {
                    let matchedExactTokenCount = 0;
                    let matchedPartialTokenCount = 0;
                    let matchedFuzzyTokenCount = 0;

                    queryTokens.forEach((token) => {
                        if (token === '') {
                            return;
                        }

                        if (candidate.includes(token) || words.some((word) => word.startsWith(token))) {
                            matchedExactTokenCount += 1;
                            return;
                        }

                        if (isSubsequenceMatch(candidate, token)) {
                            matchedPartialTokenCount += 1;
                            return;
                        }

                        const compactToken = token.replace(/[^a-z0-9]/g, '');
                        const compactCandidate = candidate.replace(/[^a-z0-9]/g, '');
                        if (compactToken === '' || compactCandidate === '') {
                            return;
                        }

                        const fuzzyWindow = compactCandidate.length > compactToken.length + 3
                            ? compactCandidate.slice(0, compactToken.length + 3)
                            : compactCandidate;
                        const tokenDistance = levenshteinDistance(fuzzyWindow, compactToken);
                        const tokenTolerance = compactToken.length <= 4 ? 1 : 2;

                        if (tokenDistance <= tokenTolerance) {
                            matchedFuzzyTokenCount += 1;
                        }
                    });

                    score += (matchedExactTokenCount * 34) + (matchedPartialTokenCount * 18) + (matchedFuzzyTokenCount * 12);

                    if (matchedExactTokenCount === queryTokens.length) {
                        score += 70;
                    } else if (matchedExactTokenCount + matchedPartialTokenCount === queryTokens.length) {
                        score += 48;
                    } else if (matchedExactTokenCount > 0) {
                        score += 22;
                    }
                }

                const compactCandidate = candidate.replace(/[^a-z0-9]/g, '');
                const compactQuery = query.replace(/[^a-z0-9]/g, '');
                if (compactCandidate === '' || compactQuery === '') {
                    return score;
                }

                const queryLength = compactQuery.length;
                const candidateWindow = compactCandidate.length > queryLength + 4
                    ? compactCandidate.slice(0, queryLength + 4)
                    : compactCandidate;

                const distance = levenshteinDistance(candidateWindow, compactQuery);
                const ratio = 1 - (distance / Math.max(candidateWindow.length, compactQuery.length, 1));

                if (queryLength <= 4 && distance <= 2) {
                    score += 52 + Math.round((ratio + 0.25) * 10);
                }

                if (ratio >= 0.58) {
                    score += 48 + Math.round(ratio * 18);
                }

                return score;
            };

            const sortSuggestionsBySensitivity = (items, query, limit = 8) => {
                const normalizedQuery = sanitizeQueryForMatching(query);
                const source = dedupeSuggestions(items);

                if (normalizedQuery === '') {
                    return source.slice(0, limit);
                }

                return source
                    .map((item, index) => {
                        const labelScore = getSuggestionScore(item.label, normalizedQuery);
                        const urlScore = getSuggestionScore(item.url, normalizedQuery);
                        return {
                            item,
                            score: Math.max(labelScore, Math.round(urlScore * 0.95)),
                            index
                        };
                    })
                    .filter((entry) => entry.score > 0)
                    .sort((a, b) => {
                        if (b.score !== a.score) {
                            return b.score - a.score;
                        }
                        if (a.item.label.length !== b.item.label.length) {
                            return a.item.label.length - b.item.label.length;
                        }
                        return a.index - b.index;
                    })
                    .map((entry) => entry.item)
                    .slice(0, limit);
            };

            const getRecommendedSuggestions = (query) => {
                const normalizedQuery = sanitizeQueryForMatching(query);
                if (normalizedQuery === '') {
                    return recommendedEntries.slice(0, 6);
                }

                return sortSuggestionsBySensitivity(recommendedEntries, normalizedQuery, 6);
            };

            const renderSuggestions = (items) => {
                renderedSuggestions = items;
                activeIndex = -1;

                if (items.length === 0) {
                    suggestionsList.innerHTML = '';
                    return;
                }

                suggestionsList.innerHTML = items
                    .map((item, index) => (
                        `<li class="site-header-search-suggest__item">` +
                        `<button type="button" class="site-header-search-suggest__btn" data-search-suggestion-index="${index}">` +
                        `<span class="site-header-search-suggest__label">${escapeHtml(item.label)}</span>` +
                        `</button>` +
                        `</li>`
                    ))
                    .join('');

                suggestionsList
                    .querySelectorAll('[data-search-suggestion-index]')
                    .forEach((button) => {
                        button.addEventListener('click', () => {
                            const itemIndex = Number.parseInt(button.getAttribute('data-search-suggestion-index') || '-1', 10);
                            if (Number.isNaN(itemIndex) || !renderedSuggestions[itemIndex]) {
                                return;
                            }

                            const selectedSuggestion = renderedSuggestions[itemIndex];
                            if (selectedSuggestion.url) {
                                window.location.assign(selectedSuggestion.url);
                                return;
                            }

                            input.value = selectedSuggestion.label;
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                                return;
                            }
                            form.submit();
                        });
                    });
            };

            const setActiveSuggestion = (nextIndex) => {
                const buttons = Array.from(suggestionsList.querySelectorAll('[data-search-suggestion-index]'));
                if (buttons.length === 0) {
                    activeIndex = -1;
                    return;
                }

                buttons.forEach((button) => button.classList.remove('is-active'));

                if (nextIndex < 0 || nextIndex >= buttons.length) {
                    activeIndex = -1;
                    return;
                }

                activeIndex = nextIndex;
                const activeButton = buttons[nextIndex];
                activeButton.classList.add('is-active');
                activeButton.scrollIntoView({ block: 'nearest' });
            };

            const fetchSiteSuggestions = async (query) => {
                const normalizedQuery = sanitizeQueryForMatching(query);
                if (normalizedQuery.length < 1 || endpoint === '') {
                    return [];
                }

                if (abortController) {
                    abortController.abort();
                }
                abortController = new AbortController();

                const buildEntries = (payload) => {
                    if (!Array.isArray(payload)) {
                        return [];
                    }

                    return payload
                        .map((item) => {
                            if (!item || typeof item !== 'object') {
                                return null;
                            }
                            const title = typeof item.title === 'string' ? item.title.trim() : '';
                            const url = typeof item.url === 'string' ? item.url.trim() : '';
                            if (title === '' && url === '') {
                                return null;
                            }
                            const normalizedUrl = toAbsoluteUrl(url);
                            if (isBlockedWordPressUrl(normalizedUrl)) {
                                return null;
                            }
                            return {
                                label: title !== '' ? title : url,
                                url: normalizedUrl
                            };
                        })
                        .filter(Boolean);
                };

                const postParams = new URLSearchParams();
                postParams.set('search', normalizedQuery);
                postParams.set('per_page', '12');
                postParams.set('_fields', 'title,url,subtype');
                subtypes.forEach((subtype) => {
                    postParams.append('subtype[]', subtype);
                });

                const requests = [
                    window.fetch(`${endpoint}?${postParams.toString()}`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        signal: abortController.signal
                    })
                ];

                const termParams = new URLSearchParams();
                termParams.set('type', 'term');
                termParams.set('search', normalizedQuery);
                termParams.set('per_page', '8');
                termParams.set('_fields', 'title,url,subtype');
                termParams.append('subtype[]', 'categoria_noticia');

                requests.push(
                    window.fetch(`${endpoint}?${termParams.toString()}`, {
                        method: 'GET',
                        credentials: 'same-origin',
                        signal: abortController.signal
                    })
                );

                try {
                    const settledResponses = await Promise.allSettled(requests);
                    const responsePayloads = await Promise.all(
                        settledResponses.map(async (settled) => {
                            if (settled.status !== 'fulfilled') {
                                return null;
                            }
                            const response = settled.value;
                            if (!response.ok) {
                                return null;
                            }

                            try {
                                return await response.json();
                            } catch (_error) {
                                return null;
                            }
                        })
                    );

                    const mergedEntries = responsePayloads.flatMap((payload) => buildEntries(payload));
                    return sortSuggestionsBySensitivity(mergedEntries, normalizedQuery, 12);
                } catch (_error) {
                    return [];
                }
            };

            const updateSuggestions = () => {
                const query = input.value.trim();

                if (debounceTimer) {
                    window.clearTimeout(debounceTimer);
                }

                debounceTimer = window.setTimeout(async () => {
                    const recommended = getRecommendedSuggestions(query);
                    const siteSuggestions = await fetchSiteSuggestions(query);
                    const normalizedQuery = sanitizeQueryForMatching(query);
                    const mustPrioritizeNoticias = normalizedQuery.includes('noticia');
                    const merged = sortSuggestionsBySensitivity(
                        [
                            ...(mustPrioritizeNoticias ? [noticiasCategoryEntry] : []),
                            ...recommended,
                            ...siteSuggestions,
                            ...siteLinkEntries
                        ],
                        query,
                        10
                    );
                    renderSuggestions(merged);
                }, 70);
            };

            trigger.addEventListener('mouseenter', () => {
                openSearch(false);
                updateSuggestions();
            });

            searchShell.addEventListener('mouseenter', () => {
                openSearch(false);
            });

            searchShell.addEventListener('mouseleave', () => {
                scheduleClose();
            });

            searchShell.addEventListener('focusin', () => {
                openSearch(false);
            });

            searchShell.addEventListener('focusout', (event) => {
                const relatedTarget = event.relatedTarget;
                if (relatedTarget instanceof Node && searchShell.contains(relatedTarget)) {
                    return;
                }
                scheduleClose();
            });

            trigger.addEventListener('click', () => {
                if (isOpen) {
                    closeSearch();
                    return;
                }
                openSearch(true);
                updateSuggestions();
            });

            input.addEventListener('focus', () => {
                openSearch(false);
                updateSuggestions();
            });

            input.addEventListener('input', () => {
                updateSuggestions();
            });

            input.addEventListener('keydown', (event) => {
                const buttons = Array.from(suggestionsList.querySelectorAll('[data-search-suggestion-index]'));
                if (buttons.length === 0) {
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    const next = activeIndex < buttons.length - 1 ? activeIndex + 1 : 0;
                    setActiveSuggestion(next);
                    return;
                }

                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    const next = activeIndex > 0 ? activeIndex - 1 : buttons.length - 1;
                    setActiveSuggestion(next);
                    return;
                }

                if (event.key === 'Enter' && activeIndex >= 0 && renderedSuggestions[activeIndex]) {
                    event.preventDefault();
                    const selectedSuggestion = renderedSuggestions[activeIndex];
                    if (selectedSuggestion.url) {
                        window.location.assign(selectedSuggestion.url);
                        return;
                    }

                    input.value = selectedSuggestion.label;
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                        return;
                    }
                    form.submit();
                }
            });

            form.addEventListener('submit', (event) => {
                const value = input.value.trim();
                if (value === '') {
                    event.preventDefault();
                    input.focus();
                }
            });

            document.addEventListener('click', (event) => {
                const target = event.target;
                if (target instanceof Node && root.contains(target)) {
                    return;
                }
                closeSearch();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key !== 'Escape' || !isOpen) {
                    return;
                }
                closeSearch();
                trigger.focus({ preventScroll: true });
            });

            renderSuggestions(getRecommendedSuggestions(''));
        });
    };

    initNavigation();
    initHeroSliders();
    initAvisosCarousels();
    initNewsCarousels();
    initNewsGalleries();
    initDireccionAccordions();
    initHeaderSearch();
})();
