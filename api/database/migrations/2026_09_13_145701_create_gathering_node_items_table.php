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
        Schema::create('gathering_node_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('gathering_node_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('gathering_item_id');

            $table->foreign('gathering_node_id')
                ->references('id')
                ->on('gathering_nodes')
                ->cascadeOnDelete();

            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->cascadeOnDelete();

            $table->unique([
                'gathering_node_id',
                'item_id',
            ]);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gathering_node_items');
    }
};
