<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023201009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the waves and the pick lists, and give orders their wave and their accepted backorder';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pick_lists (tenant_id UUID NOT NULL, wave_id UUID NOT NULL, status VARCHAR(10) NOT NULL, lines JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_pick_lists_tenant_wave ON pick_lists (tenant_id, wave_id)');
        $this->addSql('CREATE TABLE waves (tenant_id UUID NOT NULL, warehouse_id UUID NOT NULL, status VARCHAR(10) NOT NULL, order_ids JSON NOT NULL, pick_list_ids JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, released_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_waves_tenant_status ON waves (tenant_id, status)');
        // No order has accepted a backorder yet. The default is only there to fill the old rows.
        $this->addSql('ALTER TABLE orders ADD backorder_accepted BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE orders ALTER backorder_accepted DROP DEFAULT');
        $this->addSql('ALTER TABLE orders ADD wave_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pick_lists');
        $this->addSql('DROP TABLE waves');
        $this->addSql('ALTER TABLE orders DROP backorder_accepted');
        $this->addSql('ALTER TABLE orders DROP wave_id');
    }
}
