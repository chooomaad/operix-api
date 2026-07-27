<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'code', 'description'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
