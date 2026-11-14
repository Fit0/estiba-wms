<?php

declare(strict_types=1);

namespace App\Tests\Functional\Erp;

use App\Erp\Application\ImportLogEntry;
use App\Erp\Application\ImportResult;
use App\Erp\Application\ShipmentConfirmation;
use App\Erp\Application\ShippedLine;
use App\Erp\Infrastructure\Persistence\DbalErpImportLog;
use App\Erp\Infrastructure\Persistence\DbalShipmentOutbox;
use App\Identity\Domain\Role;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Support\ApiTestCase;
use App\Tests\Unit\Support\Ids;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What the integration panel reads and does: the confirmations to the ERP and the files that were imported (ADR 0050).
 */
final class ErpIntegrationApiTest extends ApiTestCase
{
    private DbalShipmentOutbox $outbox;

    private DbalErpImportLog $log;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->connection();
        $this->outbox = new DbalShipmentOutbox($connection);
        $this->log = new DbalErpImportLog($connection);
        // The tenant and its user exist in the database before anything is written for them.
        $this->tokenFor($this->tenant, Role::Supervisor);
    }

    public function testListsTheConfirmationsLatestFirstWithWhatTheyDeliver(): void
    {
        $first = $this->queued('P1', '2026-11-09 10:00:00');
        $second = $this->queued('P2', '2026-11-09 11:00:00');
        $this->outbox->markSent($first, new \DateTimeImmutable('2026-11-09 10:01:00', new \DateTimeZone('UTC')));
        $this->outbox->scheduleRetry($second, 2, new \DateTimeImmutable('2026-11-09 11:03:00', new \DateTimeZone('UTC')), 'the ERP did not answer');

        $confirmations = $this->asSupervisor('GET', '/api/erp/confirmations');

        $this->assertStatus(200);
        self::assertCount(2, $confirmations);
        [$latest, $oldest] = $confirmations;
        self::assertIsArray($latest);
        self::assertIsArray($oldest);
        self::assertSame($second, $latest['shipmentId']);
        self::assertSame('P2', $latest['orderReference']);
        self::assertSame('pending', $latest['status']);
        self::assertSame(2, $latest['attempts']);
        self::assertSame('the ERP did not answer', $latest['lastError']);
        self::assertSame('SEUR', $latest['carrier']);
        self::assertSame(1, $latest['parcels']);
        self::assertSame([['sku' => 'PV27-CAM-LIN-AZU-M', 'units' => 4]], $latest['lines']);
        self::assertSame('sent', $oldest['status']);
        self::assertNotNull($oldest['sentAt']);
    }

    public function testFiltersTheConfirmationsByState(): void
    {
        $this->queued('P1', '2026-11-09 10:00:00');
        $failed = $this->queued('P2', '2026-11-09 11:00:00');
        $this->outbox->markFailed($failed, 1, 'refused');

        $onlyFailed = $this->asSupervisor('GET', '/api/erp/confirmations', query: ['status' => 'failed']);

        self::assertCount(1, $onlyFailed);
        self::assertIsArray($onlyFailed[0]);
        self::assertSame($failed, $onlyFailed[0]['shipmentId']);

        $this->asSupervisor('GET', '/api/erp/confirmations', query: ['status' => 'lost']);
        $this->assertStatus(400);
    }

    public function testAFailedConfirmationCanBeSentAgain(): void
    {
        $failed = $this->queued('P1', '2026-11-09 10:00:00');
        $this->outbox->markFailed($failed, 8, 'the ERP refused it');

        $retried = $this->asSupervisor('POST', '/api/erp/confirmations/'.$failed.'/retry');

        $this->assertStatus(200);
        self::assertSame('pending', $retried['status']);
        self::assertSame(0, $retried['attempts']);
        self::assertNull($retried['lastError']);
        self::assertCount(1, $this->outbox->due(new \DateTimeImmutable('+1 minute'), 10), 'It is due again.');
    }

    public function testOnlyAFailedConfirmationCanBeSentAgain(): void
    {
        $pending = $this->queued('P1', '2026-11-09 10:00:00');

        $problem = $this->asSupervisor('POST', '/api/erp/confirmations/'.$pending.'/retry');
        $this->assertProblem(404, 'erp.failed_confirmation_not_found', $problem);

        $this->asSupervisor('POST', '/api/erp/confirmations/'.Ids::next().'/retry');
        $this->assertStatus(404);
    }

    public function testATenantNeitherSeesNorRetriesTheConfirmationsOfAnother(): void
    {
        $theirs = $this->queued('P1', '2026-11-09 10:00:00', Ids::next());
        $this->outbox->markFailed($theirs, 8, 'refused');

        self::assertSame([], $this->asSupervisor('GET', '/api/erp/confirmations'));

        $this->asSupervisor('POST', '/api/erp/confirmations/'.$theirs.'/retry');
        $this->assertStatus(404);
    }

    public function testListsTheImportedFilesLatestFirst(): void
    {
        $this->imported('P1.csv', ImportResult::Processed, 1, '2026-11-09 10:00:00');
        $this->imported('P2.csv', ImportResult::Rejected, 0, '2026-11-09 11:00:00', 'erp.article_unknown', 'The ERP order names article X.');

        $imports = $this->asSupervisor('GET', '/api/erp/imports');

        $this->assertStatus(200);
        self::assertCount(2, $imports);
        self::assertIsArray($imports[0]);
        self::assertSame('P2.csv', $imports[0]['fileName']);
        self::assertSame('rejected', $imports[0]['result']);
        self::assertSame('erp.article_unknown', $imports[0]['errorCode']);
        self::assertSame('The ERP order names article X.', $imports[0]['message']);
        self::assertIsArray($imports[1]);
        self::assertSame('processed', $imports[1]['result']);
        self::assertSame(1, $imports[1]['orders']);
        self::assertNull($imports[1]['errorCode']);

        $rejected = $this->asSupervisor('GET', '/api/erp/imports', query: ['result' => 'rejected']);
        self::assertCount(1, $rejected);

        $this->asSupervisor('GET', '/api/erp/imports', query: ['result' => 'lost']);
        $this->assertStatus(400);
    }

    public function testATenantDoesNotSeeTheFilesOfAnother(): void
    {
        $this->imported('P1.csv', ImportResult::Processed, 1, '2026-11-09 10:00:00', tenant: Ids::next());

        self::assertSame([], $this->asSupervisor('GET', '/api/erp/imports'));
    }

    public function testAnOperatorCannotSeeNorDoAnything(): void
    {
        $failed = $this->queued('P1', '2026-11-09 10:00:00');
        $this->outbox->markFailed($failed, 8, 'refused');
        $this->role = Role::Operator;

        $this->request('GET', '/api/erp/confirmations');
        $this->assertStatus(403);
        $this->request('GET', '/api/erp/imports');
        $this->assertStatus(403);
        $this->request('POST', '/api/erp/confirmations/'.$failed.'/retry');
        $this->assertStatus(403);
    }

    public function testTheErrorOfARetryIsTranslated(): void
    {
        $this->acceptLanguage = 'en';

        $this->asSupervisor('POST', '/api/erp/confirmations/'.Ids::next().'/retry');

        $this->assertStatus(404);
        self::assertStringContainsString('no failed confirmation', $this->text($this->decodedResponse(), 'detail'));
    }

    /**
     * @param array<string, int|string> $query
     *
     * @return array<array-key, mixed>
     */
    private function asSupervisor(string $method, string $uri, array $query = []): array
    {
        $this->role = Role::Supervisor;

        return $this->request($method, $uri, query: $query);
    }

    private function queued(string $reference, string $at, ?string $tenant = null): string
    {
        $shipmentId = Ids::next();
        $moment = new \DateTimeImmutable($at, new \DateTimeZone('UTC'));
        $confirmation = new ShipmentConfirmation($reference, $moment, 'SEUR', null, 1, [new ShippedLine('PV27-CAM-LIN-AZU-M', 4)]);
        $this->outbox->enqueue(TenantId::fromString($tenant ?? $this->tenant), $shipmentId, $confirmation, $moment);

        return $shipmentId;
    }

    private function imported(string $file, ImportResult $result, int $orders, string $at, ?string $code = null, ?string $message = null, ?string $tenant = null): void
    {
        $this->log->record(TenantId::fromString($tenant ?? $this->tenant), new ImportLogEntry(Ids::next(), $file, $result, $orders, $code, $message, new \DateTimeImmutable($at, new \DateTimeZone('UTC'))));
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(EntityManagerInterface::class)->getConnection();
    }
}
