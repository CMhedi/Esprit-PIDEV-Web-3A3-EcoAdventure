<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260410123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize messagerie schema and secure conversation_user join table for portable installs';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['conversation_user'])) {
            $this->addSql(
                'CREATE TABLE conversation_user (
                    id_conversation INT NOT NULL,
                    id_user INT NOT NULL,
                    PRIMARY KEY(id_conversation, id_user),
                    INDEX IDX_C21CED96A94F539B (id_conversation),
                    INDEX IDX_C21CED966B3CA4B (id_user)
                ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
            );
        }

        $conversationColumns = [];
        if ($schemaManager->tablesExist(['conversation'])) {
            foreach ($schemaManager->listTableColumns('conversation') as $column) {
                $conversationColumns[$column->getName()] = true;
            }
        }

        if (!isset($conversationColumns['id_createur'])) {
            $this->addSql('ALTER TABLE conversation ADD id_createur INT DEFAULT NULL');
            $this->addSql('UPDATE conversation c SET id_createur = (
                SELECT MIN(cu.id_user)
                FROM conversation_user cu
                WHERE cu.id_conversation = c.id_conversation
            ) WHERE id_createur IS NULL');
            $this->addSql('UPDATE conversation SET id_createur = 1 WHERE id_createur IS NULL');
            $this->addSql('ALTER TABLE conversation MODIFY id_createur INT NOT NULL');
        }

        $messageColumns = [];
        if ($schemaManager->tablesExist(['message'])) {
            foreach ($schemaManager->listTableColumns('message') as $column) {
                $messageColumns[$column->getName()] = true;
            }
        }

        if (!isset($messageColumns['date_modifier'])) {
            $this->addSql('ALTER TABLE message ADD date_modifier DATETIME DEFAULT NULL');
        }

        if (!isset($messageColumns['reactions'])) {
            $this->addSql('ALTER TABLE message ADD reactions LONGTEXT DEFAULT NULL');
        }

        if (!isset($messageColumns['attachments'])) {
            $this->addSql('ALTER TABLE message ADD attachments LONGTEXT DEFAULT NULL');
        }

        $conversationUserForeignKeys = [];
        foreach ($schemaManager->listTableForeignKeys('conversation_user') as $foreignKey) {
            $conversationUserForeignKeys[$foreignKey->getName()] = true;
        }

        if (!isset($conversationUserForeignKeys['FK_C21CED96A94F539B'])) {
            $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_C21CED96A94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation) ON DELETE CASCADE');
        }

        if (!isset($conversationUserForeignKeys['FK_C21CED966B3CA4B'])) {
            $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_C21CED966B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON DELETE CASCADE');
        }

        $conversationForeignKeys = [];
        foreach ($schemaManager->listTableForeignKeys('conversation') as $foreignKey) {
            $conversationForeignKeys[$foreignKey->getName()] = true;
        }

        if (!isset($conversationForeignKeys['FK_8A8E26E9AA033611'])) {
            $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9AA033611 FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        }
    }

    public function down(Schema $schema): void
    {
        // This migration is intended as a portability/synchronization baseline.
    }
}
