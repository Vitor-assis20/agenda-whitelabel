<?php

namespace Tests\Feature\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Tenant;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regras garantidas pelo próprio Postgres, independentes do código da aplicação.
 */
class AppointmentConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private const EXCLUSION_VIOLATION = '23P01';

    private const FOREIGN_KEY_VIOLATION = '23503';

    private const CHECK_VIOLATION = '23514';

    private Professional $professional;

    protected function setUp(): void
    {
        parent::setUp();

        $this->professional = Professional::factory()->create();
    }

    public function test_overlapping_appointments_for_the_same_professional_are_rejected()
    {
        $this->book('2026-11-10 10:00', 60);

        $this->assertRejectedByDatabase(self::EXCLUSION_VIOLATION, fn () => $this->book('2026-11-10 10:30', 60));
        $this->assertRejectedByDatabase(self::EXCLUSION_VIOLATION, fn () => $this->book('2026-11-10 09:30', 60));
        $this->assertRejectedByDatabase(self::EXCLUSION_VIOLATION, fn () => $this->book('2026-11-10 10:15', 15));
    }

    public function test_back_to_back_appointments_are_allowed()
    {
        $this->book('2026-11-10 10:00', 60);
        $this->book('2026-11-10 11:00', 30);
        $this->book('2026-11-10 09:00', 60);

        $this->assertSame(3, Appointment::withoutGlobalScopes()->count());
    }

    public function test_different_professionals_can_take_the_same_slot()
    {
        $this->book('2026-11-10 10:00', 60);

        Appointment::factory()
            ->for($this->professional->tenant)
            ->at('2026-11-10 10:00')
            ->create();

        $this->assertSame(2, Appointment::withoutGlobalScopes()->count());
    }

    public function test_inactive_statuses_do_not_block_the_slot()
    {
        $this->book('2026-11-10 10:00', 60, AppointmentStatus::Cancelled);
        $this->book('2026-11-10 10:00', 60, AppointmentStatus::NoShow);

        $this->book('2026-11-10 10:00', 60, AppointmentStatus::Pending);

        $this->assertSame(3, Appointment::withoutGlobalScopes()->count());
    }

    public function test_reactivating_a_cancelled_appointment_over_a_taken_slot_is_rejected()
    {
        $cancelled = $this->book('2026-11-10 10:00', 60, AppointmentStatus::Cancelled);
        $this->book('2026-11-10 10:00', 60);

        $this->assertRejectedByDatabase(
            self::EXCLUSION_VIOLATION,
            fn () => $cancelled->update(['status' => AppointmentStatus::Confirmed]),
        );
    }

    public function test_appointment_cannot_reference_a_professional_from_another_tenant()
    {
        $outroTenant = Tenant::factory()->create();

        $this->assertRejectedByDatabase(
            self::FOREIGN_KEY_VIOLATION,
            fn () => Appointment::factory()->for($outroTenant)->create(['professional_id' => $this->professional->id]),
        );
    }

    public function test_appointment_must_end_after_it_starts()
    {
        $this->assertRejectedByDatabase(
            self::CHECK_VIOLATION,
            fn () => $this->book('2026-11-10 10:00', 0),
        );
    }

    private function book(string $startsAt, int $minutes, AppointmentStatus $status = AppointmentStatus::Confirmed): Appointment
    {
        return Appointment::factory()
            ->for($this->professional->tenant)
            ->at($startsAt, $minutes)
            ->status($status)
            ->create(['professional_id' => $this->professional->id]);
    }

    /**
     * Roda o callback num savepoint: no Postgres, um erro aborta a transação
     * inteira, e o RefreshDatabase já envolve o teste numa transação.
     */
    private function assertRejectedByDatabase(string $sqlState, Closure $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $e) {
            $this->assertSame($sqlState, $e->getCode(), $e->getMessage());

            return;
        }

        $this->fail("O banco deveria ter rejeitado a operação com SQLSTATE {$sqlState}.");
    }
}
