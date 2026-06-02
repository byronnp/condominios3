<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Catalog;
use App\Transformers\CatalogTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->responder
            ->success(Catalog::query()->with('items')->orderBy('name')->get(), [CatalogTransformer::class, 'transform'])
            ->message('Catalogos obtenidos correctamente.')
            ->respond();
    }

    public function show(Catalog $catalog): JsonResponse
    {
        return $this->responder
            ->success($catalog->load('items'), [CatalogTransformer::class, 'transform'])
            ->message('Catalogo obtenido correctamente.')
            ->respond();
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('catalogs.manage')) {
            return $this->responder->error('Solo el administrador senior puede crear catalogos globales.', 403)->respond();
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:catalogs,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->responder
            ->success(Catalog::query()->create($data), [CatalogTransformer::class, 'transform'], 201)
            ->message('Catalogo creado correctamente.')
            ->respond();
    }

    public function update(Request $request, Catalog $catalog): JsonResponse
    {
        if (! $request->user()->hasPermission('catalogs.manage')) {
            return $this->responder->error('Solo el administrador senior puede editar catalogos globales.', 403)->respond();
        }

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:80', Rule::unique('catalogs', 'code')->ignore($catalog)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $catalog->update($data);

        return $this->responder
            ->success($catalog, [CatalogTransformer::class, 'transform'])
            ->message('Catalogo actualizado correctamente.')
            ->respond();
    }
}
