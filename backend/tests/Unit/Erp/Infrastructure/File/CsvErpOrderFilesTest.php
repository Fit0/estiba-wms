<?php

declare(strict_types=1);

namespace App\Tests\Unit\Erp\Infrastructure\File;

use App\Erp\Domain\ErpOrder;
use App\Erp\Domain\ErpOrderLine;
use App\Erp\Domain\ErpUrgency;
use App\Erp\Infrastructure\File\CsvErpOrderFiles;
use App\Tests\Unit\Support\AssertsDomainErrors;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The files are what the ERP leaves: Latin-1, semicolons, CRLF, a header and a row for each line. The
 * samples here are written the way the ERP writes them (erp-legacy/inc/exportacion.php).
 */
final class CsvErpOrderFilesTest extends TestCase
{
    use AssertsDomainErrors;

    private const string HEADER = 'NUMERO_PEDIDO;FECHA_PEDIDO;CLIENTE;DIRECCION;CP;POBLACION;PAIS;URGENCIA;FECHA_LIMITE;LINEA;ARTICULO;UNIDADES';

    private const string ROW_1 = 'P2026000001;06/11/2026;Franquicia Costa del Sol;Avenida de Andalucía 118;29006;Málaga;ES;U;08/11/2026 14:00;1;PV27-PAN-CHI-ARE-44;4';

    private const string ROW_2 = 'P2026000001;06/11/2026;Franquicia Costa del Sol;Avenida de Andalucía 118;29006;Málaga;ES;U;08/11/2026 14:00;2;PV27-CAM-LIN-AZU-S;1';

    public function testReadsTheOrderWithItsLines(): void
    {
        $orders = new CsvErpOrderFiles()->read($this->file(self::HEADER, self::ROW_1, self::ROW_2));

        self::assertCount(1, $orders);
        $order = $orders[0];
        self::assertSame('P2026000001', $order->number);
        self::assertSame('2026-11-06', $order->takenOn->format('Y-m-d'));
        self::assertSame('Franquicia Costa del Sol', $order->customer);
        self::assertSame('Avenida de Andalucía 118', $order->street);
        self::assertSame('29006', $order->postalCode);
        self::assertSame('Málaga', $order->city);
        self::assertSame('ES', $order->countryCode);
        self::assertSame(ErpUrgency::Urgent, $order->urgency);
        self::assertCount(2, $order->lines);
        self::assertSame(1, $order->lines[0]->number);
        self::assertSame('PV27-PAN-CHI-ARE-44', $order->lines[0]->article);
        self::assertSame(4, $order->lines[0]->units);
        self::assertSame('PV27-CAM-LIN-AZU-S', $order->lines[1]->article);
    }

    public function testTheDueTimeIsSpainsTimeAndNotUtc(): void
    {
        $order = new CsvErpOrderFiles()->read($this->file(self::HEADER, self::ROW_1))[0];

        // 14:00 on the 8th of November in Madrid is winter time, one hour ahead of UTC.
        self::assertSame('2026-11-08T13:00:00+00:00', $order->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('c'));

        $summer = str_replace('08/11/2026 14:00', '08/07/2026 14:00', self::ROW_1);
        $order = new CsvErpOrderFiles()->read($this->file(self::HEADER, $summer))[0];
        self::assertSame('2026-07-08T12:00:00+00:00', $order->dueAt->setTimezone(new \DateTimeZone('UTC'))->format('c'));
    }

    public function testKeepsTheAccentsOfALatin1File(): void
    {
        // The bytes of "Málaga" in Latin-1: the á is one byte, 0xE1, and not the two of UTF-8.
        $bytes = $this->file(self::HEADER, self::ROW_1);
        self::assertStringContainsString("M\xE1laga", $bytes);

        self::assertSame('Málaga', new CsvErpOrderFiles()->read($bytes)[0]->city);
    }

    public function testReadsSeveralOrdersAndOrdersTheLinesByNumber(): void
    {
        $second = str_replace('P2026000001', 'P2026000002', self::ROW_1);

        $orders = new CsvErpOrderFiles()->read($this->file(self::HEADER, self::ROW_2, $second, self::ROW_1));

        self::assertSame(['P2026000001', 'P2026000002'], array_map(static fn (ErpOrder $order): string => $order->number, $orders));
        self::assertSame([1, 2], array_map(static fn (ErpOrderLine $line): int => $line->number, $orders[0]->lines));
    }

    public function testAFieldWithASemicolonOrQuotesIsReadWhole(): void
    {
        $row = str_replace('Franquicia Costa del Sol', '"Costa; ""del Sol"""', self::ROW_1);

        self::assertSame('Costa; "del Sol"', new CsvErpOrderFiles()->read($this->file(self::HEADER, $row))[0]->customer);
    }

    public function testIgnoresBlankLinesAndTheLastLineBreak(): void
    {
        $orders = new CsvErpOrderFiles()->read($this->file(self::HEADER, self::ROW_1, ''));

        self::assertCount(1, $orders);
    }

    public function testAFileWithOnlyTheHeaderHasNoOrders(): void
    {
        self::assertSame([], new CsvErpOrderFiles()->read($this->file(self::HEADER)));
    }

    public function testReadsALowercaseUrgencyAndCountry(): void
    {
        $row = str_replace(';ES;U;', ';es;n;', self::ROW_1);

        $order = new CsvErpOrderFiles()->read($this->file(self::HEADER, $row))[0];

        self::assertSame('ES', $order->countryCode);
        self::assertSame(ErpUrgency::Normal, $order->urgency);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function badFiles(): iterable
    {
        $header = self::HEADER;

        yield 'an empty file' => ['', 1, 'CABECERA'];
        yield 'a header of another file' => ["PEDIDO;LINEA\r\n1;1", 1, 'CABECERA'];
        yield 'a row with a missing column' => [$header."\r\nP1;06/11/2026;Cliente", 2, 'COLUMNAS'];
        yield 'no order number' => [$header."\r\n".str_replace('P2026000001', '', self::ROW_1), 2, 'NUMERO_PEDIDO'];
        yield 'an impossible date' => [$header."\r\n".str_replace('06/11/2026', '31/02/2026', self::ROW_1), 2, 'FECHA_PEDIDO'];
        yield 'a date written the other way round' => [$header."\r\n".str_replace('06/11/2026', '2026-11-06', self::ROW_1), 2, 'FECHA_PEDIDO'];
        yield 'a due date without a time' => [$header."\r\n".str_replace('08/11/2026 14:00', '08/11/2026', self::ROW_1), 2, 'FECHA_LIMITE'];
        yield 'an unknown urgency' => [$header."\r\n".str_replace(';U;', ';X;', self::ROW_1), 2, 'URGENCIA'];
        yield 'a country with three letters' => [$header."\r\n".str_replace(';ES;', ';ESP;', self::ROW_1), 2, 'PAIS'];
        yield 'no customer' => [$header."\r\n".str_replace('Franquicia Costa del Sol', '', self::ROW_1), 2, 'CLIENTE'];
        yield 'zero units' => [$header."\r\n".str_replace(';4', ';0', self::ROW_1), 2, 'UNIDADES'];
        yield 'units that are not a number' => [$header."\r\n".str_replace(';4', ';cuatro', self::ROW_1), 2, 'UNIDADES'];
        yield 'a line number of zero' => [$header."\r\n".str_replace(';1;PV27', ';0;PV27', self::ROW_1), 2, 'LINEA'];
        yield 'no article' => [$header."\r\n".str_replace('PV27-PAN-CHI-ARE-44', '', self::ROW_1), 2, 'ARTICULO'];
        yield 'two rows with the same line number' => [$header."\r\n".self::ROW_1."\r\n".self::ROW_1, 3, 'LINEA'];
        yield 'rows of one order that disagree' => [$header."\r\n".self::ROW_1."\r\n".str_replace('Málaga', 'Sevilla', self::ROW_2), 3, 'POBLACION'];
    }

    #[DataProvider('badFiles')]
    public function testRefusesAFileThatBreaksTheFormat(string $contents, int $line, string $field): void
    {
        self::assertDomainError('erp.order_file_malformed', ['line' => $line, 'field' => $field], static function () use ($contents): void {
            new CsvErpOrderFiles()->read(mb_convert_encoding($contents, 'ISO-8859-1', 'UTF-8'));
        });
    }

    /**
     * The bytes of a file as the ERP writes them: Latin-1, with CRLF at the end of every line.
     */
    private function file(string ...$lines): string
    {
        return mb_convert_encoding(implode("\r\n", $lines)."\r\n", 'ISO-8859-1', 'UTF-8');
    }
}
