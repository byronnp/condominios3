<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Api\Admin\Catalog\UpdateCatalogItemRequest;
use App\Models\Catalog\Catalog;
use App\Models\Catalog\CatalogItem;
use App\Transformers\CatalogItemTransformer;
use Illuminate\Http\JsonResponse;

class CatalogItemController extends Controller
{
    public function store(StoreCatalogItemRequest $request, Catalog $catalog): JsonResponse
    {
        if (! $request->user()->hasPermission('catalogs.manage')) {
            return $this->responder->error('Solo el administrador senior puede crear items globales.', 403)->respond();
        }

        return $this->responder
            ->success($catalog->items()->create($request->validated()), [CatalogItemTransformer::class, 'transform'], 201)
            ->message('Item de catalogo creado correctamente.')
            ->respond();
    }

    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem): JsonResponse
    {
        if (! $request->user()->hasPermission('catalogs.manage')) {
            return $this->responder->error('Solo el administrador senior puede editar items globales.', 403)->respond();
        }

        $catalogItem->update($request->validated());

        return $this->responder
            ->success($catalogItem, [CatalogItemTransformer::class, 'transform'])
            ->message('Item de catalogo actualizado correctamente.')
            ->respond();
    }
}
