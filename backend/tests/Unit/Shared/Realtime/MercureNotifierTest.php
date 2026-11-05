<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Realtime;

use App\Shared\Application\Clock;
use App\Shared\Application\Notification;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Realtime\HmacJwt;
use App\Shared\Infrastructure\Realtime\MercureNotifier;
use App\Shared\Infrastructure\Realtime\SubscriberToken;
use App\Tests\Support\RecordingHubTransport;
use App\Tests\Unit\Support\Ids;
use PHPUnit\Framework\TestCase;

final class MercureNotifierTest extends TestCase
{
    private const string PUBLISHER_KEY = 'publisher-key-that-is-long-enough-for-hs256-01';

    private const string SUBSCRIBER_KEY = 'subscriber-key-that-is-long-enough-for-hs256-02';

    private \DateTimeImmutable $now;

    #[\Override]
    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-04T10:00:00+00:00');
    }

    public function testPublishesAPrivateUpdateOnTheTopicOfTheTenant(): void
    {
        $transport = $this->transport();
        $tenant = TenantId::fromString(Ids::next());

        $this->notifier($transport)->notify($tenant, new Notification('short-pick:l-1:2', 'outbound.short_pick_reported', ['missingUnits' => 3]));

        self::assertCount(1, $transport->published);
        $published = $transport->published[0];
        self::assertSame('http://hub.test/.well-known/mercure', $published['url']);
        self::assertSame('/tenants/'.$tenant->value.'/notifications', $published['form']['topic']);
        self::assertSame('on', $published['form']['private']);
        self::assertSame('short-pick:l-1:2', $published['form']['id']);
        self::assertSame(['type' => 'outbound.short_pick_reported', 'data' => ['missingUnits' => 3]], json_decode($published['form']['data'], true));
    }

    public function testTheHubIsToldWhoPublishes(): void
    {
        $transport = $this->transport();
        $tenant = TenantId::fromString(Ids::next());

        $this->notifier($transport)->notify($tenant, new Notification('n-1', 'outbound.pick_list_completed'));

        $claims = HmacJwt::claims($transport->published[0]['token'], self::PUBLISHER_KEY, $this->now);
        self::assertSame(['mercure' => ['publish' => ['/tenants/'.$tenant->value.'/notifications']], 'exp' => $this->now->getTimestamp() + 60], $claims);
        self::assertNull(HmacJwt::claims($transport->published[0]['token'], self::SUBSCRIBER_KEY, $this->now), 'The subscriber key cannot sign a publication.');
    }

    public function testASubscriberOfOneTenantCannotListenToAnother(): void
    {
        $mine = TenantId::fromString(Ids::next());
        $token = new SubscriberToken(self::SUBSCRIBER_KEY)->forTenant($mine, $this->now);

        $claims = HmacJwt::claims($token, self::SUBSCRIBER_KEY, $this->now);

        self::assertSame(['subscribe' => ['/tenants/'.$mine->value.'/notifications']], $claims['mercure'] ?? null);
        self::assertSame($this->now->getTimestamp() + 3600, $claims['exp'] ?? null);
        self::assertNull(HmacJwt::claims($token, self::PUBLISHER_KEY, $this->now), 'The publisher key does not vouch for a subscriber.');
    }

    public function testAHubThatRefusesIsAnErrorForTheWorkerToRetry(): void
    {
        $transport = $this->transport(fail: true);

        $this->expectException(\RuntimeException::class);

        $this->notifier($transport)->notify(TenantId::fromString(Ids::next()), new Notification('n-1', 'outbound.pick_list_completed'));
    }

    private function notifier(RecordingHubTransport $transport): MercureNotifier
    {
        $now = $this->now;
        $clock = new readonly class($now) implements Clock {
            public function __construct(private \DateTimeImmutable $now)
            {
            }

            #[\Override]
            public function now(): \DateTimeImmutable
            {
                return $this->now;
            }
        };

        return new MercureNotifier($transport, $clock, 'http://hub.test/.well-known/mercure', self::PUBLISHER_KEY);
    }

    private function transport(bool $fail = false): RecordingHubTransport
    {
        return new RecordingHubTransport($fail);
    }
}
