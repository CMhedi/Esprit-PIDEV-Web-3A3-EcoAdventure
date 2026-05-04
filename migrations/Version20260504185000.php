<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix Doctrine Doctor issues: FK naming, nullable timestamps, decimal types
 */
final class Version20260504185000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix Doctrine Doctor issues: orphanRemoval, cascade constraints, decimal types, nullable timestamps';
    }

    public function up(Schema $schema): void
    {
        // Fix NutritionLog decimal columns
        $this->addSql('ALTER TABLE nutrition_log CHANGE calories calories NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE protein protein NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE fat fat NUMERIC(10, 2) DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE carbs carbs NUMERIC(10, 2) DEFAULT NULL');

        // Fix ResetPasswordRequest datetime types
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE reset_password_request CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        // Fix FeedbackEvent nullable timestamp
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME NOT NULL');

        // Fix RecommendationLog nullable timestamp
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME NOT NULL');

        // Add cascade constraints for orphan removal
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_reservation_evenement_id_evenement');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_reservation_evenement_id_evenement FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_reservation_evenement_id_user');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_reservation_evenement_id_user FOREIGN KEY (id_user) REFERENCES user_app (id_user) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE message DROP FOREIGN KEY IF EXISTS FK_message_id_conversation');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_id_conversation FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY IF EXISTS FK_event_rating_id_evenement');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_event_rating_id_evenement FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY IF EXISTS FK_conversation_id_createur');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conversation_id_createur FOREIGN KEY (id_createur) REFERENCES user_app (id_user) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Revert NutritionLog decimal columns
        $this->addSql('ALTER TABLE nutrition_log CHANGE calories calories DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE protein protein DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE fat fat DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE nutrition_log CHANGE carbs carbs DOUBLE PRECISION DEFAULT NULL');

        // Revert ResetPasswordRequest datetime types
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reset_password_request CHANGE expires_at expires_at VARCHAR(255) NOT NULL');

        // Revert FeedbackEvent nullable timestamp
        $this->addSql('ALTER TABLE feedback_event CHANGE created_at created_at DATETIME DEFAULT NULL');

        // Revert RecommendationLog nullable timestamp
        $this->addSql('ALTER TABLE recommendation_log CHANGE created_at created_at DATETIME DEFAULT NULL');

        // Revert cascade constraints
        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_reservation_evenement_id_evenement');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_reservation_evenement_id_evenement FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');

        $this->addSql('ALTER TABLE reservation_evenement DROP FOREIGN KEY FK_reservation_evenement_id_user');
        $this->addSql('ALTER TABLE reservation_evenement ADD CONSTRAINT FK_reservation_evenement_id_user FOREIGN KEY (id_user) REFERENCES user_app (id_user)');

        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_message_id_conversation');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_message_id_conversation FOREIGN KEY (id_conversation) REFERENCES conversation (id_conversation)');

        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_event_rating_id_evenement');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_event_rating_id_evenement FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');

        $this->addSql('ALTER TABLE conversation DROP FOREIGN KEY FK_conversation_id_createur');
        $this->addSql('ALTER TABLE conversation ADD CONSTRAINT FK_conversation_id_createur FOREIGN KEY (id_createur) REFERENCES user_app (id_user)');
    }
}
