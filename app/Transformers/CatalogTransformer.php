<?php

namespace App\Transformers;

use App\Models\Catalog\Catalog;

class CatalogTransformer
{
    public static function transform(Catalog $catalog): array
    {
        return [
            'id' => $catalog->id,
            'code' => $catalog->code,
            'name' => $catalog->name,
            'description' => $catalog->description,
            'is_active' => $catalog->is_active,
            'items' => $catalog->relationLoaded('items')
                ? $catalog->items->map(fn ($item) => CatalogItemTransformer::transform($item))->values()
                : null,
        ];
    }
}
