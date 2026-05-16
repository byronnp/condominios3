<?php

namespace App\Transformers;

use App\Models\Catalog\CatalogItem;

class CatalogItemTransformer
{
    public static function transform(CatalogItem $item): array
    {
        return [
            'id' => $item->id,
            'catalog_id' => $item->catalog_id,
            'code' => $item->code,
            'name' => $item->name,
            'description' => $item->description,
            'sort_order' => $item->sort_order,
            'is_active' => $item->is_active,
            'custom_name' => $item->pivot->custom_name ?? null,
            'is_enabled' => $item->pivot->is_enabled ?? null,
        ];
    }
}
