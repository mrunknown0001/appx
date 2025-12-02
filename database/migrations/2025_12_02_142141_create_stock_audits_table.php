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
            $table->unsignedBigInteger('requested_by')->constrained('users')->onDelete('cascade');
            $table->date('date_requested')->nullable();
            $table->unsignedBigInteger('audited_by')->constrained('users')->onDelete('cascade')->nullable();
            $table->date('date_audited')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('pending');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('audited_by')->references('id')->on('users')->onDelete('cascade');
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
