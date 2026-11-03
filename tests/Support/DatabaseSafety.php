<?php

namespace Tests\Support;

use Config\Database as DatabaseConfig;
use RuntimeException;
use Throwable;

/** @internal Guards migration-backed tests from non-test databases. */
final class DatabaseSafety
{
    /**
     * Validate test database configuration and, optionally, its live identity.
     *
     * @return array{database: string, defaultDatabase: string}
     */
    public static function verify(bool $checkLiveConnection = true): array
    {
        if (! defined('ENVIRONMENT') || ENVIRONMENT !== 'testing') {
            throw new RuntimeException('Automated tests require ENVIRONMENT=testing.');
        }

        $config = new DatabaseConfig();
        $test = $config->tests;
        $default = $config->default;
        $databaseName = trim((string) ($test['database'] ?? ''));
        $defaultDatabaseName = trim((string) ($default['database'] ?? ''));

        if ($config->defaultGroup !== 'tests') {
            throw new RuntimeException('Automated tests require the tests database group as the default group.');
        }

        if (($test['DBDriver'] ?? null) !== 'MySQLi') {
            throw new RuntimeException('Automated tests require the tests database group to use MySQLi.');
        }

        if ($databaseName === '' || stripos($databaseName, 'test') === false) {
            throw new RuntimeException('Configure a dedicated database with "test" in its name for automated tests.');
        }

        if ($defaultDatabaseName === '' || strcasecmp($databaseName, $defaultDatabaseName) === 0) {
            throw new RuntimeException('The tests database must be distinct from the default database.');
        }

        if (($test['DBPrefix'] ?? null) !== '') {
            throw new RuntimeException('The tests database table prefix must be empty for application migrations.');
        }

        if ($checkLiveConnection) {
            try {
                $connection = db_connect('tests');
                $row = $connection->query('SELECT DATABASE() AS database_name')->getRowArray();
                $activeDatabaseName = (string) ($row['database_name'] ?? '');
            } catch (Throwable) {
                throw new RuntimeException('Could not verify the active automated test database connection.');
            }

            if (strcasecmp($activeDatabaseName, $databaseName) !== 0
                || strcasecmp($activeDatabaseName, $defaultDatabaseName) === 0) {
                throw new RuntimeException('The active database does not match the isolated automated test database configuration.');
            }
        }

        return [
            'database'        => $databaseName,
            'defaultDatabase' => $defaultDatabaseName,
        ];
    }
}
