<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $user->role = Role::from($data['role']);
            $user->save();

            $this->syncTeams($user, $data['team_ids'] ?? []);

            return $user->load('teams');
        });
    }

    public function update(User $user, User $actor, array $data): User
    {
        return DB::transaction(function () use ($user, $actor, $data) {
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'password'])));

            if (isset($data['role'])) {
                $this->changeRole($user, $actor, Role::from($data['role']));
            }

            $user->save();

            if (array_key_exists('team_ids', $data)) {
                $this->syncTeams($user, $data['team_ids'] ?? []);
            }

            return $user->load('teams');
        });
    }

    private function changeRole(User $user, User $actor, Role $role): void
    {
        if ($user->role === $role) {
            return;
        }

        if ($user->is($actor) && $role !== Role::ADMIN) {
            throw ValidationException::withMessages([
                'role' => ['You cannot remove your own admin role.'],
            ]);
        }

        $user->role = $role;

        // Tokens carry the old privileges implicitly; force a fresh login.
        $user->tokens()->delete();
    }

    /**
     * Only staff belong to teams.
     */
    private function syncTeams(User $user, array $teamIds): void
    {
        $user->teams()->sync($user->isStaff() ? $teamIds : []);
    }
}
