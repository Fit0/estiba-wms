<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Api;

use App\Identity\Domain\Role;
use App\Tests\Unit\Support\Ids;

/**
 * Registering and reading orders (OUT-01, OUT-02).
 */
final class OrderApiTest extends OutboundApiTestCase
{
    public function testRegistersAnOrderWithItsLines(): void
    {
        $order = $this->registerOrder('PED-2027-0001', [
            ['productId' => $this->shirtId, 'units' => 10],
            ['productId' => $this->yoghurtId, 'units' => 4],
        ], ['priority' => 'high']);

        $this->assertStatus(201);
        self::assertSame('PED-2027-0001', $order['reference']);
        self::assertSame($this->warehouseId, $order['warehouseId']);
        self::assertSame('Marea Moda Zaragoza', $order['customer']);
        self::assertSame(['street' => 'Calle Mayor 12', 'postalCode' => '50001', 'city' => 'Zaragoza', 'countryCode' => 'ES'], $order['shipTo']);
        self::assertSame('high', $order['priority']);
        self::assertSame('registered', $order['status']);
        self::assertSame(14, $order['shortageUnits']);
        self::assertSame(1, $this->lineOf($order, 1)['lineNumber']);
        self::assertSame($this->shirtId, $this->lineOf($order, 1)['productId']);
        self::assertSame(10, $this->lineOf($order, 1)['orderedUnits']);
        self::assertSame(0, $this->lineOf($order, 1)['allocatedUnits']);
        self::assertNull($this->lineOf($order, 1)['reservationId']);
        self::assertSame(2, $this->lineOf($order, 2)['lineNumber']);
    }

    public function testThePriorityIsNormalUnlessSaid(): void
    {
        self::assertSame('normal', $this->registerOrder()['priority']);
    }

    // OUT-01
    public function testTheSameReferenceCannotBeRegisteredTwiceByATenantButCanByAnother(): void
    {
        $this->registerOrder('PED-2027-0001');
        $this->assertStatus(201);

        $problem = $this->registerOrder('PED-2027-0001');
        $this->assertProblem(409, 'outbound.order_reference_already_used', $problem);
        self::assertSame('PED-2027-0001', $this->parameter($problem, 'reference'));
    }

    public function testReportsAnUnknownWarehouse(): void
    {
        $unknown = Ids::next();

        $problem = $this->registerOrder(extra: ['warehouseId' => $unknown]);

        $this->assertProblem(422, 'outbound.warehouse_unknown', $problem);
        self::assertSame($unknown, $this->parameter($problem, 'id'));
    }

    public function testAWarehouseOfAnotherTenantIsUnknown(): void
    {
        $otherTenant = Ids::next();
        $foreign = $this->id($this->request('POST', '/api/warehouses', ['code' => 'BCN1', 'name' => 'Barcelona'], $otherTenant));

        $this->assertProblem(422, 'outbound.warehouse_unknown', $this->registerOrder(extra: ['warehouseId' => $foreign]));
    }

    // OUT-02
    public function testReportsAnUnknownProduct(): void
    {
        $unknown = Ids::next();

        $problem = $this->registerOrder(lines: [['productId' => $unknown, 'units' => 1]]);

        $this->assertProblem(422, 'outbound.product_not_orderable', $problem);
        self::assertSame('unknown', $this->parameter($problem, 'reason'));
    }

    // OUT-02
    public function testABlockedProductCannotBeOrdered(): void
    {
        $this->request('POST', '/api/products/'.$this->shirtId.'/block');
        $this->assertStatus(200);

        $problem = $this->registerOrder();

        $this->assertProblem(422, 'outbound.product_not_orderable', $problem);
        self::assertSame('unavailable', $this->parameter($problem, 'reason'));
    }

    // OUT-02
    public function testALineAsksForAtLeastOneUnit(): void
    {
        $problem = $this->registerOrder(lines: [['productId' => $this->shirtId, 'units' => 0]]);

        $this->assertProblem(422, 'outbound.ordered_quantity_invalid', $problem);
        self::assertSame(1, $this->parameter($problem, 'line'));
    }

    // OUT-02
    public function testAnOrderNeedsLines(): void
    {
        $this->assertProblem(422, 'outbound.order_lines_invalid', $this->registerOrder(lines: []));
    }

    public function testALineOfTheWrongShapeIsUnreadable(): void
    {
        $this->assertProblem(400, 'shared.request_malformed', $this->registerOrder(lines: [['productId' => $this->shirtId, 'units' => 'ten']]));
        $this->assertProblem(400, 'shared.request_malformed', $this->registerOrder(lines: ['nonsense']));
    }

    public function testRefusesAnInvalidAddressReferenceOrCustomer(): void
    {
        $this->assertProblem(422, 'outbound.shipping_address_invalid', $this->registerOrder(extra: ['shipTo' => ['street' => 'Calle', 'postalCode' => '50001', 'city' => 'Zaragoza', 'countryCode' => 'ESP']]));
        $this->assertProblem(422, 'outbound.order_reference_invalid', $this->registerOrder('   '));
        $this->assertProblem(422, 'outbound.customer_name_invalid', $this->registerOrder(extra: ['customer' => '']));
    }

    public function testRefusesAnUnknownPriority(): void
    {
        $this->assertProblem(400, 'shared.request_malformed', $this->registerOrder(extra: ['priority' => 'whenever']));
    }

    public function testGetsAnOrderAndHidesItFromAnotherTenant(): void
    {
        $id = $this->id($this->registerOrder());

        $order = $this->request('GET', '/api/orders/'.$id);
        $this->assertStatus(200);
        self::assertSame($id, $order['id']);

        $problem = $this->request('GET', '/api/orders/'.$id, tenant: Ids::next());
        $this->assertProblem(404, 'outbound.order_not_found', $problem);
    }

    public function testListsTheOrdersOfTheTenantMostUrgentFirst(): void
    {
        $this->registerOrder('PED-LOW', extra: ['priority' => 'low']);
        $this->registerOrder('PED-URGENT-LATE', extra: ['priority' => 'urgent', 'cutOffAt' => '2026-10-25T14:00:00+00:00']);
        $this->registerOrder('PED-URGENT-SOON', extra: ['priority' => 'urgent', 'cutOffAt' => '2026-10-24T09:00:00+00:00']);
        $this->registerOrder('PED-NORMAL');
        $this->registerOrder('PED-OTHER-TENANT', tenant: Ids::next());

        $orders = $this->request('GET', '/api/orders');

        $this->assertStatus(200);
        self::assertSame(['PED-URGENT-SOON', 'PED-URGENT-LATE', 'PED-NORMAL', 'PED-LOW'], array_column($orders, 'reference'));
    }

    public function testFiltersByStatusAndPages(): void
    {
        $first = $this->registerOrder('PED-1');
        $this->registerOrder('PED-2');
        $this->request('POST', '/api/orders/'.$this->id($first).'/cancel');

        self::assertSame(['PED-1'], array_column($this->request('GET', '/api/orders', query: ['status' => 'cancelled']), 'reference'));
        self::assertSame(['PED-2'], array_column($this->request('GET', '/api/orders', query: ['status' => 'registered']), 'reference'));
        self::assertCount(1, $this->request('GET', '/api/orders', query: ['itemsPerPage' => 1]));

        $this->assertProblem(400, 'shared.request_malformed', $this->request('GET', '/api/orders', query: ['status' => 'shipped']));
    }

    public function testAnOperatorCanReadOrdersButNotChangeThem(): void
    {
        $id = $this->id($this->registerOrder());
        $this->role = Role::Operator;

        $this->request('GET', '/api/orders');
        $this->assertStatus(200);
        $this->request('GET', '/api/orders/'.$id);
        $this->assertStatus(200);

        $this->assertProblem(403, 'shared.forbidden', $this->registerOrder('PED-2'));
        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/orders/'.$id.'/allocate'));
        $this->assertProblem(403, 'shared.forbidden', $this->request('POST', '/api/orders/'.$id.'/cancel'));
    }

    public function testAnonymousRequestsAreRefused(): void
    {
        $this->assertProblem(401, 'shared.unauthenticated', $this->request('GET', '/api/orders', tenant: ''));
    }

    public function testTheOrdersAreInTheOpenApiDocument(): void
    {
        $paths = $this->openApiPaths();

        foreach (['/api/orders', '/api/orders/{id}', '/api/orders/{id}/allocate', '/api/orders/{id}/cancel'] as $path) {
            self::assertArrayHasKey($path, $paths);
        }
    }

    public function testExplainsTheErrorsInTheLanguageAsked(): void
    {
        $this->registerOrder('PED-2027-0001');
        $this->acceptLanguage = 'en';

        $problem = $this->registerOrder('PED-2027-0001');

        self::assertSame('There is already an order with reference PED-2027-0001.', $this->text($problem, 'detail'));

        $this->acceptLanguage = null;
        self::assertSame('Ya existe un pedido con la referencia PED-2027-0001.', $this->text($this->registerOrder('PED-2027-0001'), 'detail'));
    }
}
