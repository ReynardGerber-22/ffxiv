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
        Schema::create('gathering_nodes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();

            $table->string('gathering_type');
            $table->unsignedInteger('gathering_level');

            $table->string('area_name');
            $table->string('territory_name');

            $table->unsignedBigInteger('map_id');

            $table->decimal('raw_x', 10, 4)->nullable();
            $table->decimal('raw_y', 10, 4)->nullable();
            $table->unsignedInteger('radius')->nullable();

            $table->unsignedInteger('map_size_factor');
            $table->integer('map_offset_x');
            $table->integer('map_offset_y');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gathering_nodes');
    }
};
