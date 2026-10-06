<?php

namespace Tests\Feature\Casts;

use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UtcDateTimeTest extends TestCase
{
    public function test_reading_back_returns_the_normalized_value_not_the_assigned_object()
    {
        $assigned = Carbon::parse('2026-11-10 09:30:15.123456', 'America/Sao_Paulo');
        $ticket = new Appointment;

        $ticket->starts_at = $assigned;
        $read = $ticket->starts_at;

        // Imutável, em UTC e sem microssegundos, igual ao que o banco devolve.
        $this->assertInstanceOf(CarbonImmutable::class, $read);
        $this->assertSame('2026-11-10 12:30:15.000000 UTC', $read->format('Y-m-d H:i:s.u e'));

        // Mexer no objeto original não altera o atributo.
        $assigned->addHour();
        $this->assertSame('12:30:15', $ticket->starts_at->format('H:i:s'));
    }
}
