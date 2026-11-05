<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'MotoStock') ?> · MotoStock</title>
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="icon" href="<?= e(url('/assets/img/logo.svg')) ?>">
</head>
<body>
  <main class="auth" id="conteudo">
    <div class="logo"><img src="<?= e(url('/assets/img/logo.svg')) ?>" alt="MotoStock"></div>
    <?= $content ?>
  </main>
</body>
</html>
