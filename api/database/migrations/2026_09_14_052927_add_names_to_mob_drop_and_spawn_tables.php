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
        Schema::table('mob_drops', function (Blueprint $table) {
            $table->string('mob_name')->nullable()->after('bnpc_name_id');
        });

        Schema::table('mob_spawns', function (Blueprint $table) {
            $table->string('territory_name')->nullable()->after('territory_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('mob_drops', function (Blueprint $table) {
            $table->dropColumn('mob_name');
        });

        Schema::table('mob_spawns', function (Blueprint $table) {
            $table->dropColumn('territory_name');
        });
    }
};
