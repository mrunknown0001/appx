<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryBatch;
use Carbon\Carbon;

class SampleSalesDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating sample sales data for forecasting...');

        // Get existing products
        $products = Product::with('inventoryBatches')->take(10)->get();

        if ($products->isEmpty()) {
            $this->command->error('No products found! Please create products first.');
            return;
        }

        $this->command->info("Found {$products->count()} products. Generating sales history...");

        // Generate sales for the last 3 years (36 months)
        $startDate = Carbon::now()->subYears(3);
        $endDate = Carbon::now();

        $saleNumber = 1000;

        // Generate sales for each week
        $currentDate = $startDate->copy();
        $weekCounter = 0;
        
        while ($currentDate <= $endDate) {
            $weekCounter++;
            
            // Create 5-15 sales per week (randomly)
            $salesPerWeek = rand(5, 15);

            for ($i = 0; $i < $salesPerWeek; $i++) {
                // Random date within the week
                $saleDate = $currentDate->copy()->addDays(rand(0, 6));

                // Skip if in the future
                if ($saleDate > Carbon::now()) {
                    continue;
                }

                // Create sale
                $sale = Sale::create([
                    'sale_number' => 'SALE-' . str_pad($saleNumber++, 6, '0', STR_PAD_LEFT),
                    'customer_name' => $this->generateRandomName(),
                    'customer_phone' => $this->generatePhoneNumber(),
                    'sale_date' => $saleDate,
                    'subtotal' => 0,
                    'tax_amount' => 0,
                    'discount_amount' => 0,
                    'total_amount' => 0,
                    'payment_method' => ['cash', 'credit_card', 'debit_card', 'gcash', 'maya'][rand(0, 3)],
                    'status' => 'completed',
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);

                // Add 1-5 products to this sale (but max available products)
                $itemsCount = rand(1, min(5, $products->count()));
                $subtotal = 0;

                // Get unique random products for this sale
                $selectedProducts = $products->shuffle()->take($itemsCount);

                foreach ($selectedProducts as $product) {
                    // Get an active batch for this product
                    $batch = $product->inventoryBatches()
                        ->where('status', 'active')
                        ->where('current_quantity', '>', 0)
                        ->first();

                    // If no active batch, create one
                    if (!$batch) {
                        $batch = InventoryBatch::create([
                            'product_id' => $product->id,
                            'batch_number' => 'BATCH-' . strtoupper(uniqid()),
                            'initial_quantity' => 1000,
                            'current_quantity' => 1000,
                            'expiry_date' => Carbon::now()->addYears(2),
                            'stock_entry_id' => 1, // Assuming a default stock entry
                            'status' => 'active',
                        ]);
                    }

                    // Random quantity (1-10)
                    $quantity = rand(1, 10);
                    
                    // Make sure we don't exceed batch quantity
                    if ($batch->current_quantity < $quantity) {
                        $quantity = max(1, $batch->current_quantity);
                    }

                    // Use product selling price or generate random price
                    $unitPrice = $batch->stockEntry->selling_price ?? rand(50, 500);
                    $totalPrice = $quantity * $unitPrice;
                    
                    // Random discount (0-10%)
                    $discountAmount = $totalPrice * (rand(0, 10) / 100);
                    $finalPrice = $totalPrice - $discountAmount;

                    // Create sale item
                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'inventory_batch_id' => $batch->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $finalPrice,
                        'discount_amount' => $discountAmount,
                        'created_at' => $saleDate,
                        'updated_at' => $saleDate,
                    ]);

                    $subtotal += $totalPrice;

                    // Update batch quantity (manually to avoid event issues)
                    $batch->current_quantity = max(0, $batch->current_quantity - $quantity);
                    $batch->save();
                }

                // Update sale totals
                $taxAmount = $subtotal * 0.12; // 12% VAT
                $totalAmount = $subtotal + $taxAmount;

                $sale->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $totalAmount,
                ]);
            }

            // Progress indicator every 10 weeks
            if ($weekCounter % 10 == 0) {
                $this->command->info("  Processing week {$weekCounter}...");
            }

            // Move to next week
            $currentDate->addWeek();
        }

        $totalSales = Sale::count();
        $totalItems = SaleItem::count();
        $totalWeeks = $weekCounter;
        
        $this->command->info("✓ Sample data created successfully!");
        $this->command->info("  - Total Sales: {$totalSales}");
        $this->command->info("  - Total Sale Items: {$totalItems}");
        $this->command->info("  - Total Weeks: {$totalWeeks}");
        $this->command->info("  - Date Range: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
        $this->command->info("\nYou can now test the forecasting feature with 3 years of data!");
    }

    /**
     * Generate random customer name
     */
    private function generateRandomName(): string
    {
        $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Miguel', 'Sofia', 'Carlos', 'Elena'];
        $lastNames = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Ramos', 'Torres', 'Flores', 'Rivera', 'Gonzales'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    /**
     * Generate random Philippine phone number
     */
    private function generatePhoneNumber(): string
    {
        $prefixes = ['0917', '0918', '0919', '0920', '0921', '0922', '0923', '0924', '0925'];
        return $prefixes[array_rand($prefixes)] . rand(1000000, 9999999);
    }
}