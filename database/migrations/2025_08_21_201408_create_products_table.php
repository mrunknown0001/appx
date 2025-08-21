<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->foreignId('product_category_id')->constrained()->onDelete('restrict');
            $table->foreignId('unit_id')->constrained()->onDelete('restrict');
            $table->string('manufacturer')->nullable();
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->integer('min_stock_level')->default(0);
            $table->integer('max_stock_level')->default(1000);
            $table->boolean('is_prescription_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_category_id', 'is_active']);
            $table->index(['name', 'is_active']);
            $table->index('generic_name');
            $table->index('manufacturer');
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
};