<?php

namespace App\Http;

/**
 * Usuario autenticado extraído del JWT (sub + role).
 */
final class RequestContext
{
    public function __construct(
        public readonly int $userId,
        public readonly string $role,
        public readonly string $token,
        public readonly ?string $email = null,
    ) {
    }
}
