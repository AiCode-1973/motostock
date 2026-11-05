<?php $path = $request->path ?? ''; $a = fn(string $h, string $l) => '<a href="' . e(url($h)) . '"' . (($h === '/plataforma' ? $path === $h : str_starts_with($path, $h)) ? ' aria-current="page"' : '') . '>' . e($l) . '</a>'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Plataforma') ?> · Plataforma MotoStock</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <meta name="robots" content="noindex">
</head>
<body>
<a class="skip" href="#conteudo">Pular para o conteúdo</a>
<div class="shell">
  <aside class="sidebar" aria-label="Menu da plataforma">
    <a class="brand" href="<?= e(url('/plataforma')) ?>"><img src="<?= e(url('/assets/img/logo-light.svg')) ?>" alt="MotoStock" width="137" height="30"></a>
    <div class="nav-group">Plataforma</div>
    <nav>
      <?= $a('/plataforma', 'Visão geral') ?>
      <?= $a('/plataforma/lojas', 'Lojas (tenants)') ?>
      <?= $a('/plataforma/planos', 'Planos') ?>
      <?= $a('/plataforma/auditoria', 'Auditoria') ?>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn--ghost btn--sm nav-toggle" type="button" data-nav-toggle aria-label="Abrir menu">☰ Menu</button>
      <strong>Administração da plataforma</strong>
      <div class="who">
        <span class="hide-sm"><?= e($user['name']) ?> · <span class="muted"><?= $user['platform_role'] === 'admin' ? 'Administrador' : 'Suporte (leitura)' ?></span></span>
        <a class="small" href="<?= e(url('/conta/seguranca')) ?>">Segurança</a>
        <form method="post" action="<?= e(url('/sair')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit" data-no-loading>Sair</button></form>
      </div>
    </header>
    <main class="content" id="conteudo">
      <?php include __DIR__ . '/../partials/flash.php'; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
