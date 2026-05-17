<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260105004046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request ADD decision_by_id INT DEFAULT NULL, ADD new_end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', ADD decision_note LONGTEXT DEFAULT NULL, DROP proposed_start_date, CHANGE type type VARCHAR(32) NOT NULL, CHANGE status status VARCHAR(16) NOT NULL, CHANGE proposed_end_date new_start_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', CHANGE note reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0F8DA2E70D FOREIGN KEY (decision_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DEA46C0F8DA2E70D ON reservation_change_request (decision_by_id)');
        $this->addSql('CREATE INDEX idx_rcr_status ON reservation_change_request (status)');
        $this->addSql('CREATE INDEX idx_rcr_type ON reservation_change_request (type)');
        $this->addSql('ALTER TABLE reservation_change_request RENAME INDEX idx_dea46c0fb83297e7 TO idx_rcr_reservation');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0F8DA2E70D');
        $this->addSql('DROP INDEX IDX_DEA46C0F8DA2E70D ON reservation_change_request');
        $this->addSql('DROP INDEX idx_rcr_status ON reservation_change_request');
        $this->addSql('DROP INDEX idx_rcr_type ON reservation_change_request');
        $this->addSql('ALTER TABLE reservation_change_request ADD proposed_start_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD proposed_end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', ADD note LONGTEXT DEFAULT NULL, DROP decision_by_id, DROP new_start_date, DROP new_end_date, DROP reason, DROP decision_note, CHANGE type type VARCHAR(20) NOT NULL, CHANGE status status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE reservation_change_request RENAME INDEX idx_rcr_reservation TO IDX_DEA46C0FB83297E7');
    }
}
