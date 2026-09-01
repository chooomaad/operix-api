<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\TenantFileService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certification extends Model
{
    use BelongsToTenant, SoftDeletes;

    // Colonnes réelles de la table `certifications` (cf. migration + schéma PG).
    protected $fillable = [
        'employee_id', 'person_type', 'person_id', 'titre', 'numero', 'organisme',
        'date_obtention', 'date_expiration', 'document', 'is_expired',
    ];

    protected $casts = [
        'date_obtention'  => 'date',
        'date_expiration' => 'date',
        'is_expired'      => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * URL (signée ou publique) du justificatif image/PDF stocké dans `document`.
     */
    public function getImageUrlAttribute(): ?string
    {
        return app(TenantFileService::class)->url($this->document);
    }

    protected static function booted(): void
    {
        static::saving(function (Certification $c) {
            // `is_expired` est dérivé de la date d'expiration (colonne réelle).
            $c->is_expired = $c->date_expiration ? $c->date_expiration->isPast() : false;
        });
    }
}
