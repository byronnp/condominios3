<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Catalog\CustomField;
use App\Models\Condominium\Condominium;
use App\Transformers\CustomFieldTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request, Condominium $condominium): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'can_manage_houses');

        return $this->responder
            ->success($condominium->customFields()->with('optionsCatalog')->orderBy('sort_order')->get(), [CustomFieldTransformer::class, 'transform'])
            ->message('Campos personalizados obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request, Condominium $condominium): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'can_manage_houses');

        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['user', 'house', 'payment', 'fee_charge'])],
            'field_key' => ['required', 'string', 'max:80', Rule::unique('custom_fields', 'field_key')->where('condominium_id', $condominium->id)->where('entity_type', $request->input('entity_type'))],
            'label' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(['text', 'number', 'date', 'boolean', 'catalog'])],
            'is_required' => ['sometimes', 'boolean'],
            'options_catalog_id' => ['nullable', 'exists:catalogs,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->responder
            ->success($condominium->customFields()->create($data), [CustomFieldTransformer::class, 'transform'], 201)
            ->message('Campo personalizado creado correctamente.')
            ->respond();
    }

    public function update(Request $request, CustomField $customField): JsonResponse
    {
        $this->abortUnlessCanManageCondominium($request->user(), $customField->condominium_id, 'can_manage_houses');

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'field_type' => ['sometimes', Rule::in(['text', 'number', 'date', 'boolean', 'catalog'])],
            'is_required' => ['sometimes', 'boolean'],
            'options_catalog_id' => ['nullable', 'exists:catalogs,id'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $customField->update($data);

        return $this->responder
            ->success($customField, [CustomFieldTransformer::class, 'transform'])
            ->message('Campo personalizado actualizado correctamente.')
            ->respond();
    }
}
