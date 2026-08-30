<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuse les PIN triviaux.
 *
 * Un PIN reste court par nature : la longueur ne suffit pas à le protéger. On
 * écarte donc les valeurs qu'un attaquant essaie en premier — chiffre répété
 * (0000, 1111) et suite consécutive (1234, 4321). La règle ne s'applique qu'aux
 * PIN purement numériques : un PIN alphanumérique plus long n'est pas concerné.
 *
 * Volontairement mesurée : elle bloque l'évident, pas le raisonnable. Une
 * politique trop stricte pousse les agents à noter leur PIN, ce qui est pire.
 */
class StrongPin implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ctype_digit($value)) {
            // Non numérique : hors du périmètre de cette règle.
            return;
        }

        // Un seul chiffre répété : 0000, 111111…
        if (preg_match('/^(\d)\1+$/', $value)) {
            $fail('Ce code est trop simple. Évitez un chiffre répété.');
            return;
        }

        if ($this->isSequential($value)) {
            $fail('Ce code est trop simple. Évitez une suite comme 1234.');
        }
    }

    /**
     * Suite strictement croissante ou décroissante de pas 1 (1234, 4321).
     */
    private function isSequential(string $value): bool
    {
        $length = strlen($value);
        if ($length < 3) {
            return false;
        }

        $ascending  = true;
        $descending = true;

        for ($i = 1; $i < $length; $i++) {
            $delta = (int) $value[$i] - (int) $value[$i - 1];
            if ($delta !== 1)  { $ascending = false; }
            if ($delta !== -1) { $descending = false; }
        }

        return $ascending || $descending;
    }
}
