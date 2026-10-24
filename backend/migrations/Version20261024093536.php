<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261024093536 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give pick lists an operator and their progress, and waves their completion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pick_lists ADD operator_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE pick_lists ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE pick_lists ADD completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE pick_lists ALTER status TYPE VARCHAR(12)');
        $this->addSql('ALTER TABLE waves ADD completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pick_lists DROP operator_id');
        $this->addSql('ALTER TABLE pick_lists DROP started_at');
        $this->addSql('ALTER TABLE pick_lists DROP completed_at');
        $this->addSql('ALTER TABLE pick_lists ALTER status TYPE VARCHAR(10)');
        $this->addSql('ALTER TABLE waves DROP completed_at');
    }
}
