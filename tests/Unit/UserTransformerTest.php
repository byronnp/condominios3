<?php

namespace Tests\Unit;

use App\Models\Auth\Role;
use App\Models\User;
use App\Transformers\UserTransformer;
use Tests\TestCase;

class UserTransformerTest extends TestCase
{
    public function test_user_role_is_exposed_as_id_and_name(): void
    {
        $role = new Role([
            'name' => 'Residente',
        ]);
        $role->id = 3;

        $user = new User([
            'name' => 'Juan Perez',
            'email' => 'juan.perez@test.com',
        ]);
        $user->id = 10;
        $user->setRelation('userRole', $role);

        $payload = UserTransformer::transform($user);

        $this->assertSame([
            'id' => 3,
            'name' => 'Residente',
        ], $payload['role']);
    }

}
