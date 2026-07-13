<?php

/**
 * Wishlist Page
 * Kids Store v5
 * Shows saved products from localStorage via AJAX
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'My Wishlist';
$metaDescription = 'Your saved products';
$canonicalUrl = SITE_URL . '/wishlist';

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <section class="mb-48" style="padding-top: 16px;">
        <div class="section-header">
            <h1 class="section-title">My Wishlist</h1>
            <span class="wishlist-count-label" id="wishlistCountLabel"></span>
        </div>

        <!-- Loading state -->
        <div class="wishlist-loading" id="wishlistLoading">
            <div class="loader-dots" style="justify-content: center; display: flex; gap: 6px; padding: 64px 0;">
                <div class="dot dot-1"></div>
                <div class="dot dot-2"></div>
                <div class="dot dot-3"></div>
            </div>
        </div>

        <!-- Products grid (filled by JS) -->
        <div class="product-grid-premium" id="wishlistGrid" style="display: none;"></div>

        <!-- Empty state -->
        <div class="empty-state" id="wishlistEmpty" style="display: none;">
            <div class="empty-state-icon">♡</div>
            <h3>Your wishlist is empty</h3>
            <p>Browse our products and tap the heart icon to save items you love.</p>
            <a href="<?= SITE_URL ?>" class="btn btn-primary mt-24" style="margin-top: 24px; display: inline-block; padding: 12px 32px; background: var(--primary); color: var(--secondary); font-weight: 700; border-radius: 50px; text-decoration: none;">Shop Now</a>
        </div>
    </section>
</div>

<script>
    (function() {
        const siteUrl = window.siteUrl || <?= json_encode(SITE_URL) ?>;
        const STORAGE_KEY = 'wishlist';
        const grid = document.getElementById('wishlistGrid');
        const loading = document.getElementById('wishlistLoading');
        const empty = document.getElementById('wishlistEmpty');
        const countLabel = document.getElementById('wishlistCountLabel');

        let items = [];
        try {
            items = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        } catch (e) {
            items = [];
        }

        if (items.length === 0) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            if (countLabel) countLabel.textContent = '0 items';
            return;
        }

        // Fetch product data from API
        const ids = items.map(i => i.id).join(',');
        fetch(siteUrl + '/api/wishlist.php?ids=' + ids)
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                const products = data.products || [];

                if (products.length === 0) {
                    empty.style.display = 'block';
                    if (countLabel) countLabel.textContent = '0 items';
                    return;
                }

                if (countLabel) countLabel.textContent = products.length + ' item' + (products.length !== 1 ? 's' : '');

                // Render cards in the order they are in localStorage
                const productMap = {};
                products.forEach(p => productMap[p.id] = p);

                const orderedProducts = items
                    .map(i => productMap[i.id])
                    .filter(Boolean);

                grid.innerHTML = orderedProducts.map(p => `
                <a href="${siteUrl}/product/${p.slug}" class="product-card-premium revealed" data-product-id="${p.id}">
                    <div class="product-img" style="position: relative;">
                        <img src="${p.image}" alt="${p.name}" loading="lazy">
                        <button type="button" class="wishlist-btn active" data-id="${p.id}" data-name="${p.name}" data-slug="${p.slug}" data-price="${p.price}" data-image="${p.image}" onclick="event.preventDefault(); event.stopPropagation(); toggleWishlistFromPage(this);" aria-label="Remove from wishlist">
                            <svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" width="18" height="18">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="product-info-premium">
                        <h3 class="product-title">${p.name}</h3>
                        <span class="product-price">${p.price_formatted}</span>
                    </div>
                </a>
            `).join('');

                grid.style.display = '';
            })
            .catch(() => {
                loading.style.display = 'none';
                empty.style.display = 'block';
            });

        // Remove from wishlist on this page
        window.toggleWishlistFromPage = function(btn) {
            const id = parseInt(btn.dataset.id);
            let wishlist = [];
            try {
                wishlist = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
            } catch (e) {
                wishlist = [];
            }
            wishlist = wishlist.filter(i => i.id !== id);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(wishlist));

            // Remove card with animation
            const card = btn.closest('.product-card-premium');
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.remove();
                    // Update count
                    const remaining = grid.querySelectorAll('.product-card-premium').length;
                    if (countLabel) countLabel.textContent = remaining + ' item' + (remaining !== 1 ? 's' : '');
                    if (remaining === 0) {
                        grid.style.display = 'none';
                        empty.style.display = 'block';
                    }
                }, 300);
            }

            // Update header badge
            if (typeof updateWishlistBadge === 'function') updateWishlistBadge();
        };
    })();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>