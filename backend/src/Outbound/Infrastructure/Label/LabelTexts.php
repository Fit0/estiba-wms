<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Label;

use App\Outbound\Application\GetParcelLabel\ParcelLabel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The words of a label, in the language asked for. The same texts go to every format.
 */
final readonly class LabelTexts
{
    private const string DOMAIN = 'labels';

    public function __construct(private TranslatorInterface $translator)
    {
    }

    /**
     * @return array{shipTo: string, order: string, parcel: string, units: string, weight: string, closedOn: string, sscc: string}
     */
    public function of(ParcelLabel $label, string $locale): array
    {
        return [
            'shipTo' => $this->translator->trans('label.ship_to', [], self::DOMAIN, $locale),
            'order' => $this->translator->trans('label.order', ['reference' => $label->orderReference], self::DOMAIN, $locale),
            'parcel' => $this->translator->trans('label.parcel', ['number' => $label->parcelNumber], self::DOMAIN, $locale),
            'units' => $this->translator->trans('label.units', ['units' => $label->units], self::DOMAIN, $locale),
            'weight' => $this->translator->trans('label.weight', ['grams' => $label->weightGrams], self::DOMAIN, $locale),
            'closedOn' => $this->translator->trans('label.closed_on', ['date' => $label->closedAt->format('Y-m-d')], self::DOMAIN, $locale),
            'sscc' => '(00) '.$label->sscc,
        ];
    }
}
