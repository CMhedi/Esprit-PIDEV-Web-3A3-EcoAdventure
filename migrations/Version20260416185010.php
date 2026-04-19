<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260416185010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE activite CHANGE type_activite type_activite VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB555A94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE conversation_user ADD CONSTRAINT FK_5AECB5556B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_c21ced96a94f539b TO IDX_5AECB555A94F539B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_c21ced966b3ca4b TO IDX_5AECB5556B3CA4B');
        $this->addSql('ALTER TABLE evenement CHANGE description description VARCHAR(1000) DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL, CHANGE prix prix DOUBLE PRECISION DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription CHANGE nom_user nom_user VARCHAR(255) DEFAULT NULL, CHANGE nom_pack nom_pack VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message CHANGE contenu contenu VARCHAR(2000) DEFAULT NULL, CHANGE date_lecture date_lecture DATETIME DEFAULT NULL, CHANGE date_modifier date_modifier DATETIME DEFAULT NULL, CHANGE id_conversation id_conversation INT NOT NULL, CHANGE id_user id_user INT NOT NULL, CHANGE reactions reactions JSON DEFAULT NULL, CHANGE attachments attachments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE food_name food_name VARCHAR(255) DEFAULT NULL, CHANGE calories calories DOUBLE PRECISION DEFAULT NULL, CHANGE log_date log_date DATE DEFAULT NULL, CHANGE protein protein DOUBLE PRECISION DEFAULT NULL, CHANGE fat fat DOUBLE PRECISION DEFAULT NULL, CHANGE carbs carbs DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE planning CHANGE titre titre VARCHAR(100) DEFAULT NULL, CHANGE description description VARCHAR(500) DEFAULT NULL, CHANGE date_fin date_fin DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_activite CHANGE id_user id_user INT NOT NULL, CHANGE id_activite id_activite INT NOT NULL');
        $this->addSql('ALTER TABLE reservation_seance CHANGE google_event_id google_event_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT NOT NULL, CHANGE id_coach id_coach INT NOT NULL');
        $this->addSql('ALTER TABLE user_app CHANGE telephone telephone VARCHAR(30) DEFAULT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL, CHANGE experience experience VARCHAR(50) DEFAULT NULL, CHANGE specialite specialite VARCHAR(255) DEFAULT NULL, CHANGE disponibilite disponibilite VARCHAR(255) DEFAULT NULL, CHANGE referral_code referral_code VARCHAR(10) DEFAULT NULL, CHANGE last_seen last_seen DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_22781144E7927C74 ON user_app (email)');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('DROP TABLE reset_password_request');
        $this->addSql('ALTER TABLE activite CHANGE type_activite type_activite VARCHAR(80) NOT NULL, CHANGE statut statut VARCHAR(30) NOT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB555A94F539B');
        $this->addSql('ALTER TABLE conversation_user DROP FOREIGN KEY FK_5AECB5556B3CA4B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_5aecb555a94f539b TO IDX_C21CED96A94F539B');
        $this->addSql('ALTER TABLE conversation_user RENAME INDEX idx_5aecb5556b3ca4b TO IDX_C21CED966B3CA4B');
        $this->addSql('ALTER TABLE evenement CHANGE description description VARCHAR(1000) DEFAULT \'NULL\', CHANGE prix prix DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE statut statut VARCHAR(255) DEFAULT \'NULL\', CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE inscription CHANGE nom_user nom_user VARCHAR(255) DEFAULT \'NULL\', CHANGE nom_pack nom_pack VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE message CHANGE contenu contenu VARCHAR(2000) DEFAULT \'NULL\', CHANGE date_lecture date_lecture DATETIME DEFAULT \'NULL\', CHANGE date_modifier date_modifier DATETIME DEFAULT \'NULL\', CHANGE reactions reactions LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE attachments attachments LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE id_conversation id_conversation INT DEFAULT NULL, CHANGE id_user id_user INT DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE nutrition_log CHANGE food_name food_name VARCHAR(255) DEFAULT \'NULL\', CHANGE calories calories DOUBLE PRECISION DEFAULT \'NULL\', CHANGE log_date log_date DATE DEFAULT \'NULL\', CHANGE protein protein DOUBLE PRECISION DEFAULT \'NULL\', CHANGE fat fat DOUBLE PRECISION DEFAULT \'NULL\', CHANGE carbs carbs DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE planning CHANGE titre titre VARCHAR(100) DEFAULT \'NULL\', CHANGE description description VARCHAR(500) DEFAULT \'NULL\', CHANGE date_fin date_fin DATE DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reservation_activite CHANGE id_user id_user INT DEFAULT NULL, CHANGE id_activite id_activite INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_seance CHANGE google_event_id google_event_id VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE seance CHANGE id_planning id_planning INT DEFAULT NULL, CHANGE id_coach id_coach INT DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_22781144E7927C74 ON user_app');
        $this->addSql('ALTER TABLE user_app CHANGE telephone telephone VARCHAR(30) DEFAULT \'NULL\', CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\', CHANGE last_seen last_seen DATETIME DEFAULT \'NULL\', CHANGE experience experience VARCHAR(50) DEFAULT \'NULL\', CHANGE specialite specialite VARCHAR(255) DEFAULT \'NULL\', CHANGE disponibilite disponibilite VARCHAR(255) DEFAULT \'NULL\', CHANGE referral_code referral_code VARCHAR(10) DEFAULT \'NULL\'');
    }
}
