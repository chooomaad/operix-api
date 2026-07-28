<?php

namespace App\Services;

use App\Support\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Point d'entrée UNIQUE pour tous les uploads de fichiers métier.
 *
 * Objectifs :
 *  - Isolation : chemins préfixés `tenants/{tenant_id}/{module}/...` sur un disque PRIVÉ.
 *    Le tenant est résolu côté serveur (TenantContext), jamais depuis le client.
 *  - Abstraction : le disque (config operix.media_disk) reste la seule dépendance
 *    infrastructure → bascule Cloudflare R2 / MinIO / S3 par simple config, sans toucher
 *    aux modules.
 *  - Évolutivité (Phase 3/prod) : en centralisant ici, on pourra brancher compression,
 *    resize, WebP, traitement en queue et quotas par tenant SANS réécrire les contrôleurs.
 */
class TenantFileService
{
    public function disk(): string
    {
        return config('operix.media_disk', 'tenant-media');
    }

    /**
     * Stocke un fichier sous `tenants/{tenant_id}/{module}/{uuid}.{ext}` (disque privé)
     * et retourne le chemin relatif à persister sur le modèle.
     */
    public function store(UploadedFile $file, string $module): string
    {
        $tenantId = $this->tenantId();
        $ext      = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';

        return $file->storeAs(
            "tenants/{$tenantId}/{$module}",
            Str::uuid() . '.' . $ext,
            $this->disk()
        );
    }

    /**
     * Remplace un fichier existant (supprime l'ancien) et retourne le nouveau chemin.
     */
    public function replace(?string $oldPath, UploadedFile $file, string $module): string
    {
        $this->delete($oldPath);
        return $this->store($file, $module);
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        // Fichiers historiques éventuels sur le disque public.
        if (! str_starts_with($path, 'tenants/')) {
            Storage::disk('public')->delete($path);
            return;
        }

        Storage::disk($this->disk())->delete($path);
    }

    /**
     * URL signée à courte durée vers l'endpoint privé de service de fichier.
     * Générée uniquement lors de la sérialisation d'une ressource du tenant courant.
     */
    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Rétro-compatibilité : anciens fichiers publics (`operix/...`) servis tels quels.
        if (! str_starts_with($path, 'tenants/')) {
            return Storage::disk('public')->url($path);
        }

        return URL::temporarySignedRoute(
            'files.serve',
            now()->addMinutes(config('operix.signed_url_ttl', 30)),
            ['path' => $path]
        );
    }

    private function tenantId(): int
    {
        $tenantId = app(TenantContext::class)->id() ?? auth()->user()?->tenant_id;

        abort_if($tenantId === null, 409, 'Contexte tenant requis pour stocker un fichier.');

        return (int) $tenantId;
    }
}
