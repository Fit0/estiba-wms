<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\OpenApi;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Lets API Platform write the document and then adds the documentation of
 * {@see OpenApiDocumentation} to the result.
 */
#[AsDecorator(decorates: 'api_platform.openapi.normalizer')]
final readonly class DocumentedOpenApiNormalizer implements NormalizerInterface
{
    private OpenApiDocumentation $documentation;

    public function __construct(
        #[AutowireDecorated]
        private NormalizerInterface $inner,
        #[Autowire('%kernel.project_dir%/config/api/operations.yaml')]
        string $operations,
    ) {
        $this->documentation = OpenApiDocumentation::fromFile($operations);
    }

    /**
     * @param array<mixed> $context
     */
    #[\Override]
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $document = $this->inner->normalize($data, $format, $context);

        return \is_array($document) ? $this->documentation->apply($document) : $document;
    }

    #[\Override]
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof OpenApi && $this->inner->supportsNormalization($data, $format, $context);
    }

    /**
     * @return array<class-string|'*'|'object'|string, bool|null>
     */
    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [OpenApi::class => true];
    }
}
