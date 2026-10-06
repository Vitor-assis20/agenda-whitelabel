<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'professional_id',
        'service_id',
        'customer_id',
        'starts_at',
        'ends_at',
        'status',
        'price_cents',
        'notes',
        'cancelled_at',
        'booked_at',
    ];

    protected static function booted(): void
    {
        // Pelo relógio da aplicação (não o now() do banco), que os testes controlam.
        static::creating(function (self $appointment) {
            $appointment->booked_at ??= now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
            'cancelled_at' => UtcDateTime::class,
            'booked_at' => UtcDateTime::class,
            'status' => AppointmentStatus::class,
            'price_cents' => 'integer',
        ];
    }

    /**
     * Agendamentos que ocupam o horário do profissional.
     *
     * @param  Builder<self>  $query
     */
    public function scopeBlocking(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::blocking());
    }

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<AppointmentReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    /**
     * O cliente pode cancelar pelo link enquanto o atendimento não começou.
     */
    public function cancellableByCustomer(): bool
    {
        return $this->status->blocksSchedule() && $this->starts_at > now();
    }
}
