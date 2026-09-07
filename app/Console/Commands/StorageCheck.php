<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vérifie que le disque médias (local ou S3/R2) est bien opérationnel :
 * écriture → lecture → suppression d'un fichier de test. À lancer depuis le shell
 * Render après configuration du stockage (ex. Cloudflare R2).
 *
 *   php artisan operix:storage-check
 */
class StorageCheck extends Command
{
    protected $signature = 'operix:storage-check';

    protected $description = 'Teste l\'écriture/lecture/suppression sur le disque médias configuré (R2/S3/local).';

    public function handle(): int
    {
        $diskName = config('operix.media_disk', 'tenant-media');
        $cfg      = config("filesystems.disks.{$diskName}", []);

        $this->line('');
        $this->line('  Disque       : ' . $diskName);
        $this->line('  Driver       : ' . ($cfg['driver'] ?? '?'));
        if (($cfg['driver'] ?? '') === 's3') {
            $this->line('  Bucket       : ' . ($cfg['bucket'] ?? '?'));
            $this->line('  Endpoint     : ' . ($cfg['endpoint'] ?? '(défaut AWS)'));
            $this->line('  Region       : ' . ($cfg['region'] ?? '?'));
            // On n'affiche JAMAIS le secret.
            $this->line('  Clé (aperçu) : ' . Str::limit((string) ($cfg['key'] ?? ''), 6, '…'));
        }
        $this->line('');

        $path    = 'healthcheck/' . Str::uuid() . '.txt';
        $content = 'operix-storage-check ' . now()->toIso8601String();
        $disk    = Storage::disk($diskName);

        try {
            $this->comment('→ Écriture…');
            $disk->put($path, $content);

            $this->comment('→ Existence…');
            if (! $disk->exists($path)) {
                $this->error('✗ Le fichier écrit est introuvable.');
                return self::FAILURE;
            }

            $this->comment('→ Lecture…');
            $read = $disk->get($path);
            if ($read !== $content) {
                $this->error('✗ Le contenu lu ne correspond pas à ce qui a été écrit.');
                $disk->delete($path);
                return self::FAILURE;
            }

            $this->comment('→ Suppression…');
            $disk->delete($path);

            $this->info('');
            $this->info('  ✅ Stockage opérationnel — écriture, lecture et suppression OK.');
            $this->info('');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('');
            $this->error('  ✗ Échec du test de stockage :');
            $this->error('    ' . $e->getMessage());
            $this->line('');
            $this->line('  Vérifie MEDIA_DISK_DRIVER=s3 et les variables MEDIA_S3_* (clé/secret/bucket/endpoint).');
            return self::FAILURE;
        }
    }
}
