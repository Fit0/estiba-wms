<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

/**
 * Sends one publication to the Mercure hub. A seam for the tests, which have no hub.
 */
interface HubTransport
{
    /**
     * @param array<string, string> $form        the fields of the publication
     * @param string                $bearerToken the publisher token
     *
     * @throws \RuntimeException when the hub did not accept it
     */
    public function publish(string $hubUrl, array $form, string $bearerToken): void;
}
