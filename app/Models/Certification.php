<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Certification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'employee_id', 'type', 'numero', 'organisme',
        'date_obtention', 'date_expiration',
        'statut', 'fichier', 'observations',
    ];

    protected $casts = [
        'date_obtention'  => 'date',
        'date_expiration' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Certification $c) {
            if ($c->date_expiration) {
                $c->statut = $c->date_expiration->isPast() ? 'expired' : 'valid';
            }
        });
    }
}
