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
                // Idempotente: se uma tentativa anterior parou no meio, as tabelas já criadas são puladas.
                $statement = preg_replace('/^CREATE TABLE (?!IF NOT EXISTS)/i', 'CREATE TABLE IF NOT EXISTS ', $statement) ?? $statement;
                try {
                    $db->pdo()->exec($statement);
                } catch (\PDOException $e) {
                    preg_match('/^\s*(CREATE TABLE IF NOT EXISTS|ALTER TABLE|INSERT INTO|UPDATE|CREATE INDEX)\s+`?(\w+)/i', $statement, $m);
                    throw new \RuntimeException(sprintf('Migration %s falhou%s: %s', $version, isset($m[2]) ? " na tabela {$m[2]}" : '', $e->getMessage()), 0, $e);
                }
            }
            $db->insert('schema_migrations', ['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
            $applied[] = $version;
        }
        return $applied;
    }
}
