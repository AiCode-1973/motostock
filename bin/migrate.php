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

$applied = App\Support\Migrator::run($db);
foreach ($applied as $version) {
    echo "Aplicada: {$version}\n";
}
echo $applied ? "Pronto.\n" : "Nada pendente.\n";
