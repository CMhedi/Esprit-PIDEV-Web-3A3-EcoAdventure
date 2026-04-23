<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Konnect payment tracking fields to pack inscriptions';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['inscription'])) {
            return;
        }

        $columns = [];
        foreach ($schemaManager->listTableColumns('inscription') as $column) {
            $columns[$column->getName()] = true;
        }

        if (!isset($columns['payment_gateway'])) {
            $this->addSql('ALTER TABLE inscription ADD payment_gateway VARCHAR(40) DEFAULT NULL');
        }

        if (!isset($columns['payment_reference'])) {
            $this->addSql('ALTER TABLE inscription ADD payment_reference VARCHAR(120) DEFAULT NULL');
        }

        if (!isset($columns['payment_order_id'])) {
            $this->addSql('ALTER TABLE inscription ADD payment_order_id VARCHAR(120) DEFAULT NULL');
        }

        if (!isset($columns['payment_status'])) {
            $this->addSql('ALTER TABLE inscription ADD payment_status VARCHAR(40) DEFAULT NULL');
        }

        if (!isset($columns['paid_at'])) {
            $this->addSql('ALTER TABLE inscription ADD paid_at DATETIME DEFAULT NULL');
        }

        $indexes = [];
        foreach ($schemaManager->listTableIndexes('inscription') as $index) {
            $indexes[strtolower($index->getName())] = true;
        }

        if (!isset($indexes['idx_inscription_payment_reference'])) {
            $this->addSql('CREATE INDEX IDX_INSCRIPTION_PAYMENT_REFERENCE ON inscription (payment_reference)');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        if (!$schemaManager->tablesExist(['inscription'])) {
            return;
        }

        $indexes = [];
        foreach ($schemaManager->listTableIndexes('inscription') as $index) {
            $indexes[strtolower($index->getName())] = true;
        }

        if (isset($indexes['idx_inscription_payment_reference'])) {
            $this->addSql('DROP INDEX IDX_INSCRIPTION_PAYMENT_REFERENCE ON inscription');
        }

        $columns = [];
        foreach ($schemaManager->listTableColumns('inscription') as $column) {
            $columns[$column->getName()] = true;
        }

        foreach (['paid_at', 'payment_status', 'payment_order_id', 'payment_reference', 'payment_gateway'] as $column) {
            if (isset($columns[$column])) {
                $this->addSql(sprintf('ALTER TABLE inscription DROP %s', $column));
            }
        }
    }
}
