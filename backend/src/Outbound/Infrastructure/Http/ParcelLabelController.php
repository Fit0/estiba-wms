<?php

declare(strict_types=1);

namespace App\Outbound\Infrastructure\Http;

use App\Outbound\Application\GetParcelLabel\GetParcelLabel;
use App\Outbound\Infrastructure\Label\PdfLabelRenderer;
use App\Outbound\Infrastructure\Label\ZplLabelRenderer;
use App\Shared\Application\Bus\QueryBus;
use App\Shared\Infrastructure\Http\RequestLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The label of a parcel, to print: `.pdf` to look at it or print it anywhere, `.zpl` to send it to a
 * Zebra printer. It is not part of the JSON resources because its answer is not JSON.
 */
#[Route('/api/shipments/{id}/parcels/{sscc}/label.{format}', name: 'outbound_parcel_label', requirements: ['format' => 'pdf|zpl'], methods: ['GET'])]
final readonly class ParcelLabelController
{
    public function __construct(
        private QueryBus $queryBus,
        private RequestLanguage $language,
        private PdfLabelRenderer $pdf,
        private ZplLabelRenderer $zpl,
    ) {
    }

    public function __invoke(Request $request, string $id, string $sscc, string $format): Response
    {
        $label = $this->queryBus->ask(new GetParcelLabel($id, $sscc));
        $locale = $this->language->of($request);

        $response = 'pdf' === $format
            ? new Response($this->pdf->render($label, $locale), Response::HTTP_OK, ['Content-Type' => 'application/pdf'])
            : new Response($this->zpl->render($label, $locale), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=utf-8']);

        $response->headers->set('Content-Disposition', \sprintf('inline; filename="%s.%s"', $label->sscc, $format));
        $response->headers->set('Content-Language', $locale);
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
