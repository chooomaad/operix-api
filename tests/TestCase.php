<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Garde-fou de sécurité multi-tenant.
     *
     * Empêche toute exécution de la suite (et donc de RefreshDatabase /
     * migrate:fresh / truncate) sur une base autre que la base de test dédiée.
     *
     * Ce contrôle s'exécute dans createApplication(), c.-à-d. AVANT setUpTraits()
     * et donc AVANT que RefreshDatabase ne touche la moindre table : si la
     * configuration ne correspond pas à l'environnement de test, on lève une
     * exception et aucune opération destructive n'est lancée sur dev/prod.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.default');
        $database   = $app['config']->get("database.connections.{$connection}.database");

        if ($app->environment() !== 'testing' || $database !== 'operix_test') {
            throw new \RuntimeException(
                "⛔ Tests interrompus par sécurité. "
                . "environnement='{$app->environment()}', base='{$database}'. "
                . "La suite ne peut s'exécuter que sur APP_ENV=testing et la base 'operix_test'. "
                . "Vérifiez que le fichier .env.testing existe et pointe vers operix_test "
                . "(voir .env.testing.example)."
            );
        }

        return $app;
    }
}
