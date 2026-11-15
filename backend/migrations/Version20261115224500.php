<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inventory: an index for the latest movements of a tenant (E10-06, ADR 0056)';
    }

    public function up(Schema $schema): void
    {
        // GET /api/stock-movements without a filter reads the ledger newest first. With 500 000 movements
        // the page cost 141 ms in a sequential scan and a sort; walking this index backwards stops at the
        // first page. The order of the columns is the order of the query, so a descending scan serves it.
        $this->addSql('CREATE INDEX idx_stock_movements_tenant_time ON stock_movements (tenant_id, occurred_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_stock_movements_tenant_time');
    }
}
