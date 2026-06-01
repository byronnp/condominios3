<?php

namespace Tests\Unit;

use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Transformers\CondominiumTransformer;
use PHPUnit\Framework\TestCase;

class CondominiumTransformerTest extends TestCase
{
    public function test_it_returns_occupancy_percentage_without_houses_array(): void
    {
        $condominium = new Condominium([
            'name' => 'Condominio Test',
            'total_houses' => 300,
            'is_active' => true,
        ]);
        $condominium->setAttribute('houses_count', 2);

        $payload = CondominiumTransformer::transform($condominium);

        $this->assertSame(2, $payload['houses_count']);
        $this->assertSame(0.67, $payload['occupancy_percentage']);
        $this->assertArrayNotHasKey('houses', $payload);
    }

    public function test_it_returns_zero_occupancy_when_total_houses_is_zero(): void
    {
        $condominium = new Condominium([
            'name' => 'Condominio Test',
            'total_houses' => 0,
            'is_active' => true,
        ]);
        $condominium->setAttribute('houses_count', 2);

        $payload = CondominiumTransformer::transform($condominium);

        $this->assertSame(0.0, $payload['occupancy_percentage']);
    }

    public function test_it_returns_administrator_name_when_loaded(): void
    {
        $condominium = new Condominium([
            'name' => 'Condominio Test',
            'ruc' => '0999999999001',
            'sector' => 'Norte',
            'total_houses' => 300,
            'is_active' => true,
        ]);
        $condominium->setRelation('administrators', collect([
            new User([
                'name' => 'Admin Test',
                'mobile_phone' => '0991111111',
                'email' => 'admin@test.com',
            ]),
        ]));
        $condominium->setRelation('status', new CatalogItem([
            'name' => 'Activo',
        ]));
        $condominium->status->id = 1;

        $payload = CondominiumTransformer::transform($condominium);

        $this->assertSame('0999999999001', $payload['ruc']);
        $this->assertSame('Norte', $payload['sector']);
        $this->assertSame(['id' => 1, 'name' => 'Activo'], $payload['status']);
        $this->assertSame('Admin Test', $payload['administrator_name']);
        $this->assertSame('0991111111', $payload['administrator_phone']);
        $this->assertSame('admin@test.com', $payload['administrator_email']);
    }
}
