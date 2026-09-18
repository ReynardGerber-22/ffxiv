<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_npcs', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->string('title')->nullable();
            $table->timestamps();
        });
        Schema::create('gil_shops', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name')->nullable();
            $table->json('source_data')->nullable();
            $table->timestamps();
        });
        Schema::create('gil_shop_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('gil_shops')->cascadeOnDelete();
            $table->unsignedInteger('subrow_id');
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price');
            $table->boolean('is_hq')->default(false);
            $table->json('source_data')->nullable();
            $table->unique(['shop_id', 'subrow_id']);
            $table->timestamps();
        });
        Schema::create('vendor_shop_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_npc_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('gil_shops')->cascadeOnDelete();
            $table->json('source_data')->nullable();
            $table->unique(['vendor_npc_id', 'shop_id']);
            $table->timestamps();
        });
        Schema::create('vendor_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_npc_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('territory_id');
            $table->string('territory');
            $table->unsignedBigInteger('map_id');
            $table->string('area')->nullable();
            $table->double('raw_x')->nullable();
            $table->double('raw_y')->nullable();
            $table->double('raw_z')->nullable();
            $table->double('x');
            $table->double('y');
            $table->string('source', 32);
            $table->string('source_key', 64);
            $table->unique(['vendor_npc_id', 'source', 'source_key'], 'vendor_location_source_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['vendor_locations', 'vendor_shop_links', 'gil_shop_items', 'gil_shops', 'vendor_npcs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
