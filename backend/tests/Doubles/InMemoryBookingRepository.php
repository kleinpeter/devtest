<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Enum\BookingStatus;
use App\Domain\Barbershop\Exception\NotFoundException;
use App\Domain\Barbershop\Repository\BookingRepositoryInterface;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;

final class InMemoryBookingRepository implements BookingRepositoryInterface
{
    /** @var array<string, Booking> */
    private array $bookings = [];

    public function getById(Uuid $id): Booking
    {
        return $this->bookings[$id->toString()]
            ?? throw new NotFoundException("Booking {$id} not found");
    }

    public function save(Booking $booking): void
    {
        $this->bookings[$booking->getId()->toString()] = $booking;
    }

    public function hasOverlappingBooking(Stylist $stylist, DateTimeImmutable $startTime, DateTimeImmutable $endTime): bool
    {
        foreach ($this->bookings as $booking) {
            if (!$booking->getStylist()->getId()->equals($stylist->getId())) {
                continue;
            }

            if ($booking->getStatus() === BookingStatus::Rejected) {
                continue;
            }

            if ($booking->getStartTime() < $endTime && $booking->getEndTime() > $startTime) {
                return true;
            }
        }

        return false;
    }

    /** @return Booking[] */
    public function all(): array
    {
        return array_values($this->bookings);
    }
}
