<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260420225216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX fk_activite_localisation ON activite');
        $this->addSql('ALTER TABLE activite DROP visibility, DROP id_localisation, CHANGE type_activite type_activite VARCHAR(255) NOT NULL, CHANGE categorie_act categorie_act VARCHAR(255) NOT NULL, CHANGE niveau_act niveau_act VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE image_url image_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_B87555151CFE4221 FOREIGN KEY (id_pack) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_8A8E26E9AA033611 FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB555A94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB5556B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_c21ced96a94f539b TO IDX_5AECB555A94F539B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_c21ced966b3ca4b TO IDX_5AECB5556B3CA4B');
        $this->addSql('ALTER TABLE evenement CHANGE prix prix DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051706B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051708B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE feedback_event ADD CONSTRAINT FK_AF0F93C2A76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE feedback_event ADD CONSTRAINT FK_AF0F93C21919B217 FOREIGN KEY (pack_id) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D61CFE4221 FOREIGN KEY (id_pack) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D66B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE localisation MODIFY id_localisation INT NOT NULL');
        $this->addSql('ALTER TABLE localisation CHANGE id_localisation id INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE message CHANGE id_conversation id_conversation INT NOT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FA94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F6B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE nutrition_log ADD CONSTRAINT FK_18B697FA76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE6064046B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE recommendation_log ADD CONSTRAINT FK_73E8AA4AA76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B7016B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B701E8AEB980 FOREIGN KEY (id_activite) REFERENCES activite (id_activite)');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX ix_res_act_user TO IDX_25C0B7016B3CA4B');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX ix_res_act_act TO IDX_25C0B701E8AEB980');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109816B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109818B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB4956B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB495F94A48E3 FOREIGN KEY (id_seance) REFERENCES seance (id_seance)');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT NOT NULL, CHANGE id_coach id_coach INT NOT NULL');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E84425363 FOREIGN KEY (id_planning) REFERENCES planning (id_planning)');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0ED1DC2CFC FOREIGN KEY (id_coach) REFERENCES user_app (id_user)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_22781144E7927C74 ON user_app (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_B87555151CFE4221');
        $this->addSql('ALTER TABLE activite ADD visibility ENUM(\'PUBLIC\', \'PRIVATE\') DEFAULT \'PUBLIC\' NOT NULL, ADD id_localisation INT DEFAULT NULL, CHANGE type_activite type_activite ENUM(\'SPORT\', \'CAMPING\', \'INTELECTUEL\', \'CULTUREL\') NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE categorie_act categorie_act ENUM(\'FITNESS\', \'RUNNING\', \'FOOTBALL\', \'BASKETBALL\', \'TENNIS\', \'NATATION\', \'RANDONNEE\', \'CYCLISME\', \'YOGA\', \'AUTRE\') NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE niveau_act niveau_act ENUM(\'DEBUTANT\', \'INTERMEDIAIRE\', \'AVANCE\') NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE statut statut ENUM(\'DISPONIBLE\', \'INDISPONIBLE\') NOT NULL COLLATE `utf8mb4_general_ci`, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX fk_activite_localisation ON activite (id_localisation)');
        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_8A8E26E9AA033611');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB555A94F539B');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB5556B3CA4B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_5aecb555a94f539b TO IDX_C21CED96A94F539B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_5aecb5556b3ca4b TO IDX_C21CED966B3CA4B');
        $this->addSql('ALTER TABLE evenement CHANGE prix prix DOUBLE PRECISION DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051706B3CA4B');
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051708B13D439');
        $this->addSql('ALTER TABLE feedback_event DROP FOREIGN KEY FK_AF0F93C2A76ED395');
        $this->addSql('ALTER TABLE feedback_event DROP FOREIGN KEY FK_AF0F93C21919B217');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D61CFE4221');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D66B3CA4B');
        $this->addSql('ALTER TABLE localisation MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE localisation CHANGE id id_localisation INT AUTO_INCREMENT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_localisation)');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FA94F539B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F6B3CA4B');
        $this->addSql('ALTER TABLE message CHANGE id_conversation id_conversation INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log DROP FOREIGN KEY FK_18B697FA76ED395');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE6064046B3CA4B');
        $this->addSql('ALTER TABLE recommendation_log DROP FOREIGN KEY FK_73E8AA4AA76ED395');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B7016B3CA4B');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B701E8AEB980');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX idx_25c0b7016b3ca4b TO ix_res_act_user');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX idx_25c0b701e8aeb980 TO ix_res_act_act');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109816B3CA4B');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109818B13D439');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB4956B3CA4B');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB495F94A48E3');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E84425363');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0ED1DC2CFC');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT DEFAULT NULL, CHANGE id_coach id_coach INT DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_22781144E7927C74 ON user_app');
    }
}
