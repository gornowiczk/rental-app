<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260423121814 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE car_service_log (id INT AUTO_INCREMENT NOT NULL, car_id INT NOT NULL, type VARCHAR(50) NOT NULL, service_date DATE NOT NULL, mileage INT DEFAULT NULL, cost NUMERIC(10, 2) DEFAULT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_C7EDE4CEC3C6F69F (car_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE car_service_log ADD CONSTRAINT FK_C7EDE4CEC3C6F69F FOREIGN KEY (car_id) REFERENCES car (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car_service_log DROP FOREIGN KEY FK_C7EDE4CEC3C6F69F');
        $this->addSql('DROP TABLE car_service_log');
    }
}
