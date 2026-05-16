<?php

namespace Database\Seeders;

use App\Models\Catalog\Catalog;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $identificationTypes = Catalog::query()->updateOrCreate([
            'code' => 'identification_types',
        ], [
            'name' => 'Tipos de identificacion',
            'description' => 'Catalogo global para cedula, RUC y pasaporte.',
            'is_active' => true,
        ]);

        $cedula = null;

        foreach ([
            ['code' => 'cedula', 'name' => 'Cedula', 'sort_order' => 1],
            ['code' => 'ruc', 'name' => 'RUC', 'sort_order' => 2],
            ['code' => 'passport', 'name' => 'Pasaporte', 'sort_order' => 3],
        ] as $item) {
            $catalogItem = $identificationTypes->items()->updateOrCreate([
                'code' => $item['code'],
            ], [
                'name' => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active' => true,
            ]);

            if ($item['code'] === 'cedula') {
                $cedula = $catalogItem;
            }
        }

        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@condominios.test'),
        ], [
            'name' => 'BYRON PILATAXI',
            'first_name' => 'BYRON',
            'last_name' => 'PILATAXI',
            'identification_type_id' => $cedula?->id,
            'identification_number' => '1716128911',
            'mobile_phone' => '0992770713',
            'landline_phone' => '3194285',
            'password' => Hash::make('admin123'),
            'role' => User::ROLE_SENIOR_ADMIN,
            'is_active' => true,
        ]);

        $this->call(SampleDataSeeder::class);
    }
}
