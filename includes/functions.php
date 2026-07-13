<?php

/**
 * Helper Functions
 * Kids Store v5
 */

/**
 * Generate URL-friendly slug from string
 */

function generateSlug(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

/**
 * Ensure unique slug in a table
 */
function ensureUniqueSlug(PDO $db, string $table, string $slug, ?int $excludeId = null): string
{
    $baseSlug = $slug;
    $counter = 1;

    while (true) {
        $sql = "SELECT id FROM {$table} WHERE slug = ?";
        $params = [$slug];

        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if (!$stmt->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter++;
    }
}

/**
 * Sanitize output for HTML
 */
function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Format price with currency
 */
function formatPrice(float $price): string
{
    $symbol = getSetting('currency_symbol', '₹');
    return $symbol . number_format($price, 2);
}

/**
 * Upload image file with automatic compression using ImageMagick
 * @param array $file The $_FILES array element
 * @param string $subdir Subdirectory within uploads folder
 * @param string|null &$error Optional error message reference
 * @return string|null Relative path on success, null on failure
 */
function uploadImage(array $file, string $subdir = 'products', ?string &$error = null): ?string
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit. Please use a smaller image.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error (no temp directory).',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default => 'Unknown upload error.'
        };
        return null;
    }

    // Check file size
    if ($file['size'] > MAX_IMAGE_SIZE) {
        $maxMB = MAX_IMAGE_SIZE / (1024 * 1024);
        $error = "Image size exceeds {$maxMB}MB limit. Please use a smaller image.";
        return null;
    }

    // Validate MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        $error = 'Invalid image type. Allowed: JPEG, PNG, WebP.';
        return null;
    }

    $extension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };

    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadDir = UPLOAD_PATH . $subdir . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $filename;
    $tempPath = $file['tmp_name'];

    // Check if compression is needed (file > 2MB or large dimensions)
    $needsCompression = $file['size'] > IMAGE_COMPRESS_THRESHOLD;

    // Get image dimensions
    $imageInfo = @getimagesize($tempPath);
    if ($imageInfo && ($imageInfo[0] > IMAGE_MAX_WIDTH || $imageInfo[1] > IMAGE_MAX_HEIGHT)) {
        $needsCompression = true;
    }

    if ($needsCompression) {
        // Try ImageMagick first
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $className = '\Imagick';
                $imagick = new $className($tempPath);

                // Auto-orient based on EXIF data
                $imagick->autoOrient();

                // Resize if needed (maintain aspect ratio)
                $width = $imagick->getImageWidth();
                $height = $imagick->getImageHeight();

                if ($width > IMAGE_MAX_WIDTH || $height > IMAGE_MAX_HEIGHT) {
                    $imagick->resizeImage(
                        IMAGE_MAX_WIDTH,
                        IMAGE_MAX_HEIGHT,
                        constant('\Imagick::FILTER_LANCZOS'),
                        1,
                        true // Best fit
                    );
                }

                // Set compression quality
                $imagick->setImageCompressionQuality(IMAGE_QUALITY);

                // Strip metadata to reduce size
                $imagick->stripImage();

                // Write to destination
                $imagick->writeImage($destination);
                $imagick->destroy();

                return $subdir . '/' . $filename;
            } catch (Exception $e) {
                $error = 'ImageMagick processing failed: ' . $e->getMessage();
                // Fall through to GD library
            }
        }

        // Fallback to GD library
        if (extension_loaded('gd')) {
            try {
                $srcImage = match ($mimeType) {
                    'image/jpeg' => imagecreatefromjpeg($tempPath),
                    'image/png' => imagecreatefrompng($tempPath),
                    'image/webp' => imagecreatefromwebp($tempPath),
                    default => null
                };

                if ($srcImage) {
                    $srcWidth = imagesx($srcImage);
                    $srcHeight = imagesy($srcImage);

                    // Calculate new dimensions
                    $ratio = min(IMAGE_MAX_WIDTH / $srcWidth, IMAGE_MAX_HEIGHT / $srcHeight);

                    if ($ratio < 1) {
                        $newWidth = (int)($srcWidth * $ratio);
                        $newHeight = (int)($srcHeight * $ratio);
                    } else {
                        $newWidth = $srcWidth;
                        $newHeight = $srcHeight;
                    }

                    // Create new image
                    $dstImage = imagecreatetruecolor($newWidth, $newHeight);

                    // Preserve transparency for PNG/WebP
                    if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                        imagealphablending($dstImage, false);
                        imagesavealpha($dstImage, true);
                    }

                    // Resize
                    imagecopyresampled(
                        $dstImage,
                        $srcImage,
                        0,
                        0,
                        0,
                        0,
                        $newWidth,
                        $newHeight,
                        $srcWidth,
                        $srcHeight
                    );

                    // Save
                    $saved = match ($mimeType) {
                        'image/jpeg' => imagejpeg($dstImage, $destination, IMAGE_QUALITY),
                        'image/png' => imagepng($dstImage, $destination, (int)(9 - (IMAGE_QUALITY / 11))),
                        'image/webp' => imagewebp($dstImage, $destination, IMAGE_QUALITY),
                        default => false
                    };

                    // GD images (GdImage) are freed automatically by PHP 8.0+ GC — no imagedestroy() needed

                    if ($saved) {
                        return $subdir . '/' . $filename;
                    }
                }
            } catch (Exception $e) {
                $error = 'GD processing failed: ' . $e->getMessage();
            }
        }
    }

    // No compression needed or compression failed - just move the file
    if (move_uploaded_file($tempPath, $destination)) {
        return $subdir . '/' . $filename;
    }

    $error = $error ?? 'Failed to save uploaded image.';
    return null;
}

/**
 * Delete uploaded image
 */
function deleteImage(string $path): bool
{
    $fullPath = UPLOAD_PATH . $path;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Get image URL
 */
function imageUrl(string $path): string
{
    // Fix: Ensure path is relative to uploads
    return UPLOAD_URL . $path;
}

/**
 * Redirect to URL
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Set flash message
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear flash message
 */
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * CSRF Token generation
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrf(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * Generate CSRF input field
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get all active banners
 */
function getBanners(bool $activeOnly = true): array
{
    $db = getDB();
    $sql = "SELECT * FROM banners";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    return $db->query($sql)->fetchAll();
}

/**
 * Get all categories (cached)
 */
function getCategories(bool $activeOnly = true): array
{
    static $cache = [];
    $cacheKey = $activeOnly ? 'active' : 'all';

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $db = getDB();
    $sql = "SELECT * FROM categories";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, name ASC";
    $cache[$cacheKey] = $db->query($sql)->fetchAll();
    return $cache[$cacheKey];
}

/**
 * Get single category by slug
 */
function getCategoryBySlug(string $slug): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

/**
 * Get single category by ID
 */
function getCategoryById(int $id): ?array
{
    $stmt = getDB()->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Get products with optional filters
 */

/**
 * Get products with optional filters
 */
function getProducts(array $filters = []): array
{
    $db = getDB();
    $sql = "SELECT DISTINCT p.*, c.name as category_name, c.slug as category_slug,
                   c.color_listable, c.variant_types
            FROM products p
            JOIN categories c ON p.category_id = c.id";

    // Join variants if filtering by variant attributes
    if (!empty($filters['size']) || !empty($filters['color']) || !empty($filters['age'])) {
        $sql .= " JOIN product_variants pv ON p.id = pv.product_id";
    }

    $sql .= " WHERE 1=1";
    $params = [];

    if (!empty($filters['category_id'])) {
        $sql .= " AND p.category_id = ?";
        $params[] = $filters['category_id'];
    }

    if (!empty($filters['is_available'])) {
        $sql .= " AND p.is_available = 1";
    }

    if (!empty($filters['is_featured'])) {
        $sql .= " AND p.is_featured = 1";
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Variant Filters
    if (!empty($filters['color'])) {
        $sql .= " AND pv.color = ?";
        $params[] = $filters['color'];
    }

    if (!empty($filters['size'])) {
        // Handle comma-separated sizes in DB: use FIND_IN_SET or LIKE
        $sql .= " AND (pv.size = ? OR FIND_IN_SET(?, pv.size))";
        $params[] = $filters['size'];
        $params[] = $filters['size'];
    }

    if (!empty($filters['age'])) {
        // Handle comma-separated ages
        $sql .= " AND (pv.age = ? OR FIND_IN_SET(?, pv.age))";
        $params[] = $filters['age'];
        $params[] = $filters['age'];
    }

    $sql .= " ORDER BY p.sort_order ASC, p.created_at DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}


/**
 * Get product by slug with images and variants
 */
function getProductBySlug(string $slug): ?array
{
    $db = getDB();

    // Get product
    $stmt = $db->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug,
               c.custom_fields, c.variant_types, c.color_listable
        FROM products p
        JOIN categories c ON p.category_id = c.id
        WHERE p.slug = ?
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) return null;

    // Get product images
    $stmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order");
    $stmt->execute([$product['id']]);
    $product['images'] = $stmt->fetchAll();

    // Fix #9: Load all variants AND their images in a single JOIN query
    $stmt = $db->prepare("
        SELECT pv.*,
               vi.id        AS vi_id,
               vi.image_path AS vi_image_path,
               vi.sort_order AS vi_sort_order
        FROM product_variants pv
        LEFT JOIN variant_images vi ON vi.variant_id = pv.id
        WHERE pv.product_id = ?
        ORDER BY pv.color, pv.size, pv.age, vi.sort_order
    ");
    $stmt->execute([$product['id']]);
    $rows = $stmt->fetchAll();

    // Group images back into each variant
    $variantsMap = [];
    foreach ($rows as $row) {
        $vid = $row['id'];
        if (!isset($variantsMap[$vid])) {
            // Strip the vi_* columns from the variant record
            $variantsMap[$vid] = array_diff_key($row, array_flip(['vi_id', 'vi_image_path', 'vi_sort_order']));
            $variantsMap[$vid]['images'] = [];
        }
        if (!empty($row['vi_id'])) {
            $variantsMap[$vid]['images'][] = [
                'id'         => $row['vi_id'],
                'image_path' => $row['vi_image_path'],
                'sort_order' => $row['vi_sort_order'],
                'variant_id' => $vid,
            ];
        }
    }
    $product['variants'] = array_values($variantsMap);

    // Parse JSON fields
    $product['custom_fields']       = json_decode($product['custom_fields']       ?? '[]', true) ?: [];
    $product['variant_types']       = json_decode($product['variant_types']       ?? '[]', true) ?: [];
    $product['custom_field_values'] = json_decode($product['custom_field_values'] ?? '{}', true) ?: [];

    return $product;
}

/**
 * Get product primary image
 */
function getProductImage(int $productId): ?string
{
    $stmt = getDB()->prepare("SELECT image_path FROM product_images WHERE product_id = ? ORDER BY sort_order LIMIT 1");
    $stmt->execute([$productId]);
    $row = $stmt->fetch();
    return $row ? $row['image_path'] : null;
}

/**
 * Get variant primary image (or fall back to product image)
 */
function getVariantImage(int $variantId, int $productId): ?string
{
    $stmt = getDB()->prepare("SELECT image_path FROM variant_images WHERE variant_id = ? ORDER BY sort_order LIMIT 1");
    $stmt->execute([$variantId]);
    $row = $stmt->fetch();

    if ($row) {
        return $row['image_path'];
    }

    return getProductImage($productId);
}

/**
 * Build WhatsApp URL
 */
function buildWhatsAppUrl(array $product, ?array $variant = null): string
{
    $phone = getSetting('whatsapp_number', '919876543210');
    $currency = getSetting('currency_symbol', '₹');

    $price = $variant && $variant['price_override']
        ? $variant['price_override']
        : $product['base_price'];

    $lines = [
        "Hi! I'm interested in:",
        "",
        "*" . $product['name'] . "*"
    ];

    if ($variant) {
        if (!empty($variant['color'])) $lines[] = "Color: " . $variant['color'];
        // DB may store comma-separated values (e.g. "S,M,L") — show only the first
        if (!empty($variant['size'])) {
            $firstSize = trim(explode(',', $variant['size'])[0]);
            $lines[] = "Size: " . $firstSize;
        }
        if (!empty($variant['age'])) {
            $firstAge = trim(explode(',', $variant['age'])[0]);
            $lines[] = "Age: " . $firstAge;
        }
    }

    $lines[] = "";
    $lines[] = "Price: " . $currency . number_format($price, 2);
    $lines[] = "";
    $lines[] = "Please confirm availability.";

    $message = implode("\n", $lines);

    return "https://wa.me/{$phone}?text=" . urlencode($message);
}

/**
 * Expand products with variants - show each variant as a separate product card
 * Products without variants show as single cards
 */
function expandProductVariants(array $products): array
{
    if (empty($products)) return [];

    $db = getDB();

    // Fix #8: Batch-fetch all variants for all products in ONE query
    $productIds = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    $stmt = $db->prepare("
        SELECT pv.*,
               vi.id         AS vi_id,
               vi.image_path AS vi_image_path,
               vi.sort_order AS vi_sort_order
        FROM product_variants pv
        LEFT JOIN variant_images vi ON vi.variant_id = pv.id
        WHERE pv.product_id IN ({$placeholders})
        ORDER BY pv.product_id, pv.color, pv.size, pv.age, vi.sort_order
    ");
    $stmt->execute($productIds);
    $rows = $stmt->fetchAll();

    // Also batch-fetch product-level primary images for products without variants
    $stmt2 = $db->prepare("
        SELECT product_id, image_path
        FROM product_images
        WHERE product_id IN ({$placeholders})
        ORDER BY product_id, sort_order
    ");
    $stmt2->execute($productIds);
    $productImages = [];
    foreach ($stmt2->fetchAll() as $row) {
        if (!isset($productImages[$row['product_id']])) {
            $productImages[$row['product_id']] = $row['image_path'];
        }
    }

    // Group variants (with their images) by product_id
    $variantsByProduct = []; // product_id => [variant_id => variant]
    foreach ($rows as $row) {
        $pid = $row['product_id'];
        $vid = $row['id'];
        if (!isset($variantsByProduct[$pid][$vid])) {
            $variantsByProduct[$pid][$vid] = array_diff_key($row, array_flip(['vi_id', 'vi_image_path', 'vi_sort_order']));
            $variantsByProduct[$pid][$vid]['primary_image'] = null;
        }
        if (!empty($row['vi_id'])) {
            if ($variantsByProduct[$pid][$vid]['primary_image'] === null) {
                $variantsByProduct[$pid][$vid]['primary_image'] = $row['vi_image_path'];
            }
        }
    }

    $expanded = [];

    foreach ($products as $product) {
        $pid      = $product['id'];
        $variants = isset($variantsByProduct[$pid]) ? array_values($variantsByProduct[$pid]) : [];

        if (!empty($variants)) {
            foreach ($variants as $variant) {
                $card = $product;

                $labels = [];
                if (!empty($variant['color'])) $labels[] = $variant['color'];
                if (!empty($variant['size']))  $labels[] = $variant['size'];
                if (!empty($variant['age']))   $labels[] = $variant['age'];

                $card['variant_label']     = implode(' / ', $labels);
                $card['variant_id']        = $variant['id'];
                $card['display_color']     = $variant['color']     ?? null;
                $card['display_size']      = $variant['size']      ?? null;
                $card['display_age']       = $variant['age']       ?? null;
                $card['display_price']     = $variant['price_override'] ?? $product['base_price'];
                $card['display_available'] = $variant['is_available'];

                // Use variant image, fall back to product-level image
                $variantImg = $variant['primary_image'] ?? null;
                $card['display_image'] = $variantImg ?? ($productImages[$pid] ?? null);

                $params = [];
                if (!empty($variant['color'])) $params['color'] = $variant['color'];
                if (!empty($variant['size']))  $params['size']  = $variant['size'];
                if (!empty($variant['age']))   $params['age']   = $variant['age'];
                $card['link_params'] = !empty($params) ? '?' . http_build_query($params) : '';

                $expanded[] = $card;
            }
        } else {
            // No variants — show product as a single card
            $product['variant_label']     = '';
            $product['display_price']     = $product['base_price'];
            $product['display_available'] = $product['is_available'];
            $product['display_image']     = $productImages[$pid] ?? null;
            $product['link_params']       = '';
            $expanded[] = $product;
        }
    }

    return $expanded;
}

/**
 * Legacy function - redirects to new function
 * @deprecated Use expandProductVariants instead
 */
function expandColorVariants(array $products): array
{
    return expandProductVariants($products);
}


/**
 * Get available filter options for a category
 */
function getCategoryFilters(int $categoryId): array
{
    $db = getDB();

    // Get all variants for products in this category
    $stmt = $db->prepare("
        SELECT pv.color, pv.size, pv.age 
        FROM product_variants pv
        JOIN products p ON pv.product_id = p.id
        WHERE p.category_id = ? AND pv.is_available = 1
    ");
    $stmt->execute([$categoryId]);
    $rows = $stmt->fetchAll();

    $filters = [
        'colors' => [],
        'sizes' => [],
        'ages' => []
    ];

    foreach ($rows as $row) {
        if (!empty($row['color'])) {
            $filters['colors'][$row['color']] = true; // Use keys for deduping
        }

        if (!empty($row['size'])) {
            // Split comma-separated values
            $parts = explode(',', $row['size']);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p) $filters['sizes'][$p] = true;
            }
        }

        if (!empty($row['age'])) {
            $parts = explode(',', $row['age']);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p) $filters['ages'][$p] = true;
            }
        }
    }

    // Return keys as simple arrays
    return [
        'colors' => array_keys($filters['colors']),
        'sizes' => array_keys($filters['sizes']),
        'ages' => array_keys($filters['ages'])
    ];
}
