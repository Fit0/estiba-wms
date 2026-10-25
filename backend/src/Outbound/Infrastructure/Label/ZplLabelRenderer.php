<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Label;

use App\Outbound\Application\GetParcelLabel\ParcelLabel;

/**
 * The label as ZPL II for a Zebra printer at 203 dpi, 4 by 6 inches (812 by 1218 dots). The text is
 * sent as UTF-8 (`^CI28`), and the barcode is Code 128 in set C opened by FNC1 (`>;` and `>8`), which
 * is how a GS1-128 is printed.
 */
final readonly class ZplLabelRenderer
{
    public function __construct(private LabelTexts $texts)
    {
    }

    public function render(ParcelLabel $label, string $locale): string
    {
        $texts = $this->texts->of($label, $locale);

        return implode("\n", [
            '^XA',
            '^CI28',
            '^PW812',
            '^LL1218',
            $this->text(40, 40, 28, $texts['shipTo']),
            $this->text(40, 80, 64, $label->customer),
            $this->text(40, 160, 44, $label->street),
            $this->text(40, 215, 44, \sprintf('%s %s (%s)', $label->postalCode, $label->city, $label->countryCode)),
            '^FO40,290^GB732,3,3^FS',
            $this->text(40, 320, 52, $texts['order']),
            $this->text(40, 395, 52, $texts['parcel']),
            $this->text(40, 470, 44, $texts['weight']),
            $this->text(40, 525, 44, $texts['units']),
            $this->text(40, 580, 36, $texts['closedOn']),
            '^FO40,650^GB732,3,3^FS',
            '^BY3,3,260',
            '^FO100,700^BCN,260,N,N,N,N^FD>;>800'.$label->sscc.'^FS',
            $this->text(40, 1000, 56, $texts['sscc']),
            '^XZ',
            '',
        ]);
    }

    private function text(int $x, int $y, int $height, string $text): string
    {
        return \sprintf('^FO%d,%d^A0N,%d,%d^FD%s^FS', $x, $y, $height, $height, $this->safe($text));
    }

    /**
     * `^` and `~` start a command in ZPL, so they cannot travel inside a field. Control characters
     * are dropped too, since a typed name should never be able to break the label.
     */
    private function safe(string $text): string
    {
        return trim((string) preg_replace('/[\^~\x00-\x1f\x7f]/u', ' ', $text));
    }
}
