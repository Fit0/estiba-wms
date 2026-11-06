<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Realtime;

/**
 * Posts to the hub with PHP's own streams, so publishing needs no package: the Mercure protocol is
 * one form-encoded POST (ADR 0045).
 */
final readonly class StreamHubTransport implements HubTransport
{
    private const int TIMEOUT_SECONDS = 5;

    #[\Override]
    public function publish(string $hubUrl, array $form, string $bearerToken): void
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Authorization: Bearer {$bearerToken}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($form),
            'timeout' => self::TIMEOUT_SECONDS,
            'ignore_errors' => true,
        ]]);

        $headers = [];
        set_error_handler(static function (int $severity, string $message): bool {
            throw new \RuntimeException('The Mercure hub could not be reached: '.$message);
        });
        try {
            file_get_contents($hubUrl, false, $context);
            $headers = \function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : [];
        } finally {
            restore_error_handler();
        }

        $status = 1 === preg_match('#^HTTP/\S+ (\d{3})#', $headers[0] ?? '', $match) ? (int) $match[1] : 0;
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException(\sprintf('The Mercure hub answered %d.', $status));
        }
    }
}
