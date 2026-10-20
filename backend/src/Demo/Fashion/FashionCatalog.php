<?php

declare(strict_types=1);

namespace App\Demo\Fashion;

use App\Catalog\Domain\Gtin;
use App\Catalog\Domain\UnitOfMeasure;

/**
 * The demo catalog of a fashion company: a spring-summer 2027 collection, in sizes and colours.
 *
 * Fashion has no lots and no expiry dates, but it has variants: a shirt in two colours and three
 * sizes is six products. The barcodes are made here with a valid GS1 check digit, from a fictional
 * company prefix, so they never clash with a real one.
 */
final class FashionCatalog
{
    private const string SEASON = 'PV27';

    /** A fictional GS1 company prefix inside Spain's range (84). */
    private const string COMPANY_PREFIX = '8412345';

    /**
     * The colours, as the feminine and the masculine form of the adjective.
     *
     * @var array<string, array{f: string, m: string}>
     */
    private const array COLOURS = [
        'BLA' => ['f' => 'blanca', 'm' => 'blanco'],
        'AZU' => ['f' => 'azul', 'm' => 'azul'],
        'ARE' => ['f' => 'arena', 'm' => 'arena'],
        'MAR' => ['f' => 'marina', 'm' => 'marino'],
        'NEG' => ['f' => 'negra', 'm' => 'negro'],
        'VER' => ['f' => 'verde', 'm' => 'verde'],
        'BEI' => ['f' => 'beige', 'm' => 'beige'],
        'GRI' => ['f' => 'gris', 'm' => 'gris'],
        'ROJ' => ['f' => 'roja', 'm' => 'rojo'],
        'MRR' => ['f' => 'marrón', 'm' => 'marrón'],
    ];

    /**
     * @var list<array{
     *     code: string, name: string, gender: 'f'|'m', sizes: list<string>, colours: list<string>,
     *     unit: UnitOfMeasure, length: int, width: int, height: int, grams: int, case: int, pallet: int,
     * }>
     */
    private const array MODELS = [
        ['code' => 'CAM-LIN', 'name' => 'Camisa de lino', 'gender' => 'f', 'sizes' => ['S', 'M', 'L'], 'colours' => ['BLA', 'AZU'], 'unit' => UnitOfMeasure::Each, 'length' => 350, 'width' => 250, 'height' => 40, 'grams' => 250, 'case' => 20, 'pallet' => 400],
        ['code' => 'PAN-CHI', 'name' => 'Pantalón chino', 'gender' => 'm', 'sizes' => ['38', '40', '42', '44'], 'colours' => ['ARE', 'MAR'], 'unit' => UnitOfMeasure::Each, 'length' => 380, 'width' => 280, 'height' => 70, 'grams' => 600, 'case' => 10, 'pallet' => 200],
        ['code' => 'VES-MID', 'name' => 'Vestido midi', 'gender' => 'm', 'sizes' => ['S', 'M', 'L'], 'colours' => ['NEG', 'VER'], 'unit' => UnitOfMeasure::Each, 'length' => 400, 'width' => 300, 'height' => 50, 'grams' => 380, 'case' => 10, 'pallet' => 200],
        ['code' => 'CHA-LIG', 'name' => 'Chaqueta ligera', 'gender' => 'f', 'sizes' => ['S', 'M', 'L'], 'colours' => ['BEI', 'NEG'], 'unit' => UnitOfMeasure::Each, 'length' => 450, 'width' => 330, 'height' => 90, 'grams' => 700, 'case' => 8, 'pallet' => 160],
        ['code' => 'JER-PUN', 'name' => 'Jersey de punto', 'gender' => 'm', 'sizes' => ['S', 'M', 'L'], 'colours' => ['GRI', 'ROJ'], 'unit' => UnitOfMeasure::Each, 'length' => 380, 'width' => 300, 'height' => 80, 'grams' => 450, 'case' => 12, 'pallet' => 240],
        ['code' => 'CIN-CUE', 'name' => 'Cinturón de cuero', 'gender' => 'm', 'sizes' => ['85', '95', '105'], 'colours' => ['MRR'], 'unit' => UnitOfMeasure::Each, 'length' => 300, 'width' => 100, 'height' => 30, 'grams' => 150, 'case' => 50, 'pallet' => 1000],
        ['code' => 'ZAP-LON', 'name' => 'Zapatilla de lona', 'gender' => 'f', 'sizes' => ['38', '40', '42', '44'], 'colours' => ['BLA'], 'unit' => UnitOfMeasure::Pair, 'length' => 330, 'width' => 200, 'height' => 120, 'grams' => 800, 'case' => 6, 'pallet' => 120],
    ];

    /**
     * @return list<DemoProduct>
     */
    public function products(): array
    {
        $products = [];
        $reference = 0;

        foreach (self::MODELS as $model) {
            foreach ($model['colours'] as $colour) {
                foreach ($model['sizes'] as $size) {
                    $products[] = new DemoProduct(
                        \sprintf('%s-%s-%s-%s', self::SEASON, $model['code'], $colour, $size),
                        \sprintf('%s %s, talla %s', $model['name'], self::COLOURS[$colour][$model['gender']], $size),
                        $model['unit'],
                        $model['length'],
                        $model['width'],
                        $model['height'],
                        $model['grams'],
                        $this->gtin(++$reference),
                        $model['case'],
                        $model['pallet'],
                    );
                }
            }
        }

        return $products;
    }

    /**
     * A GTIN-13: the company prefix, a five digit item reference and the GS1 check digit.
     */
    private function gtin(int $reference): string
    {
        $digits = self::COMPANY_PREFIX.\sprintf('%05d', $reference);

        return $digits.Gtin::checkDigitFor($digits);
    }
}
