<?php
declare(strict_types=1);

// Roda a suíte de testes no banco definido em .env.testing (é APAGADO e recriado).
// Uso: php bin/test.php              (todos)
//      php bin/test.php Isolation    (só classes/métodos que contêm o texto)

putenv('MOTOSTOCK_ENV_FILE=' . dirname(__DIR__) . '/.env.testing');
putenv('MOTOSTOCK_TESTING=1');
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Env;

if (!is_file(BASE_PATH . '/.env.testing')) {
    fwrite(STDERR, "Crie o arquivo .env.testing (copie .env.example e aponte DB_DATABASE para um banco SÓ de testes).\n");
    exit(1);
}
if (!str_contains((string) Env::get('DB_DATABASE'), 'test')) {
    fwrite(STDERR, "Por segurança, o banco de testes precisa ter 'test' no nome (DB_DATABASE).\n");
    exit(1);
}
// Recria o esquema do zero.
$argvBackup = $argv;
$argv = ['migrate.php', '--fresh'];
ob_start();
require BASE_PATH . '/bin/migrate.php';
ob_end_clean();
$argv = $argvBackup;

require BASE_PATH . '/tests/TestCase.php';
require BASE_PATH . '/tests/Browser.php';
$filter = $argv[1] ?? '';
$files = glob(BASE_PATH . '/tests/*Test.php');
sort($files);
$passed = $failed = $assertions = 0;
$failures = [];
$t0 = microtime(true);
foreach ($files as $file) {
    require_once $file;
    $class = 'Tests\\' . basename($file, '.php');
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test')) {
            continue;
        }
        if ($filter !== '' && !str_contains($class . '::' . $method, $filter)) {
            continue;
        }
        $t = new $class();
        try {
            $t->setUp();
            $t->$method();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failed++;
            echo 'F';
            $where = $e instanceof Tests\AssertionFailed ? '' : ' (' . get_class($e) . ' em ' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            $failures[] = basename($file, '.php') . "::{$method}\n    " . $e->getMessage() . $where;
        }
        $assertions += $t->assertions;
    }
}
printf("\n\n%d teste(s), %d asserção(ões), %d falha(s) em %.1fs\n", $passed + $failed, $assertions, $failed, microtime(true) - $t0);
foreach ($failures as $i => $f) {
    echo "\n" . ($i + 1) . ") {$f}\n";
}
exit($failed ? 1 : 0);
