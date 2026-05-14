<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fixes "Entity of type 'App\Entity\Pack' for IDs id_pack(1) was not found"
 * when activite / inscription (or other tables) still reference id_pack = 1.
 */
final class Version20260515120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Insert default pack id_pack=1 if missing (satisfies FK references).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
INSERT INTO pack (id_pack, nom, type_pack, prix_base, reduction, nb_activites_max, statut_pack)
VALUES (1, 'Pack par defaut', 'Standard', '0.00', '0.00', 10, 'ACTIF')
ON DUPLICATE KEY UPDATE id_pack = id_pack
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Pack id 1 may be referenced by activite, inscription, or feedback_event.');
    }
}
