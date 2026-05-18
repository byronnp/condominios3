<?php

namespace App\Models\Billing;

use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['condominium_id', 'payment_method_id', 'display_name', 'is_enabled', 'sort_order', 'instructions', 'config'])]
class CondominiumPaymentMethod extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'encrypted:array',
            'deleted_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'payment_method_id');
    }
}
