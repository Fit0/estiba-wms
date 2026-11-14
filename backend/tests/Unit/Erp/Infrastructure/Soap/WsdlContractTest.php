<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Infrastructure\Soap;

use PHPUnit\Framework\TestCase;

/**
 * The tests of the gateway read a copy of the WSDL of the simulated ERP (tests/Support/erp.wsdl), because the
 * backend container does not see the folder of the ERP. This test makes sure the copy is the original, so a change
 * to the contract of the ERP cannot go unnoticed by the tests of the gateway (ADR 0050).
 */
final class WsdlContractTest extends TestCase
{
    private const string ORIGINAL = __DIR__.'/../../../../../../erp-legacy/inc/erp.wsdl';

    private const string COPY = __DIR__.'/../../../../Support/erp.wsdl';

    public function testTheCopyOfTheWsdlIsTheWsdlOfTheErp(): void
    {
        if (!is_file(self::ORIGINAL)) {
            self::markTestSkipped('The folder of the ERP is not here: the backend container only sees backend/. CI runs this one.');
        }

        self::assertFileExists(self::COPY);
        self::assertSame(
            $this->normalised(self::ORIGINAL),
            $this->normalised(self::COPY),
            'backend/tests/Support/erp.wsdl differs from erp-legacy/inc/erp.wsdl: copy the second over the first.',
        );
    }

    public function testTheWsdlOffersTheOperationTheWarehouseCalls(): void
    {
        $wsdl = file_get_contents(self::COPY);
        self::assertIsString($wsdl);

        self::assertStringContainsString('ConfirmarEnvio', $wsdl);
    }

    /**
     * The text of the file without the differences that are not about the contract: line endings, the blank lines at the
     * end and the address of the service, which the ERP fills in when it serves the file and the copy sets to a test one.
     */
    private function normalised(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return rtrim((string) preg_replace('/location="[^"]*"/', 'location="..."', str_replace("\r\n", "\n", $contents)));
    }
}
