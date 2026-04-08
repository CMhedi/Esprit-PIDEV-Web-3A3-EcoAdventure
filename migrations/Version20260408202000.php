<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408202000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last_seen column to user_app for online status';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_app ADD last_seen DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_app DROP last_seen');
    }
}
