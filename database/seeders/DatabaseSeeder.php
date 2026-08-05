<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Models\UserFurniture;
use App\Models\UserFurnitureImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $admin = Admin::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'name'     => 'Super Admin',
                    'password' => bcrypt('password123'),
                    'status'   => 'active',
                ]
            );

            $user = User::updateOrCreate(
                ['email' => 'user@example.com'],
                [
                    'name'         => 'Budi User',
                    'phone_number' => '08123456789',
                    'address'      => 'Jl. Contoh No. 1, Jakarta',
                    'password'     => bcrypt('password123'),
                    'status'       => 'active',
                ]
            );

            $ikea = Brand::updateOrCreate(['name' => 'IKEA'], ['description' => 'Perabotan rumah asal Swedia.']);
            $informa = Brand::updateOrCreate(['name' => 'Informa'], ['description' => 'Brand furnitur lokal.']);

            $sofa = Category::updateOrCreate(['name' => 'Sofa'], ['description' => 'Sofa & kursi duduk.']);
            $meja = Category::updateOrCreate(['name' => 'Meja'], ['description' => 'Meja berbagai fungsi.']);

            $prodA = Product::updateOrCreate(
                ['name' => 'Sofa IKEA Friheten'],
                [
                    'category_id' => $sofa->id,
                    'brand_id'    => $ikea->id,
                    'description' => 'Sofa bed 3 dudukan, kondisi 90%.',
                    'status'      => 'available',
                ]
            );
            ProductImage::updateOrCreate(
                ['product_id' => $prodA->id, 'image_url' => '/storage/products/friheten-1.jpg'],
                ['sort_order' => 0]
            );

            $prodB = Product::updateOrCreate(
                ['name' => 'Meja Kerja Informa'],
                [
                    'category_id' => $meja->id,
                    'brand_id'    => $informa->id,
                    'description' => 'Meja kerja ergonomis.',
                    'status'      => 'available',
                ]
            );

            $furn = UserFurniture::updateOrCreate(
                ['user_id' => $user->id, 'name' => 'Kursi Kayu Bekas'],
                [
                    'category_id' => $sofa->id,
                    'brand_id'    => $informa->id,
                    'description' => 'Kursi kayu jati, masih kokoh.',
                    'status'      => 'available',
                ]
            );
            UserFurnitureImage::updateOrCreate(
                ['user_furniture_id' => $furn->id, 'image_url' => '/storage/user-furnitures/kursi-1.jpg'],
                ['sort_order' => 0]
            );
        });
    }
}