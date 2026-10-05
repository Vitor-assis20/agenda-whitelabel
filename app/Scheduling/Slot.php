<?php

namespace App\Scheduling;

use Carbon\CarbonImmutable;

/**
 * Intervalo semiaberto [startsAt, endsAt), sempre em UTC.
 */
final readonly class Slot
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}

    public function overlaps(self $other): bool
    {
        return $this->startsAt < $other->endsAt && $other->startsAt < $this->endsAt;
    }

    public function within(self $other): bool
    {
        return $this->startsAt >= $other->startsAt && $this->endsAt <= $other->endsAt;
    }
}
