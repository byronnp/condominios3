<?php

namespace Database\Seeders;

use App\Models\Auth\Permission;
use App\Models\Menu\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->menu('admin.dashboard', 'Dashboard', '/', 'layout-dashboard', 1, 'admin.access');

        $administration = $this->menu('admin.administration', 'Administracion', null, 'building-2', 10, 'admin.access');
        $this->menu('admin.condominiums', 'Condominios', '/admin/condominios', 'building-2', 1, 'condominiums.manage', $administration);
        $this->menu('admin.houses', 'Casas', '/admin/houses', 'home', 2, 'houses.manage', $administration);
        $this->menu('admin.residents', 'Residentes', '/admin/residents', 'users', 3, 'residents.manage', $administration);

        $billing = $this->menu('admin.billing', 'Facturacion', null, 'receipt', 20, 'admin.access');
        $this->menu('admin.fee-rates', 'Tarifas de alicuotas', '/admin/fee-rates', 'badge-dollar-sign', 1, 'fees.manage', $billing);
        $this->menu('admin.fee-charges', 'Alicuotas', '/admin/fee-charges', 'receipt-text', 2, 'fees.manage', $billing);
        $this->menu('admin.payments', 'Pagos', '/admin/payments', 'credit-card', 3, 'payments.manage', $billing);
        $this->menu('admin.payment-methods', 'Metodos de pago', '/admin/payment-methods', 'wallet-cards', 4, 'payment_methods.manage', $billing);

        $settings = $this->menu('admin.settings', 'Configuracion', null, 'settings', 30, 'admin.access');
        $this->menu('admin.catalogs', 'Catalogos', '/admin/catalogs', 'list-tree', 1, 'catalogs.manage', $settings);
        $this->menu('admin.menus', 'Menus', '/admin/menus', 'panel-left', 2, 'menus.manage', $settings);
        $this->menu('admin.roles', 'Roles', '/admin/roles', 'shield-user', 3, 'roles.manage', $settings);
        $this->menu('admin.audit-logs', 'Auditoria', '/admin/audit-logs', 'shield-check', 4, 'audit_logs.view', $settings);

        $resident = $this->menu('resident.home', 'Mi hogar', '/resident/houses', 'home', 100, 'resident.access');
        $this->menu('resident.statement', 'Estado de cuenta', '/resident/statement', 'file-text', 1, 'resident.balance.view', $resident);
        $this->menu('resident.payments', 'Mis pagos', '/resident/payments', 'credit-card', 2, 'resident.payments.view', $resident);
        $this->menu('resident.advance-payments', 'Adelantar alicuotas', '/resident/advance-payments', 'calendar-plus', 3, 'resident.payments.create', $resident);
        $this->menu('resident.invitations', 'Invitaciones', '/resident/invitations', 'user-plus', 4, 'resident.invitations.create', $resident);
    }

    private function menu(
        string $code,
        string $label,
        ?string $path,
        ?string $icon,
        int $sortOrder,
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
            'required_permission_id' => $requiredPermission
                ? Permission::query()->where('code', $requiredPermission)->value('id')
                : null,
        ]);
    }
}
