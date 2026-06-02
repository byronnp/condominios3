<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Transformers\CatalogItemTransformer;
use Illuminate\Http\Request;

class CondominiumCatalogItemController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request, Condominium $condominium)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'houses.manage');

        return $this->responder
            ->success($condominium->catalogItems()
                ->with('catalog')
                ->when($request->input('catalog'), fn ($query, $catalog) => $query->whereHas('catalog', fn ($catalogQuery) => $catalogQuery->where('code', $catalog)))
                ->when($request->boolean('enabled'), fn ($query) => $query->wherePivot('is_enabled', true))
                ->orderByPivot('sort_order')
                ->get(), [CatalogItemTransformer::class, 'transform'])
            ->message('Items de catalogo del condominio obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request, Condominium $condominium)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'houses.manage');

        $data = $request->validate([
            'catalog_item_id' => ['required', 'exists:catalog_items,id'],
            'custom_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $item = CatalogItem::query()->findOrFail($data['catalog_item_id']);
        $condominium->catalogItems()->syncWithoutDetaching([
            $item->id => [
                'custom_name' => $data['custom_name'] ?? null,
                'is_enabled' => $data['is_enabled'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ],
        ]);

        return $this->responder
            ->success($condominium->catalogItems()->where('catalog_items.id', $item->id)->first(), [CatalogItemTransformer::class, 'transform'], 201)
            ->message('Item configurado para el condominio.')
            ->respond();
    }
}
