<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->decimal('cost_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('markup_percentage', 5, 2)->nullable();
            $table->date('effective_date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'effective_date']);
            $table->index(['effective_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('price_histories');
    }
};