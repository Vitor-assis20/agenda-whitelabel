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
        // Necessária para combinar "=" (btree) e "&&" (gist) na mesma exclusion constraint.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('professional_id');
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('customer_id');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status')->default('pending');
            // Preço congelado no momento da reserva: mudar o serviço não altera o histórico.
            $table->unsignedInteger('price_cents');
            $table->text('notes')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestamps();

            // Histórico não some: profissional/serviço/cliente com agendamentos devem ser desativados, não apagados.
            $table->foreign(['tenant_id', 'professional_id'])->references(['tenant_id', 'id'])->on('professionals')->restrictOnDelete();
            $table->foreign(['tenant_id', 'service_id'])->references(['tenant_id', 'id'])->on('services')->restrictOnDelete();
            $table->foreign(['tenant_id', 'customer_id'])->references(['tenant_id', 'id'])->on('customers')->restrictOnDelete();

            $table->index(['tenant_id', 'starts_at']);
            $table->index(['customer_id', 'starts_at']);
        });

        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed', 'no_show'))");
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_valid_range CHECK (ends_at > starts_at)');

        // Garantia anti-double-booking no banco: dois agendamentos ativos do mesmo
        // profissional nunca se sobrepõem, mesmo sob requisições concorrentes.
        // O intervalo é semiaberto [início, fim), então horários colados são permitidos.
        DB::statement(<<<'SQL'
            ALTER TABLE appointments ADD CONSTRAINT appointments_no_overlap
            EXCLUDE USING gist (
                professional_id WITH =,
                tstzrange(starts_at, ends_at, '[)') WITH &&
            ) WHERE (status IN ('pending', 'confirmed'))
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
