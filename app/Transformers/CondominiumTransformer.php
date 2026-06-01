<?php

namespace App\Transformers;

use App\Models\Condominium\Condominium;
use App\Support\ResourceActions;

class CondominiumTransformer
{
    public static function transform(Condominium $condominium): array
    {
        $housesCount = $condominium->houses_count ?? null;
        $administrator = $condominium->relationLoaded('administrators')
            ? $condominium->administrators->first()
            : null;
        $status = $condominium->relationLoaded('status') ? $condominium->status : null;

        return [
            'id' => $condominium->id,
            'name' => $condominium->name,
            'ruc' => $condominium->ruc,
            'administrator_name' => $administrator?->name,
            'administrator_phone' => $administrator?->mobile_phone ?? $administrator?->landline_phone,
            'administrator_email' => $administrator?->email,
            'address' => $condominium->address,
            'city' => $condominium->city,
            'sector' => $condominium->sector,
            'status' => $status ? [
                'id' => $status->id,
                'name' => $status->name,
            ] : null,
            'total_houses' => $condominium->total_houses,
            'houses_count' => $housesCount,
            'occupancy_percentage' => self::occupancyPercentage($housesCount, $condominium->total_houses),
            'actions' => ResourceActions::condominium($condominium),
        ];
    }

    private static function occupancyPercentage(?int $housesCount, ?int $totalHouses): float
    {
        if ($housesCount === null || ! $totalHouses) {
            return 0.0;
        }

        return round(($housesCount / $totalHouses) * 100, 2);
    }
}
