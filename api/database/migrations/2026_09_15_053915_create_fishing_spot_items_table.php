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
        Schema::create('fishing_spot_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('fishing_spot_id');
            $table->unsignedBigInteger('item_id');

            $table->foreign('fishing_spot_id')
                ->references('id')
                ->on('fishing_spots')
                ->cascadeOnDelete();

            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();

            $table->unique(['fishing_spot_id', 'item_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fishing_spot_items');
    }
};
