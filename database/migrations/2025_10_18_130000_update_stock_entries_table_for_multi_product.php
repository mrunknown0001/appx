<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->unsignedInteger('total_quantity')->default(0)->after('quantity_received');
            $table->unsignedInteger('items_count')->default(0)->after('total_quantity');
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->decimal('total_cost', 15, 4)->change();
            $table->decimal('unit_cost', 12, 4)->nullable()->change();
            $table->decimal('selling_price', 12, 4)->nullable()->change();
            $table->unsignedInteger('quantity_received')->nullable()->change();
            $table->date('expiry_date')->nullable()->change();
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropColumn(['total_quantity', 'items_count']);
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->decimal('total_cost', 12, 2)->change();
            $table->decimal('unit_cost', 10, 2)->nullable(false)->change();
            $table->decimal('selling_price', 10, 2)->nullable(false)->change();
            $table->unsignedInteger('quantity_received')->nullable(false)->change();
            $table->date('expiry_date')->nullable(false)->change();
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        });
    }
};