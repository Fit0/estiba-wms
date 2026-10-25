<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025180403 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: the refresh tokens that keep a session alive (IDN-08 to IDN-10)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_tokens (family_id VARCHAR(36) NOT NULL, user_id UUID NOT NULL, secret_hash VARCHAR(64) NOT NULL, issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, session_ends_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, rotated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id VARCHAR(36) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_refresh_tokens_family ON refresh_tokens (family_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_refresh_tokens_secret_hash ON refresh_tokens (secret_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
