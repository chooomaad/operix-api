<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Configuration unique de l'organisation (TCN).
 * Instance unique — toujours récupérée via Organisation::first() ou ::sole().
 */
class Organisation extends Model
{
    protected $table = 'organisation';

    protected $fillable = [
        'name', 'short_name', 'logo',
        'primary_color', 'country', 'timezone', 'locale', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];
}
