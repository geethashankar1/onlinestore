-- Migration 004: brand / manufacturer / scale on products
--
--   brand        — the real car's marque: Porsche, Nissan, Ferrari
--   manufacturer — who made the model: Hot Wheels, Matchbox, Kyosho
--   scale        — 1:18, 1:64, …
--
-- All three are NOT NULL DEFAULT '' rather than nullable, so existing rows stay
-- valid and filtering never has to special-case NULL.
--
-- One ALTER per column, on purpose. Combining them fails on TiDB: it resolves
-- every AFTER clause against the table as it was BEFORE the statement, so
-- "ADD COLUMN manufacturer ... AFTER brand" errors with "Unknown column 'brand'"
-- even though the same statement adds it. MySQL applies them sequentially and
-- accepts it. Separate statements work on both.

SET NAMES utf8mb4;

ALTER TABLE products ADD COLUMN brand        VARCHAR(80) NOT NULL DEFAULT '' AFTER name;
ALTER TABLE products ADD COLUMN manufacturer VARCHAR(80) NOT NULL DEFAULT '' AFTER brand;
ALTER TABLE products ADD COLUMN scale        VARCHAR(20) NOT NULL DEFAULT '' AFTER manufacturer;

-- Indexed because the shop filters on them.
ALTER TABLE products ADD KEY idx_products_brand        (brand);
ALTER TABLE products ADD KEY idx_products_manufacturer (manufacturer);
ALTER TABLE products ADD KEY idx_products_scale        (scale);
