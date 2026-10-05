<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Enums\TenantRole;
use App\Models\Appointment;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cenário comum: Barbearia em São Paulo, terça 10/11/2026 às 8h, com um dono
 * e um profissional (Rafael) que também é membro da equipe.
 */
abstract class PanelTestCase extends TestCase
{
    use RefreshDatabase;

    protected const TIMEZONE = 'America/Sao_Paulo';

    protected const DATE = '2026-11-10';

    protected Tenant $tenant;

    protected User $owner;

    protected User $staff;

    protected Professional $rafael;

    protected Professional $bruno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo($this->localTime('08:00'));

        $this->owner = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->tenant = Tenant::factory()
            ->withUser($this->owner, TenantRole::Owner)
            ->withUser($this->staff, TenantRole::Staff)
            ->create(['name' => 'Barbearia', 'timezone' => self::TIMEZONE]);

        $this->rafael = Professional::factory()->for($this->tenant)->create(['name' => 'Rafael', 'user_id' => $this->staff->id]);
        $this->bruno = Professional::factory()->for($this->tenant)->create(['name' => 'Bruno']);
    }

    protected function localTime(string $time, string $date = self::DATE): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::TIMEZONE);
    }

    protected function appointment(
        Professional $professional,
        string $time,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
        string $date = self::DATE,
        int $priceCents = 5000,
    ): Appointment {
        $startsAt = $this->localTime($time, $date);

        return Appointment::factory()->for($professional->tenant)->status($status)->create([
            'professional_id' => $professional->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'price_cents' => $priceCents,
        ]);
    }
}
