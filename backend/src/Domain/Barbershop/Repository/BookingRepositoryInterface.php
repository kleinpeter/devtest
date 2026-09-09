<?php

declare(strict_types=1);

namespace App\Domain\Barbershop\Repository;

use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Exception\NotFoundException;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

interface BookingRepositoryInterface
{
    /** @throws NotFoundException */
    public function getById(Uuid $id): Booking;

    public function save(Booking $booking): void;

    /**
     * True when a non-rejected booking for this stylist overlaps the given interval.
     * Same overlap rule as GetAvailableSlotsQueryHandler:
     * existing.start < requested.end AND existing.end > requested.start.
     */
    public function hasOverlappingBooking(Stylist $stylist, DateTimeImmutable $startTime, DateTimeImmutable $endTime): bool;
}
