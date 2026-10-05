<?php

namespace App\Scheduling;

use DomainException;
use Throwable;

class BookingFailed extends DomainException
{
    public function __construct(public readonly BookingFailure $reason, ?Throwable $previous = null)
    {
        parent::__construct($reason->message(), previous: $previous);
    }
}
