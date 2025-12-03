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
        Schema::table('stock_audits', function (Blueprint $table) {
            $table->dropColumn('requested_by');
            $table->string('requested_by')->nullable();
            $table->dropColumn('audited_by');
            $table->string('audited_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_audits', function (Blueprint $table) {
            $table->dropColumn('requested_by');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->dropColumn('audited_by');
            $table->unsignedBigInteger('audited_by')->nullable();
        });
    }
};
