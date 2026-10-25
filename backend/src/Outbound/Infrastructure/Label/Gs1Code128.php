<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Label;

use App\Outbound\Domain\Sscc;

/**
 * The GS1-128 barcode of an SSCC: Code 128 in character set C, which packs two digits per symbol,
 * opened by FNC1 so scanners know the data is GS1, then the application identifier 00 and the 18
 * digits. Twenty digits fill ten symbols exactly, so no change of character set is needed.
 *
 * It answers the pattern of bars and spaces. Drawing it is up to the format of the label.
 */
final readonly class Gs1Code128
{
    private const int START_C = 105;

    private const int FNC1 = 102;

    private const int STOP = 106;

    private const string APPLICATION_IDENTIFIER = '00';

    /**
     * Widths of bar, space, bar, space… of each symbol value, from 0 to 106 (the stop).
     *
     * @var list<string>
     */
    private const array PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222',
        '122213', '122312', '132212', '221213', '221312', '231212',
        '112232', '122132', '122231', '113222', '123122', '123221',
        '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321',
        '112313', '132113', '132311', '211313', '231113', '231311',
        '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131',
        '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124',
        '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111',
        '241112', '134111', '111242', '121142', '121241', '114212',
        '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141',
        '411131', '211412', '211214', '211232', '2331112',
    ];

    /**
     * The symbol values of the barcode, from the start to the stop, check symbol included.
     *
     * @return list<int>
     */
    public static function symbolsFor(Sscc $sscc): array
    {
        $symbols = [self::START_C, self::FNC1];
        foreach (str_split(self::APPLICATION_IDENTIFIER.$sscc->value, 2) as $pair) {
            $symbols[] = (int) $pair;
        }

        $sum = self::START_C;
        foreach (\array_slice($symbols, 1) as $position => $value) {
            $sum += ($position + 1) * $value;
        }

        $symbols[] = $sum % 103;
        $symbols[] = self::STOP;

        return $symbols;
    }

    /**
     * The barcode as a run of modules, `1` for a bar and `0` for a space, without the quiet zones.
     */
    public static function modulesFor(Sscc $sscc): string
    {
        $modules = '';
        foreach (self::symbolsFor($sscc) as $symbol) {
            $isBar = true;
            foreach (str_split(self::PATTERNS[$symbol]) as $width) {
                $modules .= str_repeat($isBar ? '1' : '0', (int) $width);
                $isBar = !$isBar;
            }
        }

        return $modules;
    }

    /**
     * The widths of every symbol value, so a test can check the table itself.
     *
     * @return list<string>
     */
    public static function patterns(): array
    {
        return self::PATTERNS;
    }
}
