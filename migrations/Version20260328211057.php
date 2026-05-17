<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260328211057 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request ADD new_pickup_location VARCHAR(255) DEFAULT NULL, CHANGE status status VARCHAR(20) NOT NULL, CHANGE reason message LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD force_password_change TINYINT(1) DEFAULT 0 NOT NULL, ADD totp_secret VARCHAR(255) DEFAULT NULL, ADD is_two_factor_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_change_request DROP new_pickup_location, CHANGE status status VARCHAR(16) NOT NULL, CHANGE message reason LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE user DROP force_password_change, DROP totp_secret, DROP is_two_factor_enabled');
    }
}
