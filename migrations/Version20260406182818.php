<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260406182818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY `FK_8A8E26E9F6BD1646`');
        $this->addSql('DROP INDEX idx_8a8e26e9f6bd1646 ON conversation');
        $this->addSql('CREATE INDEX IDX_8A8E26E9AA033611 ON conversation (id_createur)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT `FK_8A8E26E9F6BD1646` FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY `FK_5AECB5556B3CA4B`');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY `FK_5AECB5558A8E26E9`');
        $this->addSql('DROP INDEX idx_5aecb5558a8e26e9 ON conversation_user');
        $this->addSql('CREATE INDEX IDX_5AECB55514B1C9E5 ON conversation_user (conversation_id_conversation)');
        $this->addSql('DROP INDEX idx_5aecb5556b3ca4b ON conversation_user');
        $this->addSql('CREATE INDEX IDX_5AECB55542F58393 ON conversation_user (user_app_id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT `FK_5AECB5556B3CA4B` FOREIGN KEY (user_app_id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT `FK_5AECB5558A8E26E9` FOREIGN KEY (conversation_id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE message ADD date_modifier DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9AA033611');
        $this->addSql('DROP INDEX idx_8a8e26e9aa033611 ON conversation');
        $this->addSql('CREATE INDEX IDX_8A8E26E9F6BD1646 ON conversation (id_createur)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9AA033611 FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB55514B1C9E5');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB55542F58393');
        $this->addSql('DROP INDEX idx_5aecb55514b1c9e5 ON conversation_user');
        $this->addSql('CREATE INDEX IDX_5AECB5558A8E26E9 ON conversation_user (conversation_id_conversation)');
        $this->addSql('DROP INDEX idx_5aecb55542f58393 ON conversation_user');
        $this->addSql('CREATE INDEX IDX_5AECB5556B3CA4B ON conversation_user (user_app_id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB55514B1C9E5 FOREIGN KEY (conversation_id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB55542F58393 FOREIGN KEY (user_app_id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE message DROP date_modifier');
    }
}
