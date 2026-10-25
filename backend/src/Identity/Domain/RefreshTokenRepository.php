<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface RefreshTokenRepository
{
    /**
     * A refresh token is presented before anyone is authenticated, so this looks across all tenants.
     */
    public function findBySecretHash(string $secretHash): ?RefreshToken;

    /**
     * @return list<RefreshToken> the ones of the family that were not revoked yet
     */
    public function findLiveInFamily(string $familyId): array;

    public function save(RefreshToken $token): void;
}
