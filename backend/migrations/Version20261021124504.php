<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Inbound context: ASNs and the receipts counted against them (E03-03, ADR 0010).
 */
final class Version20261021124504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the asns and receipts tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE asns (tenant_id UUID NOT NULL, reference VARCHAR(40) NOT NULL, supplier VARCHAR(100) NOT NULL, expected_date DATE NOT NULL, status VARCHAR(20) NOT NULL, open_receipt_id UUID DEFAULT NULL, lines JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_asns_tenant_status ON asns (tenant_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_asns_tenant_reference ON asns (tenant_id, reference)');
        $this->addSql('CREATE TABLE receipts (tenant_id UUID NOT NULL, asn_id UUID NOT NULL, dock_location_id UUID NOT NULL, status VARCHAR(20) NOT NULL, over_receipt_approved BOOLEAN NOT NULL, expected JSON NOT NULL, lines JSON NOT NULL, opened_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_receipts_asn ON receipts (asn_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE asns');
        $this->addSql('DROP TABLE receipts');
    }
}
