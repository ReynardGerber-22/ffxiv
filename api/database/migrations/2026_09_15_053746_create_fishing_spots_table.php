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
        Schema::create('fishing_spots', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();

            $table->string('name');
            $table->unsignedInteger('fishing_level');

            $table->string('territory_name');

            $table->unsignedBigInteger('map_id')->nullable();

            $table->decimal('raw_x', 10, 4)->nullable();
            $table->decimal('raw_z', 10, 4)->nullable();
            $table->unsignedInteger('radius')->nullable();

            $table->unsignedInteger('map_size_factor')->nullable();
            $table->integer('map_offset_x')->nullable();
            $table->integer('map_offset_y')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_spots');
    }
};
