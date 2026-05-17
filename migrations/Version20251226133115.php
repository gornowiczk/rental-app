<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251226133115 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice CHANGE issued_at issued_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE sale_date sale_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invoice CHANGE issued_at issued_at DATE NOT NULL, CHANGE sale_date sale_date DATE NOT NULL, CHANGE created_at created_at DATETIME NOT NULL');
    }
}
