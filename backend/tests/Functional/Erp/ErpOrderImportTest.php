<?php

declare(strict_types=1);

namespace App\Tests\Functional\Erp;

use App\Catalog\Application\Contract\ProductCatalog;
use App\Erp\Application\ErpOrderTranslator;
use App\Erp\Domain\Exception\UnknownArticle;
use App\Erp\Infrastructure\File\CsvErpOrderFiles;
use App\Identity\Domain\Email;
use App\Identity\Domain\UserId;
use App\Identity\Infrastructure\Security\SecurityUser;
use App\Outbound\Application\Contract\IntakeResult;
use App\Outbound\Application\Intake\CommandOrderIntake;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\TenantId;
use App\Tests\Functional\Outbound\Api\OutboundApiTestCase;
use App\Tests\Unit\Support\Ids;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * A file that the simulated ERP wrote (tests/Support/erp/P2026000004.csv, copied from `make erp-up`) goes
 * through the reader, the translator and Outbound, and comes out as an order of the warehouse (ADR 0047).
 */
final class ErpOrderImportTest extends OutboundApiTestCase
{
    private const array ARTICLES = ['PV27-CAM-LIN-AZU-M', 'PV27-PAN-CHI-ARE-42', 'PV27-PAN-CHI-ARE-40', 'PV27-VES-MID-NEG-S'];

    /** @var array<string, string> the id of each product, by SKU */
    private array $products = [];

    public function testAnOrderFileOfTheErpBecomesAnOrderOfTheWarehouse(): void
    {
        $this->catalogue(self::ARTICLES);

        self::assertSame(IntakeResult::Registered, $this->importFile());

        $ids = array_column($this->request('GET', '/api/orders'), 'id');
        self::assertCount(1, $ids);
        self::assertIsString($ids[0]);
        $order = $this->request('GET', '/api/orders/'.$ids[0]);
        self::assertSame('P2026000004', $order['reference']);
        self::assertSame($this->warehouseId, $order['warehouseId']);
        self::assertSame('Marea Moda Bilbao Gran Vía', $order['customer']);
        self::assertSame(['street' => 'Gran Vía de Don Diego López de Haro 25', 'postalCode' => '48009', 'city' => 'Bilbao', 'countryCode' => 'ES'], $order['shipTo']);
        self::assertSame('normal', $order['priority']);
        // 14:00 in Madrid on the 8th of November is 13:00 UTC.
        self::assertSame('2026-11-08T13:00:00+00:00', new \DateTimeImmutable($this->text($order, 'cutOffAt'))->setTimezone(new \DateTimeZone('UTC'))->format('c'));
        $lines = $order['lines'];
        self::assertIsArray($lines);
        self::assertSame(
            [$this->products['PV27-CAM-LIN-AZU-M'], $this->products['PV27-PAN-CHI-ARE-42'], $this->products['PV27-PAN-CHI-ARE-40'], $this->products['PV27-VES-MID-NEG-S']],
            array_column($lines, 'productId'),
        );
        self::assertSame([5, 6, 4, 5], array_column($lines, 'orderedUnits'));
    }

    public function testTheSameFileTwiceRegistersTheOrderOnce(): void
    {
        $this->catalogue(self::ARTICLES);

        self::assertSame(IntakeResult::Registered, $this->importFile());
        self::assertSame(IntakeResult::AlreadyRegistered, $this->importFile());

        self::assertCount(1, $this->request('GET', '/api/orders'));
    }

    public function testAnArticleTheCatalogueDoesNotHaveStopsTheImportAndRegistersNothing(): void
    {
        $this->catalogue(\array_slice(self::ARTICLES, 0, 3));

        try {
            $this->importFile();
            self::fail('The order names an article that is not in the catalogue.');
        } catch (UnknownArticle $error) {
            self::assertSame(['sku' => 'PV27-VES-MID-NEG-S'], $error->errorParameters());
        }

        self::assertSame([], $this->request('GET', '/api/orders'));
    }

    /**
     * @param list<string> $skus
     */
    private function catalogue(array $skus): void
    {
        foreach ($skus as $sku) {
            $this->products[$sku] = $this->product($sku);
        }
    }

    private function importFile(): IntakeResult
    {
        $this->actAsTenant();
        $container = self::getContainer();
        $file = file_get_contents(__DIR__.'/../../Support/erp/P2026000004.csv');
        self::assertIsString($file);

        $orders = new CsvErpOrderFiles()->read($file);
        self::assertCount(1, $orders);

        $translator = new ErpOrderTranslator($container->get(ProductCatalog::class));
        $intake = new CommandOrderIntake($container->get(CommandBus::class));

        return $intake->register($translator->translate(TenantId::fromString($this->tenant), $this->warehouseId, $orders[0]));
    }

    /**
     * Makes the tenant of the test the one the services act for, as a request of its user would. A job that
     * runs without a request has to do the same (E08-03).
     */
    private function actAsTenant(): void
    {
        $user = new SecurityUser(UserId::fromString(Ids::next()), TenantId::fromString($this->tenant), Email::fromString('erp@example.test'), 'not-a-real-hash', ['ROLE_ADMIN'], true);
        self::getContainer()->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($user, 'api', $user->getRoles()));
    }
}
