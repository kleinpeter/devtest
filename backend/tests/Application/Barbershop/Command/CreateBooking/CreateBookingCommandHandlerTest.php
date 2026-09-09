<?php

declare(strict_types=1);

namespace Tests\Application\Barbershop\Command\CreateBooking;

use App\Application\Barbershop\Command\CreateBooking\CreateBookingCommand;
use App\Application\Barbershop\Command\CreateBooking\CreateBookingCommandHandler;
use App\Domain\Barbershop\Entity\Service;
use App\Domain\Barbershop\Entity\Stylist;
use App\Infrastructure\ValueObject\Uuid;
use App\Infrastructure\ValueObject\UuidFactory;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Doubles\InMemoryBookingRepository;

final class CreateBookingCommandHandlerTest extends TestCase
{
    private const START_TIME = '2026-09-14T10:00:00+00:00';

    private InMemoryBookingRepository $bookings;
    private CreateBookingCommandHandler $handler;
    private Stylist $stylist;
    private Service $service;

    /** @var array<string, Stylist> */
    private array $stylists = [];

    protected function setUp(): void
    {
        $this->stylist = new Stylist(Uuid::fromString('bbbbbbbb-bbbb-bbbb-bbbb-aaaaaaaaaaaa'), 'Tomáš Novák');
        $this->stylists[$this->stylist->getId()->toString()] = $this->stylist;
        $this->service = new Service(Uuid::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'), 'Classic Haircut', 30, 350.0, 'CZK');
        $this->bookings = new InMemoryBookingRepository();

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturnCallback(function (string $class, mixed $id): Service|Stylist|null {
            if ($class === Service::class && $id->toString() === $this->service->getId()->toString()) {
                return $this->service;
            }
            if ($class === Stylist::class) {
                return $this->stylists[$id->toString()] ?? null;
            }
            return null;
        });

        $this->handler = new CreateBookingCommandHandler($this->bookings, $em, new UuidFactory());
    }

    public function testCreatesBookingWhenSlotIsFree(): void
    {
        $result = $this->handler->handle($this->command());

        $this->assertCount(1, $this->bookings->all());
        $this->assertSame($this->bookings->all()[0]->getId()->toString(), $result->aggregateId);
    }

    public function testRejectsSecondBookingForTheSameSlot(): void
    {
        $this->handler->handle($this->command());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This time slot is already booked.');

        $this->handler->handle($this->command());
    }

    public function testSecondSaveDoesNotCreateADuplicate(): void
    {
        $this->handler->handle($this->command());

        try {
            $this->handler->handle($this->command(customerName: 'Another Customer'));
            $this->fail('Expected DomainException for a duplicate slot.');
        } catch (DomainException $e) {
            $this->assertSame('This time slot is already booked.', $e->getMessage());
        }

        $this->assertCount(1, $this->bookings->all());
        $this->assertSame('Jane Doe', $this->bookings->all()[0]->getCustomerName());
    }

    public function testRejectsOverlappingSlot(): void
    {
        $this->handler->handle($this->command());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This time slot is already booked.');

        $this->handler->handle($this->command(startTime: '2026-09-14T10:15:00+00:00'));
    }

    public function testAllowsAdjacentSlot(): void
    {
        $this->handler->handle($this->command());
        $this->handler->handle($this->command(startTime: '2026-09-14T10:30:00+00:00'));

        $this->assertCount(2, $this->bookings->all());
    }

    public function testAllowsSameSlotForDifferentStylist(): void
    {
        $otherStylist = new Stylist(Uuid::fromString('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb'), 'Martin Dvořák');
        $this->stylists[$otherStylist->getId()->toString()] = $otherStylist;

        $this->handler->handle($this->command());
        $this->handler->handle($this->command(stylistId: $otherStylist->getId()->toString()));

        $this->assertCount(2, $this->bookings->all());
    }

    public function testAllowsRebookingAfterRejection(): void
    {
        $this->handler->handle($this->command());
        $this->bookings->all()[0]->reject();

        $result = $this->handler->handle($this->command(customerName: 'New Customer'));

        $this->assertCount(2, $this->bookings->all());
        $this->assertSame($result->aggregateId, $this->bookings->all()[1]->getId()->toString());
        $this->assertSame('New Customer', $this->bookings->all()[1]->getCustomerName());
    }

    public function testSlotDurationMatchesService(): void
    {
        $this->handler->handle($this->command());

        $booking = $this->bookings->all()[0];
        $this->assertEquals(new DateTimeImmutable(self::START_TIME), $booking->getStartTime());
        $this->assertEquals(new DateTimeImmutable('2026-09-14T10:30:00+00:00'), $booking->getEndTime());
    }

    private function command(
        ?string $stylistId = null,
        ?string $startTime = null,
        string $customerName = 'Jane Doe',
    ): CreateBookingCommand {
        return new CreateBookingCommand(
            stylistId: $stylistId ?? $this->stylist->getId()->toString(),
            serviceId: $this->service->getId()->toString(),
            startTime: $startTime ?? self::START_TIME,
            customerName: $customerName,
            customerContact: 'jane@example.com',
        );
    }
}
