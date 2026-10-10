<?php

namespace App\Traits;

use DateTimeInterface;

trait ExactDateSerialization
{
    /**
     * Prepare a date for array / JSON serialization.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
