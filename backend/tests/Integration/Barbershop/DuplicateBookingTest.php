<?php

declare(strict_types=1);

namespace Tests\Integration\Barbershop;

use App\Application\Barbershop\Command\CreateBooking\CreateBookingCommand;
use App\Application\Barbershop\Command\CreateBooking\CreateBookingCommandHandler;
use App\Domain\Barbershop\Entity\Booking;
use App\Domain\Barbershop\Entity\Business;
use App\Domain\Barbershop\Entity\Service;
use App\Domain\Barbershop\Entity\Stylist;
use App\Domain\Barbershop\Exception\SlotUnavailableException;
use App\Infrastructure\Barbershop\Repository\DoctrineBookingRepository;
use App\Infrastructure\Doctrine\Type\UuidType;
use App\Infrastructure\ValueObject\Uuid;
use App\Infrastructure\ValueObject\UuidFactory;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Regression coverage for duplicate bookings: submitting the same slot twice
 * (e.g. a double-click sending two concurrent createBooking mutations) used to
 * create two identical bookings for the same stylist and start time.
 *
 * Runs against a real SQLite database built by the project migrations, because
 * the guarantee comes from the unique index they create.
 */
final class DuplicateBookingTest extends TestCase
{
    private const BUSINESS_ID = '11111111-1111-1111-1111-111111111111';
    private const STYLIST_ID  = 'bbbbbbbb-bbbb-bbbb-bbbb-aaaaaaaaaaaa';
    private const SERVICE_ID  = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';
    private const START_TIME  = '2026-09-14 10:00:00';

    private string $databaseFile;
    private Connection $connection;
    private EntityManagerInterface $em;
    private DoctrineBookingRepository $repository;
    private CreateBookingCommandHandler $handler;

    protected function setUp(): void
    {
        $this->databaseFile = (string) tempnam(sys_get_temp_dir(), 'barbershop-test-');

        if (!Type::hasType(UuidType::NAME)) {
            Type::addType(UuidType::NAME, UuidType::class);
        }

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path'   => $this->databaseFile,
        ]);

        $this->migrate();

        $config = ORMSetup::createXMLMetadataConfiguration(
            [__DIR__ . '/../../../config/doctrine'],
            true,
            sys_get_temp_dir() . '/barbershop-test-proxies',
        );

        $this->em         = new EntityManager($this->connection, $config);
        $this->repository = new DoctrineBookingRepository($this->em);
        $this->handler    = new CreateBookingCommandHandler($this->repository, $this->em, new UuidFactory());

        $this->seedBusiness();
    }

    protected function tearDown(): void
    {
        @unlink($this->databaseFile);
    }

    public function testSecondBookingOfTheSameSlotIsRejected(): void
    {
        $this->handler->handle($this->createBookingCommand());

        try {
            $this->handler->handle($this->createBookingCommand());
            self::fail('Booking the same slot twice should have been rejected');
        } catch (SlotUnavailableException) {
            // expected
        }

        self::assertSame(1, $this->countBookings());
    }

    /**
     * Two requests handled concurrently both pass the availability check before
     * either of them is written, so only the database can settle the race.
     */
    public function testConcurrentRequestsCannotBookTheSameSlotTwice(): void
    {
        $first  = $this->createBooking();
        $second = $this->createBooking();

        $this->repository->save($first);

        try {
            $this->repository->save($second);
            self::fail('The second concurrent booking should have been rejected');
        } catch (SlotUnavailableException) {
            // expected
        }

        self::assertSame(1, $this->countBookings());
    }

    public function testRejectedBookingReleasesTheSlot(): void
    {
        $this->handler->handle($this->createBookingCommand());

        $booking = $this->em->getRepository(Booking::class)->findOneBy([]);
        self::assertInstanceOf(Booking::class, $booking);
        $booking->reject();
        $this->em->flush();

        $this->handler->handle($this->createBookingCommand());

        self::assertSame(1, $this->countBookings());
        self::assertSame(2, $this->countBookings(includeRejected: true));
    }

    private function createBookingCommand(): CreateBookingCommand
    {
        return new CreateBookingCommand(
            stylistId:       self::STYLIST_ID,
            serviceId:       self::SERVICE_ID,
            startTime:       self::START_TIME,
            customerName:    'Jan Kowalski',
            customerContact: 'jan@example.com',
        );
    }

    private function createBooking(): Booking
    {
        $start = new DateTimeImmutable(self::START_TIME);

        return new Booking(
            Uuid::generate(),
            $this->em->getReference(Service::class, Uuid::fromString(self::SERVICE_ID)),
            $this->em->getReference(Stylist::class, Uuid::fromString(self::STYLIST_ID)),
            $start,
            $start->modify('+30 minutes'),
            'Jan Kowalski',
            'jan@example.com',
        );
    }

    private function countBookings(bool $includeRejected = false): int
    {
        $sql = 'SELECT COUNT(*) FROM barbershop_bookings';
        if (!$includeRejected) {
            $sql .= " WHERE status <> 'rejected'";
        }

        return (int) $this->connection->fetchOne($sql);
    }

    private function migrate(): void
    {
        $dependencyFactory = DependencyFactory::fromConnection(
            new ConfigurationArray([
                'migrations_paths'        => ['App\Infrastructure\Migration' => __DIR__ . '/../../../migrations'],
                'all_or_nothing'          => false,
                'transactional'           => false,
                'check_database_platform' => false,
            ]),
            new ExistingConnection($this->connection),
            new NullLogger(),
        );

        $dependencyFactory->getMetadataStorage()->ensureInitialized();

        $plan = $dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion(
            $dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest'),
        );

        $dependencyFactory->getMigrator()->migrate($plan, new MigratorConfiguration());
    }

    private function seedBusiness(): void
    {
        $business = new Business(Uuid::fromString(self::BUSINESS_ID), 'Gentlemen\'s Cut', 'gentlemens-cut');
        $business->addService(new Service(Uuid::fromString(self::SERVICE_ID), 'Classic Haircut', 30, 25.0, 'EUR'));
        $business->addStylist(new Stylist(Uuid::fromString(self::STYLIST_ID), 'Tomáš Novák'));

        $this->em->persist($business);
        $this->em->flush();
    }
}
