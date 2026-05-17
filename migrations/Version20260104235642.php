<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104235642 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_log DROP FOREIGN KEY FK_F9383BB010DAF24A');
        $this->addSql('DROP INDEX IDX_F9383BB010DAF24A ON admin_log');
        $this->addSql('ALTER TABLE admin_log ADD entity VARCHAR(60) DEFAULT NULL, ADD ip VARCHAR(45) DEFAULT NULL, DROP entity_type, CHANGE action action VARCHAR(120) NOT NULL, CHANGE actor_id admin_id INT DEFAULT NULL, CHANGE meta details JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE admin_log ADD CONSTRAINT FK_F9383BB0642B8210 FOREIGN KEY (admin_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F9383BB0642B8210 ON admin_log (admin_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_log DROP FOREIGN KEY FK_F9383BB0642B8210');
        $this->addSql('DROP INDEX IDX_F9383BB0642B8210 ON admin_log');
        $this->addSql('ALTER TABLE admin_log ADD entity_type VARCHAR(120) DEFAULT NULL, DROP entity, DROP ip, CHANGE action action VARCHAR(80) NOT NULL, CHANGE admin_id actor_id INT DEFAULT NULL, CHANGE details meta JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE admin_log ADD CONSTRAINT FK_F9383BB010DAF24A FOREIGN KEY (actor_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_F9383BB010DAF24A ON admin_log (actor_id)');
    }
}
