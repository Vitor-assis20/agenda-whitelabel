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
        // Expediente semanal recorrente, no fuso do tenant (weekday: 0 = domingo).
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedSmallInteger('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();

            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->index(['professional_id', 'weekday']);
        });

        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_weekday_range CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_valid_range CHECK (ends_at > starts_at)');

        // Folgas, férias e bloqueios pontuais.
        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals')->cascadeOnDelete();
            $table->index(['professional_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE time_offs ADD CONSTRAINT time_offs_valid_range CHECK (ends_at > starts_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_offs');
        Schema::dropIfExists('working_hours');
    }
};
