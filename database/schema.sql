CREATE DATABASE IF NOT EXISTS berluxe
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE berluxe;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug),
    KEY idx_categories_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    description TEXT NULL,
    retail_price DECIMAL(12, 0) UNSIGNED NOT NULL,
    wholesale_price DECIMAL(12, 0) UNSIGNED NOT NULL,
    wholesale_min_quantity INT UNSIGNED NOT NULL,
    promotion_price DECIMAL(12, 0) UNSIGNED NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_on_promotion TINYINT(1) NOT NULL DEFAULT 0,
    is_unavailable TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category_id (category_id),
    KEY idx_products_stock (stock),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_images (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    alt_text VARCHAR(180) NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_images_file_name (file_name),
    KEY idx_product_images_product_order (product_id, display_order),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(180) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_product_variants_label (product_id, label),
    CONSTRAINT fk_product_variants_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(140) NOT NULL,
    zone ENUM('abidjan', 'hors_abidjan') NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_communes_zone_name (zone, name),
    KEY idx_communes_zone_order (zone, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(32) NOT NULL,
    customer_last_name VARCHAR(100) NOT NULL,
    customer_first_name VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(30) NOT NULL,
    commune_id BIGINT UNSIGNED NULL,
    commune_name_snapshot VARCHAR(140) NOT NULL,
    delivery_zone ENUM('abidjan', 'hors_abidjan') NOT NULL,
    address_details TEXT NULL,
    subtotal DECIMAL(12, 0) UNSIGNED NOT NULL,
    delivery_fee DECIMAL(12, 0) UNSIGNED NOT NULL DEFAULT 0,
    total DECIMAL(12, 0) UNSIGNED NOT NULL,
    status ENUM('nouvelle', 'confirmee', 'livree', 'annulee') NOT NULL DEFAULT 'nouvelle',
    stock_deducted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_order_number (order_number),
    KEY idx_orders_status_created_at (status, created_at),
    KEY idx_orders_commune_id (commune_id),
    CONSTRAINT fk_orders_commune
        FOREIGN KEY (commune_id) REFERENCES communes (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NULL,
    product_name_snapshot VARCHAR(180) NOT NULL,
    variant_label_snapshot VARCHAR(180) NULL,
    quantity INT UNSIGNED NOT NULL,
    applied_price DECIMAL(12, 0) UNSIGNED NOT NULL,
    price_type ENUM('detail', 'gros') NOT NULL,
    is_promotion TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_items_order_id (order_id),
    KEY idx_order_items_product_id (product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders (id)
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO admins (username, password_hash, full_name, must_change_password)
VALUES
    ('roxane', '$2y$10$srXLDwcoh.9h6wKPGdoSBuju2TUS7z/JjvepXlCz0LxAeJEXK7RX6', 'Roxane', 1),
    ('soum-boy', '$2y$10$0OMFyFiPY19.d1MadoqNueil.1H3FP2YkmGoY5/UapZOcKfBAqC4e', 'Soum-boy', 1);

INSERT IGNORE INTO categories (name, slug, display_order)
VALUES
    ('Outillage', 'outillage', 1),
    ('Mobilier', 'mobilier', 2),
    ('Maison et cuisine', 'maison-et-cuisine', 3),
    ('Décoration', 'decoration', 4),
    ('Électronique', 'electronique', 5);

INSERT IGNORE INTO communes (name, zone, display_order)
VALUES
    ('Abobo', 'abidjan', 1),
    ('Adjamé', 'abidjan', 2),
    ('Anyama', 'abidjan', 3),
    ('Attécoubé', 'abidjan', 4),
    ('Bingerville', 'abidjan', 5),
    ('Cocody', 'abidjan', 6),
    ('Koumassi', 'abidjan', 7),
    ('Marcory', 'abidjan', 8),
    ('Plateau', 'abidjan', 9),
    ('Port-Bouët', 'abidjan', 10),
    ('Songon', 'abidjan', 11),
    ('Treichville', 'abidjan', 12),
    ('Yopougon', 'abidjan', 13),
    ('Autre ville (hors Abidjan)', 'hors_abidjan', 1);

INSERT IGNORE INTO settings (setting_key, setting_value)
VALUES
    ('delivery_fee_abidjan', '0'),
    ('delivery_fee_outside_abidjan', '0'),
    ('stock_alert_threshold', '5');
