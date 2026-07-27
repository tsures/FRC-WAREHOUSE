-- Optional starter data for a new installation.
-- Import this only after database/warehouse.sql.

START TRANSACTION;

INSERT INTO `locations`
    (`parent_id`, `name`, `code`, `description`, `location_type`, `sort_order`, `is_active`)
VALUES
    (NULL, 'מחסן ראשי', 'MAIN-WAREHOUSE', 'מיקום ראשי לדוגמה', 'warehouse', 0, 1);

INSERT INTO `categories`
    (`parent_id`, `name_he`, `name_en`, `description`, `sort_order`, `is_active`)
VALUES
    (NULL, 'כללי', 'General', 'קטגוריית ברירת מחדל', 0, 1),
    (NULL, 'אלקטרוניקה', 'Electronics', NULL, 10, 1),
    (NULL, 'כלי עבודה', 'Tools', NULL, 20, 1),
    (NULL, 'חומרה', 'Hardware', NULL, 30, 1);

INSERT INTO `settings`
    (`setting_key`, `setting_value`, `value_type`, `is_public`)
VALUES
    ('warehouse_name', 'מחסן FRC', 'string', 1),
    ('low_stock_notifications', 'true', 'boolean', 0)
ON DUPLICATE KEY UPDATE
    `setting_value` = VALUES(`setting_value`),
    `value_type` = VALUES(`value_type`),
    `is_public` = VALUES(`is_public`);

COMMIT;
