<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add database-backed messaging blocked users and seed user id 3 as blocked';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE blocked_messaging_user (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, reason VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_blocked_messaging_user_user (user_id), INDEX IDX_A6822E02A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE blocked_messaging_user ADD CONSTRAINT FK_A6822E02A76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user) ON DELETE CASCADE');
        $this->addSql("INSERT INTO blocked_messaging_user (user_id, active, reason, created_at)
            SELECT user_app.id_user, 1, 'Global messaging block for calls and conversations', NOW()
            FROM user_app
            WHERE user_app.id_user = 3
              AND NOT EXISTS (
                  SELECT 1
                  FROM blocked_messaging_user existing_rule
                  WHERE existing_rule.user_id = user_app.id_user
              )");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE blocked_messaging_user DROP FOREIGN KEY FK_A6822E02A76ED395');
        $this->addSql('DROP TABLE blocked_messaging_user');
    }
}
