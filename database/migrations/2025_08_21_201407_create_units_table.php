<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('abbreviation', 10);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique('abbreviation');
        });
    }

    public function down()
    {
        Schema::dropIfExists('units');
    }
};