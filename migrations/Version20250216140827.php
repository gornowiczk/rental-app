<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250216140827 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX UNIQ_773DE69DFCFF3785 ON car (plate_number)');
        $this->addSql('ALTER TABLE reservation DROP status, CHANGE start_date start_date DATE NOT NULL, CHANGE end_date end_date DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation ADD status VARCHAR(255) DEFAULT \'pending\', CHANGE start_date start_date DATETIME NOT NULL, CHANGE end_date end_date DATETIME NOT NULL');
        $this->addSql('DROP INDEX UNIQ_773DE69DFCFF3785 ON car');
    }
}
