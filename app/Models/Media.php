<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class Media extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'model_type', 'model_id', 'collection',
        'name', 'file_name', 'mime_type', 'disk', 'path', 'size', 'uploaded_by',
    ];

    protected $appends = ['url'];

    /**
     * URL signée à courte durée vers l'endpoint de téléchargement privé.
     * Générée uniquement lors de la sérialisation d'un média du tenant courant
     * (les réponses API sont déjà scopées par tenant) → pas d'URL publique devinable.
     */
    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute(
            'media.download',
            now()->addMinutes(30),
            ['media' => $this->id]
        );
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function model()
    {
        return $this->morphTo();
    }
}
