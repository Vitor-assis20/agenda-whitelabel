<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /**
     * Status que ocupam o horário do profissional.
     *
     * Deve espelhar o WHERE da constraint appointments_no_overlap.
     *
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Pending, self::Confirmed];
    }

    public function blocksSchedule(): bool
    {
        return in_array($this, self::blocking(), true);
    }

    /**
     * Próximos status possíveis. Concluído, cancelado e falta são finais.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
            default => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->transitions(), true);
    }

    /**
     * Status que só fazem sentido depois do início do atendimento.
     */
    public function requiresStart(): bool
    {
        return in_array($this, [self::Completed, self::NoShow], true);
    }

    /**
     * Status que entram no faturamento previsto do dia.
     */
    public function isBillable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Completed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendente',
            self::Confirmed => 'Confirmado',
            self::Cancelled => 'Cancelado',
            self::Completed => 'Concluído',
            self::NoShow => 'Faltou',
        };
    }
}
