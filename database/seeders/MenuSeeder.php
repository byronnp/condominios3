<?php

namespace Database\Seeders;

use App\Models\Menu\Menu;
use App\Models\User;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('admin.dashboard', 'Dashboard', '/admin/dashboard', 'layout-dashboard', 1, User::ROLE_CONDOMINIUM_ADMIN);

        $administration = $this->menu('admin.administration', 'Administracion', null, 'building-2', 10, User::ROLE_CONDOMINIUM_ADMIN);
        $this->menu('admin.condominiums', 'Condominios', '/admin/condominiums', 'building-2', 1, User::ROLE_SENIOR_ADMIN, null, $administration);
        $this->menu('admin.houses', 'Casas', '/admin/houses', 'home', 2, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_houses', $administration);
        $this->menu('admin.residents', 'Residentes', '/admin/residents', 'users', 3, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_residents', $administration);

        $billing = $this->menu('admin.billing', 'Facturacion', null, 'receipt', 20, User::ROLE_CONDOMINIUM_ADMIN);
        $this->menu('admin.fee-rates', 'Tarifas de alicuotas', '/admin/fee-rates', 'badge-dollar-sign', 1, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_fees', $billing);
        $this->menu('admin.fee-charges', 'Alicuotas', '/admin/fee-charges', 'receipt-text', 2, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_fees', $billing);
        $this->menu('admin.payments', 'Pagos', '/admin/payments', 'credit-card', 3, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_payments', $billing);
        $this->menu('admin.payment-methods', 'Metodos de pago', '/admin/payment-methods', 'wallet-cards', 4, User::ROLE_CONDOMINIUM_ADMIN, 'can_manage_payments', $billing);

        $settings = $this->menu('admin.settings', 'Configuracion', null, 'settings', 30, User::ROLE_CONDOMINIUM_ADMIN);
        $this->menu('admin.catalogs', 'Catalogos', '/admin/catalogs', 'list-tree', 1, User::ROLE_SENIOR_ADMIN, null, $settings);
        $this->menu('admin.menus', 'Menus', '/admin/menus', 'panel-left', 2, User::ROLE_SENIOR_ADMIN, null, $settings);
        $this->menu('admin.audit-logs', 'Auditoria', '/admin/audit-logs', 'shield-check', 3, User::ROLE_CONDOMINIUM_ADMIN, null, $settings);

        $resident = $this->menu('resident.home', 'Mi hogar', '/resident/houses', 'home', 100, User::ROLE_RESIDENT);
        $this->menu('resident.statement', 'Estado de cuenta', '/resident/statement', 'file-text', 1, User::ROLE_RESIDENT, 'can_view_balance', $resident);
        $this->menu('resident.payments', 'Mis pagos', '/resident/payments', 'credit-card', 2, User::ROLE_RESIDENT, 'can_view_payments', $resident);
        $this->menu('resident.advance-payments', 'Adelantar alicuotas', '/resident/advance-payments', 'calendar-plus', 3, User::ROLE_RESIDENT, 'can_make_payments', $resident);
        $this->menu('resident.invitations', 'Invitaciones', '/resident/invitations', 'user-plus', 4, User::ROLE_RESIDENT, 'can_invite_users', $resident);
    }

    private function menu(
        string $code,
        string $label,
        ?string $path,
        ?string $icon,
        int $sortOrder,
        ?string $requiredRole = null,
        ?string $requiredPermission = null,
        ?Menu $parent = null,
    ): Menu {
        return Menu::query()->updateOrCreate([
            'code' => $code,
        ], [
            'parent_id' => $parent?->id,
            'label' => $label,
            'path' => $path,
            'icon' => $icon,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'required_role' => $requiredRole,
            'required_permission' => $requiredPermission,
        ]);
    }
}
