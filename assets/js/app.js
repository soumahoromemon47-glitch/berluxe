"use strict";

document.documentElement.classList.add("js");

const searchToggle = document.querySelector("[data-search-toggle]");
const searchPopover = document.querySelector("[data-search-popover]");

if (
  searchToggle instanceof HTMLButtonElement &&
  searchPopover instanceof HTMLFormElement
) {
  const closeSearch = () => {
    searchPopover.classList.remove("is-open");
    searchToggle.setAttribute("aria-expanded", "false");
    searchToggle.setAttribute("aria-label", "Ouvrir la recherche");
  };

  document.addEventListener("click", (event) => {
    const target = event.target;
    const clickedToggle =
      target instanceof Element ? target.closest("[data-search-toggle]") : null;

    if (clickedToggle === searchToggle) {
      const isOpen = searchPopover.classList.toggle("is-open");
      searchToggle.setAttribute("aria-expanded", String(isOpen));
      searchToggle.setAttribute(
        "aria-label",
        isOpen ? "Fermer la recherche" : "Ouvrir la recherche",
      );
      if (isOpen) {
        searchPopover.querySelector('input[type="search"]')?.focus();
      }
      return;
    }

    if (
      searchPopover.classList.contains("is-open") &&
      target instanceof Node &&
      !searchPopover.contains(target)
    ) {
      closeSearch();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && searchPopover.classList.contains("is-open")) {
      closeSearch();
      searchToggle.focus();
    }
  });
}

function showCarouselSlide(carousel, index) {
  const slides = Array.from(carousel.querySelectorAll("[data-carousel-slide]"));
  if (slides.length < 2) {
    return;
  }

  const dots = Array.from(carousel.querySelectorAll("[data-carousel-go]"));
  const status = carousel.querySelector("[data-carousel-status]");
  const safeIndex = ((index % slides.length) + slides.length) % slides.length;

  slides.forEach((slide, i) => {
    slide.hidden = i !== safeIndex;
  });
  dots.forEach((dot, i) => {
    dot.setAttribute("aria-current", String(i === safeIndex));
  });
  if (status instanceof HTMLElement) {
    status.textContent = `${safeIndex + 1} / ${slides.length}`;
  }
}

function nextCarouselSlide(carousel) {
  const slides = Array.from(carousel.querySelectorAll("[data-carousel-slide]"));
  if (slides.length < 2) {
    return;
  }
  const currentIndex = slides.findIndex(
    (slide) => !slide.hasAttribute("hidden"),
  );
  showCarouselSlide(carousel, currentIndex + 1);
}

document.addEventListener("click", (event) => {
  const target = event.target;
  const control =
    target instanceof Element
      ? target.closest(
          "[data-carousel-prev], [data-carousel-next], [data-carousel-go]",
        )
      : null;
  if (!(control instanceof HTMLButtonElement)) {
    return;
  }

  const carousel = control.closest("[data-carousel]");
  if (!(carousel instanceof HTMLElement)) {
    return;
  }

  if (control.hasAttribute("data-carousel-prev")) {
    const slides = Array.from(
      carousel.querySelectorAll("[data-carousel-slide]"),
    );
    const currentIndex = slides.findIndex(
      (slide) => !slide.hasAttribute("hidden"),
    );
    showCarouselSlide(carousel, currentIndex - 1);
  } else if (control.hasAttribute("data-carousel-next")) {
    nextCarouselSlide(carousel);
  } else {
    const requestedIndex = Number(control.dataset.carouselGo);
    if (!Number.isInteger(requestedIndex) || requestedIndex < 0) {
      return;
    }
    showCarouselSlide(carousel, requestedIndex);
  }
});

document.addEventListener("keydown", (event) => {
  if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") {
    return;
  }

  const carousel =
    event.target instanceof Element
      ? event.target.closest("[data-carousel]")
      : null;
  const control = carousel?.querySelector(
    event.key === "ArrowLeft" ? "[data-carousel-prev]" : "[data-carousel-next]",
  );
  if (control instanceof HTMLButtonElement) {
    event.preventDefault();
    control.click();
  }
});

const CAROUSEL_AUTOPLAY_DELAY = 4000;

document.querySelectorAll("[data-carousel]").forEach((carousel) => {
  const slides = Array.from(carousel.querySelectorAll("[data-carousel-slide]"));
  if (slides.length < 2) {
    return;
  }

  let timer = setInterval(
    () => nextCarouselSlide(carousel),
    CAROUSEL_AUTOPLAY_DELAY,
  );

  const pause = () => clearInterval(timer);
  const resume = () => {
    timer = setInterval(
      () => nextCarouselSlide(carousel),
      CAROUSEL_AUTOPLAY_DELAY,
    );
  };

  carousel.addEventListener("mouseenter", pause);
  carousel.addEventListener("mouseleave", resume);
});

const btnFilters = document.querySelector(".btn-toggle-filters");
const filtersContent = document.getElementById("filters-form");

if (
  btnFilters instanceof HTMLButtonElement &&
  filtersContent instanceof HTMLElement
) {
  btnFilters.addEventListener("click", () => {
    const isOpen = !filtersContent.hasAttribute("hidden");
    if (isOpen) {
      filtersContent.setAttribute("hidden", "");
      btnFilters.setAttribute("aria-expanded", "false");
    } else {
      filtersContent.removeAttribute("hidden");
      btnFilters.setAttribute("aria-expanded", "true");
    }
  });
}

document.addEventListener("change", (event) => {
  const target = event.target;
  if (
    target instanceof HTMLSelectElement &&
    target.hasAttribute("data-auto-submit")
  ) {
    target.form?.requestSubmit();
  }

  if (
    target instanceof HTMLSelectElement &&
    target.hasAttribute("data-delivery-preview")
  ) {
    const selectedOption = target.selectedOptions[0];
    const fee = selectedOption?.dataset.deliveryFee;
    const communeName = selectedOption?.dataset.communeName;
    const feeOutput = document.querySelector("[data-delivery-fee-output]");
    const deliveryLabel = document.querySelector("[data-delivery-label]");
    const totalOutput = document.querySelector("[data-checkout-total]");

    if (
      !(feeOutput instanceof HTMLElement) ||
      !(deliveryLabel instanceof HTMLElement) ||
      !(totalOutput instanceof HTMLElement)
    ) {
      return;
    }

    if (fee === undefined || !/^\d+$/.test(fee)) {
      deliveryLabel.textContent = "Livraison";
      feeOutput.textContent = "Selon la commune";
      totalOutput.textContent = "Choisis une commune";
      return;
    }

    const subtotal = Number(totalOutput.dataset.subtotal);
    const deliveryFee = Number(fee);
    const maximum = Number(totalOutput.dataset.maximum);
    const total = subtotal + deliveryFee;
    const formatter = new Intl.NumberFormat("fr-FR", {
      maximumFractionDigits: 0,
    });

    deliveryLabel.textContent = communeName
      ? `Livraison (${communeName})`
      : "Livraison";
    feeOutput.textContent = `${formatter.format(deliveryFee)} FCFA`;
    totalOutput.textContent =
      Number.isSafeInteger(total) && total <= maximum
        ? `${formatter.format(total)} FCFA`
        : "Montant trop élevé";
  }
});

document.addEventListener("submit", function (event) {
  const form = event.target;
  if (!form || !form.classList.contains("cart-add-form")) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();

  const submitBtn = form.querySelector('button[type="submit"]');
  if (submitBtn) submitBtn.disabled = true;

  const actionUrl = form.getAttribute("action");
  fetch(actionUrl, {
    method: "POST",
    body: new FormData(form),
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => {
      if (!response.ok) throw new Error("Erreur réseau");
      return response.json();
    })
    .then((data) => {
      if (data.success) {
        const countBadge = document.querySelector("[data-cart-count-badge]");
        if (countBadge) {
          countBadge.textContent = data.count > 99 ? "99+" : data.count;
          countBadge.hidden = false;
        }
        // Feedback visuel
        const feedback =
          form.closest(".product-card-actions")?.querySelector("[data-cart-add-feedback]") ||
          form.querySelector("[data-cart-add-feedback]");
        if (feedback) {
          feedback.textContent = "Ajouté !";
          feedback.hidden = false;
          feedback.setAttribute("aria-hidden", "false");
        }
      } else {
        alert(data.message || "Erreur lors de l'ajout.");
      }
    })
    .catch((error) => {
      console.error("Erreur:", error);
      alert("Impossible d'ajouter au panier.");
    })
    .finally(() => {
      if (submitBtn) submitBtn.disabled = false;
    });
});

document.addEventListener("click", (event) => {
  const printButton =
    event.target instanceof Element
      ? event.target.closest("[data-print]")
      : null;
  if (printButton !== null) {
    window.print();
  }
});

document.addEventListener("click", (event) => {
  const target = event.target;
  const thumbnail =
    target instanceof Element
      ? target.closest("[data-product-thumbnail]")
      : null;
  if (thumbnail instanceof HTMLButtonElement) {
    const gallery = thumbnail.closest(".product-gallery");
    const mainImage = gallery?.querySelector("[data-product-main-image]");
    if (mainImage instanceof HTMLImageElement) {
      mainImage.src = thumbnail.dataset.imageSrc || mainImage.src;
      mainImage.alt = thumbnail.dataset.imageAlt || "";
      gallery.querySelectorAll("[data-product-thumbnail]").forEach((button) => {
        button.classList.toggle("is-active", button === thumbnail);
        button.setAttribute("aria-pressed", String(button === thumbnail));
      });
    }
    return;
  }

  const stepButton =
    target instanceof Element
      ? target.closest("[data-quantity-decrease], [data-quantity-increase]")
      : null;
  if (!(stepButton instanceof HTMLButtonElement)) {
    return;
  }

  const stepper = stepButton.closest("[data-quantity-stepper]");
  const input = stepper?.querySelector('input[name="quantity"]');
  if (!(input instanceof HTMLInputElement)) {
    return;
  }

  const minimum = Number(input.min || 1);
  const maximum = Number(input.max || Number.MAX_SAFE_INTEGER);
  const current = Number(input.value);
  const next = stepButton.hasAttribute("data-quantity-increase")
    ? Math.min(maximum, (Number.isFinite(current) ? current : minimum) + 1)
    : Math.max(minimum, (Number.isFinite(current) ? current : minimum) - 1);
  input.value = String(next);
  input.dispatchEvent(new Event("input", { bubbles: true }));

  const form = input.closest("form[data-cart-quantity-form]");
  if (form instanceof HTMLFormElement) {
    form.requestSubmit();
  }
});

document.addEventListener("change", (event) => {
  const target = event.target;
  if (
    target instanceof HTMLInputElement &&
    target.name === "quantity" &&
    target.closest("form[data-cart-quantity-form]")
  ) {
    target.form?.requestSubmit();
  }

  if (
    !(target instanceof HTMLSelectElement) ||
    !target.hasAttribute("data-cart-delivery-preview")
  ) {
    return;
  }

  const option = target.selectedOptions[0];
  const fee = option?.dataset.fee;
  const deliveryOutput = document.querySelector("[data-cart-delivery-output]");
  const deliveryLabel = document.querySelector("[data-cart-delivery-label]");
  const totalOutput = document.querySelector("[data-cart-total-output]");
  if (
    !(deliveryOutput instanceof HTMLElement) ||
    !(deliveryLabel instanceof HTMLElement) ||
    !(totalOutput instanceof HTMLElement)
  ) {
    return;
  }

  if (fee === undefined || !/^\d+$/.test(fee)) {
    deliveryLabel.textContent = "Frais de livraison";
    deliveryOutput.textContent = "Choisis une commune";
    totalOutput.textContent = "Choisis une commune";
    return;
  }

  const subtotal = Number(target.dataset.subtotal);
  const deliveryFee = Number(fee);
  const maximum = Number(target.dataset.maximum);
  const total = subtotal + deliveryFee;
  const formatter = new Intl.NumberFormat("fr-FR", {
    maximumFractionDigits: 0,
  });
  deliveryLabel.textContent = option.dataset.name
    ? `Livraison (${option.dataset.name})`
    : "Frais de livraison";
  deliveryOutput.textContent = `${formatter.format(deliveryFee)} FCFA`;
  totalOutput.textContent =
    Number.isSafeInteger(total) && total <= maximum
      ? `${formatter.format(total)} FCFA`
      : "Montant trop élevé";
});

// ============================================================
// GESTION DU TIROIR DES FILTRES (Version robuste)
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  const btnOpen = document.getElementById("btn-open-filters");
  const btnClose = document.getElementById("btn-close-filters");
  const overlay = document.getElementById("filters-overlay");
  const drawer = document.getElementById("filters-drawer");

  if (btnOpen && drawer && overlay) {
    const closeFilters = () => {
      drawer.classList.remove("is-open");
      overlay.classList.remove("is-open");
      btnOpen.setAttribute("aria-expanded", "false");
      document.body.style.overflow = "";
    };

    btnOpen.addEventListener("click", () => {
      drawer.classList.add("is-open");
      overlay.classList.add("is-open");
      btnOpen.setAttribute("aria-expanded", "true");
      document.body.style.overflow = "hidden";
    });

    if (btnClose) {
      btnClose.addEventListener("click", closeFilters);
    }

    overlay.addEventListener("click", closeFilters);
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && drawer.classList.contains("is-open")) {
        closeFilters();
        btnOpen.focus();
      }
    });
  }
});

// ============================================================
// FICHE PRODUIT : Galerie et Compteur
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  // Galerie photos
  const mainImage = document.querySelector("[data-product-main-image]");
  const thumbnails = document.querySelectorAll("[data-product-thumbnail]");

  if (mainImage && thumbnails.length > 0) {
    thumbnails.forEach((thumb) => {
      thumb.addEventListener("click", () => {
        mainImage.src = thumb.dataset.imageSrc;
        mainImage.alt = thumb.dataset.imageAlt;
        thumbnails.forEach((t) => t.classList.remove("is-active"));
        thumb.classList.add("is-active");
      });
    });
  }

  // Compteur Quantité
  const stepper = document.querySelector("[data-quantity-stepper]");
  if (stepper) {
    const input = stepper.querySelector("input");
    const btnMinus = stepper.querySelector("[data-quantity-decrease]");
    const btnPlus = stepper.querySelector("[data-quantity-increase]");

    btnMinus.addEventListener("click", () => {
      let val = parseInt(input.value) || 1;
      if (val > 1) input.value = val - 1;
    });

    btnPlus.addEventListener("click", () => {
      let val = parseInt(input.value) || 1;
      const max = parseInt(input.max) || 99;
      if (val < max) input.value = val + 1;
    });
  }
});

// ============================================================
// GESTION DES CATÉGORIES ADMIN (Édition & Ajout)
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  // Toggle du formulaire d'ajout
  const btnToggleAdd = document.getElementById("btn-toggle-add-category");
  const btnCancelAdd = document.getElementById("btn-cancel-add-category");
  const addSection = document.getElementById("add-category-section");

  if (btnToggleAdd && addSection) {
    btnToggleAdd.addEventListener("click", () => {
      addSection.hidden = !addSection.hidden;
      if (!addSection.hidden) {
        addSection.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    });
  }

  if (btnCancelAdd && addSection) {
    btnCancelAdd.addEventListener("click", () => {
      addSection.hidden = true;
    });
  }

  // Toggle des formulaires d'édition
  const editButtons = document.querySelectorAll("[data-toggle-edit]");
  editButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      const id = btn.dataset.toggleEdit;
      const form = document.getElementById("edit-form-" + id);
      if (form) {
        form.hidden = !form.hidden;
      }
    });
  });
});

// ============================================================
// EFFET FADE-IN AU SCROLL
// ============================================================
document.addEventListener("DOMContentLoaded", () => {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  const elementsToAnimate = document.querySelectorAll(
    ".storefront-section, .promo-banner, .catalogue-banner, .product-card, .trust-banner-section, .checkout-form-card, .checkout-summary-card, .confirmation-success, .confirmation-recap, .admin-card, .stat-card, .dashboard-card, .order-detail-grid > section"
  );

  if (elementsToAnimate.length === 0 || !("IntersectionObserver" in window)) {
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;

        const element = entry.target;
        element.style.opacity = "1";
        element.style.transform = "translateY(0)";
        observer.unobserve(element);

        window.setTimeout(() => {
          element.style.removeProperty("opacity");
          element.style.removeProperty("transform");
          element.style.removeProperty("transition");
        }, 700);
      });
    },
    {
      threshold: 0.1,
      rootMargin: "0px 0px -50px 0px",
    }
  );

  elementsToAnimate.forEach((element) => {
    element.style.opacity = "0";
    element.style.transform = "translateY(30px)";
    element.style.transition = "opacity 0.6s ease-out, transform 0.6s ease-out";
    observer.observe(element);
  });
});
