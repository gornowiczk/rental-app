<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250216114651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car CHANGE owner_id owner_id INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_773DE69DFCFF3785 ON car (plate_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_773DE69DFCFF3785 ON car');
        $this->addSql('ALTER TABLE car CHANGE owner_id owner_id INT DEFAULT NULL');
    }
}
