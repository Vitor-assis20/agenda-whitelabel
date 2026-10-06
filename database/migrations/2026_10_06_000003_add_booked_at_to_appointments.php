<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Quando o horário atual foi reservado. Igual ao created_at até a primeira
        // remarcação; os lembretes usam este campo, não o created_at.
        Schema::table('appointments', function (Blueprint $table) {
            $table->timestampTz('booked_at')->nullable();
        });

        DB::statement('UPDATE appointments SET booked_at = created_at');

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestampTz('booked_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('booked_at');
        });
    }
};
