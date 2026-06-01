<?php

namespace Tests\Unit;

use App\Models\User;
use App\Transformers\CondominiumAdminTransformer;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Tests\TestCase;

class CondominiumAdminTransformerTest extends TestCase
{
    public function test_it_exposes_condominium_role_from_pivot(): void
    {
        $user = new User([
            'name' => 'Maria Admin',
            'email' => 'maria.admin@test.com',
        ]);
        $user->setRelation('pivot', Pivot::fromAttributes($user, [
            'role_id' => null,
        ], 'condominium_user', true));

        $payload = CondominiumAdminTransformer::transform($user);

        $this->assertArrayHasKey('condominium_role', $payload);
        $this->assertNull($payload['condominium_role']);
    }
}
