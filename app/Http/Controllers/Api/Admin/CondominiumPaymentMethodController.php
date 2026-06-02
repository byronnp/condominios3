<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Billing\CondominiumPaymentMethod;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Services\Audit\AuditLogger;
use App\Transformers\CondominiumPaymentMethodTransformer;
use Illuminate\Http\Request;

class CondominiumPaymentMethodController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request, Condominium $condominium)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'payment_methods.manage');

        $methods = $condominium->paymentMethods()
            ->with(['condominium', 'paymentMethod'])
            ->when($request->has('enabled'), fn ($query) => $query->where('is_enabled', $request->boolean('enabled')))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $this->responder
            ->success($methods, [CondominiumPaymentMethodTransformer::class, 'transform'])
            ->message('Metodos de pago del condominio obtenidos correctamente.')
            ->respond();
    }

    public function store(Request $request, Condominium $condominium, AuditLogger $audit)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominium->id, 'payment_methods.manage');

        $data = $request->validate([
            'payment_method_id' => ['required', 'exists:catalog_items,id'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'instructions' => ['nullable', 'string'],
            'config' => ['nullable', 'array'],
        ]);

        $catalogItem = $this->paymentMethodCatalogItem($data['payment_method_id']);

        $method = CondominiumPaymentMethod::query()->updateOrCreate([
            'condominium_id' => $condominium->id,
            'payment_method_id' => $catalogItem->id,
        ], [
            'display_name' => $data['display_name'] ?? $catalogItem->name,
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => $data['sort_order'] ?? $catalogItem->sort_order,
            'instructions' => $data['instructions'] ?? null,
            'config' => $data['config'] ?? null,
        ]);

        $audit->record(
            action: $method->wasRecentlyCreated ? 'payment_method.created' : 'payment_method.updated',
            module: 'payment_methods',
            condominiumId: $condominium->id,
            user: $request->user(),
            entity: $method,
            description: 'Metodo de pago '.$method->display_name.' configurado para el condominio.',
            newValues: [
                'display_name' => $method->display_name,
                'is_enabled' => $method->is_enabled,
                'sort_order' => $method->sort_order,
                'has_config' => filled($method->config),
            ],
            request: $request,
        );

        return $this->responder
            ->success($method->load(['condominium', 'paymentMethod']), [CondominiumPaymentMethodTransformer::class, 'transform'], 201)
            ->message('Metodo de pago configurado correctamente.')
            ->respond();
    }

    public function update(Request $request, CondominiumPaymentMethod $condominiumPaymentMethod, AuditLogger $audit)
    {
        $this->abortUnlessCanManageCondominium($request->user(), $condominiumPaymentMethod->condominium_id, 'payment_methods.manage');

        $data = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $oldValues = [
            'display_name' => $condominiumPaymentMethod->display_name,
            'is_enabled' => $condominiumPaymentMethod->is_enabled,
            'sort_order' => $condominiumPaymentMethod->sort_order,
            'instructions' => $condominiumPaymentMethod->instructions,
            'has_config' => filled($condominiumPaymentMethod->config),
        ];

        $condominiumPaymentMethod->fill($data)->save();

        $audit->record(
            action: 'payment_method.updated',
            module: 'payment_methods',
            condominiumId: $condominiumPaymentMethod->condominium_id,
            user: $request->user(),
            entity: $condominiumPaymentMethod,
            description: 'Metodo de pago '.$condominiumPaymentMethod->display_name.' actualizado.',
            oldValues: $oldValues,
            newValues: [
                'display_name' => $condominiumPaymentMethod->display_name,
                'is_enabled' => $condominiumPaymentMethod->is_enabled,
                'sort_order' => $condominiumPaymentMethod->sort_order,
                'instructions' => $condominiumPaymentMethod->instructions,
                'has_config' => filled($condominiumPaymentMethod->config),
            ],
            request: $request,
        );

        return $this->responder
            ->success($condominiumPaymentMethod->load(['condominium', 'paymentMethod']), [CondominiumPaymentMethodTransformer::class, 'transform'])
            ->message('Metodo de pago actualizado correctamente.')
            ->respond();
    }

    private function paymentMethodCatalogItem(int $id): CatalogItem
    {
        return CatalogItem::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('catalog', fn ($query) => $query->where('code', 'payment_methods'))
            ->firstOrFail();
    }
}
