<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408184342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_rating (id INT AUTO_INCREMENT NOT NULL, note INT NOT NULL, created_at DATETIME NOT NULL, id_user INT NOT NULL, id_evenement INT NOT NULL, INDEX IDX_EA1051706B3CA4B (id_user), INDEX IDX_EA1051708B13D439 (id_evenement), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051706B3CA4B FOREIGN KEY (id_user) REFERENCES user_app (id_user)');
        $this->addSql('ALTER TABLE event_rating ADD CONSTRAINT FK_EA1051708B13D439 FOREIGN KEY (id_evenement) REFERENCES evenement (id_evenement)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051706B3CA4B');
        $this->addSql('ALTER TABLE event_rating DROP FOREIGN KEY FK_EA1051708B13D439');
        $this->addSql('DROP TABLE event_rating');
    }
}
