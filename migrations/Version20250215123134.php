<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250215123134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE car DROP FOREIGN KEY FK_773DE69D7E3C61F9');
        $this->addSql('DROP INDEX IDX_773DE69D7E3C61F9 ON car');
        $this->addSql('ALTER TABLE car ADD is_available TINYINT(1) NOT NULL, DROP owner_id, DROP photo, DROP is_avaliable');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_773DE69DFCFF3785 ON car (plate_number)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_773DE69DFCFF3785 ON car');
        $this->addSql('ALTER TABLE car ADD owner_id INT NOT NULL, ADD photo VARCHAR(255) DEFAULT NULL, ADD is_avaliable TINYINT(1) DEFAULT NULL, DROP is_available');
        $this->addSql('ALTER TABLE car ADD CONSTRAINT FK_773DE69D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_773DE69D7E3C61F9 ON car (owner_id)');
    }
}
