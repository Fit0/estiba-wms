<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * E03-04 (ADR 0011): the stock ledger of Inventory, the putaway tasks of Inbound, who closed each
 * receipt, and the family and turnover class of each product.
 */
final class Version20261021144416 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the stock ledger and the putaway tasks, and classify products';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE putaway_tasks (tenant_id UUID NOT NULL, receipt_id UUID NOT NULL, reference VARCHAR(40) NOT NULL, source_location_id UUID NOT NULL, suggested_location_id UUID DEFAULT NULL, status VARCHAR(20) NOT NULL, confirmed_location_id UUID DEFAULT NULL, override_reason VARCHAR(200) DEFAULT NULL, confirmed_by UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, product_id UUID NOT NULL, units INT NOT NULL, lot VARCHAR(40) DEFAULT NULL, expiry_date DATE DEFAULT NULL, quarantine BOOLEAN NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_putaway_tasks_receipt ON putaway_tasks (receipt_id)');
        $this->addSql('CREATE INDEX idx_putaway_tasks_tenant_status ON putaway_tasks (tenant_id, status)');
        $this->addSql('CREATE TABLE stock_items (tenant_id UUID NOT NULL, on_hand INT NOT NULL, id UUID NOT NULL, product_id UUID NOT NULL, location_id UUID NOT NULL, lot VARCHAR(40) NOT NULL, expiry_date DATE NOT NULL, status VARCHAR(20) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_stock_items_location ON stock_items (location_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_items_key ON stock_items (tenant_id, product_id, location_id, lot, expiry_date, status)');
        $this->addSql('CREATE TABLE stock_movements (tenant_id UUID NOT NULL, stock_item_id UUID NOT NULL, type VARCHAR(20) NOT NULL, change INT NOT NULL, balance_after INT NOT NULL, reference VARCHAR(80) DEFAULT NULL, reason VARCHAR(200) DEFAULT NULL, author_id UUID NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_stock_movements_item ON stock_movements (stock_item_id, occurred_at)');
        $this->addSql('ALTER TABLE products ADD family VARCHAR(20) DEFAULT NULL');
        // Products that exist already move at an average pace (B) until someone classifies them.
        $this->addSql("ALTER TABLE products ADD turnover_class VARCHAR(1) DEFAULT 'B' NOT NULL");
        $this->addSql('ALTER TABLE products ALTER turnover_class DROP DEFAULT');
        $this->addSql('ALTER TABLE receipts ADD closed_by UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE putaway_tasks');
        $this->addSql('DROP TABLE stock_items');
        $this->addSql('DROP TABLE stock_movements');
        $this->addSql('ALTER TABLE products DROP family');
        $this->addSql('ALTER TABLE products DROP turnover_class');
        $this->addSql('ALTER TABLE receipts DROP closed_by');
    }
}
