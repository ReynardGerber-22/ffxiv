<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fishing_baits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fish_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('fishing_spot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bait_item_id')->constrained('items')->cascadeOnDelete();
            $table->json('source_data')->nullable();
            $table->char('source_hash', 64);
            $table->timestamps();
            $table->unique(['fish_item_id', 'fishing_spot_id', 'bait_item_id', 'source_hash'], 'fishing_baits_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fishing_baits');
    }
};
