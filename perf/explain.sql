-- The queries behind the lists of the API, as the application sends them, with their plan (E10-06).
-- Run with `make perf-explain` after `make perf-seed`. Read each plan for "Seq Scan" on a big table and
-- for a "Sort" that spills to disk; those are what an index would remove.

\set ON_ERROR_STOP on
SELECT tenant_id AS tenant FROM users WHERE email = 'admin@marea.test' \gset
SELECT id AS product FROM products WHERE tenant_id = :'tenant' AND sku = 'PERF-010000' \gset
SELECT id AS item FROM stock_items WHERE tenant_id = :'tenant' AND product_id = :'product' \gset
SELECT location_id AS location FROM stock_items WHERE id = :'item' \gset

\echo '== 1. Orders of a status, most urgent first (GET /api/orders?status=registered)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT o.* FROM orders o WHERE o.tenant_id = :'tenant' AND o.status = 'registered'
ORDER BY CASE o.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, o.cut_off_at, o.reference
LIMIT 30 OFFSET 0;

\echo '== 2. Orders, last page of all (GET /api/orders?page=600)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT o.* FROM orders o WHERE o.tenant_id = :'tenant'
ORDER BY CASE o.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, o.cut_off_at, o.reference
LIMIT 30 OFFSET 18000;

\echo '== 3. Products that contain a text (GET /api/products?q=lino)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT p.id FROM products p WHERE p.tenant_id = :'tenant' AND (LOWER(p.sku) LIKE '%lino%' OR LOWER(p.name) LIKE '%lino%')
ORDER BY p.sku LIMIT 30;

\echo '== 4. Latest movements of the tenant (GET /api/stock-movements): the page'
EXPLAIN (ANALYZE, BUFFERS)
SELECT m.id, o.sku, m.type, m.change, m.occurred_at FROM stock_movements m
INNER JOIN stock_overview o ON o.stock_item_id = m.stock_item_id
WHERE m.tenant_id = :'tenant' ORDER BY m.occurred_at DESC, m.id DESC LIMIT 30 OFFSET 0;

\echo '== 4b. The same list, the count that goes in X-Total-Count'
EXPLAIN (ANALYZE, BUFFERS)
SELECT COUNT(*) FROM stock_movements m
INNER JOIN stock_overview o ON o.stock_item_id = m.stock_item_id
WHERE m.tenant_id = :'tenant';

\echo '== 5. Movements of a stock line (GET /api/stock-movements?stockItemId=)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT m.id, o.sku, m.type, m.change, m.occurred_at FROM stock_movements m
INNER JOIN stock_overview o ON o.stock_item_id = m.stock_item_id
WHERE m.tenant_id = :'tenant' AND m.stock_item_id = :'item' ORDER BY m.occurred_at DESC, m.id DESC LIMIT 30;

\echo '== 6. Movements of a product (GET /api/stock-movements?productId=)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT m.id, o.sku, m.type, m.change, m.occurred_at FROM stock_movements m
INNER JOIN stock_overview o ON o.stock_item_id = m.stock_item_id
WHERE m.tenant_id = :'tenant' AND o.product_id = :'product' ORDER BY m.occurred_at DESC, m.id DESC LIMIT 30;

\echo '== 7. Movements in a location (GET /api/stock-movements?locationId=)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT m.id, o.sku, m.type, m.change, m.occurred_at FROM stock_movements m
INNER JOIN stock_overview o ON o.stock_item_id = m.stock_item_id
WHERE m.tenant_id = :'tenant' AND o.location_id = :'location' ORDER BY m.occurred_at DESC, m.id DESC LIMIT 30;

\echo '== 8. Stock of the tenant, in the order of the screen (GET /api/stock-items)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM stock_overview WHERE tenant_id = :'tenant' AND on_hand > 0
ORDER BY sku, location_code, expiry_date, lot, status LIMIT 30;

\echo '== 9. Stock of a product (GET /api/stock-items?productId=)'
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM stock_overview WHERE tenant_id = :'tenant' AND on_hand > 0 AND product_id = :'product'
ORDER BY sku, location_code, expiry_date, lot, status LIMIT 30;
