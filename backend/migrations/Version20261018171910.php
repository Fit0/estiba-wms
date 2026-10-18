<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Warehouse context: warehouses (with their zones) and locations.
 */
final class Version20261018171910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the warehouses and locations tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE locations (tenant_id UUID NOT NULL, warehouse_id UUID NOT NULL, zone_id UUID NOT NULL, code VARCHAR(12) NOT NULL, type VARCHAR(20) NOT NULL, pick_sequence INT NOT NULL, status VARCHAR(20) NOT NULL, block_reason VARCHAR(200) DEFAULT NULL, id UUID NOT NULL, capacity_max_weight_kg INT DEFAULT NULL, capacity_max_volume_litres INT DEFAULT NULL, capacity_max_pallet_positions INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_locations_zone ON locations (zone_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_locations_warehouse_code ON locations (warehouse_id, code)');
        $this->addSql('CREATE TABLE warehouses (tenant_id UUID NOT NULL, code VARCHAR(12) NOT NULL, name VARCHAR(100) NOT NULL, zones JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_warehouses_tenant_code ON warehouses (tenant_id, code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE locations');
        $this->addSql('DROP TABLE warehouses');
    }
}
