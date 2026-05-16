<?php

namespace App\Models\Condominium;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['condominium_id', 'code', 'house_number', 'address_reference', 'status'])]
class House extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function condominium(): BelongsTo
    {
        return $this->belongsTo(Condominium::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'relationship',
                'can_view_balance',
                'can_view_payments',
                'can_make_payments',
                'can_receive_notifications',
                'can_invite_users',
                'is_primary',
                'approved_at',
                'approved_by',
            ])
            ->withTimestamps();
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(FeeCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseInvitation::class);
    }
}
