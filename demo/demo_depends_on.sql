-- ============================================================
-- Demo Database: dependsOn Feature
-- Grocery CRUD untuk CodeIgniter 4
-- ============================================================
-- Tabel ini mendemonstrasikan penggunaan dependsOn dengan
-- berbagai skenario: show/hide dan enable/disable.
-- ============================================================

CREATE TABLE `products` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`              VARCHAR(255) NOT NULL,
    `price`             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `has_discount`      TINYINT(1) NOT NULL DEFAULT 0,
    `discount_price`    DECIMAL(12,2) DEFAULT NULL,
    `discount_percent`  INT UNSIGNED DEFAULT NULL COMMENT 'Discount percentage 0-100',
    `requires_shipping` TINYINT(1) NOT NULL DEFAULT 1,
    `shipping_weight`   DECIMAL(8,2) DEFAULT NULL COMMENT 'Weight in kg',
    `shipping_notes`    TEXT DEFAULT NULL,
    `is_active`         TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample data
INSERT INTO `products` (`name`, `price`, `has_discount`, `discount_price`, `discount_percent`, `requires_shipping`, `shipping_weight`, `shipping_notes`, `is_active`) VALUES
('Laptop ASUS ROG', 15000000, 1, 12999000, 15, 1, 2.50, 'Handle with care, contains battery', 1),
('Mouse Logitech MX', 850000, 0, NULL, NULL, 1, 0.25, NULL, 1),
('Keyboard Mechanical', 1200000, 1, 999000, 20, 1, 1.10, NULL, 1),
('Software License - Antivirus', 350000, 0, NULL, NULL, 0, NULL, NULL, 1),
('USB-C Hub 7-in-1', 450000, 1, 375000, NULL, 1, 0.15, NULL, 1),
('Ebook - PHP Modern', 150000, 1, 99000, 34, 0, NULL, 'Digital product - no shipping needed', 1),
('Monitor 27 inch 4K', 4500000, 0, NULL, NULL, 1, 5.00, 'Fragile item, double box packaging', 1),
('Cloud Storage 1TB Annual', 600000, 1, 499000, NULL, 0, NULL, NULL, 1);
