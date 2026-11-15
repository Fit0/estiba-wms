// Load test of the read side of the API (E10-06, ADR 0055).
//
// It signs in ONCE, in setup(): the sign-in is limited to a few tries (ADR 0054), so a test that
// signed in on every iteration would measure the limiter and not the API.
//
//   make perf-load PASSWORD='...'            # the password you gave to `make demo`
//
// The budget is checked with 3 users, a light office load. `VUS=20` pushes the API until it queues and
// is expected to cross the thresholds: it is how the capacity of the machine is read.
//
// Needs the data of `make perf-seed`. The thresholds are the budget of the API: if one fails, k6 exits
// with an error and so does the make target.

import http from 'k6/http';
import { check, group, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://localhost:8080';
const EMAIL = __ENV.EMAIL || 'admin@marea.test';
const PASSWORD = __ENV.PASSWORD;
// How many users at the peak. With few of them the latency is what one request costs; with many, the
// queue in front of the PHP threads shows up. Run both to tell the two apart.
const PEAK = Number(__ENV.VUS || 3);

export const options = {
  scenarios: {
    readers: {
      executor: 'ramping-vus',
      startVUs: 1,
      stages: [
        { duration: '20s', target: Math.max(1, Math.ceil(PEAK / 2)) },
        { duration: '1m', target: PEAK },
        { duration: '20s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    // Not a budget: a threshold this loose is there so that k6 prints the figure of the floor.
    'http_req_duration{endpoint:health}': ['p(95)<5000'],
    'http_req_duration{endpoint:orders}': ['p(95)<400'],
    'http_req_duration{endpoint:orders_deep}': ['p(95)<600'],
    'http_req_duration{endpoint:products_search}': ['p(95)<400'],
    'http_req_duration{endpoint:stock}': ['p(95)<400'],
    'http_req_duration{endpoint:movements}': ['p(95)<600'],
    'http_req_duration{endpoint:movements_product}': ['p(95)<600'],
    'http_req_duration{endpoint:kpis}': ['p(95)<800'],
  },
};

export function setup() {
  if (!PASSWORD) {
    throw new Error('Give the password of the demo user: make perf-load PASSWORD=...');
  }
  const res = http.post(`${BASE}/api/auth/login`, JSON.stringify({ email: EMAIL, password: PASSWORD }), {
    headers: { 'Content-Type': 'application/json' },
  });
  if (res.status !== 200) {
    throw new Error(`Sign-in answered ${res.status}: ${res.body}`);
  }
  const token = res.json('token');

  // One product with stock, to ask for its movements and its stock.
  const auth = { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } };
  const products = http.get(`${BASE}/api/products?sku=PERF-010000`, auth).json();
  if (!Array.isArray(products) || products.length === 0) {
    throw new Error('No PERF- products: run `make perf-seed` first.');
  }

  return { token, productId: products[0].id };
}

export default function (data) {
  const params = (endpoint) => ({
    headers: { Authorization: `Bearer ${data.token}`, Accept: 'application/json' },
    tags: { endpoint },
  });
  const ok = (res, name) => check(res, { [`${name} is 200`]: (r) => r.status === 200 });

  group('office', () => {
    // The floor: no database and no sign-in, only what every request costs. What the endpoints
    // below add to this is what the queries and the serialising cost.
    ok(http.get(`${BASE}/health`, { tags: { endpoint: 'health' } }), 'health');
    ok(http.get(`${BASE}/api/orders?status=registered&itemsPerPage=30`, params('orders')), 'orders');
    ok(http.get(`${BASE}/api/orders?page=600&itemsPerPage=30`, params('orders_deep')), 'deep orders page');
    ok(http.get(`${BASE}/api/products?q=lino&itemsPerPage=30`, params('products_search')), 'product search');
    ok(http.get(`${BASE}/api/stock-items?itemsPerPage=30`, params('stock')), 'stock');
    ok(http.get(`${BASE}/api/stock-movements?itemsPerPage=30`, params('movements')), 'movements');
    ok(http.get(`${BASE}/api/stock-movements?productId=${data.productId}&itemsPerPage=30`, params('movements_product')), 'movements of a product');
    ok(http.get(`${BASE}/api/kpis?from=2026-08-01&until=2026-12-01`, params('kpis')), 'kpis');
  });

  sleep(1);
}
