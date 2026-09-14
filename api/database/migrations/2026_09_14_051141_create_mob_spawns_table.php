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
        Schema::create('mob_spawns', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('bnpc_name_id');
            $table->unsignedInteger('bnpc_base_id');
            $table->unsignedInteger('territory_type_id');

            $table->decimal('x', 8, 2);
            $table->decimal('y', 8, 2);
            $table->decimal('z', 10, 5)->nullable();

            $table->unsignedInteger('subtype')->default(0);

            $table->timestamps();

            $table->index('bnpc_name_id');
            $table->index(['bnpc_name_id', 'bnpc_base_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mob_spawns');
    }
};
