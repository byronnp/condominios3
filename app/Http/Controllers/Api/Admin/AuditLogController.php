<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Admin\Concerns\AuthorizesCondominiumAccess;
use App\Http\Controllers\Controller;
use App\Models\Audit\AuditLog;
use App\Transformers\AuditLogTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use AuthorizesCondominiumAccess;

    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->with(['condominium', 'user'])
            ->when(! $request->user()->isSeniorAdmin(), function ($query) use ($request): void {
                $query->whereIn('condominium_id', $this->managedCondominiumIds($request->user()));
            })
            ->when($request->integer('condominium_id'), fn ($query, $id) => $query->where('condominium_id', $id))
            ->when($request->integer('user_id'), fn ($query, $id) => $query->where('user_id', $id))
            ->when($request->input('action'), fn ($query, $action) => $query->where('action', $action))
            ->when($request->input('module'), fn ($query, $module) => $query->where('module', $module))
            ->when($request->input('entity_type'), fn ($query, $type) => $query->where('entity_type', $type))
            ->when($request->integer('entity_id'), fn ($query, $id) => $query->where('entity_id', $id))
            ->when($request->input('source'), fn ($query, $source) => $query->where('source', $source))
            ->when($request->input('from_date'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->input('to_date'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(30);

        return $this->responder
            ->success($logs, [AuditLogTransformer::class, 'transform'])
            ->message('Auditoria obtenida correctamente.')
            ->respond();
    }
}
