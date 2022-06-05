<?php

declare(strict_types=1);

namespace App\Commands;

use Phplease\Database\Connection;
use Phplease\Foundation\Application;
use RuntimeException;

class Migrations
{
    /** Create a new migration file. */
    public function create(string ...$name): string
    {
        if (!defined('ROOT')) {
            throw new RuntimeException('ROOT constant is not defined');
        }

        if (empty($name)) {
            return 'Please provide a migration name.';
        }

        $name = array_map('ucfirst', $name);
        $class_name = implode('', $name);
        $path = ROOT . '/var/migrations/' . time() . '.' . $class_name . '.php';

        file_put_contents($path, "<?php\n\nuse \\Phplease\\Database\\Connection;\n\nclass $class_name\n{\n\tpublic function up(Connection \$db)\n\t{\n\t\n\t}\n\n\tpublic function down(Connection \$db)\n\t{\n\t\n\t}\n}\n");

        return 'New migration created: ' . $path;
    }

    /** Run missing migration files. */
    public function run(): string
    {
        if (!defined('ROOT')) {
            throw new RuntimeException('ROOT constant is not defined');
        }

        $app = Application::getInstance();

        /** @var \Phplease\Database\Connection */
        $db = $app->providerOf('db');

        if (!$this->migrationTableExists($db)) {
            $this->createMigrationTable($db);
        }

        $migrations = $this->getRunnedMigrations($db);
        $files = $this->getFiles(ROOT . '/var/migrations/');

        $query = $db->query('INSERT INTO migrations (filename, timestamp) VALUES (?, ?)');

        foreach ($files as $filename) {
            if (in_array($filename, $migrations)) {
                continue;
            }

            include_once ROOT . '/var/migrations/' . $filename;

            $parts = explode('.', $filename);
            $class_name = $parts[1];
            $object = new $class_name();

            if (method_exists($object, 'up')) {
                $object->up($db);
            }

            $query->execute($filename, time());
        }

        return 'Migration finished.';
    }

    /** Rollback last migration. */
    public function rollback(): string
    {
        if (!defined('ROOT')) {
            throw new RuntimeException('ROOT constant is not defined');
        }

        $app = Application::getInstance();

        /** @var \Phplease\Database\Connection */
        $db = $app->providerOf('db');

        $filename = $this->getLastRunnedMigration($db);

        if (!$filename) {
            return 'No migration found.';
        }

        include_once ROOT . '/var/migrations/' . $filename;

        $parts = explode('.', $filename);
        $class_name = $parts[1];
        $object = new $class_name();
        
        if (method_exists($object, 'down')) {
            $object->down($db);
        }

        $db->execute('DELETE FROM migrations WHERE filename=?', $filename);

        return 'Rollback finished.';
    }

    /**
     * Get list of all php files.
     * @return array<string>
     */
    protected function getFiles(string $path): array
    {
        $files = [];
        $filenames = scandir($path) ?: [];

        foreach ($filenames as $filename) {
            if ('.' === $filename || '..' === $filename) {
                continue;
            }

            if (is_dir($filename)) {
                $files += $this->getFiles($filename);
                continue;
            }

            if (str_ends_with($filename, '.php')) {
                $files[] = $filename;
            }
        }

        return $files;
    }

    /** Check if migrations table exists. */
    protected function migrationTableExists(Connection $db): bool
    {
        return 'migrations' === $db->fetchField('SHOW TABLES LIKE "migrations"');
    }

    /** Create migration table. */
    protected function createMigrationTable(Connection $db): void
    {
        $db->execute('CREATE TABLE `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `executed_at` DATETIME DEFAULT CURRENT_DATE
        )');
    }

    /**
     * Get runned migrations.
     * @return array<string>
     */
    protected function getRunnedMigrations(Connection $db): array
    {
        return array_column($db->fetchAll('SELECT `filename` FROM migrations'), 'filename');
    }

    /** Get last runned migrations. */
    protected function getLastRunnedMigration(Connection $db): string
    {
        return $db->fetchField('SELECT `filename` FROM `migrations` ORDER BY `id` DESC') ?: '';
    }
}
