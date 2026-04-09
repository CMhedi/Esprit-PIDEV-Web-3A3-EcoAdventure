<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260409133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attachments JSON column to message for multi-file support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD attachments JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP attachments');
    }
}
