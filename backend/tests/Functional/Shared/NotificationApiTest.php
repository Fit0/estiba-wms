<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shared;

use App\Identity\Domain\Role;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Realtime\HmacJwt;
use App\Tests\Functional\Outbound\Api\ShipmentApiTestCase;
use App\Tests\Support\RecordingNotifier;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * The live notifications: what the policies announce, and who is let in to hear it (ADR 0045).
 */
final class NotificationApiTest extends ShipmentApiTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RecordingNotifier::reset();
    }

    public function testCompletingAPickListAnnouncesIt(): void
    {
        $this->pickedOrder();

        $sent = RecordingNotifier::sent();

        self::assertCount(1, $sent);
        self::assertSame($this->tenant, $sent[0]['tenant']);
        self::assertSame('outbound.pick_list_completed', $sent[0]['notification']->type);
        self::assertSame('pick-list-completed:'.$sent[0]['notification']->data['pickListId'], $sent[0]['notification']->id);
        self::assertArrayHasKey('waveId', $sent[0]['notification']->data);
    }

    public function testAShortPickAnnouncesTheUnitsNotFound(): void
    {
        $this->stock($this->shirtId, $this->shelf, 10, received: '2026-09-01');
        $order = $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));
        $wave = $this->request('POST', '/api/waves', ['orderIds' => [$this->id($order)]]);
        $released = $this->request('POST', '/api/waves/'.$this->id($wave).'/release');
        $listId = $this->listOf($released, 'pickListIds')[0] ?? null;
        self::assertIsString($listId);
        $this->request('POST', '/api/pick-lists/'.$listId.'/start');
        $this->request('POST', '/api/pick-lists/'.$listId.'/lines/1/confirm', ['location' => 'A-03-01', 'gtin' => self::SHIRT_GTIN, 'units' => 7, 'reason' => 'Solo había siete']);
        $this->assertStatus(200);

        $types = array_map(static fn (array $sent): string => $sent['notification']->type, RecordingNotifier::sent());

        self::assertEqualsCanonicalizing(['outbound.short_pick_reported', 'outbound.pick_list_completed'], $types);
        $short = array_values(array_filter(RecordingNotifier::sent(), static fn (array $sent): bool => 'outbound.short_pick_reported' === $sent['notification']->type))[0];
        self::assertSame(3, $short['notification']->data['missingUnits']);
        self::assertSame(1, $short['notification']->data['position']);
        self::assertSame('short-pick:'.$listId.':1', $short['notification']->id);
        self::assertSame($this->tenant, $short['tenant']);
    }

    public function testRegisteringAndAllocatingAnOrderAnnouncesNothing(): void
    {
        $this->stock($this->shirtId, $this->shelf, 20, received: '2026-09-01');
        $this->allocate($this->registerOrder('PED-1', [['productId' => $this->shirtId, 'units' => 10]]));

        self::assertSame([], RecordingNotifier::sent(), 'Registering and allocating an order is not news for the floor.');
    }

    public function testASupervisorIsGivenAccessToTheTopicOfItsTenant(): void
    {
        $access = $this->request('GET', '/api/notifications/access');

        $this->assertStatus(200);
        self::assertSame('/tenants/'.$this->tenant.'/notifications', $access['topic']);
        self::assertSame('/.well-known/mercure', $access['hub']);
        self::assertNotEmpty($access['expiresAt']);

        $cookie = $this->cookie();
        self::assertTrue($cookie->isHttpOnly(), 'The page cannot read the token.');
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/.well-known/mercure', $cookie->getPath(), 'Only the hub receives it.');

        $claims = HmacJwt::claims((string) $cookie->getValue(), $this->subscriberKey(), new \DateTimeImmutable());
        self::assertNotNull($claims);
        self::assertSame(['subscribe' => ['/tenants/'.$this->tenant.'/notifications']], $claims['mercure'] ?? null);
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testAnotherTenantGetsItsOwnTopicAndNeverThisOne(): void
    {
        $other = Ids::next();

        $access = $this->request('GET', '/api/notifications/access', tenant: $other);

        self::assertSame('/tenants/'.$other.'/notifications', $access['topic']);
        $claims = HmacJwt::claims((string) $this->cookie()->getValue(), $this->subscriberKey(), new \DateTimeImmutable());
        self::assertSame(['subscribe' => ['/tenants/'.$other.'/notifications']], $claims['mercure'] ?? null);
        self::assertNotSame(TenantId::fromString($this->tenant)->value, $other);
    }

    public function testAnOperatorIsNotLetIn(): void
    {
        $this->role = Role::Operator;

        $this->request('GET', '/api/notifications/access');

        $this->assertStatus(403);
    }

    public function testNeedsASession(): void
    {
        $this->request('GET', '/api/notifications/access', tenant: '');

        $this->assertStatus(401);
    }

    private function subscriberKey(): string
    {
        $key = $_SERVER['MERCURE_SUBSCRIBER_JWT_KEY'] ?? null;
        self::assertIsString($key);

        return $key;
    }

    private function cookie(): Cookie
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ('mercureAuthorization' === $cookie->getName()) {
                return $cookie;
            }
        }

        self::fail('The answer sets no mercureAuthorization cookie.');
    }
}
