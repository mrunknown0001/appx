<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_entries', 'items_count')) {
                $table->unsignedInteger('items_count')->default(0)->after('entry_date');
            }

            if (! Schema::hasColumn('stock_entries', 'total_quantity')) {
                $table->unsignedInteger('total_quantity')->default(0)->after('items_count');
            }

            if (! Schema::hasColumn('stock_entries', 'total_cost')) {
                $table->decimal('total_cost', 14, 4)->default(0)->after('total_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_entries', function (Blueprint $table) {
            if (Schema::hasColumn('stock_entries', 'items_count')) {
                $table->dropColumn('items_count');
            }

            if (Schema::hasColumn('stock_entries', 'total_quantity')) {
                $table->dropColumn('total_quantity');
            }

            if (Schema::hasColumn('stock_entries', 'total_cost')) {
                $table->dropColumn('total_cost');
            }
        });
    }
};