<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405155745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite (id_activite INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, type_activite VARCHAR(80) NOT NULL, categorie_act VARCHAR(255) NOT NULL, niveau_act VARCHAR(255) NOT NULL, prix NUMERIC(10, 2) NOT NULL, statut VARCHAR(30) NOT NULL, image_url VARCHAR(255) DEFAULT NULL, id_pack INT DEFAULT NULL, INDEX IDX_B87555151CFE4221 (id_pack), PRIMARY KEY (id_activite)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE capacity_policy (categorie_act VARCHAR(50) NOT NULL, capacite_totale INT NOT NULL, PRIMARY KEY (categorie_act)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE conversation (id_conversation INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, est_groupe TINYINT NOT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY (id_conversation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evenement (id_evenement INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, description VARCHAR(1000) DEFAULT NULL, categorie_evt VARCHAR(255) NOT NULL, date_event DATETIME NOT NULL, lieu VARCHAR(150) NOT NULL, nb_places INT NOT NULL, statut VARCHAR(255) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id_evenement)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE feedback_event (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(40) NOT NULL, created_at DATETIME DEFAULT NULL, meta_json LONGTEXT DEFAULT NULL, user_id INT NOT NULL, pack_id INT NOT NULL, INDEX IDX_AF0F93C2A76ED395 (user_id), INDEX IDX_AF0F93C21919B217 (pack_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inscription (id_inscription INT AUTO_INCREMENT NOT NULL, date_inscription DATETIME NOT NULL, statut_inscr VARCHAR(255) NOT NULL, montant_total NUMERIC(10, 2) NOT NULL, nom_user VARCHAR(255) DEFAULT NULL, nom_pack VARCHAR(255) DEFAULT NULL, id_user INT DEFAULT NULL, id_pack INT DEFAULT NULL, INDEX IDX_5E90F6D66B3CA4B (id_user), INDEX IDX_5E90F6D61CFE4221 (id_pack), PRIMARY KEY (id_inscription)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id_message INT AUTO_INCREMENT NOT NULL, type_message VARCHAR(255) NOT NULL, contenu VARCHAR(2000) DEFAULT NULL, statut_message VARCHAR(255) NOT NULL, date_envoi DATETIME NOT NULL, date_lecture DATETIME DEFAULT NULL, id_conversation INT DEFAULT NULL, id_user INT DEFAULT NULL, INDEX IDX_B6BD307FA94F539B (id_conversation), INDEX IDX_B6BD307F6B3CA4B (id_user), PRIMARY KEY (id_message)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE nutrition_log (id INT AUTO_INCREMENT NOT NULL, food_name VARCHAR(255) DEFAULT NULL, calories DOUBLE PRECISION DEFAULT NULL, log_date DATE DEFAULT NULL, protein DOUBLE PRECISION DEFAULT NULL, fat DOUBLE PRECISION DEFAULT NULL, carbs DOUBLE PRECISION DEFAULT NULL, user_id INT DEFAULT NULL, INDEX IDX_18B697FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pack (id_pack INT AUTO_INCREMENT NOT NULL, nom VARCHAR(120) NOT NULL, type_pack VARCHAR(255) NOT NULL, prix_base NUMERIC(10, 2) NOT NULL, reduction NUMERIC(10, 2) NOT NULL, nb_activites_max INT NOT NULL, statut_pack VARCHAR(255) NOT NULL, PRIMARY KEY (id_pack)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning (id_planning INT AUTO_INCREMENT NOT NULL, titre VARCHAR(100) DEFAULT NULL, description VARCHAR(500) DEFAULT NULL, date_debut DATE NOT NULL, date_fin DATE DEFAULT NULL, statut VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id_planning)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reclamation (id_reclamation INT AUTO_INCREMENT NOT NULL, type VARCHAR(80) NOT NULL, contenu VARCHAR(2000) NOT NULL, statut VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, reponse LONGTEXT DEFAULT NULL, id_user INT DEFAULT NULL, INDEX IDX_CE6064046B3CA4B (id_user), PRIMARY KEY (id_reclamation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE recommendation_log (rec_id INT AUTO_INCREMENT NOT NULL, created_at DATETIME DEFAULT NULL, request_json LONGTEXT NOT NULL, results_json LONGTEXT NOT NULL, user_id INT NOT NULL, INDEX IDX_73E8AA4AA76ED395 (user_id), PRIMARY KEY (rec_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation_activite (id_res_act INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, statut_res VARCHAR(255) NOT NULL, nb_personnes INT NOT NULL, id_user INT DEFAULT NULL, id_activite INT DEFAULT NULL, INDEX IDX_25C0B7016B3CA4B (id_user), INDEX IDX_25C0B701E8AEB980 (id_activite), PRIMARY KEY (id_res_act)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation_evenement (id_res_evt INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, statut_res VARCHAR(255) NOT NULL, nb_billets INT NOT NULL, id_user INT DEFAULT NULL, id_evenement INT DEFAULT NULL, INDEX IDX_116109816B3CA4B (id_user), INDEX IDX_116109818B13D439 (id_evenement), PRIMARY KEY (id_res_evt)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation_seance (id_reservation INT AUTO_INCREMENT NOT NULL, date_reservation DATETIME NOT NULL, statut VARCHAR(255) NOT NULL, google_event_id VARCHAR(255) DEFAULT NULL, google_event_link LONGTEXT DEFAULT NULL, statut_presence VARCHAR(255) NOT NULL, id_user INT DEFAULT NULL, id_seance INT DEFAULT NULL, INDEX IDX_978CB4956B3CA4B (id_user), INDEX IDX_978CB495F94A48E3 (id_seance), PRIMARY KEY (id_reservation)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE seance (id_seance INT AUTO_INCREMENT NOT NULL, date_seance DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, capacite INT NOT NULL, statut_seance VARCHAR(255) NOT NULL, nom VARCHAR(100) NOT NULL, id_planning INT DEFAULT NULL, id_coach INT DEFAULT NULL, INDEX IDX_DF7DFD0E84425363 (id_planning), INDEX IDX_DF7DFD0ED1DC2CFC (id_coach), PRIMARY KEY (id_seance)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_app (id_user INT AUTO_INCREMENT NOT NULL, nom VARCHAR(80) NOT NULL, prenom VARCHAR(80) NOT NULL, email VARCHAR(120) NOT NULL, telephone VARCHAR(30) DEFAULT NULL, image_url VARCHAR(255) DEFAULT NULL, role VARCHAR(255) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, age INT DEFAULT NULL, experience VARCHAR(50) DEFAULT NULL, specialite VARCHAR(255) DEFAULT NULL, bio_certifs LONGTEXT DEFAULT NULL, disponibilite VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id_user)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_B87555151CFE4221 FOREIGN KEY (id_pack) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE feedback_event ADD CONSTRAINT FK_AF0F93C2A76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE feedback_event ADD CONSTRAINT FK_AF0F93C21919B217 FOREIGN KEY (pack_id) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D66B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE inscription ADD CONSTRAINT FK_5E90F6D61CFE4221 FOREIGN KEY (id_pack) REFERENCES pack (id_pack)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FA94F539B FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F6B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE nutrition_log ADD CONSTRAINT FK_18B697FA76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE6064046B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE recommendation_log ADD CONSTRAINT FK_73E8AA4AA76ED395 FOREIGN KEY (user_id) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B7016B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_activite ADD CONSTRAINT FK_25C0B701E8AEB980 FOREIGN KEY (id_activite) REFERENCES activite (id_activite)');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109816B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_116109818B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB4956B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE reservation_seance ADD CONSTRAINT FK_978CB495F94A48E3 FOREIGN KEY (id_seance) REFERENCES seance (id_seance)');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E84425363 FOREIGN KEY (id_planning) REFERENCES planning (id_planning)');
        $this->addSql('ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0ED1DC2CFC FOREIGN KEY (id_coach) REFERENCES user_app (id_user)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_B87555151CFE4221');
        $this->addSql('ALTER TABLE feedback_event DROP FOREIGN KEY FK_AF0F93C2A76ED395');
        $this->addSql('ALTER TABLE feedback_event DROP FOREIGN KEY FK_AF0F93C21919B217');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D66B3CA4B');
        $this->addSql('ALTER TABLE inscription DROP FOREIGN KEY FK_5E90F6D61CFE4221');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FA94F539B');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F6B3CA4B');
        $this->addSql('ALTER TABLE nutrition_log DROP FOREIGN KEY FK_18B697FA76ED395');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE6064046B3CA4B');
        $this->addSql('ALTER TABLE recommendation_log DROP FOREIGN KEY FK_73E8AA4AA76ED395');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B7016B3CA4B');
        $this->addSql('ALTER TABLE reservation_activite DROP FOREIGN KEY FK_25C0B701E8AEB980');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109816B3CA4B');
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_116109818B13D439');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB4956B3CA4B');
        $this->addSql('ALTER TABLE reservation_seance DROP FOREIGN KEY FK_978CB495F94A48E3');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E84425363');
        $this->addSql('ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0ED1DC2CFC');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE capacity_policy');
        $this->addSql('DROP TABLE conversation');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE feedback_event');
        $this->addSql('DROP TABLE inscription');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE nutrition_log');
        $this->addSql('DROP TABLE pack');
        $this->addSql('DROP TABLE planning');
        $this->addSql('DROP TABLE reclamation');
        $this->addSql('DROP TABLE recommendation_log');
        $this->addSql('DROP TABLE reservation_activite');
        $this->addSql('DROP TABLE reservation_evenement');
        $this->addSql('DROP TABLE reservation_seance');
        $this->addSql('DROP TABLE seance');
        $this->addSql('DROP TABLE user_app');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
