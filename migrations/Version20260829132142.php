<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drops companies.types: the application no longer distinguishes clients,
 * suppliers and subcontractors — every record is simply a company.
 */
final class Version20260829132142 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop companies.types (company type concept removed)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies DROP types');
    }

    public function down(Schema $schema): void
    {
        // Re-added with a default so the rollback also works on a populated table.
        $this->addSql("ALTER TABLE companies ADD types JSON NOT NULL DEFAULT '[]'");
        $this->addSql('ALTER TABLE companies ALTER COLUMN types DROP DEFAULT');
    }
}
