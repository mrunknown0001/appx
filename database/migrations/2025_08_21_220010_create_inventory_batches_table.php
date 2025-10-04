<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('stock_entry_id')->constrained()->onDelete('restrict');
            $table->string('batch_number')->nullable();
            $table->integer('initial_quantity');
            $table->integer('current_quantity');
            $table->date('expiry_date');
            $table->string('location')->nullable();
            $table->enum('status', ['active', 'expired', 'out_of_stock'])->default('active');
            $table->timestamps();

            $table->index(['product_id', 'status']);
            $table->index(['expiry_date', 'status']);
            $table->index(['current_quantity']);
            $table->index(['batch_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_batches');
    }
};