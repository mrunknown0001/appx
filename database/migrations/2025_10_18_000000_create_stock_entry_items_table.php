<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_entry_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_received');
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('total_cost', 14, 4)->default(0);
            $table->decimal('selling_price', 12, 4)->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('batch_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stock_entry_id', 'product_id']);
            $table->index(['product_id', 'expiry_date']);
            $table->index(['batch_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_entry_items');
    }
};