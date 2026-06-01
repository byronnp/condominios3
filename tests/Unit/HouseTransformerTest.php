<?php

namespace Tests\Unit;

use App\Models\Condominium\House;
use App\Models\User;
use App\Transformers\HouseTransformer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Tests\TestCase;

class HouseTransformerTest extends TestCase
{
    public function test_it_exposes_owner_when_users_are_loaded(): void
    {
        $house = new House([
            'code' => '1-01-CLC',
            'house_number' => '01',
            'status' => 'active',
        ]);
        $house->id = 5;

        $owner = new User([
            'name' => 'Juan Perez',
            'email' => 'juan.perez@test.com',
            'mobile_phone' => '0991234567',
            'landline_phone' => '042123456',
        ]);
        $owner->id = 10;
        $owner->setRelation('pivot', Pivot::fromAttributes($owner, [
            'is_primary' => true,
        ], 'house_user', true));

        $administrator = new User([
            'name' => 'Maria Representante',
            'email' => 'maria.representante@test.com',
            'mobile_phone' => '0997654321',
            'landline_phone' => null,
        ]);
        $administrator->id = 11;
        $administrator->setRelation('pivot', Pivot::fromAttributes($administrator, [
            'is_primary' => false,
        ], 'house_user', true));

        $house->setRelation('ownerUsers', new Collection([$owner]));
        $house->setRelation('administratorUsers', new Collection([$administrator]));

        $payload = HouseTransformer::transform($house);

        $this->assertSame([
            'id' => 10,
            'name' => 'Juan Perez',
            'email' => 'juan.perez@test.com',
            'mobile_phone' => '0991234567',
            'landline_phone' => '042123456',
        ], $payload['owner']);
        $this->assertSame([
            'id' => 11,
            'name' => 'Maria Representante',
            'email' => 'maria.representante@test.com',
            'mobile_phone' => '0997654321',
            'landline_phone' => null,
        ], $payload['administrator']);
    }
}
