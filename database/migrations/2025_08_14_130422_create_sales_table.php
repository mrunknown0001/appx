<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number')->unique();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->timestamp('sale_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->enum('payment_method', ['cash', 'card', 'digital_wallet', 'bank_transfer', 'credit'])->default('cash');
            $table->enum('status', ['pending', 'completed', 'cancelled', 'refunded'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['sale_date', 'status']);
            $table->index(['customer_phone']);
            $table->index(['payment_method']);
            $table->index(['total_amount']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('sales');
    }
};