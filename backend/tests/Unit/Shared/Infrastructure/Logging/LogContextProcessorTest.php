<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Logging;

use App\Shared\Application\CurrentTenant;
use App\Shared\Application\CurrentUser;
use App\Shared\Application\TenantNotResolved;
use App\Shared\Domain\TenantId;
use App\Shared\Infrastructure\Logging\LogContextProcessor;
use App\Shared\Infrastructure\Logging\RequestId;
use App\Tests\Unit\Support\Ids;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class LogContextProcessorTest extends TestCase
{
    public function testAddsTheRequestTheUserAndTheTenant(): void
    {
        $userId = Ids::next();
        $tenantId = Ids::next();
        $requestId = new RequestId();
        $requestId->adopt('request-12345');

        $record = new LogContextProcessor($requestId, $this->user($userId), $this->tenant($tenantId))($this->record());

        self::assertSame(
            ['request_id' => 'request-12345', 'user_id' => $userId, 'tenant_id' => $tenantId],
            $record->extra,
        );
    }

    public function testWithNobodySignedInItIsOnlyTheRequest(): void
    {
        $requestId = new RequestId();
        $requestId->adopt('request-12345');

        $record = new LogContextProcessor($requestId, $this->user(null), $this->tenant(null))($this->record());

        self::assertSame(['request_id' => 'request-12345'], $record->extra);
    }

    public function testKeepsWhatTheLineAlreadyHad(): void
    {
        $processor = new LogContextProcessor(new RequestId(), $this->user(null), $this->tenant(null));

        $record = $processor($this->record(['file' => 'x.php']));

        self::assertSame('x.php', $record->extra['file']);
        self::assertArrayHasKey('request_id', $record->extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function record(array $extra = []): LogRecord
    {
        return new LogRecord(new \DateTimeImmutable('2026-11-17 10:00:00'), 'app', Level::Info, 'Something happened', [], $extra);
    }

    private function user(?string $id): CurrentUser
    {
        return new readonly class($id) implements CurrentUser {
            public function __construct(private ?string $id)
            {
            }

            #[\Override]
            public function id(): string
            {
                return $this->id ?? throw TenantNotResolved::missing();
            }
        };
    }

    private function tenant(?string $id): CurrentTenant
    {
        return new readonly class($id) implements CurrentTenant {
            public function __construct(private ?string $id)
            {
            }

            #[\Override]
            public function id(): TenantId
            {
                return TenantId::fromString($this->id ?? throw TenantNotResolved::missing());
            }
        };
    }
}
