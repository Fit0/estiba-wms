<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023174047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reserve stock: reserved units, first receipt and version of stock items, and the stock reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE stock_reservations (id UUID NOT NULL, tenant_id UUID NOT NULL, reference VARCHAR(80) NOT NULL, product_id UUID NOT NULL, requested INT NOT NULL, strategy VARCHAR(10) NOT NULL, status VARCHAR(10) NOT NULL, allocations JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_stock_reservations_tenant_reference ON stock_reservations (tenant_id, reference)');

        // Nothing is reserved yet in the stock that exists. The default is only there to fill the old rows.
        $this->addSql('ALTER TABLE stock_items ADD reserved INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE stock_items ALTER reserved DROP DEFAULT');
        $this->addSql('ALTER TABLE stock_items ADD first_received_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE stock_items ADD version INT DEFAULT 1 NOT NULL');

        // FIFO needs to know when each stock item first got units. The ledger has it.
        $this->addSql('UPDATE stock_items SET first_received_at = (SELECT MIN(m.occurred_at) FROM stock_movements m WHERE m.stock_item_id = stock_items.id AND m.change > 0)');

        // STK-02 once more, in the database: the application checks it, and this stops anything that bypasses it.
        $this->addSql('ALTER TABLE stock_items ADD CONSTRAINT chk_stock_items_reserved CHECK (reserved >= 0 AND reserved <= on_hand)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE stock_items DROP CONSTRAINT chk_stock_items_reserved');
        $this->addSql('DROP TABLE stock_reservations');
        $this->addSql('ALTER TABLE stock_items DROP reserved');
        $this->addSql('ALTER TABLE stock_items DROP first_received_at');
        $this->addSql('ALTER TABLE stock_items DROP version');
    }
}
