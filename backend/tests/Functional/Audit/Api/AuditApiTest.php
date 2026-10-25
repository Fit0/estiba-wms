<?php

declare(strict_types=1);

namespace App\Tests\Functional\Audit\Api;

use App\Identity\Domain\Role;
use App\Tests\Functional\Outbound\Api\OutboundApiTestCase;
use App\Tests\Unit\Support\Ids;

/**
 * The audit trail: every domain event that is published, with who did it (AUD-01 to AUD-03).
 */
final class AuditApiTest extends OutboundApiTestCase
{
    public function testAChangeLeavesItsEventsInTheTrail(): void
    {
        $order = $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]);

        $entries = $this->entriesOf('outbound.order_registered');

        self::assertCount(1, $entries);
        self::assertSame($this->id($order), $entries[0]['subjectId']);
        self::assertSame($this->id($order), $this->dataOf($entries[0])['orderId']);
        self::assertSame('PED-1', $this->dataOf($entries[0])['reference']);
        self::assertSame($this->tenant, $this->dataOf($entries[0])['tenantId']);
        self::assertNotEmpty($entries[0]['id']);
        self::assertNotNull($entries[0]['occurredAt']);
        self::assertNotNull($entries[0]['recordedAt']);
    }

    // AUD-02
    public function testTheActorIsTheUserWhoMadeTheRequest(): void
    {
        $me = $this->request('GET', '/api/me');
        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]);

        self::assertSame($this->id($me), $this->entriesOf('outbound.order_registered')[0]['actorId']);
    }

    public function testTheTrailTellsEveryEventOfARequest(): void
    {
        $this->stock($this->shirtId, $this->location($this->warehouseId, $this->zoneId, 'A-03-01', 'reserve'), 5);
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]));

        $types = array_column($this->request('GET', '/api/audit-log', query: ['subjectId' => $this->id($order)]), 'eventType');

        self::assertContains('outbound.order_registered', $types);
        self::assertContains('outbound.order_allocated', $types);
    }

    // AUD-02: a change that is refused leaves no trace, and one that is accepted always does
    public function testARefusedChangeLeavesNothingInTheTrail(): void
    {
        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]);

        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 5]]);
        $this->assertStatus(409);

        self::assertCount(1, $this->entriesOf('outbound.order_registered'));
    }

    public function testTheLatestEventsComeFirstAndAreSplitInPages(): void
    {
        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 1]]);
        $this->registerOrder('PED-2', [['productId' => $this->shirtId, 'units' => 1]]);
        $this->registerOrder('PED-3', [['productId' => $this->shirtId, 'units' => 1]]);

        $all = $this->entriesOf('outbound.order_registered');
        $first = $this->request('GET', '/api/audit-log', query: ['eventType' => 'outbound.order_registered', 'itemsPerPage' => 2]);
        $second = $this->request('GET', '/api/audit-log', query: ['eventType' => 'outbound.order_registered', 'itemsPerPage' => 2, 'page' => 2]);

        self::assertSame(['PED-3', 'PED-2', 'PED-1'], array_map(fn (array $entry): mixed => $this->dataOf($entry)['reference'], $all));
        self::assertCount(2, $first);
        self::assertCount(1, $second);
        self::assertSame($all[2]['id'], $this->entriesIn($second)[0]['id']);
    }

    public function testNarrowsTheTrailByEventSubjectActorAndTime(): void
    {
        $first = $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 1]]);
        $this->registerOrder('PED-2', [['productId' => $this->shirtId, 'units' => 1]]);
        $me = $this->id($this->request('GET', '/api/me'));

        self::assertCount(1, $this->request('GET', '/api/audit-log', query: ['subjectId' => $this->id($first), 'eventType' => 'outbound.order_registered']));
        self::assertCount(2, $this->request('GET', '/api/audit-log', query: ['actorId' => $me, 'eventType' => 'outbound.order_registered']));
        self::assertSame([], $this->request('GET', '/api/audit-log', query: ['actorId' => Ids::next()]));
        self::assertSame([], $this->request('GET', '/api/audit-log', query: ['eventType' => 'outbound.wave_created']));
        self::assertCount(2, $this->request('GET', '/api/audit-log', query: ['eventType' => 'outbound.order_registered', 'from' => new \DateTimeImmutable('-1 hour')->format(\DATE_ATOM)]));
        self::assertSame([], $this->request('GET', '/api/audit-log', query: ['from' => new \DateTimeImmutable('+1 hour')->format(\DATE_ATOM)]));
        self::assertSame([], $this->request('GET', '/api/audit-log', query: ['until' => new \DateTimeImmutable('-1 hour')->format(\DATE_ATOM)]));
        self::assertNotEmpty($this->request('GET', '/api/audit-log', query: ['until' => new \DateTimeImmutable('+1 hour')->format(\DATE_ATOM)]));
    }

    public function testRefusesADateThatIsNotADate(): void
    {
        $this->request('GET', '/api/audit-log', query: ['from' => 'yesterday']);

        $this->assertStatus(400);
    }

    public function testEachTenantSeesOnlyItsOwnTrail(): void
    {
        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 1]]);

        self::assertSame([], $this->request('GET', '/api/audit-log', tenant: Ids::next()));
        self::assertNotEmpty($this->request('GET', '/api/audit-log'));
    }

    public function testOnlySupervisorsAndAdminsReadTheTrail(): void
    {
        $this->role = Role::Operator;
        $this->request('GET', '/api/audit-log');
        $this->assertStatus(403);

        $this->role = Role::Supervisor;
        $this->request('GET', '/api/audit-log');
        $this->assertStatus(200);

        $this->role = Role::Admin;
        $this->request('GET', '/api/audit-log');
        $this->assertStatus(200);

        $this->request('GET', '/api/audit-log', tenant: '');
        $this->assertStatus(401);
    }

    // AUD-01
    public function testTheTrailCannotBeChangedThroughTheApi(): void
    {
        $this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 1]]);
        $id = $this->entriesOf('outbound.order_registered')[0]['id'];
        self::assertIsString($id);

        foreach (['POST' => '/api/audit-log', 'PUT' => '/api/audit-log/'.$id, 'PATCH' => '/api/audit-log/'.$id, 'DELETE' => '/api/audit-log/'.$id] as $method => $uri) {
            $this->request($method, $uri, []);
            self::assertContains($this->client->getResponse()->getStatusCode(), [404, 405], $method.' '.$uri);
        }
        self::assertCount(1, $this->entriesOf('outbound.order_registered'));
    }

    public function testTheOpenApiDocumentHasTheTrail(): void
    {
        self::assertArrayHasKey('/api/audit-log', $this->openApiPaths());
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function entriesOf(string $eventType): array
    {
        return $this->entriesIn($this->request('GET', '/api/audit-log', query: ['eventType' => $eventType, 'itemsPerPage' => 100]));
    }

    /**
     * @param array<array-key, mixed> $list
     *
     * @return list<array<array-key, mixed>>
     */
    private function entriesIn(array $list): array
    {
        $entries = [];
        foreach ($list as $entry) {
            self::assertIsArray($entry);
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return array<array-key, mixed>
     */
    private function dataOf(array $entry): array
    {
        $data = $entry['data'] ?? null;
        self::assertIsArray($data);

        return $data;
    }
}
