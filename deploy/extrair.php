<?php
// MotoStock · extrator do pacote no servidor (vai para a raiz do domínio como motostock-extrair.php).
// Uso: https://motostock.aicode.dev.br/motostock-extrair.php?token=DEPLOY_TOKEN
// 1. Na primeira vez, move a versão anterior (api/, frontend/, index.html…) para _versao_anterior_<data>/ (nada é apagado).
// 2. Extrai o pacote. O .env do servidor nunca é sobrescrito; na primeira instalação, .env.production vira .env.
// 3. Cria as pastas de storage e aplica as migrations pendentes.
// 4. Apaga o pacote e a si mesmo.

declare(strict_types=1);

const DEPLOY_TOKEN = '__DEPLOY_TOKEN__';
const ZIP_NAME = 'motostock-pacote.zip';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$given = (string) ($_GET['token'] ?? '');
if (strlen(DEPLOY_TOKEN) < 24 || DEPLOY_TOKEN === '__DEPLOY' . '_TOKEN__' || !hash_equals(DEPLOY_TOKEN, $given)) {
    http_response_code(404);
    exit('Não encontrado.');
}
set_time_limit(300);
$root = __DIR__;
$log = [];
$fail = function (string $msg) use (&$log): void {
    $log[] = '✗ ' . $msg;
    echo '<h1>Falha na atualização</h1><pre>' . htmlspecialchars(implode("\n", $log)) . '</pre>';
    exit;
};

if (!class_exists('ZipArchive')) {
    $fail('Extensão zip do PHP indisponível. Extraia ' . ZIP_NAME . ' pelo Gerenciador de Arquivos do cPanel e abra /instalacao.');
}
$zipFile = $root . '/' . ZIP_NAME;
if (!is_file($zipFile)) {
    $fail(ZIP_NAME . ' não encontrado na pasta do domínio.');
}

// 1. Versão anterior (Vue + API antiga): guardar, não apagar.
$old = array_values(array_filter(['api', 'frontend', 'index.html', 'setup.php', 'sw.js', 'manifest.webmanifest', 'registerSW.js', 'favicon.svg', 'icons', 'assets'], fn($p) => file_exists($root . '/' . $p)));
if ($old && is_dir($root . '/frontend')) {
    $backup = $root . '/_versao_anterior_' . date('Ymd-His');
    mkdir($backup, 0750);
    foreach ($old as $p) {
        rename($root . '/' . $p, $backup . '/' . $p);
    }
    if (is_file($root . '/.htaccess')) {
        copy($root . '/.htaccess', $backup . '/htaccess.antigo');
    }
    $log[] = '✓ Versão anterior guardada em ' . basename($backup) . ' (' . implode(', ', $old) . ')';
}

// 2. Extração
$zip = new ZipArchive();
if ($zip->open($zipFile) !== true) {
    $fail('Não foi possível abrir o pacote.');
}
$hasEnv = is_file($root . '/.env');
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    if ($name === '' || str_contains($name, '..') || str_starts_with($name, '/')) {
        continue;
    }
    if ($name === '.env.production') {
        if ($hasEnv) {
            continue;
        }
        file_put_contents($root . '/.env', (string) $zip->getFromIndex($i));
        @chmod($root . '/.env', 0600);
        $log[] = '✓ .env criado a partir de .env.production';
        continue;
    }
    $target = $root . '/' . $name;
    if (str_ends_with($name, '/')) {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }
        continue;
    }
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    file_put_contents($target, (string) $zip->getFromIndex($i));
}
$count = $zip->numFiles;
$zip->close();
$log[] = "✓ {$count} arquivos extraídos" . ($hasEnv ? ' (.env do servidor preservado)' : '');

// 3. Pastas graváveis e banco
foreach (['storage', 'storage/logs', 'storage/uploads', 'storage/exports', 'storage/backups'] as $d) {
    if (!is_dir($root . '/' . $d)) {
        mkdir($root . '/' . $d, 0755, true);
    }
}
if (!is_file($root . '/.env')) {
    $log[] = '! Sem .env: crie-o a partir de deploy/env.production.example antes de continuar.';
} else {
    try {
        require_once $root . '/app/bootstrap.php';
        $applied = App\Support\Migrator::run(App\Core\Database::connect());
        $log[] = $applied ? '✓ Banco atualizado: ' . implode(', ', $applied) : '✓ Banco já estava atualizado';
    } catch (Throwable $e) {
        $log[] = '! Banco não atualizado agora: ' . $e->getMessage() . ' — confira DB_* no .env e abra /instalacao?token=SETUP_TOKEN.';
    }
}

// 4. Limpeza
@unlink($zipFile);
@unlink(__FILE__);
$log[] = '✓ Pacote e extrator removidos do servidor';

echo '<!doctype html><meta charset="utf-8"><title>MotoStock</title><body style="font-family:sans-serif;max-width:720px;margin:40px auto">';
echo '<h1>Atualização concluída</h1><pre>' . htmlspecialchars(implode("\n", $log)) . '</pre>';
echo '<p>Primeira instalação? Abra <code>/instalacao?token=SETUP_TOKEN</code> para criar o administrador.</p><p><a href="/">Abrir o MotoStock</a></p>';
