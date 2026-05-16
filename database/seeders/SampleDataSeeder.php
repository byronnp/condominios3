<?php

namespace Database\Seeders;

use App\Models\Billing\FeeCharge;
use App\Models\Billing\Payment;
use App\Models\Catalog\Catalog;
use App\Models\Condominium\Condominium;
use App\Models\Condominium\House;
use App\Models\Condominium\HouseInvitation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $identificationTypes = Catalog::query()->where('code', 'identification_types')->firstOrFail();
        $cedula = $identificationTypes->items()->where('code', 'cedula')->firstOrFail();

        $paymentMethods = Catalog::query()->updateOrCreate([
            'code' => 'payment_methods',
        ], [
            'name' => 'Metodos de pago',
            'description' => 'Catalogo global de formas de pago.',
            'is_active' => true,
        ]);

        foreach ([
            ['code' => 'cash', 'name' => 'Efectivo', 'sort_order' => 1],
            ['code' => 'transfer', 'name' => 'Transferencia', 'sort_order' => 2],
            ['code' => 'deposit', 'name' => 'Deposito', 'sort_order' => 3],
        ] as $item) {
            $paymentMethods->items()->updateOrCreate(['code' => $item['code']], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }

        $seniorAdmin = User::query()->where('email', 'admin@condominios.test')->firstOrFail();

        $condominium = Condominium::query()->updateOrCreate([
            'name' => 'Condominio Los Ceibos',
        ], [
            'address' => 'Av. Principal y Calle 1',
            'is_active' => true,
        ]);

        foreach ($paymentMethods->items as $item) {
            $condominium->catalogItems()->syncWithoutDetaching([
                $item->id => [
                    'custom_name' => null,
                    'is_enabled' => true,
                    'sort_order' => $item->sort_order,
                ],
            ]);
        }

        $condominium->customFields()->updateOrCreate([
            'entity_type' => 'house',
            'field_key' => 'parking_number',
        ], [
            'label' => 'Numero de parqueo',
            'field_type' => 'text',
            'is_required' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $condominiumAdmin = User::query()->updateOrCreate([
            'email' => 'admin.ceibos@test.com',
        ], [
            'name' => 'MARIA ADMIN',
            'first_name' => 'MARIA',
            'last_name' => 'ADMIN',
            'identification_type_id' => $cedula->id,
            'identification_number' => '0911111111',
            'mobile_phone' => '0991111111',
            'landline_phone' => '042111111',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_CONDOMINIUM_ADMIN,
            'is_active' => true,
        ]);

        $condominium->administrators()->syncWithoutDetaching([
            $condominiumAdmin->id => [
                'role' => User::ROLE_CONDOMINIUM_ADMIN,
                'can_manage_houses' => true,
                'can_manage_residents' => true,
                'can_manage_fees' => true,
                'can_manage_payments' => true,
                'can_manage_invitations' => true,
                'approved_at' => Carbon::now(),
                'approved_by' => $seniorAdmin->id,
                'deleted_at' => null,
            ],
        ]);

        $houseA01 = House::query()->updateOrCreate([
            'condominium_id' => $condominium->id,
            'code' => 'A-01',
        ], [
            'house_number' => '1',
            'address_reference' => 'Manzana A Casa 1',
            'status' => 'active',
        ]);

        $houseA02 = House::query()->updateOrCreate([
            'condominium_id' => $condominium->id,
            'code' => 'A-02',
        ], [
            'house_number' => '2',
            'address_reference' => 'Manzana A Casa 2',
            'status' => 'active',
        ]);

        $owner = User::query()->updateOrCreate([
            'email' => 'juan.perez@test.com',
        ], [
            'name' => 'JUAN PEREZ',
            'first_name' => 'JUAN',
            'last_name' => 'PEREZ',
            'identification_type_id' => $cedula->id,
            'identification_number' => '0922222222',
            'mobile_phone' => '0992222222',
            'landline_phone' => '042222222',
            'password' => Hash::make('resident123'),
            'role' => User::ROLE_RESIDENT,
            'is_active' => true,
        ]);

        $family = User::query()->updateOrCreate([
            'email' => 'ana.perez@test.com',
        ], [
            'name' => 'ANA PEREZ',
            'first_name' => 'ANA',
            'last_name' => 'PEREZ',
            'identification_type_id' => $cedula->id,
            'identification_number' => '0933333333',
            'mobile_phone' => '0993333333',
            'landline_phone' => null,
            'password' => Hash::make('resident123'),
            'role' => User::ROLE_RESIDENT,
            'is_active' => true,
        ]);

        $houseA01->users()->syncWithoutDetaching([
            $owner->id => [
                'relationship' => 'owner',
                'can_view_balance' => true,
                'can_view_payments' => true,
                'can_make_payments' => true,
                'can_receive_notifications' => true,
                'can_invite_users' => true,
                'is_primary' => true,
                'approved_at' => Carbon::now(),
                'approved_by' => $seniorAdmin->id,
            ],
            $family->id => [
                'relationship' => 'family',
                'can_view_balance' => true,
                'can_view_payments' => true,
                'can_make_payments' => false,
                'can_receive_notifications' => true,
                'can_invite_users' => false,
                'is_primary' => false,
                'approved_at' => Carbon::now(),
                'approved_by' => $owner->id,
            ],
        ]);

        $mayCharge = $this->charge($houseA01, '2026-05', 40, 40, 'paid', 'Alicuota mayo');
        $this->charge($houseA01, '2026-06', 40, 0, 'pending', 'Alicuota junio');
        $this->charge($houseA02, '2026-06', 45, 0, 'pending', 'Alicuota junio');

        Payment::query()->updateOrCreate([
            'reference' => 'SAMPLE-TRX-001',
        ], [
            'fee_charge_id' => $mayCharge->id,
            'house_id' => $houseA01->id,
            'registered_by' => $seniorAdmin->id,
            'amount' => 40,
            'paid_at' => Carbon::parse('2026-05-16 10:00:00'),
            'payment_method' => 'transfer',
            'notes' => 'Pago de ejemplo generado por seeder.',
        ]);

        HouseInvitation::query()->updateOrCreate([
            'house_id' => $houseA01->id,
            'email' => 'invitado@test.com',
        ], [
            'relationship' => 'family',
            'token' => (string) Str::uuid(),
            'can_view_balance' => true,
            'can_view_payments' => true,
            'can_make_payments' => false,
            'can_receive_notifications' => true,
            'can_invite_users' => false,
            'invited_by' => $owner->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
    }

    private function charge(House $house, string $period, float $amount, float $paidAmount, string $status, string $description): FeeCharge
    {
        return FeeCharge::query()->updateOrCreate([
            'house_id' => $house->id,
            'period' => $period,
        ], [
            'amount' => $amount,
            'paid_amount' => $paidAmount,
            'balance' => max(0, $amount - $paidAmount),
            'due_date' => $period.'-30',
            'status' => $status,
            'description' => $description,
        ]);
    }
}
