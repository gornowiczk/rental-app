<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251225000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create invoice table as snapshot for reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE invoice (
            id INT AUTO_INCREMENT NOT NULL,
            reservation_id INT NOT NULL,
            invoice_number VARCHAR(64) NOT NULL,
            issued_at DATE NOT NULL,
            sale_date DATE NOT NULL,
            seller_name VARCHAR(255) NOT NULL,
            seller_address VARCHAR(255) NOT NULL,
            seller_nip VARCHAR(32) NOT NULL,
            seller_bank VARCHAR(255) DEFAULT NULL,
            seller_iban VARCHAR(64) DEFAULT NULL,
            buyer_name VARCHAR(255) NOT NULL,
            buyer_address VARCHAR(255) NOT NULL,
            buyer_nip VARCHAR(32) DEFAULT NULL,
            item_name VARCHAR(255) NOT NULL,
            days INT NOT NULL,
            price_per_day NUMERIC(10, 2) NOT NULL,
            vat_rate NUMERIC(5, 4) NOT NULL,
            net_total NUMERIC(10, 2) NOT NULL,
            vat_amount NUMERIC(10, 2) NOT NULL,
            gross_total NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL,
            UNIQUE INDEX uniq_invoice_number (invoice_number),
            UNIQUE INDEX uniq_invoice_reservation (reservation_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE invoice ADD CONSTRAINT FK_invoice_reservation FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoice DROP FOREIGN KEY FK_invoice_reservation');
        $this->addSql('DROP TABLE invoice');
    }
}
