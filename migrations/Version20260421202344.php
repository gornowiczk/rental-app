<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260421202344 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car ADD vin VARCHAR(30) DEFAULT NULL, ADD mileage INT DEFAULT NULL, ADD fuel_type VARCHAR(30) DEFAULT NULL, ADD transmission VARCHAR(30) DEFAULT NULL, ADD insurance_valid_until DATE DEFAULT NULL, ADD inspection_valid_until DATE DEFAULT NULL, ADD last_service_date DATE DEFAULT NULL, ADD notes LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car DROP vin, DROP mileage, DROP fuel_type, DROP transmission, DROP insurance_valid_until, DROP inspection_valid_until, DROP last_service_date, DROP notes');
    }
}
