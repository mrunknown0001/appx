<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->foreignId('stock_entry_item_id')
                ->nullable()
                ->after('stock_entry_id')
                ->constrained('stock_entry_items')
                ->cascadeOnUpdate()
                ->nullOnDelete()
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            $table->dropForeign(['stock_entry_item_id']);
            $table->dropColumn('stock_entry_item_id');
        });
    }
};