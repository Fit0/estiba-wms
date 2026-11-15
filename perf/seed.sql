-- Volume data to measure the API with (E10-06, ADR 0055).
--
-- It writes straight to the tables, without going through the domain, so it is fast: 20 000 products
-- with one stock line each, half a million ledger movements and 20 000 orders. It is NOT demo data
-- and it is only for a development database that already ran `make demo` (it needs the tenant and
-- the warehouse of Marea Moda). Running it twice replaces what it wrote: everything it creates starts
-- with PERF- (products, orders) or has the reference PERF (movements).
--
-- The ids are random UUIDs and not UUID v7, which makes no difference to a measurement. The ledger
-- of each stock line adds up to its stock, but nothing else is promised: do not run
-- `make stock-rebuild` on a database with this data and expect the figures of the demo back.

\set ON_ERROR_STOP on
\timing on

SELECT tenant_id AS tenant, id AS author FROM users WHERE email = 'admin@marea.test' \gset
SELECT id AS wh, code AS whcode FROM warehouses WHERE tenant_id = :'tenant' ORDER BY code LIMIT 1 \gset

BEGIN;

DELETE FROM stock_movements WHERE tenant_id = :'tenant' AND reference = 'PERF';
DELETE FROM stock_overview WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%';
DELETE FROM stock_items WHERE tenant_id = :'tenant' AND product_id IN (SELECT id FROM products WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%');
DELETE FROM orders WHERE tenant_id = :'tenant' AND reference LIKE 'PERF-%';
DELETE FROM products WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%';

INSERT INTO products (id, tenant_id, sku, name, base_unit, storage_class, status, gtins, packaging_levels,
                      dimensions_length_mm, dimensions_width_mm, dimensions_height_mm, dimensions_weight_grams,
                      tracking_by_lot, tracking_by_expiry, turnover_class)
SELECT gen_random_uuid(), :'tenant'::uuid, 'PERF-' || lpad(n::text, 6, '0'),
       (ARRAY['Camiseta', 'Pantalón', 'Vestido', 'Chaqueta', 'Falda', 'Jersey', 'Abrigo', 'Camisa'])[1 + n % 8]
           || ' ' || (ARRAY['algodón', 'lino', 'lana', 'vaquero', 'punto', 'seda'])[1 + n % 6] || ' modelo ' || n,
       'each', 'ambient', 'active', '[]'::json, '[]'::json, 300, 200, 50, 400, false, false, 'B'
FROM generate_series(1, 20000) AS n;

CREATE TEMP TABLE perf_products AS
SELECT id, sku, name, row_number() OVER (ORDER BY sku) AS rn
FROM products WHERE tenant_id = :'tenant' AND sku LIKE 'PERF-%';
CREATE UNIQUE INDEX ON perf_products (rn);
ANALYZE perf_products;

CREATE TEMP TABLE perf_locations AS
SELECT row_number() OVER (ORDER BY code) AS rn, id, code
FROM locations
WHERE tenant_id = :'tenant' AND warehouse_id = :'wh' AND type IN ('reserve', 'pick_face') AND status = 'active';

SELECT count(*) AS nloc FROM perf_locations \gset

INSERT INTO stock_items (id, tenant_id, on_hand, product_id, location_id, lot, expiry_date, status, reserved, first_received_at, version)
SELECT gen_random_uuid(), :'tenant'::uuid, 50 + p.rn % 200, p.id, l.id, '', DATE '9999-12-31', 'available', 0,
       (now() AT TIME ZONE 'utc') - interval '90 days', 1
FROM perf_products p
JOIN perf_locations l ON l.rn = 1 + p.rn % :nloc;

INSERT INTO stock_overview (stock_item_id, tenant_id, product_id, sku, product_name, warehouse_id, warehouse_code,
                            location_id, location_code, lot, expiry_date, status, on_hand)
SELECT s.id, s.tenant_id, s.product_id, p.sku, p.name, :'wh'::uuid, :'whcode', s.location_id, l.code, NULL, NULL, s.status, s.on_hand
FROM stock_items s
JOIN perf_products p ON p.id = s.product_id
JOIN perf_locations l ON l.id = s.location_id
WHERE s.tenant_id = :'tenant';

-- 25 movements per stock line: one receipt, twelve issues, and six pairs of receipt and issue.
-- The balance after the last one is the stock of the line.
INSERT INTO stock_movements (id, tenant_id, stock_item_id, type, change, balance_after, reference, reason, author_id, occurred_at)
SELECT gen_random_uuid(), s.tenant_id, s.id,
       CASE WHEN m.change > 0 THEN 'receipt' ELSE 'issue' END,
       m.change, m.balance, 'PERF', NULL, :'author'::uuid,
       (now() AT TIME ZONE 'utc') - ((25 - k) * interval '3 days') - ((abs(hashtext(s.id::text)) % 1000) * interval '1 minute')
FROM stock_items s
JOIN perf_products p ON p.id = s.product_id
CROSS JOIN generate_series(1, 25) AS k
CROSS JOIN LATERAL (
    SELECT CASE WHEN k = 1 THEN s.on_hand + 120 WHEN k <= 13 THEN -10 WHEN k % 2 = 0 THEN 10 ELSE -10 END AS change,
           CASE WHEN k = 1 THEN s.on_hand + 120
                WHEN k <= 13 THEN s.on_hand + 120 - 10 * (k - 1)
                WHEN k % 2 = 0 THEN s.on_hand + 10
                ELSE s.on_hand END AS balance
) AS m
WHERE s.tenant_id = :'tenant';

INSERT INTO orders (id, tenant_id, reference, warehouse_id, customer, priority, cut_off_at, registered_at, status, lines,
                    ship_to_street, ship_to_postal_code, ship_to_city, ship_to_country_code, backorder_accepted)
SELECT gen_random_uuid(), :'tenant'::uuid, 'PERF-' || lpad(n::text, 6, '0'), :'wh'::uuid,
       'Cliente ' || (n % 500),
       (ARRAY['low', 'normal', 'normal', 'high', 'urgent'])[1 + n % 5],
       (now() AT TIME ZONE 'utc') + ((n % 72) * interval '1 hour'),
       (now() AT TIME ZONE 'utc') - ((n % 3000) * interval '1 minute'),
       st.status,
       json_build_array(
           json_build_object('line_number', 1, 'product_id', (SELECT id FROM perf_products WHERE rn = 1 + (n * 7) % 20000),
                             'ordered_units', 1 + n % 10, 'allocated_units', st.allocated * (1 + n % 10),
                             'reservation_id', CASE WHEN st.allocated = 1 THEN gen_random_uuid() END,
                             'picked_units', st.picked * (1 + n % 10), 'shipped_units', st.shipped * (1 + n % 10)),
           json_build_object('line_number', 2, 'product_id', (SELECT id FROM perf_products WHERE rn = 1 + (n * 13) % 20000),
                             'ordered_units', 2 + n % 5, 'allocated_units', st.allocated * (2 + n % 5),
                             'reservation_id', CASE WHEN st.allocated = 1 THEN gen_random_uuid() END,
                             'picked_units', st.picked * (2 + n % 5), 'shipped_units', st.shipped * (2 + n % 5))
       ),
       'Calle Mayor ' || (n % 90), '50001', 'Zaragoza', 'ES', false
FROM generate_series(1, 20000) AS n
CROSS JOIN LATERAL (
    SELECT CASE n % 10 WHEN 0 THEN 'registered' WHEN 1 THEN 'registered' WHEN 2 THEN 'registered' WHEN 3 THEN 'registered'
                       WHEN 4 THEN 'allocated' WHEN 5 THEN 'allocated' WHEN 6 THEN 'picked' ELSE 'shipped' END AS status,
           CASE WHEN n % 10 >= 4 THEN 1 ELSE 0 END AS allocated,
           CASE WHEN n % 10 >= 6 THEN 1 ELSE 0 END AS picked,
           CASE WHEN n % 10 >= 7 THEN 1 ELSE 0 END AS shipped
) AS st;

COMMIT;

ANALYZE products;
ANALYZE stock_items;
ANALYZE stock_overview;
ANALYZE stock_movements;
ANALYZE orders;

SELECT 'products' AS "table", count(*) FROM products WHERE sku LIKE 'PERF-%'
UNION ALL SELECT 'stock_items', count(*) FROM stock_items WHERE product_id IN (SELECT id FROM perf_products)
UNION ALL SELECT 'stock_movements', count(*) FROM stock_movements WHERE reference = 'PERF'
UNION ALL SELECT 'orders', count(*) FROM orders WHERE reference LIKE 'PERF-%';
