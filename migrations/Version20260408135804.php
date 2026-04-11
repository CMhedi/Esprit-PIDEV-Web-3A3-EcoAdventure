<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408135804 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inscription ADD id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D66B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('CREATE INDEX IDX_5E90F6D66B3CA4B ON inscription (id_user)');
        $this->addSql('ALTER TABLE user_app ADD is_verified TINYINT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D66B3CA4B');
        $this->addSql('DROP INDEX IDX_5E90F6D66B3CA4B ON inscription');
        $this->addSql('ALTER TABLE inscription DROP id_user');
        $this->addSql('ALTER TABLE user_app DROP is_verified');
    }
}
