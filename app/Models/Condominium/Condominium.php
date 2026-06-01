<?php

namespace App\Models\Condominium;

use App\Models\Billing\CondominiumFeeRate;
use App\Models\Billing\CondominiumPaymentMethod;
use App\Models\Catalog\CatalogItem;
use App\Models\Catalog\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'ruc', 'address', 'city', 'sector', 'status_id', 'total_houses', 'is_active'])]
class Condominium extends Model
{
    use SoftDeletes;

    protected $table = 'condominiums';

    protected function casts(): array
    {
        return [
            'status_id' => 'integer',
            'total_houses' => 'integer',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'status_id');
    }

    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role_id',
                'approved_at',
                'approved_by',
                'deleted_at',
            ])
            ->wherePivotNull('deleted_at')
            ->withTimestamps();
    }

    public function catalogItems(): BelongsToMany
    {
        return $this->belongsToMany(CatalogItem::class, 'condominium_catalog_items')
            ->withPivot(['custom_name', 'is_enabled', 'sort_order'])
            ->withTimestamps();
    }

    public function customFields(): HasMany
    {
        return $this->hasMany(CustomField::class);
    }

    public function feeRates(): HasMany
    {
        return $this->hasMany(CondominiumFeeRate::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(CondominiumPaymentMethod::class);
    }
}
