<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250217154620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_773DE69DFCFF3785 ON car');
        $this->addSql('ALTER TABLE car ADD registration_number VARCHAR(255) NOT NULL, DROP plate_number, CHANGE location location VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_773DE69D38CEDFBE ON car (registration_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_773DE69D38CEDFBE ON car');
        $this->addSql('ALTER TABLE car ADD plate_number VARCHAR(20) NOT NULL, DROP registration_number, CHANGE location location VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_773DE69DFCFF3785 ON car (plate_number)');
    }
}
