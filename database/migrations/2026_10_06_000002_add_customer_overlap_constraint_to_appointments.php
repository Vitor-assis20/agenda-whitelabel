<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A constraint não pode ser criada sobre dados que a violam. Cancelar
        // agendamentos de clientes não é decisão de uma migration: listamos os
        // conflitos e paramos, para alguém resolvê-los antes.
        $conflicts = DB::select(<<<'SQL'
            SELECT a.id AS first, b.id AS second
            FROM appointments a
            JOIN appointments b ON b.customer_id = a.customer_id AND b.id > a.id
            WHERE a.status IN ('pending', 'confirmed')
              AND b.status IN ('pending', 'confirmed')
              AND tstzrange(a.starts_at, a.ends_at, '[)') && tstzrange(b.starts_at, b.ends_at, '[)')
        SQL);

        if ($conflicts !== []) {
            $pairs = collect($conflicts)->map(fn ($c) => "#{$c->first} × #{$c->second}")->join(', ');

            throw new RuntimeException("Há clientes com agendamentos simultâneos ativos ({$pairs}). Cancele um de cada par e rode a migration de novo.");
        }

        // Mesmo cliente não ocupa dois horários sobrepostos, nem com profissionais
        // diferentes. customer_id já é por negócio: clientes de negócios distintos
        // são registros distintos e não se bloqueiam.
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_customer_no_overlap
            EXCLUDE USING gist (
                customer_id WITH =,
                tstzrange(starts_at, ends_at, '[)') WITH &&
            ) WHERE (status IN ('pending', 'confirmed'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_customer_no_overlap');
    }
};
