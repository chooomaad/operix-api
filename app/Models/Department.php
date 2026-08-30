<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

use App\Models\Concerns\Auditable;
class Department extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = ['name', 'code', 'description'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
