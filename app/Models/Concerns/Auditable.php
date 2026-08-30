<?php

namespace App\Models\Concerns;

/**
 * Marqueur : le modele est audite (creation / modification / suppression
 * journalisees par AuditObserver, enregistre dans AppServiceProvider).
 *
 * On N'enregistre PAS l'observer via `static::observe()` dans un boot de trait :
 * `observe()` fait `new static`, ce qui, pendant le boot du modele, provoque un
 * boot re-entrant (LogicException). L'enregistrement se fait donc dans le provider.
 */
trait Auditable
{
}
