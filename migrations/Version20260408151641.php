<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408151641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation CHANGE createur_id id_createur INT NOT NULL');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9AA033611 FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        $this->addSql('CREATE INDEX IDX_8A8E26E9AA033611 ON conversation (id_createur)');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY `FK_C21CED966B3CA4B`');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY `FK_C21CED96A94F539B`');
        $this->addSql('DROP INDEX IDX_C21CED96A94F539B ON conversation_user');
        $this->addSql('DROP INDEX IDX_C21CED966B3CA4B ON conversation_user');
        $this->addSql('ALTER TABLE conversation_user ADD conversation_id_conversation INT NOT NULL, ADD user_app_id_user INT NOT NULL, DROP id_conversation, DROP id_user, DROP PRIMARY KEY, ADD PRIMARY KEY (conversation_id_conversation, user_app_id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB55514B1C9E5 FOREIGN KEY (conversation_id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB55542F58393 FOREIGN KEY (user_app_id_user) REFERENCES user_app (id_user)');
        $this->addSql('CREATE INDEX IDX_5AECB55514B1C9E5 ON conversation_user (conversation_id_conversation)');
        $this->addSql('CREATE INDEX IDX_5AECB55542F58393 ON conversation_user (user_app_id_user)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9AA033611');
        $this->addSql('DROP INDEX IDX_8A8E26E9AA033611 ON conversation');
        $this->addSql('ALTER TABLE conversation CHANGE id_createur createur_id INT NOT NULL');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB55514B1C9E5');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB55542F58393');
        $this->addSql('DROP INDEX IDX_5AECB55514B1C9E5 ON conversation_user');
        $this->addSql('DROP INDEX IDX_5AECB55542F58393 ON conversation_user');
        $this->addSql('ALTER TABLE conversation_user ADD id_conversation INT NOT NULL, ADD id_user INT NOT NULL, DROP conversation_id_conversation, DROP user_app_id_user, DROP PRIMARY KEY, ADD PRIMARY KEY (id_conversation, id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT `FK_C21CED966B3CA4B` FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT `FK_C21CED96A94F539B` FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('CREATE INDEX IDX_C21CED96A94F539B ON conversation_user (id_conversation)');
        $this->addSql('CREATE INDEX IDX_C21CED966B3CA4B ON conversation_user (id_user)');
    }
}
