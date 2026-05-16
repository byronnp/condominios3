<?php

namespace App\Models\Catalog;

use App\Models\Condominium\Condominium;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'condominium_id',
    'entity_type',
    'field_key',
    'label',
    'field_type',
    'is_required',
    'options_catalog_id',
    'sort_order',
    'is_active',
])]
class CustomField extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function optionsCatalog(): BelongsTo
    {
        return $this->belongsTo(Catalog::class, 'options_catalog_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }
}
