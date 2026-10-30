import type { Page, Route } from '@playwright/test';

/**
 * A small stand-in for the API that lives in the test: it keeps products, deliveries, receipts,
 * orders and waves in memory and answers the calls of the screens the way the real API does (same
 * paths, same shapes, `X-Total-Count` on the lists). It is installed in the browser with
 * `page.route`, so the tests run the built app in a real browser with no server behind it (ADR 0039).
 * It knows nothing about the domain rules beyond what the flow needs; the real rules are tested in
 * the backend.
 */
type Rec = Record<string, unknown>;

interface Role {
  readonly roles: readonly string[];
}

const warehouse = { id: 'w-1', code: 'ZGZ1', name: 'Zaragoza', zones: [] };
const dock = {
  id: 'l-D-01',
  warehouseId: 'w-1',
  zoneId: 'z-a',
  code: 'D-01',
  type: 'dock',
  pickSequence: 10,
  status: 'active',
};

function text(source: Rec, key: string): string {
  const value = source[key];

  return typeof value === 'string' ? value : '';
}

function whole(source: Rec, key: string): number {
  const value = source[key];

  return typeof value === 'number' ? value : 0;
}

function records(source: Rec, key: string): Rec[] {
  const value = source[key];

  return Array.isArray(value) ? value.filter((item): item is Rec => typeof item === 'object') : [];
}

function problem(status: number, code: string): { status: number; body: Rec } {
  return { status, body: { status, code, detail: code, parameters: {} } };
}

export class Backend {
  private signedIn = false;
  private counter = 0;
  private readonly products: Rec[] = [];
  private readonly asns: Rec[] = [];
  private readonly receipts: Rec[] = [];
  private readonly orders: Rec[] = [];
  private readonly waves: Rec[] = [];
  private readonly pickLists: Rec[] = [];
  private readonly stock = new Map<string, number>();
  private readonly held = new Map<string, number>();

  constructor(private readonly user: Role) {}

  /** Starts answering the calls of the app in this page. */
  async install(page: Page): Promise<void> {
    await page.route('**/api/**', (route) => this.handle(route));
  }

  private id(prefix: string): string {
    this.counter += 1;

    return `${prefix}-${String(this.counter)}`;
  }

  private me(): Rec {
    return {
      id: 'u-1',
      email: 'ana@marea-moda.example',
      roles: this.user.roles,
      tenant: { id: 't-1', name: 'Marea Moda', defaultLanguage: 'es' },
    };
  }

  private async handle(route: Route): Promise<void> {
    const request = route.request();
    const url = new URL(request.url());
    const method = request.method();
    const path = url.pathname;
    const raw = request.postData();
    const body: Rec = raw === null || raw === '' ? {} : (JSON.parse(raw) as Rec);

    const answer = this.dispatch(method, path, url.searchParams, body);
    if (answer === undefined) {
      const missing = problem(404, 'shared.not_found');
      await route.fulfill({
        status: 404,
        contentType: 'application/problem+json',
        body: JSON.stringify(missing.body),
      });

      return;
    }
    const failed = answer.status >= 400;
    await route.fulfill({
      status: answer.status,
      contentType: failed ? 'application/problem+json' : 'application/json',
      headers: answer.total === undefined ? {} : { 'X-Total-Count': String(answer.total) },
      body: answer.body === undefined ? '' : JSON.stringify(answer.body),
    });
  }

  private dispatch(
    method: string,
    path: string,
    query: URLSearchParams,
    body: Rec,
  ): { status: number; body?: unknown; total?: number } | undefined {
    const ok = (data: unknown, status = 200) => ({ status, body: data });
    const list = (items: Rec[]) => {
      const size = Number(query.get('itemsPerPage') ?? '30');
      const page = Number(query.get('page') ?? '1');

      return {
        status: 200,
        body: items.slice((page - 1) * size, page * size),
        total: items.length,
      };
    };
    const key = `${method} ${path}`;
    let m: RegExpMatchArray | null;

    // ---- session
    if (key === 'POST /api/auth/refresh') {
      return this.signedIn
        ? ok({ token: 'token-1' })
        : problem(401, 'identity.refresh_token_invalid');
    }
    if (key === 'POST /api/auth/login') {
      if (text(body, 'password') !== 'secret') {
        return problem(401, 'identity.credentials_invalid');
      }
      this.signedIn = true;

      return ok({ token: 'token-1' });
    }
    if (key === 'POST /api/auth/logout') {
      this.signedIn = false;

      return { status: 204 };
    }
    if (key === 'GET /api/me') {
      return this.signedIn ? ok(this.me()) : problem(401, 'identity.unauthenticated');
    }

    // ---- warehouses
    if (key === 'GET /api/warehouses') {
      return ok([warehouse]);
    }
    if (key === 'GET /api/warehouses/w-1') {
      return ok(warehouse);
    }
    if (key === 'GET /api/warehouses/w-1/locations') {
      return list([dock]);
    }

    // ---- products
    if (key === 'GET /api/products') {
      const q = (query.get('q') ?? '').toLowerCase();
      const found = this.products.filter(
        (product) =>
          q === '' ||
          text(product, 'sku').toLowerCase().includes(q) ||
          text(product, 'name').toLowerCase().includes(q),
      );

      return list(found);
    }
    if (key === 'POST /api/products') {
      if (this.products.some((product) => text(product, 'sku') === text(body, 'sku'))) {
        return problem(409, 'catalog.sku_taken');
      }
      const product: Rec = {
        ...body,
        id: this.id('p'),
        status: 'active',
        gtins: [],
        packagingLevels: [],
      };
      this.products.push(product);

      return ok(product, 201);
    }
    m = /^\/api\/products\/([^/]+)$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      return this.product(m[1]);
    }
    m = /^\/api\/products\/([^/]+)\/gtins$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      const product = this.products.find((item) => item.id === m?.[1]);
      if (product === undefined) {
        return problem(404, 'shared.not_found');
      }
      product.gtins = [...(product.gtins as string[]), text(body, 'gtin')];

      return ok(product);
    }
    m = /^\/api\/products\/([^/]+)\/stock$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      const product = this.products.find((item) => item.id === m?.[1]);
      if (product === undefined) {
        return problem(404, 'shared.not_found');
      }

      return ok(this.productStock(product));
    }
    if (key === 'GET /api/stock-items') {
      const product = this.products.find((item) => item.id === query.get('productId'));
      const onHand = product === undefined ? 0 : (this.stock.get(text(product, 'id')) ?? 0);
      const lines =
        product === undefined || onHand === 0
          ? []
          : [
              {
                id: `s-${text(product, 'id')}`,
                productId: product.id,
                sku: product.sku,
                productName: product.name,
                warehouseId: 'w-1',
                warehouseCode: 'ZGZ1',
                locationId: dock.id,
                locationCode: dock.code,
                lot: null,
                expiryDate: null,
                status: 'available',
                onHand,
              },
            ];

      return list(lines);
    }

    // ---- deliveries and receipts
    if (key === 'GET /api/asns') {
      return list(this.asns);
    }
    if (key === 'POST /api/asns') {
      const asn: Rec = {
        id: this.id('a'),
        reference: text(body, 'reference'),
        supplier: text(body, 'supplier'),
        expectedDate: text(body, 'expectedDate'),
        status: 'expected',
        openReceiptId: null,
        lines: records(body, 'lines').map((line) => ({
          productId: line.productId,
          expectedUnits: whole(line, 'units'),
          receivedUnits: 0,
          lot: line.lot ?? null,
          expiryDate: line.expiryDate ?? null,
        })),
      };
      this.asns.push(asn);

      return ok(asn, 201);
    }
    m = /^\/api\/asns\/([^/]+)$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      return this.byId(this.asns, m[1]);
    }
    m = /^\/api\/asns\/([^/]+)\/receipts$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      const asn = this.asns.find((item) => item.id === m?.[1]);
      if (asn === undefined) {
        return problem(404, 'shared.not_found');
      }
      const receipt: Rec = {
        id: this.id('r'),
        asnId: asn.id,
        dockLocationId: text(body, 'dockLocationId'),
        status: 'open',
        overReceivedUnits: 0,
        overReceiptApproved: false,
        openedAt: new Date().toISOString(),
        closedAt: null,
        stockBooked: false,
        expected: records(asn, 'lines').map((line) => ({
          productId: line.productId,
          units: line.expectedUnits,
        })),
        lines: [],
        discrepancies: [],
      };
      this.receipts.push(receipt);
      asn.openReceiptId = receipt.id;

      return ok(receipt, 201);
    }
    m = /^\/api\/receipts\/([^/]+)$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      return this.byId(this.receipts, m[1]);
    }
    m = /^\/api\/receipts\/([^/]+)\/items$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      return this.count(m[1], body);
    }
    m = /^\/api\/receipts\/([^/]+)\/close$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      return this.closeReceipt(m[1]);
    }

    // ---- orders
    if (key === 'GET /api/orders') {
      const status = query.get('status');

      return list(this.orders.filter((order) => status === null || order.status === status));
    }
    if (key === 'POST /api/orders') {
      if (this.orders.some((order) => text(order, 'reference') === text(body, 'reference'))) {
        return problem(409, 'outbound.order_reference_taken');
      }
      const order: Rec = {
        id: this.id('o'),
        reference: text(body, 'reference'),
        warehouseId: text(body, 'warehouseId'),
        customer: text(body, 'customer'),
        shipTo: body.shipTo,
        priority: text(body, 'priority'),
        cutOffAt: text(body, 'cutOffAt'),
        registeredAt: new Date().toISOString(),
        status: 'registered',
        shortageUnits: 0,
        backorderAccepted: false,
        waveId: null,
        lines: records(body, 'lines').map((line, index) => ({
          lineNumber: index + 1,
          productId: line.productId,
          orderedUnits: whole(line, 'units'),
          allocatedUnits: 0,
          shortageUnits: 0,
          pickedUnits: 0,
          shippedUnits: 0,
          reservationId: null,
        })),
      };
      this.orders.push(order);

      return ok(order, 201);
    }
    m = /^\/api\/orders\/([^/]+)$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      return this.byId(this.orders, m[1]);
    }
    m = /^\/api\/orders\/([^/]+)\/allocate$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      return this.allocate(m[1]);
    }

    // ---- waves and pick lists
    if (key === 'GET /api/waves') {
      const status = query.get('status');

      return list(this.waves.filter((wave) => status === null || wave.status === status));
    }
    if (key === 'POST /api/waves') {
      const orderIds = Array.isArray(body.orderIds) ? (body.orderIds as string[]) : [];
      const wave: Rec = {
        id: this.id('wave'),
        warehouseId: 'w-1',
        status: 'planned',
        orderIds,
        pickListIds: [],
        createdAt: new Date().toISOString(),
        releasedAt: null,
        completedAt: null,
      };
      this.waves.push(wave);
      for (const order of this.orders.filter((item) => orderIds.includes(text(item, 'id')))) {
        order.status = 'in_wave';
        order.waveId = wave.id;
      }

      return ok(wave, 201);
    }
    m = /^\/api\/waves\/([^/]+)$/.exec(path);
    if (method === 'GET' && m?.[1] !== undefined) {
      return this.byId(this.waves, m[1]);
    }
    m = /^\/api\/waves\/([^/]+)\/release$/.exec(path);
    if (method === 'POST' && m?.[1] !== undefined) {
      return this.release(m[1], body);
    }
    if (key === 'GET /api/pick-lists') {
      const waveId = query.get('waveId');

      return ok(this.pickLists.filter((pickList) => waveId === null || pickList.waveId === waveId));
    }

    return undefined;
  }

  private byId(items: Rec[], id: string) {
    const found = items.find((item) => item.id === id);

    return found === undefined ? problem(404, 'shared.not_found') : { status: 200, body: found };
  }

  private product(id: string) {
    return this.byId(this.products, id);
  }

  private productStock(product: Rec): Rec {
    const id = text(product, 'id');
    const onHand = this.stock.get(id) ?? 0;
    const onHold = this.held.get(id) ?? 0;
    const row = {
      onHand,
      available: onHand - onHold,
      quarantine: 0,
      damaged: 0,
      onHold,
    };

    return {
      productId: id,
      sku: product.sku,
      name: product.name,
      ...row,
      warehouses:
        onHand === 0 ? [] : [{ warehouseId: 'w-1', warehouseCode: 'ZGZ1', ...row, locations: 1 }],
    };
  }

  /** Counts a scanned item against the receipt: the product is the one with that barcode. */
  private count(receiptId: string, body: Rec) {
    const receipt = this.receipts.find((item) => item.id === receiptId);
    const product = this.products.find((item) =>
      (item.gtins as string[]).includes(text(body, 'gtin')),
    );
    if (receipt === undefined || product === undefined) {
      return problem(422, 'inbound.unknown_gtin');
    }
    const lines = records(receipt, 'lines');
    lines.push({
      lineNumber: lines.length + 1,
      productId: product.id,
      units: whole(body, 'units'),
      lot: body.lot ?? null,
      expiryDate: body.expiryDate ?? null,
      condition: text(body, 'condition') || 'good',
      receivedAt: new Date().toISOString(),
    });
    receipt.lines = lines;
    this.refresh(receipt);

    return { status: 201, body: receipt };
  }

  /** Recomputes what the receipt owes: the units over what was announced, and the discrepancies. */
  private refresh(receipt: Rec): void {
    const counted = new Map<string, number>();
    for (const line of records(receipt, 'lines')) {
      counted.set(
        text(line, 'productId'),
        (counted.get(text(line, 'productId')) ?? 0) + whole(line, 'units'),
      );
    }
    let over = 0;
    const discrepancies: Rec[] = [];
    for (const expected of records(receipt, 'expected')) {
      const productId = text(expected, 'productId');
      const difference = (counted.get(productId) ?? 0) - whole(expected, 'units');
      if (difference > 0) {
        over += difference;
        discrepancies.push({ type: 'excess', productId, units: difference });
      } else if (difference < 0) {
        discrepancies.push({ type: 'shortage', productId, units: -difference });
      }
    }
    receipt.overReceivedUnits = over;
    receipt.discrepancies = discrepancies;
    const asn = this.asns.find((item) => item.id === receipt.asnId);
    if (asn !== undefined) {
      asn.lines = records(asn, 'lines').map((line) => ({
        ...line,
        receivedUnits: counted.get(text(line, 'productId')) ?? 0,
      }));
      const complete = records(asn, 'lines').every(
        (line) => whole(line, 'receivedUnits') >= whole(line, 'expectedUnits'),
      );
      asn.status = complete ? 'received' : 'partially_received';
    }
  }

  private closeReceipt(receiptId: string) {
    const receipt = this.receipts.find((item) => item.id === receiptId);
    if (receipt === undefined) {
      return problem(404, 'shared.not_found');
    }
    receipt.status = 'closed';
    receipt.closedAt = new Date().toISOString();
    receipt.stockBooked = true;
    for (const line of records(receipt, 'lines')) {
      const id = text(line, 'productId');
      this.stock.set(id, (this.stock.get(id) ?? 0) + whole(line, 'units'));
    }
    const asn = this.asns.find((item) => item.id === receipt.asnId);
    if (asn !== undefined) {
      asn.openReceiptId = null;
    }

    return { status: 200, body: receipt };
  }

  /** Reserves what the stock covers; what it does not cover is the shortage. */
  private allocate(orderId: string) {
    const order = this.orders.find((item) => item.id === orderId);
    if (order === undefined) {
      return problem(404, 'shared.not_found');
    }
    let shortage = 0;
    order.lines = records(order, 'lines').map((line) => {
      const id = text(line, 'productId');
      const available = (this.stock.get(id) ?? 0) - (this.held.get(id) ?? 0);
      const allocated = Math.min(whole(line, 'orderedUnits'), available);
      this.held.set(id, (this.held.get(id) ?? 0) + allocated);
      shortage += whole(line, 'orderedUnits') - allocated;

      return {
        ...line,
        allocatedUnits: allocated,
        shortageUnits: whole(line, 'orderedUnits') - allocated,
        reservationId: allocated > 0 ? this.id('res') : null,
      };
    });
    order.shortageUnits = shortage;
    order.status = shortage === 0 ? 'allocated' : 'partially_allocated';

    return { status: 200, body: order };
  }

  private release(waveId: string, body: Rec) {
    const wave = this.waves.find((item) => item.id === waveId);
    if (wave === undefined) {
      return problem(404, 'shared.not_found');
    }
    const lists = Math.max(1, whole(body, 'pickLists'));
    const total = this.orders
      .filter((order) => (wave.orderIds as string[]).includes(text(order, 'id')))
      .flatMap((order) => records(order, 'lines'))
      .reduce((sum, line) => sum + whole(line, 'allocatedUnits'), 0);
    const ids: string[] = [];
    for (let index = 0; index < lists; index += 1) {
      const id = this.id('pl');
      ids.push(id);
      this.pickLists.push({
        id,
        waveId,
        status: 'generated',
        operatorId: null,
        unitsToPick: Math.ceil(total / lists),
        unitsPicked: 0,
        lines: [],
        createdAt: new Date().toISOString(),
        startedAt: null,
        completedAt: null,
      });
    }
    wave.status = 'released';
    wave.releasedAt = new Date().toISOString();
    wave.pickListIds = ids;

    return { status: 200, body: wave };
  }
}
