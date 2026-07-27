<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'model_type', 'model_id', 'collection',
        'name', 'file_name', 'mime_type', 'disk', 'path', 'size', 'uploaded_by',
    ];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
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
