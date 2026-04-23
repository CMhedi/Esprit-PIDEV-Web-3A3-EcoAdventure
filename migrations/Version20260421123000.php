<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add priority field to message for notification levels';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE message ADD priorite_message VARCHAR(16) NOT NULL DEFAULT 'NORMAL'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP priorite_message');
    }
}
