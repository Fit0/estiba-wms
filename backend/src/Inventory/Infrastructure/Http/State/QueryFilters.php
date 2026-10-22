<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\State;

use App\Shared\Domain\PageRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the page and the text filters of a list request. A filter left out, or empty, is null.
 */
final readonly class QueryFilters
{
    private function __construct(private Request $request)
    {
    }

    /**
     * @param array<string, mixed> $context the context API Platform gives a provider
     */
    public static function of(array $context): self
    {
        $request = $context['request'] ?? null;
        \assert($request instanceof Request);

        return new self($request);
    }

    public function page(): PageRequest
    {
        return PageRequest::of(
            $this->request->query->getInt('page', 1),
            $this->request->query->getInt('itemsPerPage', PageRequest::DEFAULT_ITEMS_PER_PAGE),
        );
    }

    public function text(string $name): ?string
    {
        $value = trim($this->request->query->getString($name));

        return '' === $value ? null : $value;
    }
}
