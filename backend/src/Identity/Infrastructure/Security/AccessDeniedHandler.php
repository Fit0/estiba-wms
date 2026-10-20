<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\Infrastructure\Http\HttpProblem;
use App\Shared\Infrastructure\Http\ProblemFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * An authenticated user asked for something its roles do not allow: 403, in the common problem
 * shape and the requested language. An anonymous request never gets here, it is a 401.
 */
final readonly class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private ProblemFactory $problems)
    {
    }

    #[\Override]
    public function handle(Request $request, AccessDeniedException $accessDeniedException): JsonResponse
    {
        return $this->problems->create($request, Response::HTTP_FORBIDDEN, HttpProblem::Forbidden->value, [], $accessDeniedException->getMessage());
    }
}
