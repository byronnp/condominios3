<?php

namespace App\Http\Controllers\Api\Resident;

use App\Http\Controllers\Controller;
use App\Models\Condominium\House;
use App\Transformers\HouseTransformer;
use Illuminate\Http\Request;

class MyHouseController extends Controller
{
    public function index(Request $request)
    {
        return $this->responder
            ->success($request->user()
                ->houses()
                ->with('condominium')
                ->wherePivotNotNull('approved_at')
                ->get(), [HouseTransformer::class, 'transform'])
            ->message('Casas del residente obtenidas correctamente.')
            ->respond();
    }

    public function statement(Request $request, House $house)
    {
        $membership = $this->membership($request, $house);

        if (! $membership || ! $request->user()->hasHousePermission('resident.balance.view', $house->id)) {
            return $this->responder->error('No autorizado para ver el saldo pendiente de esta casa.', 403)->respond();
        }

        $charges = $house->feeCharges()
            ->with('payments')
            ->orderByDesc('period')
            ->get();

        return $this->responder->success([
            'house' => $house->load('condominium'),
            'summary' => [
                'total_charged' => $charges->sum('amount'),
                'total_paid' => $charges->sum('paid_amount'),
                'pending_balance' => $charges->sum('balance'),
            ],
            'fee_charges' => $charges,
        ])->message('Estado de cuenta obtenido correctamente.')->respond();
    }

    private function membership(Request $request, House $house): ?House
    {
        return $request->user()
            ->houses()
            ->where('houses.id', $house->id)
            ->wherePivotNotNull('approved_at')
            ->first();
    }
}
