<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110114259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Erp: the log of the files of orders that the import dealt with (E08-05)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE erp_import_log (
                  tenant_id UUID NOT NULL,
                  file_name VARCHAR(255) NOT NULL,
                  result VARCHAR(10) NOT NULL,
                  orders INT NOT NULL,
                  error_code VARCHAR(100) DEFAULT NULL,
                  message TEXT DEFAULT NULL,
                  recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                  id UUID NOT NULL,
                  PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE INDEX idx_erp_import_log_tenant_time ON erp_import_log (tenant_id, recorded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE erp_import_log');
    }
}
