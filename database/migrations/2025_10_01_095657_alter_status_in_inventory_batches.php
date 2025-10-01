<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            // change status enum to: active, expired, out_of_stock
            $table->dropColumn('status');
            $table->enum('status', ['active', 'expired', 'out_of_stock'])->default('active')->after('location');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_batches', function (Blueprint $table) {
            // revert status enum to: active, expired, depleted
            $table->dropColumn('status');
            $table->enum('status', ['active', 'expired', 'depleted'])->default('active')->after('location');
        });
    }
};
