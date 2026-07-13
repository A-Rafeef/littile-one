-- Supabase PostgreSQL Schema for Little One Kids Store

-- Drop tables if they exist (for migration script clean run)
DROP TABLE IF EXISTS variant_images CASCADE;
DROP TABLE IF EXISTS product_variants CASCADE;
DROP TABLE IF EXISTS product_images CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS banners CASCADE;
DROP TABLE IF EXISTS settings CASCADE;
DROP TABLE IF EXISTS admins CASCADE;
DROP TABLE IF EXISTS store_product_fields CASCADE;
DROP TABLE IF EXISTS user_feature_flags CASCADE;
DROP TABLE IF EXISTS stores CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS plans CASCADE;

-- Admins Table
CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Banners Table
CREATE TABLE banners (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    button_text VARCHAR(100) DEFAULT NULL,
    button_url VARCHAR(255) DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    position VARCHAR(50) DEFAULT 'hero' CHECK (position IN ('hero', 'promo', 'sidebar')),
    sort_order INT DEFAULT 0,
    is_active SMALLINT DEFAULT 1,
    starts_at TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    ends_at TIMESTAMP WITH TIME ZONE DEFAULT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_active_position ON banners (is_active, position, sort_order);

-- Categories Table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    custom_fields JSONB DEFAULT NULL,
    variant_types JSONB DEFAULT NULL,
    color_listable SMALLINT DEFAULT 0,
    sort_order INT DEFAULT 0,
    is_active SMALLINT DEFAULT 1,
    meta_title VARCHAR(160) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_categories_slug ON categories (slug);
CREATE INDEX idx_categories_active_sort ON categories (is_active, sort_order);

-- Products Table
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    category_id INT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT DEFAULT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    custom_field_values JSONB DEFAULT NULL,
    is_available SMALLINT DEFAULT 1,
    is_featured SMALLINT DEFAULT 0,
    meta_title VARCHAR(160) DEFAULT NULL,
    meta_description VARCHAR(320) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    has_variants SMALLINT DEFAULT 0,
    track_stock SMALLINT DEFAULT 0,
    stock_quantity INT DEFAULT NULL,
    custom_attributes JSONB DEFAULT NULL
);
CREATE INDEX idx_products_slug ON products (slug);
CREATE INDEX idx_products_category ON products (category_id);
CREATE INDEX idx_products_featured ON products (is_featured, is_available);
CREATE INDEX idx_products_available ON products (is_available);

-- Product Images Table
CREATE TABLE product_images (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_product_images_product ON product_images (product_id, sort_order);

-- Product Variants Table
CREATE TABLE product_variants (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    color VARCHAR(50) DEFAULT NULL,
    size VARCHAR(100) DEFAULT NULL,
    age VARCHAR(100) DEFAULT NULL,
    price_override DECIMAL(10,2) DEFAULT NULL,
    is_available SMALLINT DEFAULT 1,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT unique_variant UNIQUE (product_id, color, size, age)
);
CREATE INDEX idx_variants_product ON product_variants (product_id);
CREATE INDEX idx_variants_color ON product_variants (product_id, color);
CREATE INDEX idx_variants_available ON product_variants (is_available);

-- Variant Images Table
CREATE TABLE variant_images (
    id SERIAL PRIMARY KEY,
    variant_id INT NOT NULL REFERENCES product_variants(id) ON DELETE CASCADE,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_variant_images_variant ON variant_images (variant_id, sort_order);

-- Settings Table
CREATE TABLE settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT DEFAULT NULL,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Store Product Fields Table (Required by product-fields.php)
CREATE TABLE store_product_fields (
    store_id INT DEFAULT 1,
    field_key VARCHAR(100) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    field_type VARCHAR(50) DEFAULT 'text',
    is_required SMALLINT DEFAULT 0,
    is_active SMALLINT DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (store_id, field_key)
);

-- Plans Table (For FeatureGate helper structure)
CREATE TABLE plans (
    id SERIAL PRIMARY KEY,
    features JSONB DEFAULT NULL
);

-- Users Table (For FeatureGate helper structure)
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    plan_id INT REFERENCES plans(id) ON DELETE SET NULL
);

-- User Feature Flags Table (For FeatureGate helper structure)
CREATE TABLE user_feature_flags (
    user_id INT NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    unlock_reason VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (user_id, feature_key)
);

-- Stores Table (For FeatureGate helper structure)
CREATE TABLE stores (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    product_count INT DEFAULT 0
);
