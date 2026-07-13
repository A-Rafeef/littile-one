/**
 * Kids Store - Main JavaScript
 * AJIO-Style Variant Selection Logic
 */

document.addEventListener("DOMContentLoaded", function () {
  // Mobile Navigation Toggle
  const navToggle = document.getElementById("navToggle");
  const mobileNav = document.getElementById("mobileNav");
  const closeNav = document.getElementById("closeNav");

  if (navToggle && mobileNav) {
    navToggle.addEventListener("click", function () {
      mobileNav.classList.add("active");
      document.body.style.overflow = "hidden"; // Prevent scroll
    });

    const closeNavFn = function () {
      mobileNav.classList.remove("active");
      document.body.style.overflow = ""; // Restore scroll
    };

    if (closeNav) closeNav.addEventListener("click", closeNavFn);

    document.addEventListener("click", function (e) {
      if (mobileNav.classList.contains("active") && !mobileNav.children[0].contains(e.target) && !navToggle.contains(e.target)) {
        closeNavFn();
      }
    });
  }

  // Search Toggle
  const searchToggle = document.getElementById("searchToggle");
  const searchBar = document.getElementById("searchBar");
  const searchInput = document.getElementById("searchInput");

  if (searchToggle && searchBar) {
    searchToggle.addEventListener("click", function () {
      searchBar.classList.toggle("active");
      if (searchBar.classList.contains("active") && searchInput) {
        searchInput.focus();
      }
    });

    // Close search on escape
    document.addEventListener("keydown", function(e) {
      if (e.key === "Escape") {
        searchBar.classList.remove("active");
      }
    });

    // Close on click outside
    document.addEventListener("click", function(e) {
      if (searchBar.classList.contains("active") && !searchToggle.contains(e.target) && !searchBar.contains(e.target)) {
        searchBar.classList.remove("active");
      }
    });
  }

  // Image Gallery
  initGallery();

  // Variant Selection (AJIO-Style)
  initAjioVariantSelector();

  // Hero Slider
  initHeroSlider();

  // Copy Link / Share button
  initShareButtons();

  // Recently Viewed
  initRecentlyViewed();

  // Scroll-triggered card reveals
  initScrollReveal();

  // Add to Cart Buttons
  initAddToCart();

  // Wishlist
  initWishlist();
});

/**
 * Initialize "ADD" buttons on product cards
 */
function initAddToCart() {
  const addButtons = document.querySelectorAll(".product-card-premium .add-btn");
  
  addButtons.forEach(btn => {
    btn.addEventListener("click", function(e) {
      e.preventDefault(); // Prevent navigation immediately
      e.stopPropagation(); // Stop bubbling
      
      const card = this.closest(".product-card-premium");
      if (!card) return;

      // Visual feedback then navigate to product page
      const originalText = this.textContent;
      this.textContent = "✔";
      this.classList.add("added");
      
      setTimeout(() => {
        // Navigate to the product page so user can place order via WhatsApp
        window.location.href = card.href;
      }, 500);
    });
  });
}

/**
 * Scroll-triggered reveal for product cards in related/recently-viewed sections
 */
function initScrollReveal() {
  // Observe both product page sidebar sections AND homepage product grids
  const sidebarSections = document.querySelectorAll(".related-products, .recently-viewed");
  const homepageGrids = document.querySelectorAll(".product-grid-premium");

  const revealSection = (section) => {
    const cards = section.querySelectorAll(".product-card-premium");
    cards.forEach((card, i) => {
      card.style.setProperty("--reveal-delay", (i * 0.08) + "s");
      card.classList.add("revealed");
    });
  };

  // For sidebar sections: use IntersectionObserver (lazy)
  if (sidebarSections.length) {
    const sidebarObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        revealSection(entry.target);
        sidebarObserver.unobserve(entry.target);
      });
    }, { threshold: 0.15 });
    sidebarSections.forEach(s => sidebarObserver.observe(s));
  }

  // For homepage grids: each card reveals as it enters the viewport individually
  if (homepageGrids.length) {
    const cardObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const card = entry.target;
        card.classList.add("revealed");
        cardObserver.unobserve(card);
      });
    }, { threshold: 0.1, rootMargin: "0px 0px -40px 0px" });

    homepageGrids.forEach(grid => {
      const cards = grid.querySelectorAll(".product-card-premium");
      cards.forEach((card, i) => {
        card.style.setProperty("--reveal-delay", (i * 0.08) + "s");
        cardObserver.observe(card);
      });
    });
  }
}

/**
 * Initialize Share Button (Gallery top-right icon)
 */
function initShareButtons() {
  const shareBtn = document.getElementById("galleryShareBtn");
  if (!shareBtn) return;

  shareBtn.addEventListener("click", function (e) {
    // Ripple effect
    const ripple = document.createElement("span");
    ripple.className = "ripple";
    const rect = this.getBoundingClientRect();
    ripple.style.left = (e.clientX - rect.left) + "px";
    ripple.style.top = (e.clientY - rect.top) + "px";
    this.appendChild(ripple);
    ripple.addEventListener("animationend", () => ripple.remove());

    const url = this.dataset.url;
    const name = this.dataset.name;
    const price = this.dataset.price;
    const whatsappUrl = this.dataset.whatsapp;

    // Try Web Share API (mobile native share sheet)
    if (navigator.share) {
      navigator.share({
        title: name,
        text: "Check out " + name + " - " + price,
        url: url,
      }).catch(() => {});
      return;
    }

    // Fallback: open WhatsApp share
    window.open(whatsappUrl, "_blank");
  });
}

/**
 * Initialize Hero Slider
 */
function initHeroSlider() {
  const slider = document.querySelector(".hero-slider");
  const slides = document.querySelectorAll(".hero-banner");
  const dotsContainer = document.querySelector(".hero-dots");
  
  if (!slides || slides.length === 0) return;

  let currentSlide = 0;
  const slideInterval = 5000; // 5 seconds
  let intervalId;

  // Create dots
  if (dotsContainer) {
    slides.forEach((_, index) => {
      const dot = document.createElement("div");
      dot.classList.add("hero-dot");
      if (index === 0) dot.classList.add("active");
      dot.addEventListener("click", () => goToSlide(index));
      dotsContainer.appendChild(dot);
    });
  }

  const dots = document.querySelectorAll(".hero-dot");

  // Show first slide
  slides[currentSlide].classList.add("active");

  function goToSlide(index) {
    slides[currentSlide].classList.remove("active");
    if (dots.length > 0) dots[currentSlide].classList.remove("active");
    currentSlide = (index + slides.length) % slides.length;
    slides[currentSlide].classList.add("active");
    if (dots.length > 0) dots[currentSlide].classList.add("active");
    resetTimer();
  }

  function nextSlide() { goToSlide(currentSlide + 1); }
  function prevSlide() { goToSlide(currentSlide - 1); }

  function resetTimer() {
    clearInterval(intervalId);
    intervalId = setInterval(nextSlide, slideInterval);
  }

  // Touch / Swipe Support
  if (slider && slides.length > 1) {
    let touchStartX = 0;
    let touchStartY = 0;
    let isSwiping = false;

    slider.addEventListener("touchstart", function(e) {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      isSwiping = false;
    }, { passive: true });

    slider.addEventListener("touchmove", function(e) {
      const diffX = Math.abs(e.touches[0].clientX - touchStartX);
      const diffY = Math.abs(e.touches[0].clientY - touchStartY);
      if (diffX > diffY && diffX > 10) {
        isSwiping = true;
      }
    }, { passive: true });

    slider.addEventListener("touchend", function(e) {
      if (!isSwiping) return;
      const diff = touchStartX - e.changedTouches[0].clientX;
      if (diff > 50) nextSlide();
      else if (diff < -50) prevSlide();
    }, { passive: true });
  }

  // Start auto-play
  resetTimer();
}

/**
 * Initialize Product Image Gallery (with touch/swipe support)
 */
function initGallery() {
  const mainImage = document.getElementById("galleryMain");
  const thumbs = document.querySelectorAll(".gallery-thumb");
  const dots = document.querySelectorAll("#galleryDots .dot");

  if (!mainImage || thumbs.length === 0) return;

  let currentIndex = 0;

  function goToImage(index) {
    if (index < 0 || index >= thumbs.length) return;
    currentIndex = index;

    const thumb = thumbs[currentIndex];

    // Fade transition
    mainImage.classList.add("img-fading");
    setTimeout(() => {
      mainImage.src = thumb.dataset.image;
      mainImage.onload = () => mainImage.classList.remove("img-fading");
    }, 150);

    // Update active thumb
    thumbs.forEach((t) => t.classList.remove("active"));
    thumb.classList.add("active");

    // Update active dot
    if (dots.length > 0) {
      dots.forEach((dot, i) => {
        dot.classList.toggle("active", i === currentIndex);
      });
    }

    // Update image counter
    const counter = document.getElementById("galleryCounter");
    if (counter) {
      counter.textContent = (currentIndex + 1) + " / " + thumbs.length;
    }

    // Scroll active thumb into view
    thumb.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
  }

  // Thumbnail click handlers
  thumbs.forEach((thumb, i) => {
    thumb.onclick = null;
    thumb.onclick = function () {
      goToImage(i);
    };
  });

  // Touch/Swipe support on main image
  const galleryMain = mainImage.closest(".gallery-main");
  if (galleryMain && thumbs.length > 1) {
    let touchStartX = 0;
    let touchStartY = 0;
    let isSwiping = false;

    galleryMain.addEventListener("touchstart", function (e) {
      touchStartX = e.touches[0].clientX;
      touchStartY = e.touches[0].clientY;
      isSwiping = false;
    }, { passive: true });

    galleryMain.addEventListener("touchmove", function (e) {
      const diffX = Math.abs(e.touches[0].clientX - touchStartX);
      const diffY = Math.abs(e.touches[0].clientY - touchStartY);
      // If horizontal movement is dominant, prevent page scroll
      if (diffX > diffY && diffX > 10) {
        isSwiping = true;
        e.preventDefault();
      }
    }, { passive: false });

    galleryMain.addEventListener("touchend", function (e) {
      if (!isSwiping) return;
      const touchEndX = e.changedTouches[0].clientX;
      const diff = touchStartX - touchEndX;
      const threshold = 50;

      if (diff > threshold) {
        // Swipe left → next image
        goToImage(currentIndex < thumbs.length - 1 ? currentIndex + 1 : 0);
      } else if (diff < -threshold) {
        // Swipe right → previous image
        goToImage(currentIndex > 0 ? currentIndex - 1 : thumbs.length - 1);
      }
    }, { passive: true });
  }
}

/**
 * AJIO-Style Variant Selector
 * 
 * Rules:
 * 1. Color selection → Updates images + Filters available sizes/ages + Auto-selects first available
 * 2. Size/Age selection → Updates price/availability only (NO image change)
 * 3. Auto-select first AVAILABLE variant on page load
 * 4. WhatsApp button disabled if variant unavailable
 */
function initAjioVariantSelector() {
  const variants = window.productVariants;
  if (!variants || variants.length === 0) return;

  // DOM elements
  const colorOptions = document.querySelectorAll('[data-variant-type="color"]');
  const sizeOptions = document.querySelectorAll('[data-variant-type="size"]');
  const ageOptions = document.querySelectorAll('[data-variant-type="age"]');
  const priceDisplay = document.getElementById("productPrice");
  const whatsappBtn = document.getElementById("whatsappBtn");
  const galleryMain = document.getElementById("galleryMain");
  const galleryThumbs = document.getElementById("galleryThumbs");
  const galleryDots = document.getElementById("galleryDots");
  const colorLabel = document.getElementById("selectedColorLabel");

  // Current selection state
  let selectedColor = null;
  let selectedSize = null;
  let selectedAge = null;

  /**
   * Helper to check if a value matches a variant attribute (handles comma-separated)
   */
  function matches(variantAttr, selectedVal) {
    // If nothing is selected for this axis, any variant qualifies — do NOT filter
    if (!selectedVal) return true;
    if (!variantAttr) return false;
    // Split and trim to handle "2,3,4" styles
    const parts = variantAttr.split(',').map(p => p.trim());
    return parts.includes(selectedVal);
  }

  /**
   * Find the best matching variant based on current selections and optional overrides
   */
  function findBestVariant(overrides = {}) {
    const targetColor = "color" in overrides ? overrides.color : selectedColor;
    const targetSize = "size" in overrides ? overrides.size : selectedSize;
    const targetAge = "age" in overrides ? overrides.age : selectedAge;

    // Rank 1: Perfect match (In Stock)
    let best = variants.find(v => 
      v.color === targetColor && 
      matches(v.size, targetSize) && 
      matches(v.age, targetAge) && 
      v.is_available == 1
    );
    if (best) return best;

    // Rank 2: Perfect match (Any Stock)
    best = variants.find(v => 
      v.color === targetColor && 
      matches(v.size, targetSize) && 
      matches(v.age, targetAge)
    );
    if (best) return best;

    // Rank 3: Match Color + Size (In Stock)
    best = variants.find(v => 
      v.color === targetColor && 
      matches(v.size, targetSize) && 
      v.is_available == 1
    );
    if (best) return best;

    // Rank 4: Match Color + Size (Any Stock)
    best = variants.find(v => 
      v.color === targetColor && 
      matches(v.size, targetSize)
    );
    if (best) return best;

    // Rank 5: Match Color ONLY (In Stock)
    best = variants.find(v => v.color === targetColor && v.is_available == 1);
    if (best) return best;

    // Rank 6: Match Color ONLY (Any Stock)
    best = variants.find(v => v.color === targetColor);
    
    return best || variants[0];
  }

  /**
   * Find exact matching variant for current selection
   */
  function findExactVariant() {
    return variants.find((v) => {
      // For no-color products, both v.color and selectedColor may be null/empty — treat as match
      const colorMatch = (selectedColor === null && !v.color) ||
                         (selectedColor !== null && v.color === selectedColor);
      const sizeMatch = matches(v.size, selectedSize);
      const ageMatch = matches(v.age, selectedAge);
      return colorMatch && sizeMatch && ageMatch;
    });
  }

  /**
   * Update image gallery (called only on COLOR change)
   */
  function updateGallery() {
    const colorVariant = variants.find((v) => v.color === selectedColor && v.images && v.images.length > 0);
    
    if (colorVariant && colorVariant.images.length > 0) {
      const images = colorVariant.images;
      const galleryMainEl = galleryMain ? galleryMain.closest(".gallery-main") : null;

      // Show skeleton
      if (galleryMainEl) galleryMainEl.classList.add("loading");
      
      if (galleryMain) {
        galleryMain.src = window.uploadUrl + images[0].image_path;
        // Remove skeleton when image loads
        galleryMain.onload = function () {
          if (galleryMainEl) galleryMainEl.classList.remove("loading");
        };
      }
      
      if (galleryThumbs) {
        galleryThumbs.innerHTML = images.map((img, i) => `
          <div class="gallery-thumb slide-in ${i === 0 ? "active" : ""}" 
               data-image="${window.uploadUrl + img.image_path}"
               data-index="${i}"
               style="--thumb-delay: ${i * 0.08}s; opacity: 0;">
            <img src="${window.uploadUrl + img.image_path}" alt="">
          </div>
        `).join("");
        
        initGallery();
      }

      if (galleryDots) {
        galleryDots.innerHTML = images.map((_, i) => `
          <span class="dot ${i === 0 ? "active" : ""}"></span>
        `).join("");
      }

      // Update image counter
      const counter = document.getElementById("galleryCounter");
      if (counter) {
        counter.textContent = "1 / " + images.length;
      }
    }
  }

  /**
   * Build WhatsApp URL
   * NOTE: A PHP equivalent exists in functions.php > buildWhatsAppUrl().
   * Keep message format in sync between both implementations.
   */
  function buildWhatsAppUrl(variant) {
    const phone = window.whatsappNumber;
    const productName = window.productName;
    const price = variant.price_override || window.productBasePrice;

    let lines = ["Hi! I'm interested in:", "", "*" + productName + "*"];

    if (variant.color) lines.push("Color: " + variant.color);
    // Use the user's current single selection (not the raw comma-separated DB value)
    if (selectedSize) lines.push("Size: " + selectedSize);
    if (selectedAge)  lines.push("Age: "  + selectedAge);

    lines.push("");
    lines.push("Price: " + window.currencySymbol + parseFloat(price).toFixed(2));
    lines.push("");
    lines.push("Please confirm availability.");

    return "https://wa.me/" + phone + "?text=" + encodeURIComponent(lines.join("\n"));
  }

  /**
   * Update option buttons - show valid, highlight active, show out-of-stock
   */
  function updateOptionButtons() {
    // 1. Update Colors (Always visible, show stock status based on GLOBAL existence)
    colorOptions.forEach(btn => {
      const val = btn.dataset.value;
      const inStock = variants.some(v => v.color === val && v.is_available == 1);
      btn.classList.toggle("disabled", !inStock);
      btn.classList.toggle("active", val === selectedColor);
    });

    // 2. Update Sizes (ALWAYS visible, disabled if not available for current color)
    sizeOptions.forEach(btn => {
      const val = btn.dataset.value;
      const existsInColor = variants.some(v => v.color === selectedColor && matches(v.size, val));
      const inStockInColor = variants.some(v => v.color === selectedColor && matches(v.size, val) && v.is_available == 1);
      
      // Always show all sizes, but disable if not in this color or out of stock
      btn.style.display = ""; // Always visible
      btn.classList.toggle("disabled", !existsInColor || !inStockInColor);
      btn.classList.toggle("active", existsInColor && val === selectedSize);
    });

    // 3. Update Ages (Visible if they exist for current Color + Size)
    ageOptions.forEach(btn => {
      const val = btn.dataset.value;
      const existsInContext = variants.some(v => v.color === selectedColor && matches(v.size, selectedSize) && matches(v.age, val));
      const inStockInContext = variants.some(v => v.color === selectedColor && matches(v.size, selectedSize) && matches(v.age, val) && v.is_available == 1);
      
      btn.style.display = existsInContext ? "" : "none";
      btn.classList.toggle("disabled", !inStockInContext);
      btn.classList.toggle("active", existsInContext && val === selectedAge);
    });

    if (colorLabel) colorLabel.textContent = selectedColor;
  }

  /**
   * Update images, price, and WhatsApp
   */
  function syncUI(changedType) {
    const variant = findExactVariant();
    if (!variant) return;

    // Update Price
    if (priceDisplay) {
      const price = variant.price_override || window.productBasePrice;
      const newText = window.currencySymbol + parseFloat(price).toFixed(2);
      if (priceDisplay.textContent !== newText) {
        priceDisplay.textContent = newText;
        // Trigger price pop animation
        priceDisplay.classList.remove("price-changed");
        void priceDisplay.offsetWidth; // force reflow
        priceDisplay.classList.add("price-changed");
        priceDisplay.addEventListener("animationend", () => {
          priceDisplay.classList.remove("price-changed");
        }, { once: true });
      }
    }

    // Update Availability / WhatsApp
    const isAvailable = variant.is_available == 1;
    const btnText = document.getElementById("whatsappBtnText");
    if (whatsappBtn) {
      whatsappBtn.classList.toggle("disabled", !isAvailable);
      whatsappBtn.href = isAvailable ? buildWhatsAppUrl(variant) : "#";
    }
    if (btnText) {
      btnText.textContent = isAvailable ? "Order via WhatsApp" : "Out of Stock";
    }

    // Fix #5: Keep wishlist Save button price in sync with current variant
    const saveBtn = document.getElementById("wishlistSaveBtn");
    if (saveBtn) {
      const currentPrice = variant.price_override || window.productBasePrice;
      saveBtn.dataset.price = currentPrice;
    }

    // Update availability badge
    const availBadge = document.getElementById("availabilityBadge");
    if (availBadge) {
      const prevState = availBadge.textContent;
      availBadge.textContent = isAvailable ? "\u2713 In Stock" : "Out of Stock";
      availBadge.className = "availability-badge " + (isAvailable ? "in-stock" : "out-of-stock");
      // Trigger pop animation if state changed
      if (prevState !== availBadge.textContent) {
        void availBadge.offsetWidth;
        availBadge.classList.add("badge-pop");
        availBadge.addEventListener("animationend", () => {
          availBadge.classList.remove("badge-pop");
        }, { once: true });
      }
    }

    // Update Gallery only on color change
    if (changedType === "color") {
      updateGallery();
    }

    updateOptionButtons();
  }

  /**
   * Get the first available size from a variant's size field
   */
  function getFirstSizeFromVariant(variant) {
    if (!variant || !variant.size) return null;
    const parts = variant.size.split(',').map(p => p.trim());
    return parts[0] || null;
  }

  /**
   * Get the first available age from a variant's age field
   */
  function getFirstAgeFromVariant(variant) {
    if (!variant || !variant.age) return null;
    const parts = variant.age.split(',').map(p => p.trim());
    return parts[0] || null;
  }

  /**
   * Handle option click
   */
  function handleOptionClick(type, value) {
    let match;
    
    if (type === "color") {
      // When color changes, try to keep current size if it exists in new color
      const newColorVariants = variants.filter(v => v.color === value);
      const currentSizeExistsInNewColor = newColorVariants.some(v => matches(v.size, selectedSize));
      
      if (currentSizeExistsInNewColor) {
        // Keep the current size selection
        match = findBestVariant({ color: value });
      } else {
        // Reset to first available size in new color
        match = variants.find(v => v.color === value && v.is_available == 1) || 
                variants.find(v => v.color === value);
        if (match) {
          selectedSize = getFirstSizeFromVariant(match);
          selectedAge = getFirstAgeFromVariant(match);
        }
      }
      
      if (match) {
        selectedColor = match.color || null;
        // If current size exists in new color, keep it; otherwise reset
        // (age preservation handled separately in size handler)
        syncUI(type);
      }
    } else if (type === "size") {
      selectedSize = value; // Direct assignment for size clicks
      match = findBestVariant({ size: value });
      if (match) {
        selectedColor = match.color || null;
        // Fix #7: Preserve the user's current age selection if it still applies
        // to this size; only reset if the age doesn't exist in the new context
        const ageStillValid = selectedAge && variants.some(v =>
          v.color === selectedColor &&
          matches(v.size, value) &&
          matches(v.age, selectedAge)
        );
        if (!ageStillValid) {
          selectedAge = getFirstAgeFromVariant(match);
        }
        syncUI(type);
      }
    } else if (type === "age") {
      selectedAge = value; // Direct assignment for age clicks
      match = findBestVariant({ age: value });
      if (match) {
        selectedColor = match.color || null;
        syncUI(type);
      }
    }
  }

  /**
   * Setup click listeners
   */
  function setupListeners(options, type) {
    options.forEach((btn) => {
      btn.addEventListener("click", function () {
        if (this.classList.contains("disabled")) {
          // If out of stock, we still allow clicking to select it, 
          // Ajio behavior: selection proceeds but CTA stays disabled
        }
        handleOptionClick(type, this.dataset.value);
      });
    });
  }

  setupListeners(colorOptions, "color");
  setupListeners(sizeOptions, "size");
  setupListeners(ageOptions, "age");

  /**
   * Initialize
   */
  function initialize() {
    const urlParams = new URLSearchParams(window.location.search);
    const initialMatch = findBestVariant({
      color: urlParams.get("color"),
      size: urlParams.get("size"),
      age: urlParams.get("age")
    });

    selectedColor = initialMatch.color || null;
    // Fix #2: DB may store comma-separated sizes/ages — pick only the FIRST value for initial state
    selectedSize = getFirstSizeFromVariant(initialMatch);
    selectedAge  = getFirstAgeFromVariant(initialMatch);

    syncUI("color");
  }

  initialize();
}

/**
 * Recently Viewed Products (localStorage)
 */
function initRecentlyViewed() {
  const siteUrl = window.siteUrl;
  if (!siteUrl) return;

  const STORAGE_KEY = "recentlyViewed";
  const MAX_ITEMS = 8;
  const product = window.currentProduct;

  // 1. RECORDING: If on a product page, add to history
  let items = [];
  try {
    items = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
  } catch (e) {
    items = [];
  }

  if (product) {
    // Remove current product if already in list
    items = items.filter(item => item.id !== product.id);

    // Add current product to front
    items.unshift({
      id: product.id,
      name: product.name,
      slug: product.slug,
      price: product.price,
      image: product.image,
      category: product.category
    });

    // Keep only MAX_ITEMS
    items = items.slice(0, MAX_ITEMS);

    // Save back
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }

  // 2. RENDERING: If container exists, show items
  const section = document.getElementById("recentlyViewedSection");
  const grid = document.getElementById("recentlyViewedGrid");
  
  if (!section || !grid) return;

  // Filter out current product from display (if we are on a product page)
  const currentId = product ? product.id : null;
  const toShow = items.filter(item => item.id !== currentId).slice(0, 4);

  if (toShow.length === 0) {
      section.style.display = "none";
      return;
  }

  grid.innerHTML = toShow.map(item => {
    // Check if item is in wishlist
    let wishlistItems = [];
    try { wishlistItems = JSON.parse(localStorage.getItem('wishlist')) || []; } catch(e) { wishlistItems = []; }
    const isWishlisted = wishlistItems.some(w => w.id === item.id);
    return `
    <a href="${siteUrl}/product/${item.slug}" class="product-card-premium" data-product-id="${item.id}">
      <div class="product-img">
        <img src="${item.image}" alt="${item.name}" loading="lazy" width="400" height="400">
        <button type="button" class="wishlist-btn ${isWishlisted ? 'active' : ''}" data-id="${item.id}" data-name="${item.name}" data-slug="${item.slug}" data-price="${item.price}" data-image="${item.image}" aria-label="Toggle wishlist">
          <svg viewBox="0 0 24 24" fill="${isWishlisted ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" width="18" height="18">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </button>
      </div>
      <div class="product-info-premium">
        <h3 class="product-title">${item.name}</h3>
        <span class="product-price">${item.price}</span>
      </div>
    </a>
  `;
  }).join("");

  section.style.display = "";
}

/**
 * Wishlist (localStorage)
 */
function initWishlist() {
  const STORAGE_KEY = "wishlist";

  // Helper: get wishlist from localStorage
  function getWishlist() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
    } catch (e) {
      return [];
    }
  }

  // Helper: save wishlist to localStorage
  function saveWishlist(items) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  }

  // Update header badge globally
  window.updateWishlistBadge = function () {
    const badge = document.getElementById("wishlistBadge");
    if (!badge) return;
    const count = getWishlist().length;
    badge.textContent = count;
    badge.style.display = count > 0 ? "flex" : "none";
  };

  // Toggle wishlist item
  function toggleWishlist(btn) {
    const id = parseInt(btn.dataset.id);
    const name = btn.dataset.name || "";
    const slug = btn.dataset.slug || "";
    const price = btn.dataset.price || "";
    const image = btn.dataset.image || "";

    let items = getWishlist();
    const exists = items.some(i => i.id === id);

    if (exists) {
      items = items.filter(i => i.id !== id);
      btn.classList.remove("active");
      btn.querySelector("svg").setAttribute("fill", "none");
    } else {
      items.unshift({ id, name, slug, price, image });
      btn.classList.add("active");
      btn.querySelector("svg").setAttribute("fill", "currentColor");
    }

    // Pulse animation
    btn.classList.remove("pulse");
    void btn.offsetWidth;
    btn.classList.add("pulse");
    btn.addEventListener("animationend", () => btn.classList.remove("pulse"), { once: true });

    saveWishlist(items);
    window.updateWishlistBadge();
  }

  // Attach listeners to all wishlist buttons on the page
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".wishlist-btn");
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    toggleWishlist(btn);
  });

  // Product detail page: "Save to Wishlist" button
  const saveBtn = document.getElementById("wishlistSaveBtn");
  if (saveBtn) {
    const productId = parseInt(saveBtn.dataset.id);
    const isActive = getWishlist().some(i => i.id === productId);
    if (isActive) {
      saveBtn.classList.add("active");
      saveBtn.querySelector("svg").setAttribute("fill", "currentColor");
      saveBtn.querySelector(".wishlist-save-text").textContent = "Saved";
    }

    saveBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const id = parseInt(this.dataset.id);
      let items = getWishlist();
      const exists = items.some(i => i.id === id);

      if (exists) {
        items = items.filter(i => i.id !== id);
        this.classList.remove("active");
        this.querySelector("svg").setAttribute("fill", "none");
        this.querySelector(".wishlist-save-text").textContent = "Save";
      } else {
        items.unshift({
          id: id,
          name: this.dataset.name || "",
          slug: this.dataset.slug || "",
          price: this.dataset.price || "",
          image: this.dataset.image || ""
        });
        this.classList.add("active");
        this.querySelector("svg").setAttribute("fill", "currentColor");
        this.querySelector(".wishlist-save-text").textContent = "Saved";
      }

      saveWishlist(items);
      window.updateWishlistBadge();
    });
  }

  // Initialize badge on page load
  window.updateWishlistBadge();
}

