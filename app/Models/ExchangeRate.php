<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = ['base_currency', 'quote_currency', 'rate'];

    protected $casts = [
        'rate' => 'decimal:6',
    ];
}
