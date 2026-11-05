<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Database;

/** Aplica migrations pendentes. Usado pelo terminal (bin/migrate.php), pelo instalador web e pelo pacote de envio. */
final class Migrator
{
    public static function ensureTable(Database $db): void
    {
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
    }

    /** @return string[] versões pendentes */
    public static function pending(Database $db): array
    {
        self::ensureTable($db);
        $done = $db->pdo()->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        return array_values(array_filter(array_map(fn($f) => basename($f, '.sql'), $files), fn($v) => !in_array($v, $done, true)));
    }

    /** @return string[] versões aplicadas agora */
    public static function run(Database $db): array
    {
        $applied = [];
        foreach (self::pending($db) as $version) {
            $sql = (string) file_get_contents(BASE_PATH . '/database/migrations/' . $version . '.sql');
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $db->pdo()->exec($statement);
            }
            $db->insert('schema_migrations', ['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }
}
