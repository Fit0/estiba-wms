<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Label;

use App\Outbound\Application\GetParcelLabel\ParcelLabel;
use App\Outbound\Domain\Sscc;

/**
 * The label as a one-page PDF of 4 by 6 inches. It is written by hand, since it only needs text in
 * the standard Helvetica fonts and filled rectangles, which keeps a PDF library out of the project.
 */
final readonly class PdfLabelRenderer
{
    private const int WIDTH = 288;

    private const int HEIGHT = 432;

    private const float MODULE = 1.5;

    private const int BAR_HEIGHT = 110;

    public function __construct(private LabelTexts $texts)
    {
    }

    public function render(ParcelLabel $label, string $locale): string
    {
        $texts = $this->texts->of($label, $locale);

        $content = implode("\n", [
            $this->text('F1', 9, 20, 404, $texts['shipTo']),
            $this->text('F2', 20, 20, 382, $label->customer),
            $this->text('F1', 13, 20, 360, $label->street),
            $this->text('F1', 13, 20, 342, \sprintf('%s %s (%s)', $label->postalCode, $label->city, $label->countryCode)),
            '20 328 248 1 re f',
            $this->text('F2', 16, 20, 300, $texts['order']),
            $this->text('F2', 16, 20, 276, $texts['parcel']),
            $this->text('F1', 12, 20, 254, $texts['weight']),
            $this->text('F1', 12, 20, 238, $texts['units']),
            $this->text('F1', 10, 20, 222, $texts['closedOn']),
            $this->bars(Gs1Code128::modulesFor(Sscc::fromString($label->sscc)), 70),
            $this->text('F2', 15, 20, 40, $texts['sscc']),
        ]);

        return $this->document($content);
    }

    private function text(string $font, int $size, int $x, int $y, string $text): string
    {
        return \sprintf('BT /%s %d Tf %d %d Td (%s) Tj ET', $font, $size, $x, $y, $this->escape($text));
    }

    /**
     * Draws every run of bars as one rectangle, centred on the page.
     */
    private function bars(string $modules, int $y): string
    {
        $left = (self::WIDTH - \strlen($modules) * self::MODULE) / 2;
        $rectangles = [];

        preg_match_all('/1+/', $modules, $runs, \PREG_OFFSET_CAPTURE);
        foreach ($runs[0] as [$run, $offset]) {
            $rectangles[] = \sprintf('%.2f %d %.2f %d re', $left + $offset * self::MODULE, $y, \strlen($run) * self::MODULE, self::BAR_HEIGHT);
        }

        return implode("\n", $rectangles)."\nf";
    }

    /**
     * The text in the encoding of the font (Windows-1252), with the characters that have a meaning
     * in a PDF string escaped.
     */
    private function escape(string $text): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);

        return addcslashes(false === $encoded ? '' : $encoded, '()\\');
    }

    private function document(string $content): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            \sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>', self::WIDTH, self::HEIGHT),
            \sprintf("<< /Length %d >>\nstream\n%s\nendstream", \strlen($content), $content),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= \sprintf("%d 0 obj\n%s\nendobj\n", $index + 1, $object);
        }

        $xref = \strlen($pdf);
        $pdf .= \sprintf("xref\n0 %d\n0000000000 65535 f \n", \count($objects) + 1);
        foreach ($offsets as $offset) {
            $pdf .= \sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.\sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", \count($objects) + 1, $xref);
    }
}
