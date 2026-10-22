<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * E03-06 (ADR 0013): the stock read model, one row per stock item. It copies data from Catalog and
 * Warehouse, which SQL may not join, so it starts empty: fill it with `app:stock-overview:rebuild`.
 */
final class Version20261022095020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the stock read model';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_overview (stock_item_id UUID NOT NULL, tenant_id UUID NOT NULL, product_id UUID NOT NULL, sku VARCHAR(40) NOT NULL, product_name VARCHAR(150) NOT NULL, warehouse_id UUID NOT NULL, warehouse_code VARCHAR(12) NOT NULL, location_id UUID NOT NULL, location_code VARCHAR(12) NOT NULL, lot VARCHAR(40) DEFAULT NULL, expiry_date DATE DEFAULT NULL, status VARCHAR(20) NOT NULL, on_hand INT NOT NULL, PRIMARY KEY (stock_item_id))');
        $this->addSql('CREATE INDEX idx_stock_overview_product ON stock_overview (tenant_id, product_id)');
        $this->addSql('CREATE INDEX idx_stock_overview_location ON stock_overview (tenant_id, location_id)');
        $this->addSql('CREATE INDEX idx_stock_overview_warehouse ON stock_overview (tenant_id, warehouse_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE stock_overview');
    }
}
