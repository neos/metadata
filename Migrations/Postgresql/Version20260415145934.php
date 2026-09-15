<?php

declare(strict_types=1);

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\PostgreSqlPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415145934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial neos_metadata_value table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('CREATE TABLE neos_metadata_value (
            asset_source_id VARCHAR(255) DEFAULT NULL,
            asset_id VARCHAR(40) DEFAULT NULL,
            property_name VARCHAR(40) NOT NULL,
            property_value TEXT NOT NULL,
            dimension_hash VARCHAR(250) NOT NULL,
            CONSTRAINT idx_unique UNIQUE (asset_source_id, asset_id, property_name, dimension_hash)
        )');
        $this->addSql('ALTER TABLE neos_metadata_value ADD CONSTRAINT fk_asset FOREIGN KEY (asset_id) REFERENCES neos_media_domain_model_asset (persistence_object_identifier) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, 'Migration can only be executed safely on PostgreSQL.');

        $this->addSql('ALTER TABLE neos_metadata_value DROP CONSTRAINT fk_asset');
        $this->addSql('DROP TABLE neos_metadata_value');
    }
}
