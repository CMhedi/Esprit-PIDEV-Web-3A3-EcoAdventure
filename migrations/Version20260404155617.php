<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404155617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE capacity_policy CHANGE categorie_act categorie_act VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE conversation CHANGE titre titre VARCHAR(255) NOT NULL, CHANGE est_groupe est_groupe TINYINT NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE titre titre VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE categorie_evt categorie_evt VARCHAR(255) NOT NULL, CHANGE lieu lieu VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE feedback_event CHANGE id id INT AUTO_INCREMENT NOT NULL, CHANGE action action VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE meta_json meta_json LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY `fk_inscr_pack`');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY `fk_inscr_user`');
        $this->addSql('ALTER TABLE inscription CHANGE date_inscription date_inscription DATETIME NOT NULL, CHANGE statut_inscr statut_inscr VARCHAR(255) NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_pack id_pack INT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D66B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D61CFE4221 FOREIGN KEY (id_pack) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE inscription RENAME INDEX ix_inscr_user TO IDX_5E90F6D66B3CA4B');
        $this->addSql('ALTER TABLE inscription RENAME INDEX ix_inscr_pack TO IDX_5E90F6D61CFE4221');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_msg_conv`');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY `fk_msg_user`');
        $this->addSql('ALTER TABLE message CHANGE type_message type_message VARCHAR(255) NOT NULL, CHANGE contenu contenu VARCHAR(255) DEFAULT NULL, CHANGE statut_message statut_message VARCHAR(255) NOT NULL, CHANGE date_envoi date_envoi DATETIME NOT NULL, CHANGE id_conversation id_conversation INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FA94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F6B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE message RENAME INDEX ix_msg_conv TO IDX_B6BD307FA94F539B');
        $this->addSql('ALTER TABLE message RENAME INDEX ix_msg_user TO IDX_B6BD307F6B3CA4B');
        $this->addSql('ALTER TABLE pack CHANGE type_pack type_pack VARCHAR(20) NOT NULL, CHANGE reduction reduction NUMERIC(10, 2) NOT NULL, CHANGE statut_pack statut_pack VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE planning CHANGE periode periode VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_recl_user`');
        $this->addSql('ALTER TABLE reclamation CHANGE type type VARCHAR(255) NOT NULL, CHANGE contenu contenu VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE reponse reponse LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE6064046B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reclamation RENAME INDEX ix_recl_user TO IDX_CE6064046B3CA4B');
        $this->addSql('ALTER TABLE recommendation_log CHANGE rec_id rec_id INT AUTO_INCREMENT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL, CHANGE request_json request_json LONGTEXT NOT NULL, CHANGE results_json results_json LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY `fk_res_act_act`');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY `fk_res_act_user`');
        $this->addSql('ALTER TABLE reservation_activite CHANGE date_reservation date_reservation DATETIME NOT NULL, CHANGE statut_res statut_res VARCHAR(255) NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_activite id_activite INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B7016B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B701E8AEB980 FOREIGN KEY (id_activite) REFERENCES activite (id_activite)');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX ix_res_act_user TO IDX_25C0B7016B3CA4B');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX ix_res_act_act TO IDX_25C0B701E8AEB980');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY `fk_res_evt_evt`');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY `fk_res_evt_user`');
        $this->addSql('ALTER TABLE reservation_evenement CHANGE date_reservation date_reservation DATETIME NOT NULL, CHANGE statut_res statut_res VARCHAR(255) NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_evenement id_evenement INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109816B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109818B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE reservation_evenement RENAME INDEX ix_res_evt_user TO IDX_116109816B3CA4B');
        $this->addSql('ALTER TABLE reservation_evenement RENAME INDEX ix_res_evt_evt TO IDX_116109818B13D439');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY `fk_reservation_seance`');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY `fk_reservation_user`');
        $this->addSql('ALTER TABLE reservation_seance CHANGE date_reservation date_reservation DATETIME NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_seance id_seance INT DEFAULT NULL, CHANGE google_event_link google_event_link LONGTEXT DEFAULT NULL, CHANGE statut_presence statut_presence VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB4956B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB495F94A48E3 FOREIGN KEY (id_seance) REFERENCES seance (id_seance)');
        $this->addSql('ALTER TABLE reservation_seance RENAME INDEX idx_user TO IDX_978CB4956B3CA4B');
        $this->addSql('ALTER TABLE reservation_seance RENAME INDEX idx_seance TO IDX_978CB495F94A48E3');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY `fk_seance_coach`');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY `fk_seance_planning`');
        $this->addSql('ALTER TABLE seance CHANGE statut_seance statut_seance VARCHAR(255) NOT NULL, CHANGE id_planning id_planning INT DEFAULT NULL, CHANGE id_coach id_coach INT DEFAULT NULL, CHANGE nom nom VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E84425363 FOREIGN KEY (id_planning) REFERENCES planning (id_planning)');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0ED1DC2CFC FOREIGN KEY (id_coach) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE seance RENAME INDEX ix_seance_planning TO IDX_DF7DFD0E84425363');
        $this->addSql('ALTER TABLE seance RENAME INDEX ix_seance_coach TO IDX_DF7DFD0ED1DC2CFC');
        $this->addSql('ALTER TABLE user_app CHANGE role role VARCHAR(20) NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL, CHANGE specialite specialite VARCHAR(30) DEFAULT NULL, CHANGE bio_certifs bio_certifs LONGTEXT DEFAULT NULL, CHANGE disponibilite disponibilite VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_app RENAME INDEX email TO UNIQ_22781144E7927C74');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE capacity_policy CHANGE categorie_act categorie_act VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE conversation CHANGE titre titre VARCHAR(150) NOT NULL, CHANGE est_groupe est_groupe TINYINT DEFAULT 0 NOT NULL, CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE titre titre VARCHAR(150) NOT NULL, CHANGE description description VARCHAR(1000) DEFAULT NULL, CHANGE categorie_evt categorie_evt ENUM(\'TOURNOI\', \'MARATHON\', \'COMPETITION\', \'STAGE\', \'FORMATION\', \'FESTIVAL_SPORTIF\', \'AUTRE\') NOT NULL, CHANGE lieu lieu VARCHAR(150) NOT NULL, CHANGE statut statut VARCHAR(30) NOT NULL');
        $this->addSql('ALTER TABLE feedback_event CHANGE id id BIGINT AUTO_INCREMENT NOT NULL, CHANGE action action VARCHAR(40) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE meta_json meta_json TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D66B3CA4B');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D61CFE4221');
        $this->addSql('ALTER TABLE inscription CHANGE date_inscription date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE statut_inscr statut_inscr ENUM(\'EN_ATTENTE\', \'VALIDEE\', \'ANNULEE\', \'CONFIRMEE\') NOT NULL, CHANGE id_user id_user INT NOT NULL, CHANGE id_pack id_pack INT NOT NULL');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT `fk_inscr_pack` FOREIGN KEY (id_pack) REFERENCES pack (id_pack) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT `fk_inscr_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE inscription RENAME INDEX idx_5e90f6d66b3ca4b TO ix_inscr_user');
        $this->addSql('ALTER TABLE inscription RENAME INDEX idx_5e90f6d61cfe4221 TO ix_inscr_pack');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FA94F539B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F6B3CA4B');
        $this->addSql('ALTER TABLE message CHANGE type_message type_message ENUM(\'TEXTE\', \'VOCAL\', \'IMAGE\') NOT NULL, CHANGE contenu contenu VARCHAR(2000) DEFAULT NULL, CHANGE statut_message statut_message ENUM(\'ENVOYE\', \'LU\', \'SUPPRIME\') NOT NULL, CHANGE date_envoi date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE id_conversation id_conversation INT NOT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT `fk_msg_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE message RENAME INDEX idx_b6bd307fa94f539b TO ix_msg_conv');
        $this->addSql('ALTER TABLE message RENAME INDEX idx_b6bd307f6b3ca4b TO ix_msg_user');
        $this->addSql('ALTER TABLE pack CHANGE type_pack type_pack ENUM(\'INDIVIDUEL\', \'GROUPE\', \'ENTREPRISE\') NOT NULL, CHANGE reduction reduction NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, CHANGE statut_pack statut_pack ENUM(\'ACTIF\', \'INACTIF\') NOT NULL');
        $this->addSql('ALTER TABLE planning CHANGE periode periode VARCHAR(60) NOT NULL, CHANGE description description VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE6064046B3CA4B');
        $this->addSql('ALTER TABLE reclamation CHANGE type type VARCHAR(80) NOT NULL, CHANGE contenu contenu VARCHAR(2000) NOT NULL, CHANGE statut statut ENUM(\'EN_ATTENTE\', \'TRAITEE\', \'REJETEE\') DEFAULT \'EN_ATTENTE\' NOT NULL, CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE reponse reponse TEXT DEFAULT NULL, CHANGE id_user id_user INT NOT NULL');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_recl_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reclamation RENAME INDEX idx_ce6064046b3ca4b TO ix_recl_user');
        $this->addSql('ALTER TABLE recommendation_log CHANGE rec_id rec_id BIGINT AUTO_INCREMENT NOT NULL, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE request_json request_json TEXT NOT NULL, CHANGE results_json results_json TEXT NOT NULL');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B7016B3CA4B');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B701E8AEB980');
        $this->addSql('ALTER TABLE reservation_activite CHANGE date_reservation date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE statut_res statut_res ENUM(\'EN_ATTENTE\', \'CONFIRMEE\', \'ANNULEE\') NOT NULL, CHANGE id_user id_user INT NOT NULL, CHANGE id_activite id_activite INT NOT NULL');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT `fk_res_act_act` FOREIGN KEY (id_activite) REFERENCES activite (id_activite) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT `fk_res_act_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX idx_25c0b7016b3ca4b TO ix_res_act_user');
        $this->addSql('ALTER TABLE reservation_activite RENAME INDEX idx_25c0b701e8aeb980 TO ix_res_act_act');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109816B3CA4B');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109818B13D439');
        $this->addSql('ALTER TABLE reservation_evenement CHANGE date_reservation date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE statut_res statut_res ENUM(\'EN_ATTENTE\', \'CONFIRMEE\', \'ANNULEE\') NOT NULL, CHANGE id_user id_user INT NOT NULL, CHANGE id_evenement id_evenement INT NOT NULL');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT `fk_res_evt_evt` FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT `fk_res_evt_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE reservation_evenement RENAME INDEX idx_116109816b3ca4b TO ix_res_evt_user');
        $this->addSql('ALTER TABLE reservation_evenement RENAME INDEX idx_116109818b13d439 TO ix_res_evt_evt');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB4956B3CA4B');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB495F94A48E3');
        $this->addSql('ALTER TABLE reservation_seance CHANGE date_reservation date_reservation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE statut statut ENUM(\'CONFIRMEE\', \'ANNULEE\') DEFAULT \'CONFIRMEE\' NOT NULL, CHANGE google_event_link google_event_link TEXT DEFAULT NULL, CHANGE statut_presence statut_presence ENUM(\'PRESENT\', \'ABSENT\', \'NON_MARQUE\') DEFAULT \'NON_MARQUE\' NOT NULL, CHANGE id_user id_user INT NOT NULL, CHANGE id_seance id_seance INT NOT NULL');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT `fk_reservation_seance` FOREIGN KEY (id_seance) REFERENCES seance (id_seance) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT `fk_reservation_user` FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_seance RENAME INDEX idx_978cb4956b3ca4b TO idx_user');
        $this->addSql('ALTER TABLE reservation_seance RENAME INDEX idx_978cb495f94a48e3 TO idx_seance');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E84425363');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0ED1DC2CFC');
        $this->addSql('ALTER TABLE seance CHANGE statut_seance statut_seance ENUM(\'PLANIFIEE\', \'ANNULEE\', \'TERMINEE\') NOT NULL, CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE id_planning id_planning INT NOT NULL, CHANGE id_coach id_coach INT NOT NULL');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT `fk_seance_coach` FOREIGN KEY (id_coach) REFERENCES user_app (id_user) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT `fk_seance_planning` FOREIGN KEY (id_planning) REFERENCES planning (id_planning) ON UPDATE CASCADE ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE seance RENAME INDEX idx_df7dfd0ed1dc2cfc TO ix_seance_coach');
        $this->addSql('ALTER TABLE seance RENAME INDEX idx_df7dfd0e84425363 TO ix_seance_planning');
        $this->addSql('ALTER TABLE user_app CHANGE role role ENUM(\'ADMIN\', \'COACH\', \'USER_SIMPLE\') NOT NULL, CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE specialite specialite ENUM(\'FITNESS\', \'RUNNING\', \'FOOTBALL\', \'BASKETBALL\', \'TENNIS\', \'NATATION\', \'RANDONNEE\', \'CYCLISME\', \'YOGA\', \'AUTRE\') DEFAULT NULL, CHANGE bio_certifs bio_certifs TEXT DEFAULT NULL, CHANGE disponibilite disponibilite ENUM(\'MATIN\', \'SOIR\', \'JOURNEE_COMPLETE\') DEFAULT NULL');
        $this->addSql('ALTER TABLE user_app RENAME INDEX uniq_22781144e7927c74 TO email');
    }
}
