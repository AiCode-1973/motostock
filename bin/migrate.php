<?php
declare(strict_types=1);

// Aplica as migrations pendentes de database/migrations em ordem.
// Uso: php bin/migrate.php            (aplica pendentes)
//      php bin/migrate.php --fresh    (APAGA tudo e recria — só desenvolvimento/testes)

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$fresh = in_array('--fresh', $argv, true);
$db = Database::connect();
$pdo = $db->pdo();

if ($fresh) {
    if (Env::get('APP_ENV') === 'production') {
        fwrite(STDERR, "--fresh bloqueado em produção.\n");
        exit(1);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec('DROP TABLE `' . str_replace('`', '', $t) . '`');
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
$done = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob(BASE_PATH . '/database/migrations/*.sql');
sort($files);
$applied = 0;
foreach ($files as $file) {
    $version = basename($file, '.sql');
    if (in_array($version, $done, true)) {
        continue;
    }
    $sql = (string) file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
    }
    $db->insert('schema_migrations', ['version' => $version, 'applied_at' => gmdate('Y-m-d H:i:s')]);
    echo "Aplicada: {$version}\n";
    $applied++;
}
echo $applied ? "Pronto.\n" : "Nada pendente.\n";
