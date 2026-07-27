<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kept as empty stub to avoid breaking any remaining references during migration.
 * All tenant logic has been moved to Organisation (single-row TCN config).
 * @deprecated Remove once all references are cleared.
 */
class Tenant extends Model
{
    protected $fillable = [];
}
