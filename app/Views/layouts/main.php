<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'MotoStock') ?> · MotoStock</title>
  <meta name="description" content="MotoStock: estoque, compras, vendas e loja virtual para lojas de peças e acessórios de moto.">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="icon" href="<?= e(url('/assets/img/logo.svg')) ?>">
</head>
<body>
  <a class="skip" href="#conteudo">Pular para o conteúdo</a>
  <header class="site-header">
    <div class="wrap">
      <a href="<?= e(url('/')) ?>"><img src="<?= e(url('/assets/img/logo-light.svg')) ?>" alt="MotoStock" height="32" width="146"></a>
      <nav aria-label="Principal">
        <?php if (!empty($user)): ?>
          <a href="<?= e(url($user['platform_role'] ? '/plataforma' : '/painel')) ?>">Meu painel</a>
          <form method="post" action="<?= e(url('/sair')) ?>" class="inline-form"><?= csrf_field() ?><button class="btn btn--ghost btn--sm" type="submit" data-no-loading>Sair</button></form>
        <?php else: ?>
          <a href="<?= e(url('/')) ?>#planos">Planos</a>
          <a href="<?= e(url('/entrar')) ?>">Entrar</a>
          <a class="btn btn--sm" href="<?= e(url('/cadastro')) ?>">Criar minha loja</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>
  <main id="conteudo">
    <?php if (!empty($flash)): ?><div class="wrap"><br><?php include __DIR__ . '/../partials/flash.php'; ?></div><?php endif; ?>
    <?= $content ?>
  </main>
  <footer class="site-footer">MotoStock · plataforma para lojas de peças e acessórios de moto</footer>
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
