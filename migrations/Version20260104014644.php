<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260104014644 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reservation_change_request (id INT AUTO_INCREMENT NOT NULL, reservation_id INT NOT NULL, created_by_id INT NOT NULL, type VARCHAR(20) NOT NULL, proposed_start_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', proposed_end_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', status VARCHAR(20) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', decided_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_DEA46C0FB83297E7 (reservation_id), INDEX IDX_DEA46C0FB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0FB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_change_request ADD CONSTRAINT FK_DEA46C0FB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD cancelled_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD cancelled_by VARCHAR(20) DEFAULT NULL, ADD cancel_reason LONGTEXT DEFAULT NULL, ADD cancellation_fee_gross VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0FB83297E7');
        $this->addSql('ALTER TABLE reservation_change_request DROP FOREIGN KEY FK_DEA46C0FB03A8386');
        $this->addSql('DROP TABLE reservation_change_request');
        $this->addSql('ALTER TABLE reservation DROP cancelled_at, DROP cancelled_by, DROP cancel_reason, DROP cancellation_fee_gross');
    }
}
