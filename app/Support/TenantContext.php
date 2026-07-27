<?php

namespace App\Support;

/**
 * Contexte tenant de la requête courante (singleton applicatif).
 *
 * SÉCURITÉ : renseigné UNIQUEMENT côté serveur, par le middleware ResolveTenant,
 * à partir de l'utilisateur authentifié — jamais depuis une entrée client
 * (header, query string, corps JSON, formulaire).
 *
 * Le bypass du global scope est réservé aux opérations plateforme explicites
 * (contrôleurs /superadmin/*), via runWithoutScope().
 */
class TenantContext
{
    private ?int $tenantId = null;

    private bool $bypassed = false;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function has(): bool
    {
        return $this->tenantId !== null;
    }

    public function clear(): void
    {
        $this->tenantId = null;
    }

    public function bypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Exécute un callback avec le global scope tenant désactivé.
     * Réservé aux opérations plateforme (super admin) — bypass explicite et localisé.
     */
    public function runWithoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }
}
