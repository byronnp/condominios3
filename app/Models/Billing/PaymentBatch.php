<?php

namespace App\Models\Billing;

use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\House;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['house_id', 'registered_by', 'total_amount', 'payment_method_id', 'condominium_payment_method_id', 'reference', 'paid_at', 'notes'])]
class PaymentBatch extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'payment_method_id');
    }

    public function condominiumPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(CondominiumPaymentMethod::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
