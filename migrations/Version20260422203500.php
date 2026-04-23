<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422203500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repairs activite schema drift and normalizes invalid activite enum values left by legacy rows.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        $this->abortIf(
            !$schemaManager->tablesExist(['activite']),
            'The activite table does not exist, so the schema repair migration cannot be applied.'
        );

        $activiteTable = $schemaManager->introspectTable('activite');

        // Columns already exist in the database, skip adding them
        // $this->addSql('ALTER TABLE activite ADD prix NUMERIC(10, 2) NOT NULL DEFAULT 0');
        // $this->addSql('ALTER TABLE activite ADD latitude DOUBLE PRECISION DEFAULT NULL');
        // $this->addSql('ALTER TABLE activite ADD longitude DOUBLE PRECISION DEFAULT NULL');

        $this->addSql("UPDATE activite SET type_activite = 'SPORT' WHERE type_activite IS NULL OR TRIM(type_activite) = '' OR type_activite NOT IN ('SPORT', 'CAMPING', 'INTELECTUEL', 'CULTUREL')");
        $this->addSql("UPDATE activite SET categorie_act = 'AUTRE' WHERE categorie_act IS NULL OR TRIM(categorie_act) = '' OR categorie_act NOT IN ('FITNESS', 'RUNNING', 'FOOTBALL', 'BASKETBALL', 'TENNIS', 'NATATION', 'RANDONNEE', 'CYCLISME', 'YOGA', 'AUTRE')");
        $this->addSql("UPDATE activite SET niveau_act = 'DEBUTANT' WHERE niveau_act IS NULL OR TRIM(niveau_act) = '' OR niveau_act NOT IN ('DEBUTANT', 'INTERMEDIAIRE', 'AVANCE')");
        $this->addSql("UPDATE activite SET statut = 'DISPONIBLE' WHERE statut IS NULL OR TRIM(statut) = '' OR statut NOT IN ('DISPONIBLE', 'INDISPONIBLE')");
    }

    public function down(Schema $schema): void
    {
        // Repair migration: keep down() empty to avoid dropping valid production columns.
    }
}
