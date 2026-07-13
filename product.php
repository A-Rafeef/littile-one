<?php

/**
 * Product Detail Page
 * Kids Store v5
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// Get product slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    redirect(SITE_URL);
}

// Get product with all data
$product = getProductBySlug($slug);
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product Not Found';
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><div class="empty-state"><div class="empty-state-icon">😕</div><h3>Product Not Found</h3><p>The product you\'re looking for doesn\'t exist.</p></div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Page meta - canonical excludes variant params
$pageTitle = $product['meta_title'] ?: $product['name'];
$metaDescription = $product['meta_description'] ?: substr(strip_tags($product['description']), 0, 160);
$canonicalUrl = SITE_URL . '/product/' . $product['slug'];

// Pre-select variant from URL params
$preselectedColor = $_GET['color'] ?? null;
$preselectedSize = $_GET['size'] ?? null;
$preselectedAge = $_GET['age'] ?? null;

// Get variant types for this category
$variantTypes = $product['variant_types'] ?: [];
$hasVariants = !empty($product['variants']);

// Extract unique variant values
$colors = [];
$sizes = [];
$ages = [];

if ($hasVariants) {
    foreach ($product['variants'] as $v) {
        if (!empty($v['color']) && !in_array($v['color'], $colors)) $colors[] = $v['color'];

        // Split comma-separated sizes
        if (!empty($v['size'])) {
            $parts = explode(',', $v['size']);
            foreach ($parts as $p) {
                $p = trim($p);
                if (!empty($p) && !in_array($p, $sizes)) $sizes[] = $p;
            }
        }

        // Split comma-separated ages
        if (!empty($v['age'])) {
            $parts = explode(',', $v['age']);
            foreach ($parts as $p) {
                $p = trim($p);
                if (!empty($p) && !in_array($p, $ages)) $ages[] = $p;
            }
        }
    }
}

// Determine initial variant for display (AJIO-style: first AVAILABLE)
$initialVariant = null;
if ($hasVariants) {
    // Try URL params first
    if ($preselectedColor || $preselectedSize || $preselectedAge) {
        foreach ($product['variants'] as $v) {
            $colorMatch = !$preselectedColor || $v['color'] === $preselectedColor;
            // Handle comma-separated sizes/ages stored in DB (e.g. "S,M,L")
            $sizeMatch  = !$preselectedSize  || ($v['size'] === $preselectedSize  || in_array($preselectedSize,  array_map('trim', explode(',', (string)$v['size']))));
            $ageMatch   = !$preselectedAge   || ($v['age']  === $preselectedAge   || in_array($preselectedAge,   array_map('trim', explode(',', (string)$v['age']))));
            if ($colorMatch && $sizeMatch && $ageMatch) {
                $initialVariant = $v;
                break;
            }
        }
    }

    // If no URL match, find first AVAILABLE variant
    if (!$initialVariant) {
        foreach ($product['variants'] as $v) {
            if ($v['is_available']) {
                $initialVariant = $v;
                break;
            }
        }
    }

    // Fallback to first variant
    if (!$initialVariant) {
        $initialVariant = $product['variants'][0];
    }
}

// Get display images (variant images override product images)
$displayImages = [];
if ($initialVariant && !empty($initialVariant['images'])) {
    $displayImages = $initialVariant['images'];
} elseif (!empty($product['images'])) {
    $displayImages = $product['images'];
}

// Get price and availability
$displayPrice = $initialVariant && $initialVariant['price_override']
    ? $initialVariant['price_override']
    : $product['base_price'];
$isAvailable = $initialVariant
    ? $initialVariant['is_available']
    : $product['is_available'];

// WhatsApp settings
$whatsappNumber = getSetting('whatsapp_number');
$currencySymbol = getSetting('currency_symbol', '₹');

// JSON-LD Schema
$schemaData = [
    "@context" => "https://schema.org/",
    "@type" => "Product",
    "name" => $product['name'],
    "image" => !empty($displayImages) ? imageUrl($displayImages[0]['image_path']) : SITE_URL . '/assets/img/placeholder.png',
    "description" => strip_tags($product['description']),
    "brand" => [
        "@type" => "Brand",
        "name" => getSetting('store_name', 'Kids Store')
    ],
    "offers" => [
        "@type" => "Offer",
        "url" => $canonicalUrl,
        "priceCurrency" => "INR", // Assuming INR based on symbol choice, could be configurable
        "price" => $displayPrice,
        "availability" => $isAvailable ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
        "itemCondition" => "https://schema.org/NewCondition"
    ]
];

// BreadcrumbList JSON-LD Schema
$breadcrumbSchema = [
    "@context" => "https://schema.org",
    "@type" => "BreadcrumbList",
    "itemListElement" => [
        [
            "@type" => "ListItem",
            "position" => 1,
            "name" => "Home",
            "item" => SITE_URL
        ],
        [
            "@type" => "ListItem",
            "position" => 2,
            "name" => $product['category_name'],
            "item" => SITE_URL . '/category/' . $product['category_slug']
        ],
        [
            "@type" => "ListItem",
            "position" => 3,
            "name" => $product['name']
        ]
    ]
];

// Get related products (same category, excluding current product)
$relatedProducts = getProducts(['category_id' => $product['category_id'], 'is_available' => true, 'limit' => 8]);
$relatedProducts = array_filter($relatedProducts, fn($p) => $p['id'] != $product['id']);
$relatedProducts = array_slice($relatedProducts, 0, 4);
$relatedProducts = expandProductVariants($relatedProducts);
// Deduplicate by product id - show only first variant per product
$seen = [];
$uniqueRelated = [];
foreach ($relatedProducts as $rp) {
    if (!isset($seen[$rp['id']])) {
        $seen[$rp['id']] = true;
        $uniqueRelated[] = $rp;
    }
}
$relatedProducts = array_slice($uniqueRelated, 0, 4);

require_once __DIR__ . '/includes/header.php';

?>

<div class="product-page">
    <div class="container">
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs">
            <a href="<?= SITE_URL ?>">Home</a>
            <span>/</span>
            <a href="<?= SITE_URL ?>/category/<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a>
            <span>/</span>
            <span class="current"><?= e($product['name']) ?></span>
        </nav>

        <div class="product-detail">
            <!-- SEO Schema -->
            <script type="application/ld+json">
                <?= json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
            </script>
            <script type="application/ld+json">
                <?= json_encode($breadcrumbSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>
            </script>

            <!-- Image Gallery Section -->

            <div class="product-gallery-section">
                <div class="product-gallery">
                    <div class="gallery-main">
                        <button type="button" class="gallery-share-btn" id="galleryShareBtn"
                            data-url="<?= e($canonicalUrl) ?>"
                            data-name="<?= e($product['name']) ?>"
                            data-price="<?= e(formatPrice($displayPrice)) ?>"
                            data-whatsapp="https://wa.me/?text=<?= urlencode('Check out ' . $product['name'] . ' - ' . formatPrice($displayPrice) . "\n" . $canonicalUrl) ?>"
                            aria-label="Share product">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                                <circle cx="18" cy="5" r="3"></circle>
                                <circle cx="6" cy="12" r="3"></circle>
                                <circle cx="18" cy="19" r="3"></circle>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                                <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                            </svg>
                        </button>
                        <?php if (!empty($displayImages)): ?>
                            <img id="galleryMain" src="<?= imageUrl($displayImages[0]['image_path']) ?>" alt="<?= e($product['name']) ?>">
                        <?php else: ?>
                            <img id="galleryMain" src="<?= SITE_URL ?>/assets/img/placeholder.png" alt="<?= e($product['name']) ?>">
                        <?php endif; ?>

                        <!-- Image Counter -->
                        <?php if (count($displayImages) > 1): ?>
                            <div class="gallery-counter" id="galleryCounter">1 / <?= count($displayImages) ?></div>
                        <?php endif; ?>

                        <!-- Pagination Dots (JS handled) -->
                        <div class="gallery-dots" id="galleryDots">
                            <?php foreach ($displayImages as $i => $img): ?>
                                <span class="dot <?= $i === 0 ? 'active' : '' ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (count($displayImages) > 1): ?>
                        <div class="gallery-thumbs" id="galleryThumbs">
                            <?php foreach ($displayImages as $i => $img): ?>
                                <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" data-image="<?= imageUrl($img['image_path']) ?>" data-index="<?= $i ?>">
                                    <img src="<?= imageUrl($img['image_path']) ?>" alt="">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Product Info Section -->
            <div class="product-info-section">
                <div class="product-info">
                    <h1 class="product-title"><?= e($product['name']) ?></h1>
                    <a href="<?= SITE_URL ?>/category/<?= e($product['category_slug']) ?>" class="product-category-link"><?= e($product['category_name']) ?></a>

                    <div class="product-price-row">
                        <div class="product-price" id="productPrice"><?= formatPrice($displayPrice) ?></div>
                        <span class="availability-badge <?= $isAvailable ? 'in-stock' : 'out-of-stock' ?>" id="availabilityBadge">
                            <?= $isAvailable ? '✓ In Stock' : 'Out of Stock' ?>
                        </span>
                    </div>

                    <!-- Variant Selectors (Modernized) -->
                    <?php if ($hasVariants): ?>
                        <div class="variant-selectors">
                            <?php if (in_array('color', $variantTypes) && !empty($colors)): ?>
                                <div class="variant-group">
                                    <div class="variant-header">
                                        <span class="variant-label">Color: <span id="selectedColorLabel"><?= e($initialVariant['color'] ?? $colors[0]) ?></span></span>
                                    </div>
                                    <div class="variant-options swatches">
                                        <?php foreach ($colors as $color):
                                            // Map color name → CSS value for reliable swatch rendering
                                            $colorMap = [
                                                'red' => '#e53935',
                                                'blue' => '#1e88e5',
                                                'green' => '#43a047',
                                                'yellow' => '#fdd835',
                                                'orange' => '#fb8c00',
                                                'pink' => '#e91e8c',
                                                'purple' => '#8e24aa',
                                                'white' => '#ffffff',
                                                'black' => '#212121',
                                                'grey' => '#757575',
                                                'gray' => '#757575',
                                                'brown' => '#6d4c41',
                                                'navy' => '#283593',
                                                'navy blue' => '#283593',
                                                'sky blue' => '#29b6f6',
                                                'lime' => '#c6ef00',
                                                'lime green' => '#8bc34a',
                                                'maroon' => '#880e4f',
                                                'beige' => '#f5f5dc',
                                                'cream' => '#fffdd0',
                                                'teal' => '#00897b',
                                                'gold' => '#ffc107',
                                                'silver' => '#b0bec5',
                                                'rose gold' => '#b76e79',
                                                'coral' => '#ff7043',
                                                'lavender' => '#ce93d8',
                                                'mint' => '#a5d6a7',
                                                'peach' => '#ffccbc',
                                                'mustard' => '#f9a825',
                                            ];
                                            $colorCss = $colorMap[strtolower(trim($color))] ?? $color;
                                        ?>
                                            <button type="button"
                                                class="variant-option swatch <?= ($initialVariant && $initialVariant['color'] === $color) ? 'active' : '' ?>"
                                                data-variant-type="color"
                                                data-value="<?= e($color) ?>"
                                                style="--sw: <?= e($colorCss) ?>;"
                                                title="<?= e($color) ?>">
                                                <span class="swatch-label"><?= e($color) ?></span>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array('size', $variantTypes) && !empty($sizes)): ?>
                                <div class="variant-group">
                                    <div class="variant-header">
                                        <span class="variant-label">Select Size</span>
                                    </div>
                                    <div class="variant-options pills">
                                        <?php foreach ($sizes as $size): ?>
                                            <button type="button"
                                                class="variant-option pill <?= ($initialVariant && $initialVariant['size'] === $size) ? 'active' : '' ?>"
                                                data-variant-type="size"
                                                data-value="<?= e($size) ?>">
                                                <?= e($size) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (in_array('age', $variantTypes) && !empty($ages)): ?>
                                <div class="variant-group">
                                    <div class="variant-header">
                                        <span class="variant-label">Select Age</span>
                                    </div>
                                    <div class="variant-options pills">
                                        <?php foreach ($ages as $age): ?>
                                            <button type="button"
                                                class="variant-option pill <?= ($initialVariant && $initialVariant['age'] === $age) ? 'active' : '' ?>"
                                                data-variant-type="age"
                                                data-value="<?= e($age) ?>">
                                                <?= e($age) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Accordion Sections -->
                    <div class="product-accordions">
                        <details class="accordion-item" open>
                            <summary class="accordion-header">
                                <span>Product Description</span>
                                <span class="icon">⌄</span>
                            </summary>
                            <div class="accordion-content">
                                <?= nl2br(e($product['description'])) ?>
                            </div>
                        </details>

                        <?php if (!empty($product['custom_field_values'])): ?>
                            <details class="accordion-item">
                                <summary class="accordion-header">
                                    <span>Product Details</span>
                                    <span class="icon">⌄</span>
                                </summary>
                                <div class="accordion-content">
                                    <ul class="specs-list">
                                        <?php
                                        $customFields = $product['custom_fields'];
                                        $fieldValues = $product['custom_field_values'];
                                        foreach ($customFields as $field):
                                            $value = $fieldValues[$field['name']] ?? '';
                                            if (empty($value)) continue;
                                        ?>
                                            <li>
                                                <span class="spec-label"><?= e($field['label']) ?></span>
                                                <span class="spec-value"><?= e($value) ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </details>
                        <?php endif; ?>


                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    <?php if (!empty($relatedProducts)): ?>
        <section class="related-products mb-48">
            <div class="section-header">
                <h2 class="section-title">You May Also Like</h2>
                <a href="<?= SITE_URL ?>/category/<?= e($product['category_slug']) ?>" class="view-all">View All</a>
            </div>

            <div class="product-grid-premium">
                <?php foreach ($relatedProducts as $rp): ?>
                    <a href="<?= SITE_URL ?>/product/<?= e($rp['slug']) ?><?= $rp['link_params'] ?? '' ?>" class="product-card-premium">
                        <div class="product-img">
                            <?php if (!empty($rp['display_image'])): ?>
                                <img src="<?= imageUrl($rp['display_image']) ?>" alt="<?= e($rp['name']) ?>" loading="lazy">
                            <?php else: ?>
                                <img src="<?= SITE_URL ?>/assets/img/placeholder.png" alt="<?= e($rp['name']) ?>" loading="lazy">
                            <?php endif; ?>
                            <button type="button" class="wishlist-btn" data-id="<?= $rp['id'] ?>" data-name="<?= e($rp['name']) ?>" data-slug="<?= e($rp['slug']) ?>" data-price="<?= $rp['display_price'] ?>" data-image="<?= !empty($rp['display_image']) ? imageUrl($rp['display_image']) : '' ?>" aria-label="Toggle wishlist">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                                </svg>
                            </button>
                        </div>
                        <div class="product-info-premium">
                            <h3 class="product-title"><?= e($rp['name']) ?></h3>
                            <span class="product-price"><?= formatPrice($rp['display_price']) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Recently Viewed -->
    <section class="recently-viewed mb-48" id="recentlyViewedSection" style="display:none;">
        <div class="section-header">
            <h2 class="section-title">Recently Viewed</h2>
        </div>
        <div class="product-grid-premium" id="recentlyViewedGrid"></div>
    </section>
</div>

<!-- Sticky Bottom Footer -->
<div class="sticky-footer">
    <div class="container">
        <button type="button" class="wishlist-save-btn" id="wishlistSaveBtn"
            data-id="<?= $product['id'] ?>"
            data-name="<?= e($product['name']) ?>"
            data-slug="<?= e($product['slug']) ?>"
            data-price="<?= $displayPrice ?>"
            data-image="<?= !empty($displayImages) ? imageUrl($displayImages[0]['image_path']) : SITE_URL . '/assets/img/placeholder.png' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
            </svg>
            <span class="wishlist-save-text">Save</span>
        </button>
        <a href="<?= $isAvailable ? buildWhatsAppUrl($product, $initialVariant) : '#' ?>"
            class="whatsapp-btn <?= !$isAvailable ? 'disabled' : '' ?>"
            id="whatsappBtn"
            target="_blank" style="flex: 1;">
            <svg class="whatsapp-icon" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
            </svg>
            <span id="whatsappBtnText"><?= $isAvailable ? 'Order via WhatsApp' : 'Out of Stock' ?></span>
        </a>
    </div>
</div>

<!-- Variant Data for JavaScript -->
<?php if ($hasVariants): ?>
    <script>
        window.productVariants = <?= json_encode($product['variants']) ?>;
        window.productBasePrice = <?= $product['base_price'] ?>;
        window.productName = <?= json_encode($product['name']) ?>;
        window.whatsappNumber = <?= json_encode($whatsappNumber) ?>;
        window.currencySymbol = <?= json_encode($currencySymbol) ?>;
        window.uploadUrl = <?= json_encode(UPLOAD_URL) ?>;
    </script>
<?php endif; ?>

<!-- Current product data for Recently Viewed -->
<script>
    window.currentProduct = {
        id: <?= $product['id'] ?>,
        name: <?= json_encode($product['name']) ?>,
        slug: <?= json_encode($product['slug']) ?>,
        price: <?= json_encode(formatPrice($displayPrice)) ?>,
        image: <?= json_encode(!empty($displayImages) ? imageUrl($displayImages[0]['image_path']) : SITE_URL . '/assets/img/placeholder.png') ?>,
        category: <?= json_encode($product['category_name']) ?>
    };
    window.siteUrl = <?= json_encode(SITE_URL) ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>