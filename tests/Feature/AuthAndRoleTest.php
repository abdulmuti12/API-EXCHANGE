<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected User $user;
    protected string $adminToken;
    protected string $userToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBase();
    }

    protected function seedBase(): void
    {
        $this->admin = Admin::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'User Test',
            'email' => 'user@test.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->adminToken = JWTAuth::fromUser($this->admin);
        $this->userToken  = JWTAuth::fromUser($this->user);
    }

    public function test_admin_login_success(): void
    {
        $response = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['access_token', 'admin' => ['id', 'email']]]);
    }

    public function test_admin_login_wrong_password(): void
    {
        $response = $this->postJson('/api/auth/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401);
    }

    public function test_user_register_and_login(): void
    {
        $reg = $this->postJson('/api/auth/user/register', [
            'name' => 'New User',
            'email' => 'newuser@test.com',
            'password' => 'password123',
        ]);
        $reg->assertCreated()->assertJsonPath('data.user.email', 'newuser@test.com');

        $login = $this->postJson('/api/auth/user/login', [
            'email' => 'newuser@test.com',
            'password' => 'password123',
        ]);
        $login->assertOk()->assertJsonStructure(['data' => ['access_token', 'user']]);
    }

    public function test_token_admin_tidak_boleh_akses_endpoint_user(): void
    {
        $this->getJson('/api/user/profile', [
            'Authorization' => "Bearer {$this->adminToken}",
        ])->assertStatus(403);
    }

    public function test_token_user_tidak_boleh_akses_endpoint_admin(): void
    {
        $this->getJson('/api/admin/dashboard', [
            'Authorization' => "Bearer {$this->userToken}",
        ])->assertStatus(403);
    }

    public function test_tanpa_token_mengembalikan_401(): void
    {
        $this->getJson('/api/user/profile')->assertStatus(401);
        $this->getJson('/api/admin/dashboard')->assertStatus(401);
    }
}