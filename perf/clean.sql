-- Removes what perf/seed.sql wrote (E10-06, ADR 0055): the PERF- products with their stock, the ledger
-- movements with the reference PERF and the PERF- orders. It leaves the demo data as it was.
--
-- Run it when you go back to working by hand on the development database: the seed fills every
-- reserve and pick face with stock, so a location with a limit has no room left and a putaway to it
-- is refused.

\set ON_ERROR_STOP on

SELECT tenant_id AS tenant FROM users WHERE email = 'admin@marea.test' \gset

BEGIN;

DELETE FROM stock_movements WHERE tenant_id = :'tenant' AND reference = 'PERF';
DELETE FROM stock_overview WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%';
DELETE FROM stock_items WHERE tenant_id = :'tenant' AND product_id IN (SELECT id FROM products WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%');
DELETE FROM orders WHERE tenant_id = :'tenant' AND reference LIKE 'PERF-%';
DELETE FROM products WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%';

COMMIT;

VACUUM ANALYZE;
