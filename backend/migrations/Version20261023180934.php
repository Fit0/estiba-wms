<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261023180934 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the orders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE orders (tenant_id UUID NOT NULL, reference VARCHAR(40) NOT NULL, warehouse_id UUID NOT NULL, customer VARCHAR(100) NOT NULL, priority VARCHAR(10) NOT NULL, cut_off_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, status VARCHAR(20) NOT NULL, lines JSON NOT NULL, id UUID NOT NULL, ship_to_street VARCHAR(120) NOT NULL, ship_to_postal_code VARCHAR(12) NOT NULL, ship_to_city VARCHAR(60) NOT NULL, ship_to_country_code VARCHAR(2) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_orders_tenant_status ON orders (tenant_id, status)');
        $this->addSql('CREATE UNIQUE INDEX uniq_orders_tenant_reference ON orders (tenant_id, reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE orders');
    }
}
