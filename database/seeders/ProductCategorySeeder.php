<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProductCategory;
use App\Models\Product;
use App\Models\Unit;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 5 product categories
        $categories = [
            ['name' => 'Medicines', 'description' => 'Prescription and over-the-counter medicines', 'is_active' => true],
            ['name' => 'Supplements', 'description' => 'Dietary supplements and vitamins', 'is_active' => true],
            ['name' => 'First Aid', 'description' => 'First aid supplies and wound care', 'is_active' => true],
            ['name' => 'Personal Care', 'description' => 'Personal hygiene and care products', 'is_active' => true],
            ['name' => 'Baby Care', 'description' => 'Products for infants and toddlers', 'is_active' => true],
        ];

        foreach ($categories as $category) {
            ProductCategory::firstOrCreate($category);
        }

        // Get the created categories
        $medicineCategory = ProductCategory::where('name', 'Medicines')->first();
        $supplementCategory = ProductCategory::where('name', 'Supplements')->first();
        $firstAidCategory = ProductCategory::where('name', 'First Aid')->first();
        $personalCareCategory = ProductCategory::where('name', 'Personal Care')->first();
        $babyCareCategory = ProductCategory::where('name', 'Baby Care')->first();

        // Create units for products
        $unit = Unit::firstOrCreate(['name' => 'Piece', 'abbreviation' => 'pc']);

        // Create 10 products with required fields
        $products = [
            [
                'name' => 'Paracetamol',
                'description' => 'Pain reliever',
                'sku' => 'PARACETAMOL-500MG',
                'barcode' => '1234567890123',
                'product_category_id' => $medicineCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 10,
                'max_stock_level' => 100,
            ],
            [
                'name' => 'Vitamin C',
                'description' => 'Immune system booster',
                'sku' => 'VITAMIN-C-1000MG',
                'barcode' => '2345678901234',
                'product_category_id' => $supplementCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 10,
                'max_stock_level' => 100,
            ],
            [
                'name' => 'Band-Aid',
                'description' => 'Wound care',
                'sku' => 'BAND-AID-ASSORTED',
                'barcode' => '3456789012345',
                'product_category_id' => $firstAidCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 20,
                'max_stock_level' => 200,
            ],
            [
                'name' => 'Toothpaste',
                'description' => 'Oral hygiene',
                'sku' => 'TOOTHPASTE-150ML',
                'barcode' => '4567890123456',
                'product_category_id' => $personalCareCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 15,
                'max_stock_level' => 150,
            ],
            [
                'name' => 'Baby Lotion',
                'description' => 'Skin care for babies',
                'sku' => 'BABY-LOTION-200ML',
                'barcode' => '5678901234567',
                'product_category_id' => $babyCareCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 10,
                'max_stock_level' => 100,
            ],
            [
                'name' => 'Aspirin',
                'description' => 'Pain reliever',
                'sku' => 'ASPIRIN-81MG',
                'barcode' => '6789012345678',
                'product_category_id' => $medicineCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 20,
                'max_stock_level' => 200,
            ],
            [
                'name' => 'Calcium Supplement',
                'description' => 'Bone health',
                'sku' => 'CALCIUM-SUPPLEMENT-500MG',
                'barcode' => '7890123456789',
                'product_category_id' => $supplementCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 15,
                'max_stock_level' => 150,
            ],
            [
                'name' => 'Antiseptic Wipes',
                'description' => 'Wound cleaning',
                'sku' => 'ANTISEPTIC-WIPES-10PCS',
                'barcode' => '8901234567890',
                'product_category_id' => $firstAidCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 20,
                'max_stock_level' => 200,
            ],
            [
                'name' => 'Shampoo',
                'description' => 'Hair care',
                'sku' => 'SHAMPOO-250ML',
                'barcode' => '9012345678901',
                'product_category_id' => $personalCareCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 15,
                'max_stock_level' => 150,
            ],
            [
                'name' => 'Diapers',
                'description' => 'Baby hygiene',
                'sku' => 'DIAPERS-SIZE-M-20PCS',
                'barcode' => '0123456789012',
                'product_category_id' => $babyCareCategory->id,
                'unit_id' => $unit->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 30,
                'max_stock_level' => 300,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate($product);
        }
    }
}