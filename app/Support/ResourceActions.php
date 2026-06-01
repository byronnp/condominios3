<?php

namespace App\Support;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Billing\PaymentBatch;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Models\User;
use Throwable;

class ResourceActions
{
    /**
     * @return array<string, bool>
     */
    public static function global(?User $user): array
    {
        return [
            'manage_roles' => self::can($user, 'roles.manage'),
            'manage_menus' => self::can($user, 'menus.manage'),
            'manage_catalogs' => self::can($user, 'catalogs.manage'),
            'manage_condominiums' => self::can($user, 'condominiums.manage'),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function condominium(Condominium $condominium, ?User $user = null): array
    {
        $user ??= self::currentUser();

        return [
            'edit' => self::can($user, 'condominiums.manage'),
            'delete' => self::can($user, 'condominiums.manage'),
            'manage_admins' => self::can($user, 'admins.manage'),
            'manage_houses' => self::can($user, 'houses.manage', $condominium->id),
            'manage_residents' => self::can($user, 'residents.manage', $condominium->id),
            'manage_fees' => self::can($user, 'fees.manage', $condominium->id),
            'manage_payments' => self::can($user, 'payments.manage', $condominium->id),
            'manage_payment_methods' => self::can($user, 'payment_methods.manage', $condominium->id),
            'view_audit_logs' => self::can($user, 'audit_logs.view', $condominium->id),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function house(House $house, ?User $user = null): array
    {
        $user ??= self::currentUser();
        $condominiumId = (int) $house->condominium_id;
        $houseId = (int) $house->id;

        return [
            'edit' => self::can($user, 'houses.manage', $condominiumId),
            'assign_resident' => self::can($user, 'residents.manage', $condominiumId),
            'manage_fees' => self::can($user, 'fees.manage', $condominiumId),
            'view_payments' => self::can($user, 'payments.manage', $condominiumId) || self::canHouse($user, 'resident.payments.view', $houseId),
            'register_payment' => self::can($user, 'payments.manage', $condominiumId),
            'view_balance' => self::can($user, 'fees.manage', $condominiumId) || self::canHouse($user, 'resident.balance.view', $houseId),
            'advance_payment' => self::canHouse($user, 'resident.payments.create', $houseId),
            'invite_user' => self::can($user, 'invitations.manage', $condominiumId) || self::canHouse($user, 'resident.invitations.create', $houseId),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function feeCharge(FeeCharge $charge, ?User $user = null): array
    {
        $user ??= self::currentUser();
        $house = $charge->relationLoaded('house') ? $charge->house : $charge->house()->first();
        $condominiumId = (int) $house?->condominium_id;

        return [
            'view' => self::can($user, 'fees.manage', $condominiumId) || self::canHouse($user, 'resident.balance.view', (int) $charge->house_id),
            'edit' => self::can($user, 'fees.manage', $condominiumId),
            'register_payment' => self::can($user, 'payments.manage', $condominiumId),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function payment(Payment $payment, ?User $user = null): array
    {
        $user ??= self::currentUser();
        $house = $payment->relationLoaded('house') ? $payment->house : $payment->house()->first();
        $condominiumId = (int) $house?->condominium_id;

        $view = self::can($user, 'payments.manage', $condominiumId)
            || self::canHouse($user, 'resident.payments.view', (int) $payment->house_id);

        return [
            'view' => $view,
            'edit' => false,
            'delete' => false,
            'print_receipt' => $view,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function paymentBatch(PaymentBatch $batch, ?User $user = null): array
    {
        $user ??= self::currentUser();
        $house = $batch->relationLoaded('house') ? $batch->house : $batch->house()->first();
        $condominiumId = (int) $house?->condominium_id;

        $view = self::can($user, 'payments.manage', $condominiumId)
            || self::canHouse($user, 'resident.payments.view', (int) $batch->house_id);

        return [
            'view' => $view,
            'print_receipt' => $view,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function invitation(HouseInvitation $invitation, ?User $user = null): array
    {
        $user ??= self::currentUser();
        $house = $invitation->relationLoaded('house') ? $invitation->house : $invitation->house()->first();
        $condominiumId = (int) $house?->condominium_id;

        return [
            'view' => self::can($user, 'invitations.manage', $condominiumId)
                || self::canHouse($user, 'resident.invitations.create', (int) $invitation->house_id),
            'revoke' => self::can($user, 'invitations.manage', $condominiumId)
                || self::canHouse($user, 'resident.invitations.create', (int) $invitation->house_id),
        ];
    }

    private static function can(?User $user, string $permission, ?int $condominiumId = null): bool
    {
        return $user?->hasPermission($permission, $condominiumId) ?? false;
    }

    private static function canHouse(?User $user, string $permission, int $houseId): bool
    {
        return $user?->hasHousePermission($permission, $houseId) ?? false;
    }

    private static function currentUser(): ?User
    {
        try {
            return auth()->user();
        } catch (Throwable) {
            return null;
        }
    }
}
