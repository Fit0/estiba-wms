<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Realtime;

use App\Shared\Infrastructure\Realtime\HmacJwt;
use PHPUnit\Framework\TestCase;

final class HmacJwtTest extends TestCase
{
    private const string KEY = 'a-key-that-is-long-enough-for-hs256-0123456789';

    private \DateTimeImmutable $now;

    #[\Override]
    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-04T10:00:00+00:00');
    }

    public function testAHubCanReadWhatWasSigned(): void
    {
        $token = HmacJwt::sign(['mercure' => ['subscribe' => ['/tenants/t/notifications']], 'exp' => $this->now->getTimestamp() + 60], self::KEY);

        self::assertSame(
            ['mercure' => ['subscribe' => ['/tenants/t/notifications']], 'exp' => $this->now->getTimestamp() + 60],
            HmacJwt::claims($token, self::KEY, $this->now),
        );
    }

    public function testHasTheShapeOfAJwtWithoutPaddingOrPlusSigns(): void
    {
        $token = HmacJwt::sign(['n' => '???>>>'], self::KEY);

        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', $token);
        $header = json_decode((string) base64_decode(strtr(explode('.', $token)[0], '-_', '+/'), true), true);
        self::assertSame(['alg' => 'HS256', 'typ' => 'JWT'], $header);
    }

    public function testRefusesAnotherKey(): void
    {
        $token = HmacJwt::sign(['exp' => $this->now->getTimestamp() + 60], self::KEY);

        self::assertNull(HmacJwt::claims($token, 'another-key-that-is-long-enough-for-hs256-9876', $this->now));
    }

    public function testRefusesATamperedPayload(): void
    {
        [$header, , $signature] = explode('.', HmacJwt::sign(['tenant' => 'a'], self::KEY));
        $forged = rtrim(strtr(base64_encode('{"tenant":"b"}'), '+/', '-_'), '=');

        self::assertNull(HmacJwt::claims($header.'.'.$forged.'.'.$signature, self::KEY, $this->now));
    }

    public function testRefusesAnExpiredToken(): void
    {
        $token = HmacJwt::sign(['exp' => $this->now->getTimestamp()], self::KEY);

        self::assertNull(HmacJwt::claims($token, self::KEY, $this->now));
        self::assertNotNull(HmacJwt::claims($token, self::KEY, $this->now->modify('-1 second')));
    }

    public function testRefusesWhatIsNotAToken(): void
    {
        self::assertNull(HmacJwt::claims('not-a-token', self::KEY, $this->now));
        self::assertNull(HmacJwt::claims('a.b', self::KEY, $this->now));
    }
}
