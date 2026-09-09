<?php

declare(strict_types=1);

namespace App\Infrastructure\Barbershop\Repository;

use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Enum\BookingStatus;
use App\Domain\Barbershop\Exception\NotFoundException;
use App\Domain\Barbershop\Repository\BookingRepositoryInterface;
use App\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

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

    public function save(Booking $booking): void
    {
        try {
            $this->em->persist($booking);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            throw new DomainException('This time slot is already booked.', previous: $e);
        }
    }

    public function hasOverlappingBooking(Stylist $stylist, DateTimeImmutable $startTime, DateTimeImmutable $endTime): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Booking::class, 'b')
            ->where('b.stylist = :stylist')
            ->andWhere('b.status != :rejected')
            ->andWhere('b.startTime < :endTime')
            ->andWhere('b.endTime > :startTime')
            ->setParameter('stylist', $stylist)
            ->setParameter('rejected', BookingStatus::Rejected->value)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
