<?php

namespace App\Models\Catalog;

use App\Models\Condominium\Condominium;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['catalog_id', 'code', 'name', 'description', 'sort_order', 'is_active'])]
class CatalogItem extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class);
    }

    public function condominiums(): BelongsToMany
    {
        return $this->belongsToMany(Condominium::class, 'condominium_catalog_items')
            ->withPivot(['custom_name', 'is_enabled', 'sort_order'])
            ->withTimestamps();
    }
}
