<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109125520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Erp: the outbox of shipment confirmations and the orders imported from the ERP (E08-04)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE erp_imported_orders (
                  imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  tenant_id UUID NOT NULL,
                  reference VARCHAR(40) NOT NULL,
                  PRIMARY KEY (tenant_id, reference)
                )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE erp_shipment_outbox (
                  tenant_id UUID NOT NULL,
                  order_reference VARCHAR(40) NOT NULL,
                  payload JSON NOT NULL,
                  status VARCHAR(10) NOT NULL,
                  attempts INT NOT NULL,
                  next_attempt_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  last_error TEXT DEFAULT NULL,
                  created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                  shipment_id UUID NOT NULL,
                  PRIMARY KEY (shipment_id)
                )
            SQL);
        $this->addSql('CREATE INDEX idx_erp_shipment_outbox_due ON erp_shipment_outbox (status, next_attempt_at)');
        $this->addSql('CREATE INDEX idx_erp_shipment_outbox_tenant ON erp_shipment_outbox (tenant_id, status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE erp_imported_orders');
        $this->addSql('DROP TABLE erp_shipment_outbox');
    }
}
