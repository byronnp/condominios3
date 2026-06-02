<?php

namespace Tests\Unit;

use App\Models\Auth\Role;
use App\Models\Board\BoardMember;
use App\Models\Board\BoardTerm;
use App\Models\Catalog\CatalogItem;
use App\Models\Condominium\Condominium;
use App\Models\User;
use App\Transformers\BoardTermTransformer;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class BoardTermTransformerTest extends TestCase
{
    public function test_it_exposes_term_members_with_safe_nested_payloads(): void
    {
        $condominium = new Condominium(['name' => 'Condominio Los Ceibos']);
        $condominium->id = 1;

        $user = new User([
            'name' => 'Juan Perez',
            'first_name' => 'Juan',
            'last_name' => 'Perez',
            'email' => 'juan.perez@test.com',
        ]);
        $user->id = 10;

        $position = new CatalogItem(['name' => 'Tesorero']);
        $position->id = 20;

        $role = new Role(['name' => 'Tesorero de directiva']);
        $role->id = 30;

        $member = new BoardMember([
            'starts_at' => '2026-01-01',
            'ends_at' => null,
            'is_active' => true,
        ]);
        $member->id = 40;
        $member->setRelation('user', $user);
        $member->setRelation('position', $position);
        $member->setRelation('role', $role);

        $term = new BoardTerm([
            'name' => 'Directiva 2026',
            'starts_at' => '2026-01-01',
            'ends_at' => '2026-12-31',
            'status' => BoardTerm::STATUS_ACTIVE,
        ]);
        $term->id = 50;
        $term->setRelation('condominium', $condominium);
        $term->setRelation('members', new Collection([$member]));

        $payload = BoardTermTransformer::transform($term);

        $this->assertSame(['id' => 1, 'name' => 'Condominio Los Ceibos'], $payload['condominium']);
        $this->assertSame(['id' => 20, 'name' => 'Tesorero'], $payload['members'][0]['position']);
        $this->assertSame(['id' => 30, 'name' => 'Tesorero de directiva'], $payload['members'][0]['role']);
        $this->assertArrayNotHasKey('code', $payload['members'][0]['position']);
    }
}
