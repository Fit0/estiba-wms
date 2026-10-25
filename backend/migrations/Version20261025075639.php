<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025075639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the audit trail: one line per domain event, with who did it';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_entries (tenant_id UUID NOT NULL, event_type VARCHAR(100) NOT NULL, occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, actor_id UUID DEFAULT NULL, subject_id UUID DEFAULT NULL, data JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_audit_entries_tenant_time ON audit_entries (tenant_id, occurred_at)');
        $this->addSql('CREATE INDEX idx_audit_entries_tenant_subject ON audit_entries (tenant_id, subject_id)');
        $this->addSql('CREATE INDEX idx_audit_entries_tenant_type ON audit_entries (tenant_id, event_type)');
        $this->addSql('CREATE INDEX idx_audit_entries_tenant_actor ON audit_entries (tenant_id, actor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_entries');
    }
}
