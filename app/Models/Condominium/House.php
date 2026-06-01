<?php

namespace App\Models\Condominium;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentBatch;
use App\Models\Catalog\CatalogItem;
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
                'relationship_type_id',
                'role_id',
                'can_receive_notifications',
                'is_primary',
                'approved_at',
                'approved_by',
            ])
            ->withTimestamps();
    }

    public function ownerUsers(): BelongsToMany
    {
        return $this->usersByRelationship('owner');
    }

    public function administratorUsers(): BelongsToMany
    {
        return $this->usersByRelationship('representative');
    }

    public function feeCharges(): HasMany
    {
        return $this->hasMany(FeeCharge::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paymentBatches(): HasMany
    {
        return $this->hasMany(PaymentBatch::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(HouseInvitation::class);
    }

    public static function generateCode(Condominium $condominium, string $houseNumber): string
    {
        $normalizedHouseNumber = self::formatHouseNumber($houseNumber);
        $initials = collect(preg_split('/\s+/', trim($condominium->name)) ?: [])
            ->filter(fn ($part) => $part !== '' && ! in_array(mb_strtolower($part), ['condominio', 'condominium'], true))
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        if ($initials === '') {
            $initials = mb_strtoupper(mb_substr($condominium->name, 0, 2));
        }

        return $condominium->id.'-'.$normalizedHouseNumber.'-'.$initials;
    }

    private static function formatHouseNumber(string $houseNumber): string
    {
        $trimmed = trim($houseNumber);

        if (preg_match('/^\d+$/', $trimmed) !== 1) {
            return $trimmed;
        }

        return str_pad($trimmed, 2, '0', STR_PAD_LEFT);
    }

    private function usersByRelationship(string $relationshipCode): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->whereIn('house_user.relationship_type_id', CatalogItem::query()
                ->select('catalog_items.id')
                ->where('catalog_items.code', $relationshipCode)
                ->whereHas('catalog', fn ($query) => $query->where('code', 'house_relationship_types')))
            ->withPivot([
                'relationship_type_id',
                'role_id',
                'can_receive_notifications',
                'is_primary',
                'approved_at',
                'approved_by',
            ])
            ->withTimestamps();
    }
}
