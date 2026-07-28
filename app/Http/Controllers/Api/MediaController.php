<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /** Disque privé (local en dev, MinIO/S3 en prod) — jamais servi publiquement. */
    private const DISK = 'tenant-media';

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file'       => ['required', 'file', 'max:20480'],
            'model_type' => ['required', 'string'],
            'model_id'   => ['required', 'integer'],
            'collection' => ['nullable', 'string', 'max:100'],
        ]);

        $file     = $request->file('file');
        $tenantId = $request->user()->tenant_id;

        // Chemin préfixé par tenant sur un disque PRIVÉ : aucune URL publique devinable.
        $path = $file->storeAs(
            "tenants/{$tenantId}/{$request->model_type}",
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            self::DISK
        );

        // tenant_id auto-affecté par le trait BelongsToTenant (contexte serveur).
        $media = Media::create([
            'model_type'  => $request->model_type,
            'model_id'    => $request->model_id,
            'collection'  => $request->input('collection', 'default'),
            'name'        => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name'   => $file->getClientOriginalName(),
            'mime_type'   => $file->getMimeType(),
            'disk'        => self::DISK,
            'path'        => $path,
            'size'        => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json($media, 201);
    }

    public function show(int $id): JsonResponse
    {
        // Scopé par tenant (global scope) → 404 pour un média d'une autre entreprise.
        return response()->json(Media::findOrFail($id));
    }

    /**
     * Téléchargement/affichage via URL signée à courte durée.
     *
     * La route est `signed` (pas d'auth Sanctum) pour servir les <img>. La signature,
     * émise côté serveur uniquement pour un média du tenant courant (attribut `url`),
     * vaut autorisation : on lit donc le média hors global scope tenant.
     */
    public function download(int $media): StreamedResponse
    {
        $media = app(TenantContext::class)->runWithoutScope(
            fn () => Media::findOrFail($media)
        );

        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->response(
            $media->path,
            $media->file_name,
            ['Content-Type' => $media->mime_type]
        );
    }

    public function destroy(int $id): JsonResponse
    {
        // Scopé par tenant → un utilisateur ne peut supprimer que les médias de son entreprise.
        $media = Media::findOrFail($id);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'Fichier supprimé.']);
    }
}
