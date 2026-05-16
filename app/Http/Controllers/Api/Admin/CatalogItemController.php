<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Catalog;
use App\Models\Catalog\CatalogItem;
use App\Transformers\CatalogItemTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogItemController extends Controller
{
    public function store(Request $request, Catalog $catalog): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede crear items globales.', 403)->respond();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('catalog_items', 'code')->where('catalog_id', $catalog->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->responder
            ->success($catalog->items()->create($data), [CatalogItemTransformer::class, 'transform'], 201)
            ->message('Item de catalogo creado correctamente.')
            ->respond();
    }

    public function update(Request $request, CatalogItem $catalogItem): JsonResponse
    {
        if (! $request->user()->isSeniorAdmin()) {
            return $this->responder->error('Solo el administrador senior puede editar items globales.', 403)->respond();
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:80', Rule::unique('catalog_items', 'code')->where('catalog_id', $catalogItem->catalog_id)->ignore($catalogItem)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $catalogItem->update($data);

        return $this->responder
            ->success($catalogItem, [CatalogItemTransformer::class, 'transform'])
            ->message('Item de catalogo actualizado correctamente.')
            ->respond();
    }
}
