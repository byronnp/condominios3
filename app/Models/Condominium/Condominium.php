<?php

namespace App\Models\Condominium;

use App\Models\Catalog\CatalogItem;
use App\Models\Catalog\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'address', 'is_active'])]
class Condominium extends Model
{
    use SoftDeletes;

    protected $table = 'condominiums';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function houses(): HasMany
    {
        return $this->hasMany(House::class);
    }

    public function administrators(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'can_manage_houses',
                'can_manage_residents',
                'can_manage_fees',
                'can_manage_payments',
                'can_manage_invitations',
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
}
