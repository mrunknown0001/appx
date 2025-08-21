<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Additional indexes for analytics and reporting
        Schema::table('sale_items', function (Blueprint $table) {
            $table->index(['created_at', 'product_id'], 'sale_items_analytics_idx');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->index(['expiry_date', 'current_quantity'], 'inventory_expiry_qty_idx');
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->index(['entry_date', 'product_id'], 'stock_entries_analytics_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['min_stock_level', 'max_stock_level'], 'products_stock_levels_idx');
        });
    }

    public function down()
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropIndex('sale_items_analytics_idx');
        });

        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropIndex('inventory_expiry_qty_idx');
        });

        Schema::table('stock_entries', function (Blueprint $table) {
            $table->dropIndex('stock_entries_analytics_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_stock_levels_idx');
        });
    }
};