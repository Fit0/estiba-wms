<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024194008 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the shipments that pack an order into parcels, and the SSCC serial counter';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE shipments (tenant_id UUID NOT NULL, order_id UUID NOT NULL, warehouse_id UUID NOT NULL, status VARCHAR(7) NOT NULL, picked JSON NOT NULL, parcels JSON NOT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, packed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_shipments_tenant_status ON shipments (tenant_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_shipments_order ON shipments (order_id)');
        $this->addSql('CREATE TABLE sscc_serials (last_serial BIGINT NOT NULL, company_prefix VARCHAR(10) NOT NULL, PRIMARY KEY (company_prefix))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE shipments');
        $this->addSql('DROP TABLE sscc_serials');
    }
}
