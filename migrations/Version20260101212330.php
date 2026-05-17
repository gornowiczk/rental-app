<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260101212330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation_status_log (id INT AUTO_INCREMENT NOT NULL, reservation_id INT NOT NULL, actor_id INT DEFAULT NULL, old_status VARCHAR(32) DEFAULT NULL, new_status VARCHAR(32) NOT NULL, changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ip_address VARCHAR(64) DEFAULT NULL, user_agent VARCHAR(255) DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, INDEX IDX_31FAF1D310DAF24A (actor_id), INDEX idx_rsl_reservation (reservation_id), INDEX idx_rsl_changed_at (changed_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation_status_log ADD CONSTRAINT FK_31FAF1D3B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_status_log ADD CONSTRAINT FK_31FAF1D310DAF24A FOREIGN KEY (actor_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_status_log DROP FOREIGN KEY FK_31FAF1D3B83297E7');
        $this->addSql('ALTER TABLE reservation_status_log DROP FOREIGN KEY FK_31FAF1D310DAF24A');
        $this->addSql('DROP TABLE reservation_status_log');
    }
}
