<?php

declare(strict_types=1);

namespace App\Infrastructure\Barbershop\Repository;

use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Enum\BookingStatus;
use App\Domain\Barbershop\Exception\NotFoundException;
use App\Domain\Barbershop\Exception\SlotUnavailableException;
use App\Domain\Barbershop\Repository\BookingRepositoryInterface;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBookingRepository implements BookingRepositoryInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function getById(Uuid $id): Booking
    {
        return $this->em->find(Booking::class, $id)
            ?? throw new NotFoundException("Booking {$id} not found");
    }

    public function hasOverlappingBooking(
        Stylist $stylist,
        DateTimeImmutable $startTime,
        DateTimeImmutable $endTime,
    ): bool {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Booking::class, 'b')
            ->where('b.stylist = :stylist')
            ->andWhere('b.startTime < :endTime')
            ->andWhere('b.endTime > :startTime')
            ->andWhere('b.status != :rejected')
            ->setParameter('stylist', $stylist)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->setParameter('rejected', BookingStatus::Rejected->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function save(Booking $booking): void
    {
        $this->em->persist($booking);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // The unique index on (stylist_id, start_time) rejected a booking created
            // by a request that ran concurrently with ours.
            throw SlotUnavailableException::create();
        }
    }
}
