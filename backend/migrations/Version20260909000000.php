<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260909000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent two active bookings for the same stylist and start time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE UNIQUE INDEX UNIQ_BOOKING_STYLIST_START ON barbershop_bookings (stylist_id, start_time) WHERE status != 'rejected'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_BOOKING_STYLIST_START');
    }
}
