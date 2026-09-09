<?php

declare(strict_types=1);

namespace App\Domain\Barbershop\Repository;

use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Exception\NotFoundException;
use App\Domain\Barbershop\Exception\SlotUnavailableException;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface BookingRepositoryInterface
{
    /** @throws NotFoundException */
    public function getById(Uuid $id): Booking;

    /**
     * Tells whether the stylist already has a non-rejected booking overlapping the given interval.
     */
    public function hasOverlappingBooking(
        Stylist $stylist,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
    ): bool;

    /** @throws SlotUnavailableException when the slot was taken by a concurrent request */
    public function save(Booking $booking): void;
}
