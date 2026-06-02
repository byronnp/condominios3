<?php

namespace App\Services\Condominium;

use App\Models\Auth\Role;
use App\Models\Condominium\Condominium;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CondominiumAdminService
{
    /**
     * @param  array{
     *     first_name:string,
     *     last_name:string,
     *     identification_type_id:int,
     *     identification_number:string,
     *     mobile_phone?:string|null,
     *     landline_phone?:string|null,
     *     email:string,
     *     password?:string|null,
     *     role_id?:int|null
     * }  $data
     */
    public function assign(Condominium $condominium, array $data, User $approvedBy): User
    {
        return DB::transaction(function () use ($condominium, $data, $approvedBy): User {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => User::fullName($data['first_name'], $data['last_name']),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'identification_type_id' => $data['identification_type_id'],
                    'identification_number' => $data['identification_number'],
                    'mobile_phone' => $data['mobile_phone'] ?? null,
                    'landline_phone' => $data['landline_phone'] ?? null,
                    'password' => $data['password'] ?? str()->password(16),
                    'role' => User::ROLE_CONDOMINIUM_ADMIN,
                    'is_active' => true,
                ],
            );

            if ($user->isSeniorAdmin()) {
                abort(422, 'Un administrador senior no debe asignarse como administrador de condominio.');
            }

            if (! $user->wasRecentlyCreated) {
                $user->forceFill([
                    'name' => User::fullName($data['first_name'], $data['last_name']),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'identification_type_id' => $data['identification_type_id'],
                    'identification_number' => $data['identification_number'],
                    'mobile_phone' => $data['mobile_phone'] ?? null,
                    'landline_phone' => $data['landline_phone'] ?? null,
                ])->save();
            }

            $this->ensureCondominiumAdminRole($user);

            $condominium->administrators()->syncWithoutDetaching([
                $user->id => [
                    'role_id' => $data['role_id'] ?? Role::idForCode(User::ROLE_CONDOMINIUM_ADMIN),
                    'approved_at' => Carbon::now(),
                    'approved_by' => $approvedBy->id,
                    'deleted_at' => null,
                ],
            ]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Condominium $condominium, User $admin, array $data): User
    {
        if (! $condominium->administrators()->where('users.id', $admin->id)->exists()) {
            abort(404, 'Administrador no asignado al condominio.');
        }

        if ($admin->isSeniorAdmin()) {
            abort(422, 'Un administrador senior no debe asignarse como administrador de condominio.');
        }

        DB::transaction(function () use ($admin, $condominium, $data): void {
            $userData = collect($data)
                ->only([
                    'first_name',
                    'last_name',
                    'identification_type_id',
                    'identification_number',
                    'mobile_phone',
                    'landline_phone',
                    'email',
                    'password',
                ])
                ->all();

            if (($userData['password'] ?? null) === null) {
                unset($userData['password']);
            }

            if (array_key_exists('first_name', $userData) || array_key_exists('last_name', $userData)) {
                $firstName = $userData['first_name'] ?? $admin->first_name;
                $lastName = $userData['last_name'] ?? $admin->last_name;
                $userData['name'] = User::fullName($firstName, $lastName);
            }

            if ($userData !== []) {
                $admin->forceFill($userData)->save();
            }

            $this->ensureCondominiumAdminRole($admin);

            $pivotData = collect($data)->only(['role_id'])->all();

            if ($pivotData !== []) {
                $condominium->administrators()->updateExistingPivot($admin->id, $pivotData);
            }
        });

        return $admin;
    }

    public function remove(Condominium $condominium, User $admin): bool
    {
        return DB::table('condominium_user')
            ->where('condominium_id', $condominium->id)
            ->where('user_id', $admin->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]) > 0;
    }

    private function ensureCondominiumAdminRole(User $user): void
    {
        if (! $user->isCondominiumAdmin()) {
            $user->forceFill(['role' => User::ROLE_CONDOMINIUM_ADMIN])->save();
        }
    }
}
