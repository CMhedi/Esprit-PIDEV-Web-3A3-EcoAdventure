<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406173845 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add createur and participants relations to conversation table';
    }

    public function up(Schema $schema): void
    {
        // Fix existing conversations with invalid id_createur values
        $this->addSql('UPDATE conversation SET id_createur = 1 WHERE id_createur = 0 OR id_createur NOT IN (SELECT id_user FROM user_app)');

        // Add foreign key constraint for existing id_createur column
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9F6BD1646 FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9F6BD1646 ON conversation (id_createur)');

        // Create conversation_user join table for participants
        $this->addSql('CREATE TABLE conversation_user (conversation_id_conversation INT NOT NULL, user_app_id_user INT NOT NULL, INDEX IDX_5AECB5558A8E26E9 (conversation_id_conversation), INDEX IDX_5AECB5556B3CA4B (user_app_id_user), PRIMARY KEY(conversation_id_conversation, user_app_id_user)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB5558A8E26E9 FOREIGN KEY (conversation_id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB5556B3CA4B FOREIGN KEY (user_app_id_user) REFERENCES user_app (id_user)');
    }

    public function down(Schema $schema): void
    {
        // Remove conversation_user join table
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB5558A8E26E9');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB5556B3CA4B');
        $this->addSql('DROP TABLE conversation_user');

        // Remove foreign key constraint for id_createur column
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9F6BD1646');
        $this->addSql('DROP INDEX IDX_8A8E26E9F6BD1646 ON conversation');
    }
}
