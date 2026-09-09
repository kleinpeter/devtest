<?php

declare(strict_types=1);

namespace App\Domain\Barbershop\Exception;

final class SlotUnavailableException extends \DomainException
{
    public static function create(): self
    {
        return new self('This time slot is no longer available');
    }
}
