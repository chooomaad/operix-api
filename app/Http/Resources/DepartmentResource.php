<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'code'            => $this->code,
            'description'     => $this->description,
            'manager'         => $this->manager,
            'employee_count'  => $this->whenCounted('employees'),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
