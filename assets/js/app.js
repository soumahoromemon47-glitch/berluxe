'use strict';

document.documentElement.classList.add('js');

const searchToggle = document.querySelector('[data-search-toggle]');
const searchPopover = document.querySelector('[data-search-popover]');

if (searchToggle instanceof HTMLButtonElement && searchPopover instanceof HTMLFormElement) {
    const closeSearch = () => {
        searchPopover.classList.remove('is-open');
        searchToggle.setAttribute('aria-expanded', 'false');
        searchToggle.setAttribute('aria-label', 'Ouvrir la recherche');
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        const clickedToggle = target instanceof Element
            ? target.closest('[data-search-toggle]')
            : null;

        if (clickedToggle === searchToggle) {
            const isOpen = searchPopover.classList.toggle('is-open');
            searchToggle.setAttribute('aria-expanded', String(isOpen));
            searchToggle.setAttribute(
                'aria-label',
                isOpen ? 'Fermer la recherche' : 'Ouvrir la recherche'
            );
            if (isOpen) {
                searchPopover.querySelector('input[type="search"]')?.focus();
            }
            return;
        }

        if (searchPopover.classList.contains('is-open')
            && target instanceof Node
            && !searchPopover.contains(target)
        ) {
            closeSearch();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && searchPopover.classList.contains('is-open')) {
            closeSearch();
            searchToggle.focus();
        }
    });
}

function showCarouselSlide(carousel, index) {
    const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
    if (slides.length < 2) {
        return;
    }

    const dots = Array.from(carousel.querySelectorAll('[data-carousel-go]'));
    const status = carousel.querySelector('[data-carousel-status]');
    const safeIndex = ((index % slides.length) + slides.length) % slides.length;

    slides.forEach((slide, i) => {
        slide.hidden = i !== safeIndex;
    });
    dots.forEach((dot, i) => {
        dot.setAttribute('aria-current', String(i === safeIndex));
    });
    if (status instanceof HTMLElement) {
        status.textContent = `${safeIndex + 1} / ${slides.length}`;
    }
}

function nextCarouselSlide(carousel) {
    const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
    if (slides.length < 2) {
        return;
    }
    const currentIndex = slides.findIndex((slide) => !slide.hasAttribute('hidden'));
    showCarouselSlide(carousel, currentIndex + 1);
}

document.addEventListener('click', (event) => {
    const target = event.target;
    const control = target instanceof Element
        ? target.closest('[data-carousel-prev], [data-carousel-next], [data-carousel-go]')
        : null;
    if (!(control instanceof HTMLButtonElement)) {
        return;
    }

    const carousel = control.closest('[data-carousel]');
    if (!(carousel instanceof HTMLElement)) {
        return;
    }

    if (control.hasAttribute('data-carousel-prev')) {
        const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
        const currentIndex = slides.findIndex((slide) => !slide.hasAttribute('hidden'));
        showCarouselSlide(carousel, currentIndex - 1);
    } else if (control.hasAttribute('data-carousel-next')) {
        nextCarouselSlide(carousel);
    } else {
        const requestedIndex = Number(control.dataset.carouselGo);
        if (!Number.isInteger(requestedIndex) || requestedIndex < 0) {
            return;
        }
        showCarouselSlide(carousel, requestedIndex);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
        return;
    }

    const carousel = event.target instanceof Element
        ? event.target.closest('[data-carousel]')
        : null;
    const control = carousel?.querySelector(
        event.key === 'ArrowLeft' ? '[data-carousel-prev]' : '[data-carousel-next]'
    );
    if (control instanceof HTMLButtonElement) {
        event.preventDefault();
        control.click();
    }
});

const CAROUSEL_AUTOPLAY_DELAY = 4000;

document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = Array.from(carousel.querySelectorAll('[data-carousel-slide]'));
    if (slides.length < 2) {
        return;
    }

    let timer = setInterval(() => nextCarouselSlide(carousel), CAROUSEL_AUTOPLAY_DELAY);

    const pause = () => clearInterval(timer);
    const resume = () => {
        timer = setInterval(() => nextCarouselSlide(carousel), CAROUSEL_AUTOPLAY_DELAY);
    };

    carousel.addEventListener('mouseenter', pause);
    carousel.addEventListener('mouseleave', resume);
});

const btnFilters = document.querySelector('.btn-toggle-filters');
const filtersContent = document.getElementById('filters-form');

if (btnFilters instanceof HTMLButtonElement && filtersContent instanceof HTMLElement) {
    btnFilters.addEventListener('click', () => {
        const isOpen = !filtersContent.hasAttribute('hidden');
        if (isOpen) {
            filtersContent.setAttribute('hidden', '');
            btnFilters.setAttribute('aria-expanded', 'false');
        } else {
            filtersContent.removeAttribute('hidden');
            btnFilters.setAttribute('aria-expanded', 'true');
        }
    });
}

document.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLSelectElement && target.hasAttribute('data-auto-submit')) {
        target.form?.requestSubmit();
    }

    if (target instanceof HTMLSelectElement && target.hasAttribute('data-delivery-preview')) {
        const selectedOption = target.selectedOptions[0];
        const fee = selectedOption?.dataset.deliveryFee;
        const communeName = selectedOption?.dataset.communeName;
        const feeOutput = document.querySelector('[data-delivery-fee-output]');
        const deliveryLabel = document.querySelector('[data-delivery-label]');
        const totalOutput = document.querySelector('[data-checkout-total]');

        if (!(feeOutput instanceof HTMLElement)
            || !(deliveryLabel instanceof HTMLElement)
            || !(totalOutput instanceof HTMLElement)
        ) {
            return;
        }

        if (fee === undefined || !/^\d+$/.test(fee)) {
            deliveryLabel.textContent = 'Livraison';
            feeOutput.textContent = 'Selon la commune';
            totalOutput.textContent = 'Choisis une commune';
            return;
        }

        const subtotal = Number(totalOutput.dataset.subtotal);
        const deliveryFee = Number(fee);
        const maximum = Number(totalOutput.dataset.maximum);
        const total = subtotal + deliveryFee;
        const formatter = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

        deliveryLabel.textContent = communeName ? `Livraison (${communeName})` : 'Livraison';
        feeOutput.textContent = `${formatter.format(deliveryFee)} FCFA`;
        totalOutput.textContent = Number.isSafeInteger(total) && total <= maximum
            ? `${formatter.format(total)} FCFA`
            : 'Montant trop élevé';
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)
        || !form.matches('.cart-add-form')
        || typeof window.fetch !== 'function'
    ) {
        return;
    }

    const actionUrl = form.getAttribute('action');
    if (!actionUrl) {
        return;
    }

    const cartLink = document.querySelector('[data-cart-link]');
    const countBadge = cartLink?.querySelector('[data-cart-count-badge]');
    const feedback = form.querySelector('[data-cart-add-feedback]');
    const submitButton = form.querySelector('button[type="submit"]');
    if (!(cartLink instanceof HTMLAnchorElement)
        || !(countBadge instanceof HTMLElement)
        || !(feedback instanceof HTMLElement)
        || !(submitButton instanceof HTMLButtonElement)
    ) {
        return;
    }

    event.preventDefault();
    const originalButtonText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Ajout en cours...';
    form.setAttribute('aria-busy', 'true');
    feedback.hidden = true;
    feedback.textContent = '';
    delete feedback.dataset.state;

    try {
        const response = await window.fetch(actionUrl, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });
        const contentType = response.headers.get('content-type') ?? '';
        if (!contentType.toLowerCase().includes('application/json')) {
            throw new Error('Le service panier a renvoyé une réponse inattendue. Réessaie.');
        }

        const result = await response.json();
        if (result === null || typeof result !== 'object') {
            throw new Error('La réponse du panier est invalide. Réessaie.');
        }

        if (!response.ok || result.success !== true) {
            throw new Error(
                typeof result.message === 'string'
                    ? result.message
                    : 'Le produit n’a pas pu être ajouté au panier.'
            );
        }
        if (typeof result.message !== 'string') {
            throw new Error('La réponse du panier est invalide. Réessaie.');
        }
        if (!Number.isSafeInteger(result.count) || result.count < 0) {
            throw new Error('Le panier a été mis à jour, mais son compteur est invalide.');
        }

        const formattedCount = new Intl.NumberFormat('fr-FR', {
            maximumFractionDigits: 0,
        }).format(result.count);
        countBadge.textContent = result.count > 99 ? '99+' : formattedCount;
        countBadge.hidden = result.count === 0;
        cartLink.setAttribute(
            'aria-label',
            result.count === 0
                ? 'Panier vide'
                : `Panier, ${formattedCount} ${result.count === 1 ? 'article' : 'articles'}`
        );
        feedback.dataset.state = 'success';
        feedback.textContent = result.message;
        feedback.hidden = false;
    } catch (error) {
        feedback.dataset.state = 'error';
        feedback.textContent = error instanceof Error
            ? error.message
            : 'Impossible de confirmer l’ajout au panier. Réessaie.';
        feedback.hidden = false;
    } finally {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
        form.removeAttribute('aria-busy');
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form instanceof HTMLFormElement ? form.dataset.confirm : '';
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

document.addEventListener('click', (event) => {
    const printButton = event.target instanceof Element
        ? event.target.closest('[data-print]')
        : null;
    if (printButton !== null) {
        window.print();
    }
});
