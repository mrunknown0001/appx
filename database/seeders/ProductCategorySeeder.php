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
        $unitBottles = Unit::firstOrCreate(['name' => 'Bottles', 'abbreviation' => 'btl']);
        $unitPacks = Unit::firstOrCreate(['name' => 'Packs', 'abbreviation' => 'pack']);
        $unitStrips = Unit::firstOrCreate(['name' => 'Strips', 'abbreviation' => 'strip']);
        $unitTubes = Unit::firstOrCreate(['name' => 'Tubes', 'abbreviation' => 'tube']);

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
                'min_stock_level' => 2000,
                'max_stock_level' => 100000,
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
                'min_stock_level' => 2000,
                'max_stock_level' => 100000,
            ],
            [
                'name' => 'Johnson’s Baby Bath (500ml)',
                'description' => 'Johnson’s Baby Bath (500ml)',
                'sku' => 'JJ-00001',
                'barcode' => '6789012345123',
                'product_category_id' => $babyCareCategory->id,
                'unit_id' => $unitBottles->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 100,
                'max_stock_level' => 1000,
            ],
            [
                'name' => 'Huggies Dry Diapers (Medium Pack)',
                'description' => 'Huggies Dry Diapers (Medium Pack)',
                'sku' => 'HG-00001',
                'barcode' => '6789012345124',
                'product_category_id' => $babyCareCategory->id,
                'unit_id' => $unitPacks->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 100,
                'max_stock_level' => 1000,
            ],
            [
                'name' => 'Band-Aid Flexible Fabric Strips (20s)',
                'description' => 'Band-Aid Flexible Fabric Strips (20s)',
                'sku' => 'BA-00001',
                'barcode' => '6789012345125',
                'product_category_id' => $firstAidCategory->id,
                'unit_id' => $unitStrips->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 200,
                'max_stock_level' => 2000,
            ],
            [
                'name' => 'Betadine Antiseptic Solution (60ml)',
                'description' => 'Betadine Antiseptic Solution (60ml)',
                'sku' => 'BAS-00001',
                'barcode' => '6789012345126',
                'product_category_id' => $firstAidCategory->id,
                'unit_id' => $unitBottles->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 200,
                'max_stock_level' => 2000,
            ],
            [
                'name' => 'Cetaphil Gentle Skin Cleanser (250ml)',
                'description' => 'Cetaphil Gentle Skin Cleanser (250ml)',
                'sku' => 'CET-00001',
                'barcode' => '6789012345127',
                'product_category_id' => $personalCareCategory->id,
                'unit_id' => $unitBottles->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 200,
                'max_stock_level' => 2000,
            ],
            [
                'name' => 'Colgate Total Toothpaste (120g)',
                'description' => 'Colgate Total Toothpaste (120g)',
                'sku' => 'CGT-00001',
                'barcode' => '6789012345128',
                'product_category_id' => $personalCareCategory->id,
                'unit_id' => $unitTubes->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 200,
                'max_stock_level' => 2000,
            ],
            [
                'name' => 'Vitamin C 500mg (Ascorbic Acid)',
                'description' => 'Vitamin C 500mg (Ascorbic Acid)',
                'sku' => 'VIT-00001',
                'barcode' => '6789012345129',
                'product_category_id' => $supplementCategory->id,
                'unit_id' => $unitStrips->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 1000,
                'max_stock_level' => 5000,
            ],
            [
                'name' => 'Fish Oil Omega-3 Softgels',
                'description' => 'Fish Oil Omega-3 Softgels',
                'sku' => 'VIT-00002',
                'barcode' => '6789012345130',
                'product_category_id' => $supplementCategory->id,
                'unit_id' => $unitBottles->id,
                'is_active' => true,
                'is_prescription_required' => false,
                'min_stock_level' => 1000,
                'max_stock_level' => 5000,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate($product);
        }
    }
}