<?php

namespace App\Transformers;

use App\Models\Condominium\Condominium;

class CondominiumTransformer
{
    public static function transform(Condominium $condominium): array
    {
        return [
            'id' => $condominium->id,
            'name' => $condominium->name,
            'address' => $condominium->address,
            'is_active' => $condominium->is_active,
            'houses_count' => $condominium->houses_count ?? null,
        ];
    }
}
