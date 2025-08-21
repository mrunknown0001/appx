<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('stock_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('restrict');
            $table->string('supplier_name');
            $table->string('invoice_number')->nullable();
            $table->date('entry_date');
            $table->integer('quantity_received');
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('total_cost', 12, 2);
            $table->date('expiry_date');
            $table->string('batch_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'entry_date']);
            $table->index(['expiry_date']);
            $table->index(['supplier_name']);
            $table->index(['batch_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_entries');
    }
};