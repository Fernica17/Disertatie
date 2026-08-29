<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Collapses the role set to ROLE_ADMIN / ROLE_MANAGER / ROLE_CLIENT.
 *
 * ROLE_CONSULTANT was the base "internal staff" role that ROLE_MANAGER
 * inherited, so it maps up to ROLE_MANAGER to preserve access.
 * ROLE_COMPANY_MANAGER inherited ROLE_CLIENT, so it maps down to ROLE_CLIENT.
 */
final class Version20260829163526 extends AbstractMigration
{
    private const array ROLE_MAP = [
        'ROLE_CONSULTANT' => 'ROLE_MANAGER',
        'ROLE_COMPANY_MANAGER' => 'ROLE_CLIENT',
    ];

    public function getDescription(): string
    {
        return 'Map ROLE_CONSULTANT to ROLE_MANAGER and ROLE_COMPANY_MANAGER to ROLE_CLIENT';
    }

    public function up(Schema $schema): void
    {
        $this->remapRoles(self::ROLE_MAP);
    }

    public function down(Schema $schema): void
    {
        // Not reversible: the original roles cannot be recovered once merged.
        $this->throwIrreversibleMigrationException(
            'Removing ROLE_CONSULTANT / ROLE_COMPANY_MANAGER is a one-way data merge.'
        );
    }

    /**
     * @param array<string, string> $map
     */
    private function remapRoles(array $map): void
    {
        $cases = '';
        foreach ($map as $from => $to) {
            $cases .= sprintf(" WHEN %s THEN %s", $this->quote($from), $this->quote($to));
        }

        $conditions = [];
        foreach (array_keys($map) as $from) {
            $conditions[] = sprintf('roles::text LIKE %s', $this->quote('%' . $from . '%'));
        }

        $this->addSql(sprintf(
            'UPDATE users SET roles = (
                SELECT COALESCE(jsonb_agg(DISTINCT mapped), \'[]\'::jsonb)::json
                FROM jsonb_array_elements_text(roles::jsonb) AS original(role),
                LATERAL (SELECT CASE original.role%s ELSE original.role END) AS m(mapped)
            ) WHERE %s',
            $cases,
            implode(' OR ', $conditions),
        ));
    }

    private function quote(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
