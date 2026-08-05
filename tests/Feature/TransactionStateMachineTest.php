<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserFurniture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class TransactionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected User $user;
    protected string $adminToken;
    protected string $userToken;
    protected Brand $brand;
    protected Category $category;
    protected Product $product;
    protected UserFurniture $furniture;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->brand     = Brand::create(['name' => 'IKEA']);
        $this->category  = Category::create(['name' => 'Sofa']);

        $this->product = Product::create([
            'name' => 'Sofa Test',
            'brand_id'    => $this->brand->id,
            'category_id' => $this->category->id,
            'status'      => 'available',
        ]);

        $this->furniture = UserFurniture::create([
            'user_id' => $this->user->id,
            'name'    => 'Kursi Test',
            'status'  => 'available',
        ]);
    }

    protected function authUser(): array
    {
        return ['Authorization' => "Bearer {$this->userToken}"];
    }

    protected function authAdmin(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    public function test_submit_transaksi_membuat_record_dengan_status_pending(): void
    {
        $resp = $this->postJson('/api/user/transactions', [
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
        ], $this->authUser());

        $resp->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('transactions', [
            'id'     => $resp->json('data.id'),
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('user_furnitures', [
            'id'     => $this->furniture->id,
            'status' => 'in_transaction',
        ]);
    }

    public function test_submit_transaksi_furniture_bukan_milik_user_ditolak(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $otherFurniture = UserFurniture::create([
            'user_id' => $otherUser->id,
            'name'    => 'Kursi Other',
            'status'  => 'available',
        ]);

        $this->postJson('/api/user/transactions', [
            'user_furniture_id' => $otherFurniture->id,
            'product_id'        => $this->product->id,
        ], $this->authUser())->assertStatus(403);
    }

    public function test_submit_transaksi_furniture_tidak_available_ditolak(): void
    {
        $this->furniture->update(['status' => 'swapped']);

        $this->postJson('/api/user/transactions', [
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
        ], $this->authUser())->assertStatus(422);
    }

    public function test_state_machine_pending_checking_approved_menandai_swapped(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);
        $this->furniture->update(['status' => 'in_transaction']);

        // pending -> checking
        $this->patchJson("/api/admin/transactions/{$trx->id}/checking", [], $this->authAdmin())
            ->assertOk()
            ->assertJsonPath('data.status', 'checking');

        // checking -> approved
        $this->patchJson("/api/admin/transactions/{$trx->id}/approve", [], $this->authAdmin())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('user_furnitures', ['id' => $this->furniture->id, 'status' => 'swapped']);
        $this->assertDatabaseHas('products',       ['id' => $this->product->id,   'status' => 'swapped']);
    }

    public function test_tidak_boleh_approve_langsung_dari_pending(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);

        $this->patchJson("/api/admin/transactions/{$trx->id}/approve", [], $this->authAdmin())
            ->assertStatus(422);
    }

    public function test_tidak_boleh_approve_transaksi_yang_sudah_final(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'approved',
            'admin_id'          => $this->admin->id,
        ]);

        $this->patchJson("/api/admin/transactions/{$trx->id}/approve", [], $this->authAdmin())
            ->assertStatus(422);
    }

    public function test_reject_tanpa_reason_ditolak_validasi(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);

        $this->patchJson("/api/admin/transactions/{$trx->id}/reject", [], $this->authAdmin())
            ->assertStatus(422)
            ->assertJsonPath('error.reject_reason.0', 'The reject reason field is required.');
    }

    public function test_reject_dari_pending_mengembalikan_furniture_ke_available(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);
        $this->furniture->update(['status' => 'in_transaction']);

        $this->patchJson("/api/admin/transactions/{$trx->id}/reject", [
            'reject_reason' => 'Tidak sesuai kriteria',
        ], $this->authAdmin())->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('transactions', [
            'id'     => $trx->id,
            'status' => 'rejected',
            'reject_reason' => 'Tidak sesuai kriteria',
        ]);
        $this->assertDatabaseHas('user_furnitures', [
            'id'     => $this->furniture->id,
            'status' => 'available',
        ]);
    }

    public function test_tidak_boleh_reject_transaksi_yang_sudah_final(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'rejected',
            'admin_id'          => $this->admin->id,
            'reject_reason'     => 'alasan',
        ]);

        $this->patchJson("/api/admin/transactions/{$trx->id}/reject", [
            'reject_reason' => 'lagi',
        ], $this->authAdmin())->assertStatus(422);
    }

    public function test_chat_hanya_boleh_saat_checking(): void
    {
        $trx = Transaction::create([
            'user_id'           => $this->user->id,
            'user_furniture_id' => $this->furniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);

        $this->postJson("/api/user/transactions/{$trx->id}/messages", [
            'message' => 'halo',
        ], $this->authUser())->assertStatus(422);

        $trx->update(['status' => 'checking', 'admin_id' => $this->admin->id]);

        $this->postJson("/api/user/transactions/{$trx->id}/messages", [
            'message' => 'halo',
        ], $this->authUser())->assertCreated();
    }

    public function test_user_hanya_lihat_transaksi_milik_sendiri(): void
    {
        $otherUser = User::create([
            'name' => 'Other',
            'email' => 'other@test.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $otherToken = JWTAuth::fromUser($otherUser);
        $otherFurniture = UserFurniture::create([
            'user_id' => $otherUser->id,
            'name'    => 'Kursi Other',
            'status'  => 'available',
        ]);
        $trx = Transaction::create([
            'user_id'           => $otherUser->id,
            'user_furniture_id' => $otherFurniture->id,
            'product_id'        => $this->product->id,
            'status'            => 'pending',
        ]);

        $this->getJson("/api/user/transactions/{$trx->id}", [
            'Authorization' => "Bearer {$this->userToken}",
        ])->assertStatus(403);
    }
}