<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TenantFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(private TenantFileService $files)
    {
    }

    /**
     * Sert un fichier privé via URL signée (route `signed`).
     *
     * La signature couvre l'intégralité de l'URL, chemin inclus : elle ne peut être
     * ni forgée ni altérée. Le serveur n'émet de signature que pour les fichiers du
     * tenant courant (TenantFileService::url appelé sur des ressources déjà scopées).
     * On refuse tout chemin hors de l'espace `tenants/` par prudence (anti-traversée).
     */
    public function serve(Request $request): StreamedResponse
    {
        $path = (string) $request->query('path', '');

        abort_unless(str_starts_with($path, 'tenants/'), 404);

        $disk = $this->files->disk();
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}
