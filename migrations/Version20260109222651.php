<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260109222651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0FB03A8386');
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0F8DA2E70D');
        $this->addSql('DROP INDEX IDX_DEA46C0F8DA2E70D ON reservation_change_request');
        $this->addSql('DROP INDEX IDX_DEA46C0FB03A8386 ON reservation_change_request');
        $this->addSql('DROP INDEX idx_rcr_type ON reservation_change_request');
        $this->addSql('ALTER TABLE reservation_change_request DROP type, DROP decision_note, CHANGE created_by_id requester_id INT NOT NULL, CHANGE decision_by_id decided_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0FED442CF4 FOREIGN KEY (requester_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0FE26B496B FOREIGN KEY (decided_by_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DEA46C0FED442CF4 ON reservation_change_request (requester_id)');
        $this->addSql('CREATE INDEX IDX_DEA46C0FE26B496B ON reservation_change_request (decided_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0FED442CF4');
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0FE26B496B');
        $this->addSql('DROP INDEX IDX_DEA46C0FED442CF4 ON reservation_change_request');
        $this->addSql('DROP INDEX IDX_DEA46C0FE26B496B ON reservation_change_request');
        $this->addSql('ALTER TABLE reservation_change_request ADD type VARCHAR(32) NOT NULL, ADD decision_note LONGTEXT DEFAULT NULL, CHANGE requester_id created_by_id INT NOT NULL, CHANGE decided_by_id decision_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0F8DA2E70D FOREIGN KEY (decision_by_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_DEA46C0F8DA2E70D ON reservation_change_request (decision_by_id)');
        $this->addSql('CREATE INDEX IDX_DEA46C0FB03A8386 ON reservation_change_request (created_by_id)');
        $this->addSql('CREATE INDEX idx_rcr_type ON reservation_change_request (type)');
    }
}
