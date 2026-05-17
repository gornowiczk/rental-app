<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251225043800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_log (id INT AUTO_INCREMENT NOT NULL, admin_id INT NOT NULL, action VARCHAR(120) NOT NULL, entity VARCHAR(80) DEFAULT NULL, entity_id INT DEFAULT NULL, details JSON DEFAULT NULL, ip VARCHAR(45) DEFAULT NULL, user_agent LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F9383BB0642B8210 (admin_id), INDEX idx_adminlog_created (created_at), INDEX idx_adminlog_action (action), INDEX idx_adminlog_entity (entity), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE admin_log ADD CONSTRAINT FK_F9383BB0642B8210 FOREIGN KEY (admin_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_log DROP FOREIGN KEY FK_F9383BB0642B8210');
        $this->addSql('DROP TABLE admin_log');
    }
}
