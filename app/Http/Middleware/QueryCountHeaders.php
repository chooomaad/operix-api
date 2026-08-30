<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Instrumentation de performance — HORS PRODUCTION UNIQUEMENT.
 *
 * Ajoute a chaque reponse trois en-tetes de diagnostic :
 *   X-Query-Count : nombre de requetes SQL executees pendant la requete HTTP,
 *   X-DB-Ms       : temps cumule passe dans PostgreSQL (ms),
 *   X-Duration-Ms : duree totale de traitement (ms).
 *
 * C'est le detecteur de N+1 : un endpoint de liste dont X-Query-Count croit avec
 * le nombre de lignes trahit une requete par ligne. Volontairement inactif en
 * production — aucune fuite d'information ni cout de mesure chez le client final.
 */
class QueryCountHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('production')) {
            return $next($request);
        }

        $count = 0;
        $dbMicros = 0.0;
        DB::listen(function ($query) use (&$count, &$dbMicros) {
            $count++;
            $dbMicros += $query->time; // deja en millisecondes
        });

        $start = microtime(true);
        $response = $next($request);
        $durationMs = (microtime(true) - $start) * 1000;

        $response->headers->set('X-Query-Count', (string) $count);
        $response->headers->set('X-DB-Ms', number_format($dbMicros, 1, '.', ''));
        $response->headers->set('X-Duration-Ms', number_format($durationMs, 1, '.', ''));

        return $response;
    }
}
