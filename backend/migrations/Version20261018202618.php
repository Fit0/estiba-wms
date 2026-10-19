<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalog context: products and the index that keeps a GTIN unique per tenant.
 */
final class Version20261018202618 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the products and product_gtins tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE product_gtins (product_id UUID NOT NULL, tenant_id UUID NOT NULL, gtin VARCHAR(14) NOT NULL, PRIMARY KEY (tenant_id, gtin))');
        $this->addSql('CREATE INDEX idx_product_gtins_product ON product_gtins (product_id)');
        $this->addSql('CREATE TABLE products (tenant_id UUID NOT NULL, sku VARCHAR(40) NOT NULL, name VARCHAR(150) NOT NULL, base_unit VARCHAR(20) NOT NULL, storage_class VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, gtins JSON NOT NULL, packaging_levels JSON NOT NULL, id UUID NOT NULL, dimensions_length_mm INT NOT NULL, dimensions_width_mm INT NOT NULL, dimensions_height_mm INT NOT NULL, dimensions_weight_grams INT NOT NULL, tracking_by_lot BOOLEAN NOT NULL, tracking_by_expiry BOOLEAN NOT NULL, tracking_min_shelf_life_receipt_days INT DEFAULT NULL, tracking_min_shelf_life_shipment_days INT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_products_tenant_sku ON products (tenant_id, sku)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE product_gtins');
        $this->addSql('DROP TABLE products');
    }
}
