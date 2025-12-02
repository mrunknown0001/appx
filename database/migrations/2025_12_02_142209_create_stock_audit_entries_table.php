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
        Schema::create('stock_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_audit_id')->constrained('stock_audits')->onDelete('cascade');
            $table->unsignedBigInteger('product_id')->constrained('products')->onDelete('cascade');
            $table->integer('expected_quantity')->nullable();
            $table->integer('actual_quantity')->nullable();
            $table->boolean('matched')->default(false);
            $table->boolean('is_audited')->defaul('false');
            $table->foreign('stock_audit_id')->references('id')->on('stock_audits')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_audit_entries');
    }
};
