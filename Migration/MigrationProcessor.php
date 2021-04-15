<?php

namespace App\Services\Migration;

use Facades\App\Services\DatabaseConnector;
use Facades\App\Services\Settings\SettingsLoader;

/**
 * Class MigrationProcessor
 * @package App\Services\Migration
 */
class MigrationProcessor
{
    const SYSTEM_NAME_CODE = '043';

    private $migrationPaths = [
        "OCR" => "database/migrations/ocr",
        "proactive_config" => "database/migrations/config",
        "addresses" => "database/migrations/addresses",
        "sharedStorage" => "database/migrations/sharedStorage",
        "customers" => "database/migrations",
        "proactive-default" => "database/migrations",
    ];

    private $dbConnections = [
        "OCR" => "mysql_ocr",
        "proactive_config" => "mysql_config",
        "proactive-default" => "mysql_proactive_default",
        "addresses" => "mysql_addresses",
        "sharedStorage" => "mysql_shared_storage",
        "customers" => "mysql"
    ];

    private $artisan;

    /**
     * @param array $databases
     */
    public function migrate(string $group, array $databases = []): void
    {
        if (!array_key_exists($group, $this->migrationPaths)) {
            throw MigrationException::becauseGroupIsInvalid($group);
        }

        if ($group == "customers") {
            $this->_runCustomerDatabaseMigrations($group, $databases);
        } else {
            $this->_runStandaloneDatabaseMigrations($group);
        }
    }

    /**
     * Process and run migrations for customer databases
     * This will always run migration for proactive-default database
     * even if it couldn't find a customer database from config
     * @param string $group
     * @param array $databases
     * @codeCoverageIgnore
     */
    protected function _runCustomerDatabaseMigrations(string $group, array $databases = []): void
    {
        if (empty($databases)) {
            $databases = $this->_getCustomerDatabases();
        }

        $this->_runStandaloneDatabaseMigrations("proactive-default");

        if (!empty($databases)) {
            foreach ($databases as $database) {
                $this->_setRequestClient($database);
                $this->_loadSystemSettings();
                $this->_connectSystemDatabase();
                $this->_runStandaloneDatabaseMigrations($group);
            }
        }
    }

    /**
     * Run migration on standalone databases
     * Can be also used when the customer connection was set
     * @param string $group
     * @codeCoverageIgnore
     */
    protected function _runStandaloneDatabaseMigrations(string $group): void
    {
        if ($this->artisan == null) {
            $this->artisan = \App::make(\Illuminate\Contracts\Console\Kernel::class);
        }
        $path = $this->migrationPaths[$group];

        $this->artisan->call('migrate', [
            "--database" => $this->dbConnections[$group],
            "--path" => $path,
            "--force" => true
        ]);

        $this->_resetSystemDatabaseConnection($group);
    }

    /**
     * Get all customer databases from config
     * @codeCoverageIgnore
     */
    protected function _getCustomerDatabases(): array
    {
        return \ApplicationSettingsAllClients::getAll()
            ->pluck(self::SYSTEM_NAME_CODE)
            ->toArray();
    }

    /**
     * Set the system client in request
     *
     * @param string $systemName
     * @codeCoverageIgnore
     */
    protected function _setRequestClient(string $systemName): void
    {
        \App::get("request")->client = $systemName;
    }

    /**
     * Load config settings for selected system
     * @codeCoverageIgnore
     */
    protected function _loadSystemSettings(): void
    {
        SettingsLoader::load();
    }

    /**
     * Connect database for selected system
     * @codeCoverageIgnore
     */
    protected function _connectSystemDatabase(): void
    {
        DatabaseConnector::connect();
    }

    /**
     * Reset database connection for selected system
     * @codeCoverageIgnore
     * @param string $connectionGroup
     */
    protected function _resetSystemDatabaseConnection(string $connectionGroup = ""): void
    {
        \DB::purge($this->dbConnections[$connectionGroup]);
    }
}
