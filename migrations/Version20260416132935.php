<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416132935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB555A94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB5556B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE evenement CHANGE prix prix DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051706B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051708B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('DROP INDEX IDX_INSCRIPTION_PAYMENT_REFERENCE ON inscription');
        $this->addSql('ALTER TABLE inscription CHANGE id_user id_user INT NOT NULL, CHANGE id_pack id_pack INT NOT NULL');
        $this->addSql('ALTER TABLE message CHANGE id_conversation id_conversation INT NOT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE reservation_activite CHANGE id_user id_user INT NOT NULL, CHANGE id_activite id_activite INT NOT NULL');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT NOT NULL, CHANGE id_coach id_coach INT NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_22781144E7927C74 ON user_app (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB555A94F539B');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB5556B3CA4B');
        $this->addSql('ALTER TABLE evenement CHANGE prix prix DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051706B3CA4B');
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051708B13D439');
        $this->addSql('ALTER TABLE inscription CHANGE id_pack id_pack INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_INSCRIPTION_PAYMENT_REFERENCE ON inscription (payment_reference)');
        $this->addSql('ALTER TABLE message CHANGE id_conversation id_conversation INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_activite CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_activite id_activite INT DEFAULT NULL');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT DEFAULT NULL, CHANGE id_coach id_coach INT DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_22781144E7927C74 ON user_app');
    }
}
