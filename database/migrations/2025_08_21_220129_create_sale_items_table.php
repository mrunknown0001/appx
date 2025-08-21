<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->foreignId('inventory_batch_id')->constrained()->onDelete('restrict');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 12, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['sale_id']);
            $table->index(['product_id', 'created_at']);
            $table->index(['inventory_batch_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sale_items');
    }
};