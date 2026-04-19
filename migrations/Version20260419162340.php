<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419162340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE evenement CHANGE description description VARCHAR(1000) DEFAULT NULL, CHANGE prix prix DOUBLE PRECISION DEFAULT 0 NOT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE inscription CHANGE nom_user nom_user VARCHAR(255) DEFAULT NULL, CHANGE nom_pack nom_pack VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message CHANGE contenu contenu VARCHAR(2000) DEFAULT NULL, CHANGE date_lecture date_lecture DATETIME DEFAULT NULL, CHANGE date_modifier date_modifier DATETIME DEFAULT NULL, CHANGE reactions reactions JSON DEFAULT NULL, CHANGE attachments attachments JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE food_name food_name VARCHAR(255) DEFAULT NULL, CHANGE calories calories DOUBLE PRECISION DEFAULT NULL, CHANGE log_date log_date DATE DEFAULT NULL, CHANGE protein protein DOUBLE PRECISION DEFAULT NULL, CHANGE fat fat DOUBLE PRECISION DEFAULT NULL, CHANGE carbs carbs DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE planning CHANGE titre titre VARCHAR(100) DEFAULT NULL, CHANGE description description VARCHAR(500) DEFAULT NULL, CHANGE date_fin date_fin DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reservation_seance CHANGE google_event_id google_event_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user_app ADD face_descriptor JSON DEFAULT NULL, CHANGE telephone telephone VARCHAR(30) DEFAULT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL, CHANGE last_seen last_seen DATETIME DEFAULT NULL, CHANGE experience experience VARCHAR(50) DEFAULT NULL, CHANGE specialite specialite VARCHAR(255) DEFAULT NULL, CHANGE disponibilite disponibilite VARCHAR(255) DEFAULT NULL, CHANGE referral_code referral_code VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE evenement CHANGE description description VARCHAR(1000) DEFAULT \'NULL\', CHANGE prix prix DOUBLE PRECISION DEFAULT \'0\' NOT NULL, CHANGE statut statut VARCHAR(255) DEFAULT \'NULL\', CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE inscription CHANGE nom_user nom_user VARCHAR(255) DEFAULT \'NULL\', CHANGE nom_pack nom_pack VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE message CHANGE contenu contenu VARCHAR(2000) DEFAULT \'NULL\', CHANGE date_lecture date_lecture DATETIME DEFAULT \'NULL\', CHANGE date_modifier date_modifier DATETIME DEFAULT \'NULL\', CHANGE reactions reactions LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`, CHANGE attachments attachments LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE nutrition_log CHANGE food_name food_name VARCHAR(255) DEFAULT \'NULL\', CHANGE calories calories DOUBLE PRECISION DEFAULT \'NULL\', CHANGE log_date log_date DATE DEFAULT \'NULL\', CHANGE protein protein DOUBLE PRECISION DEFAULT \'NULL\', CHANGE fat fat DOUBLE PRECISION DEFAULT \'NULL\', CHANGE carbs carbs DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE planning CHANGE titre titre VARCHAR(100) DEFAULT \'NULL\', CHANGE description description VARCHAR(500) DEFAULT \'NULL\', CHANGE date_fin date_fin DATE DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reservation_seance CHANGE google_event_id google_event_id VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE user_app DROP face_descriptor, CHANGE telephone telephone VARCHAR(30) DEFAULT \'NULL\', CHANGE image_url image_url VARCHAR(255) DEFAULT \'NULL\', CHANGE last_seen last_seen DATETIME DEFAULT \'NULL\', CHANGE experience experience VARCHAR(50) DEFAULT \'NULL\', CHANGE specialite specialite VARCHAR(255) DEFAULT \'NULL\', CHANGE disponibilite disponibilite VARCHAR(255) DEFAULT \'NULL\', CHANGE referral_code referral_code VARCHAR(10) DEFAULT \'NULL\'');
    }
}
