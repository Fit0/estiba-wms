<?php

declare(strict_types=1);

namespace App\Tests\Functional\Outbound\Label;

use App\Outbound\Application\GetParcelLabel\ParcelLabel;
use App\Outbound\Infrastructure\Label\PdfLabelRenderer;
use App\Outbound\Infrastructure\Label\ZplLabelRenderer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The label of a parcel in the two formats, with the texts in the language asked for.
 */
final class LabelRenderersTest extends KernelTestCase
{
    private const string SSCC = '084000000000000017';

    public function testZplHasTheWholeLabelBetweenStartAndEnd(): void
    {
        $zpl = $this->zpl()->render($this->label(), 'es');

        self::assertStringStartsWith("^XA\n", $zpl);
        self::assertStringEndsWith("^XZ\n", $zpl);
        self::assertStringContainsString('^CI28', $zpl, 'The text travels as UTF-8.');
        self::assertStringContainsString('^FDMarea Moda Zaragoza^FS', $zpl);
        self::assertStringContainsString('^FDCalle Mayor 12^FS', $zpl);
        self::assertStringContainsString('^FD50001 Zaragoza (ES)^FS', $zpl);
    }

    public function testZplPrintsTheSsccAsAGs1128BarcodeAndAsText(): void
    {
        $zpl = $this->zpl()->render($this->label(), 'es');

        self::assertStringContainsString('^BCN,260,N,N,N,N^FD>;>800'.self::SSCC.'^FS', $zpl, 'Code 128 in set C, FNC1, the identifier 00 and the SSCC.');
        self::assertStringContainsString('^FD(00) '.self::SSCC.'^FS', $zpl);
    }

    public function testTheTextsFollowTheLanguage(): void
    {
        $spanish = $this->zpl()->render($this->label(), 'es');
        $english = $this->zpl()->render($this->label(), 'en');

        self::assertStringContainsString('^FDDestinatario^FS', $spanish);
        self::assertStringContainsString('^FDPedido PED-2027-0001^FS', $spanish);
        self::assertStringContainsString('^FDBulto 2^FS', $spanish);
        self::assertStringContainsString('^FDPeso: 2.300 g^FS', $spanish);
        self::assertStringContainsString('^FD14 unidades^FS', $spanish);
        self::assertStringContainsString('^FDFecha: 2026-10-24^FS', $spanish);

        self::assertStringContainsString('^FDShip to^FS', $english);
        self::assertStringContainsString('^FDOrder PED-2027-0001^FS', $english);
        self::assertStringContainsString('^FDParcel 2^FS', $english);
        self::assertStringContainsString('^FDWeight: 2,300 g^FS', $english);
        self::assertStringContainsString('^FD14 units^FS', $english);
    }

    public function testAnythingThatStartsAZplCommandIsTakenOutOfTypedText(): void
    {
        $zpl = $this->zpl()->render($this->label(customer: "Moda ^XZ~HS\x07Sur"), 'es');

        self::assertStringContainsString('^FDModa  XZ HS Sur^FS', $zpl);
        self::assertSame(1, substr_count($zpl, '^XZ'), 'Only the real end of the label.');
    }

    public function testThePdfIsOnePageWithTheBarcodeAndTheTexts(): void
    {
        $pdf = $this->pdf()->render($this->label(), 'es');

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertStringContainsString('/MediaBox [0 0 288 432]', $pdf);
        self::assertStringContainsString('(Marea Moda Zaragoza) Tj', $pdf);
        self::assertStringContainsString('(\(00\) '.self::SSCC.') Tj', $pdf, 'Parentheses are escaped inside a PDF string.');
        self::assertGreaterThan(30, substr_count($pdf, ' re'), 'One rectangle per bar of the barcode.');
    }

    public function testThePdfTextIsInTheEncodingOfItsFont(): void
    {
        $pdf = $this->pdf()->render($this->label(customer: 'Pañería Núñez'), 'es');

        self::assertStringContainsString((string) iconv('UTF-8', 'Windows-1252', 'Pañería Núñez'), $pdf);
    }

    public function testThePdfCrossReferenceTableSaysWhereEveryObjectIs(): void
    {
        $pdf = $this->pdf()->render($this->label(), 'en');

        self::assertSame(1, preg_match('/startxref\n(\d+)\n%%EOF/', $pdf, $start));
        $table = substr($pdf, (int) $start[1]);
        self::assertStringStartsWith("xref\n0 7\n", $table);

        preg_match_all('/(\d{10}) 00000 n /', $table, $offsets);
        self::assertCount(6, $offsets[1]);
        foreach ($offsets[1] as $index => $offset) {
            self::assertStringStartsWith(\sprintf('%d 0 obj', $index + 1), substr($pdf, (int) $offset), \sprintf('Object %d.', $index + 1));
        }
    }

    public function testThePdfStreamHasTheLengthItSays(): void
    {
        $pdf = $this->pdf()->render($this->label(), 'es');

        self::assertSame(1, preg_match('/<< \/Length (\d+) >>\nstream\n(.*)\nendstream/s', $pdf, $stream));
        self::assertSame((int) $stream[1], \strlen($stream[2]));
    }

    private function label(string $customer = 'Marea Moda Zaragoza'): ParcelLabel
    {
        return new ParcelLabel(
            self::SSCC,
            'PED-2027-0001',
            $customer,
            'Calle Mayor 12',
            '50001',
            'Zaragoza',
            'ES',
            2,
            2300,
            14,
            new \DateTimeImmutable('2026-10-24T10:00:00+00:00'),
        );
    }

    private function zpl(): ZplLabelRenderer
    {
        self::bootKernel();

        return self::getContainer()->get(ZplLabelRenderer::class);
    }

    private function pdf(): PdfLabelRenderer
    {
        self::bootKernel();

        return self::getContainer()->get(PdfLabelRenderer::class);
    }
}
