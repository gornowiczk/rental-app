<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251216224402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation ADD phone_number VARCHAR(30) DEFAULT NULL, ADD rental_location VARCHAR(255) DEFAULT NULL, ADD wants_invoice TINYINT(1) NOT NULL, ADD invoice_name VARCHAR(255) DEFAULT NULL, ADD invoice_address VARCHAR(255) DEFAULT NULL, ADD invoice_nip VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation DROP phone_number, DROP rental_location, DROP wants_invoice, DROP invoice_name, DROP invoice_address, DROP invoice_nip');
    }
}
