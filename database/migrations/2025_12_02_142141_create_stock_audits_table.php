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
        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_by');
            $table->date('date_requested')->nullable();
            $table->date('target_audit_date')->nullable();
            $table->date('actual_audit_date')->nullable();
            $table->unsignedBigInteger('audited_by')->nullable();
            $table->date('date_audited')->nullable();
            $table->date('completed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_audits');
    }
};
