<?php

namespace Tests\Feature\Repositories;

use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public static function operationalScopes(): array
    {
        return [
            'network' => [RoleScope::NETWORK],
            'pos' => [RoleScope::POS],
        ];
    }

    #[DataProvider('operationalScopes')]
    public function test_operational_user_creation_does_not_write_the_generated_role_scope_column(RoleScope $scope): void
    {
        $user = (new UserRepository(new User()))->createForScope([
            'role_id' => null,
            'role_scope' => $scope->value,
            'user_full_name' => 'Operational Scope Test',
            'user_phone_number' => '77'.random_int(1000000, 9999999),
            'user_password' => Hash::make('SecurePass123'),
            'status' => UserStatus::PENDING,
        ], $scope);

        $this->assertModelExists($user);
        $this->assertDatabaseHas('users', [
            'user_id' => $user->user_id,
            'role_id' => null,
            'role_scope' => null,
        ]);
        $this->assertNull($user->fresh()->role_scope);
    }

    public function test_create_for_scope_rejects_platform_accounts(): void
    {
        $this->expectException(LogicException::class);

        (new UserRepository(new User()))->createForScope([
            'role_id' => null,
            'user_full_name' => 'Platform Scope Test',
            'user_phone_number' => '771234567',
            'user_password' => Hash::make('SecurePass123'),
            'status' => UserStatus::PENDING,
        ], RoleScope::PLATFORM);
    }

    public function test_create_for_scope_rejects_operational_users_with_a_global_role(): void
    {
        $this->expectException(LogicException::class);

        (new UserRepository(new User()))->createForScope([
            'role_id' => 1,
            'user_full_name' => 'Invalid Operational Scope Test',
            'user_phone_number' => '771234568',
            'user_password' => Hash::make('SecurePass123'),
            'status' => UserStatus::PENDING,
        ], RoleScope::NETWORK);
    }
}
