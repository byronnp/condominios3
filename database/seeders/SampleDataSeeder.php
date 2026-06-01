<?php

namespace Database\Seeders;

use App\Models\Auth\Role;
use App\Models\Billing\CondominiumFeeRate;
use App\Models\Billing\CondominiumPaymentMethod;
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
        $relationshipTypes = Catalog::query()->where('code', 'house_relationship_types')->firstOrFail();
        $ownerType = $relationshipTypes->items()->where('code', 'owner')->firstOrFail();
        $familyType = $relationshipTypes->items()->where('code', 'family')->firstOrFail();

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
            ['code' => 'deuna', 'name' => 'Deuna', 'sort_order' => 4],
            ['code' => 'kushpago', 'name' => 'KushPago', 'sort_order' => 5],
        ] as $item) {
            $paymentMethods->items()->updateOrCreate(['code' => $item['code']], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);
        }
        $transferPaymentMethod = $paymentMethods->items()->where('code', 'transfer')->firstOrFail();

        $seniorAdmin = User::query()->where('email', 'admin@condominios.test')->firstOrFail();
        $activeCondominiumStatusId = Catalog::query()
            ->where('code', 'condominium_statuses')
            ->firstOrFail()
            ->items()
            ->where('code', 'active')
            ->value('id');

        $ceibos = Condominium::query()->updateOrCreate([
            'name' => 'Condominio Los Ceibos',
        ], [
            'ruc' => '0999999999001',
            'address' => 'Av. Principal y Calle 1',
            'city' => 'Guayaquil',
            'sector' => 'Norte',
            'status_id' => $activeCondominiumStatusId,
            'total_houses' => 2,
            'is_active' => true,
        ]);

        $this->configureCondominium($ceibos, $paymentMethods);
        $ceibosTransferMethod = $ceibos->paymentMethods()
            ->where('payment_method_id', $transferPaymentMethod->id)
            ->firstOrFail();
        $this->feeRate($ceibos, 40);

        $ceibosAdmin = User::query()->updateOrCreate([
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

        $this->assignCondominiumAdmin($ceibos, $ceibosAdmin, $seniorAdmin);

        $houseA01 = House::query()->updateOrCreate([
            'condominium_id' => $ceibos->id,
            'house_number' => '1',
        ], [
            'house_number' => '1',
            'code' => House::generateCode($ceibos, '1'),
            'address_reference' => 'Manzana A Casa 1',
            'status' => 'active',
        ]);

        $houseA02 = House::query()->updateOrCreate([
            'condominium_id' => $ceibos->id,
            'house_number' => '2',
        ], [
            'house_number' => '2',
            'code' => House::generateCode($ceibos, '2'),
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
                'relationship_type_id' => $ownerType->id,
                'role_id' => Role::idForCode(Role::RESIDENT_OWNER),
                'can_receive_notifications' => true,
                'is_primary' => true,
                'approved_at' => Carbon::now(),
                'approved_by' => $seniorAdmin->id,
            ],
            $family->id => [
                'relationship_type_id' => $familyType->id,
                'role_id' => Role::idForCode(Role::RESIDENT_VIEWER),
                'can_receive_notifications' => true,
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
            'payment_method_id' => $transferPaymentMethod->id,
            'condominium_payment_method_id' => $ceibosTransferMethod->id,
            'notes' => 'Pago de ejemplo generado por seeder.',
        ]);

        HouseInvitation::query()->updateOrCreate([
            'house_id' => $houseA01->id,
            'email' => 'invitado@test.com',
        ], [
            'relationship_type_id' => $familyType->id,
            'role_id' => Role::idForCode(Role::RESIDENT_VIEWER),
            'token' => (string) Str::uuid(),
            'can_receive_notifications' => true,
            'invited_by' => $owner->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $prados = Condominium::query()->updateOrCreate([
            'name' => 'Condominio Los Prados',
        ], [
            'ruc' => '0999999998001',
            'address' => 'Calle Los Jardines y Av. Norte',
            'city' => 'Guayaquil',
            'sector' => 'Via a la costa',
            'status_id' => $activeCondominiumStatusId,
            'total_houses' => 1,
            'is_active' => true,
        ]);

        $this->configureCondominium($prados, $paymentMethods);
        $this->feeRate($prados, 55);

        $pradosAdmin = User::query()->updateOrCreate([
            'email' => 'admin.prados@test.com',
        ], [
            'name' => 'CARLOS ADMIN',
            'first_name' => 'CARLOS',
            'last_name' => 'ADMIN',
            'identification_type_id' => $cedula->id,
            'identification_number' => '0944444444',
            'mobile_phone' => '0994444444',
            'landline_phone' => '042444444',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_CONDOMINIUM_ADMIN,
            'is_active' => true,
        ]);

        $this->assignCondominiumAdmin($prados, $pradosAdmin, $seniorAdmin);

        $houseB01 = House::query()->updateOrCreate([
            'condominium_id' => $prados->id,
            'house_number' => '101',
        ], [
            'house_number' => '101',
            'code' => House::generateCode($prados, '101'),
            'address_reference' => 'Bloque B Casa 101',
            'status' => 'active',
        ]);

        $houseB02 = House::query()->updateOrCreate([
            'condominium_id' => $prados->id,
            'house_number' => '102',
        ], [
            'house_number' => '102',
            'code' => House::generateCode($prados, '102'),
            'address_reference' => 'Bloque B Casa 102',
            'status' => 'active',
        ]);

        $pradosOwner = User::query()->updateOrCreate([
            'email' => 'luisa.gomez@test.com',
        ], [
            'name' => 'LUISA GOMEZ',
            'first_name' => 'LUISA',
            'last_name' => 'GOMEZ',
            'identification_type_id' => $cedula->id,
            'identification_number' => '0955555555',
            'mobile_phone' => '0995555555',
            'landline_phone' => '042555555',
            'password' => Hash::make('resident123'),
            'role' => User::ROLE_RESIDENT,
            'is_active' => true,
        ]);

        $houseB01->users()->syncWithoutDetaching([
            $pradosOwner->id => [
                'relationship_type_id' => $ownerType->id,
                'role_id' => Role::idForCode(Role::RESIDENT_OWNER),
                'can_receive_notifications' => true,
                'is_primary' => true,
                'approved_at' => Carbon::now(),
                'approved_by' => $seniorAdmin->id,
            ],
        ]);

        $this->charge($houseB01, '2026-06', 55, 0, 'pending', 'Alicuota junio');
        $this->charge($houseB02, '2026-06', 55, 0, 'pending', 'Alicuota junio');
    }

    private function configureCondominium(Condominium $condominium, Catalog $paymentMethods): void
    {
        foreach ($paymentMethods->items()->get() as $item) {
            $condominium->catalogItems()->syncWithoutDetaching([
                $item->id => [
                    'custom_name' => null,
                    'is_enabled' => true,
                    'sort_order' => $item->sort_order,
                ],
            ]);

            CondominiumPaymentMethod::query()->updateOrCreate([
                'condominium_id' => $condominium->id,
                'payment_method_id' => $item->id,
            ], [
                'display_name' => $item->name,
                'is_enabled' => true,
                'sort_order' => $item->sort_order,
                'instructions' => $this->paymentInstructions($item->code),
                'config' => $this->paymentConfig($condominium, $item->code),
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
    }

    private function paymentInstructions(string $code): ?string
    {
        return match ($code) {
            'transfer' => 'Realiza la transferencia y registra el numero de comprobante.',
            'deuna' => 'Paga con Deuna y coloca el numero de casa en el detalle.',
            'kushpago' => 'Completa el pago en KushPago desde el enlace generado.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentConfig(Condominium $condominium, string $code): ?array
    {
        return match ($code) {
            'deuna' => [
                'merchant_id' => 'demo-deuna-'.$condominium->id,
                'qr_url' => 'https://example.test/deuna/'.$condominium->id.'/qr',
            ],
            'kushpago' => [
                'merchant_id' => 'demo-kushpago-'.$condominium->id,
                'public_key' => 'pk_demo_'.$condominium->id,
                'secret_key' => 'sk_demo_'.$condominium->id,
            ],
            default => null,
        };
    }

    private function assignCondominiumAdmin(Condominium $condominium, User $condominiumAdmin, User $seniorAdmin): void
    {
        $condominium->administrators()->syncWithoutDetaching([
            $condominiumAdmin->id => [
                'role_id' => Role::idForCode(User::ROLE_CONDOMINIUM_ADMIN),
                'approved_at' => Carbon::now(),
                'approved_by' => $seniorAdmin->id,
                'deleted_at' => null,
            ],
        ]);
    }

    private function feeRate(Condominium $condominium, float $amount): CondominiumFeeRate
    {
        return CondominiumFeeRate::query()->updateOrCreate([
            'condominium_id' => $condominium->id,
            'starts_at' => '2026-01-01',
        ], [
            'amount' => $amount,
            'ends_at' => null,
            'is_active' => true,
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
