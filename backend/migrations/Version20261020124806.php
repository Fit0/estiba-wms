<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Identity context: tenants and the users who sign in to them.
 */
final class Version20261020124806 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the tenants and users tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenants (name VARCHAR(100) NOT NULL, default_language VARCHAR(2) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE users (tenant_id UUID NOT NULL, email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL, roles JSON NOT NULL, status VARCHAR(20) NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_users_tenant ON users (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tenants');
        $this->addSql('DROP TABLE users');
    }
}
